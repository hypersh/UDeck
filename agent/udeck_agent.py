"""
UDeck 자동 기록 - PC 에이전트
Windows 작업 스케줄러가 15분마다 실행한다. 몇 초 안에 끝난다.

하는 일
  1) 접속 신호(heartbeat) 전송
  2) 전송 실패로 쌓인 기록 재전송
  3) Codex(ChatGPT 앱) 세션 기록 중 새로 바뀐 것 전송
  4) 감시 폴더의 HANDOFF.md가 바뀌었으면 전송

사용법
  python udeck_agent.py            한 번 실행
  python udeck_agent.py --dry-run  보내지 않고 보낼 내용만 출력 (진단)
"""
import hashlib
import json
import os
import re
import sys
import time

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from udeck_common import (load_config, load_state, save_state, post, flush_queue, log, clip,
                          text_of, is_human_text, read_tail_lines, read_head_lines, iso)

SKIP_DIRS = {".git", "node_modules", "__pycache__", ".venv", "venv", "env", ".stversions",
             ".idea", ".vscode", "dist", "build", ".sync", "$RECYCLE.BIN"}
UUID_RE = re.compile(r"([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})", re.I)


# ---------------------------------------------------------------------
# Codex 세션
# ---------------------------------------------------------------------
def parse_codex(path: str) -> dict:
    """Codex 세션 기록(JSONL)에서 세션 ID, 작업 폴더, 마지막 요청·답변, 요청 수를 찾는다.
    형식이 버전마다 조금씩 달라 여러 형태를 모두 확인한다."""
    info = {"id": None, "cwd": None, "started": None}
    for d in read_head_lines(path, 30):
        p = d.get("payload") if isinstance(d.get("payload"), dict) else d
        if d.get("type") == "session_meta" or ("id" in p and ("cwd" in p or "instructions" in p)):
            info["id"] = info["id"] or p.get("id")
            info["cwd"] = info["cwd"] or p.get("cwd")
            info["started"] = info["started"] or p.get("timestamp") or d.get("timestamp")
        if not info["cwd"] and d.get("type") == "turn_context":
            info["cwd"] = p.get("cwd")
        if not info["cwd"]:
            # <environment_context> 안의 <cwd>…</cwd>
            t = text_of(p.get("content")) if isinstance(p, dict) else ""
            m = re.search(r"<cwd>(.*?)</cwd>", t or "", re.S)
            if m:
                info["cwd"] = m.group(1).strip()
    if not info["id"]:
        m = UUID_RE.search(os.path.basename(path))
        info["id"] = m.group(1) if m else os.path.basename(path)

    # 같은 요청이 response_item과 event_msg 두 형태로 함께 기록되는 버전이 있어 따로 센다
    last_user, last_assistant, n_event, n_item = None, None, 0, 0
    for d in read_tail_lines(path):
        p = d.get("payload") if isinstance(d.get("payload"), dict) else d
        typ = p.get("type")
        if typ == "user_message":                      # event_msg 형식
            t = p.get("message") or ""
            if is_human_text(t):
                last_user, n_event = t, n_event + 1
        elif typ == "agent_message":
            last_assistant = p.get("message") or last_assistant
        elif typ == "message" or p.get("role"):
            role = p.get("role")
            t = text_of(p.get("content"))
            if role == "user" and is_human_text(t):
                last_user, n_item = t, n_item + 1
            elif role == "assistant" and t.strip():
                last_assistant = t
        elif typ == "turn_context" and p.get("cwd") and not info["cwd"]:
            info["cwd"] = p.get("cwd")
    info.update(last_user=last_user, last_assistant=last_assistant, turns=n_event or n_item)
    return info


def scan_codex(cfg, state, dry):
    root = os.path.expandvars(cfg.get("codex_dir") or "")
    if not root or not os.path.isdir(root):
        return 0, "Codex 세션 폴더 없음"
    seen = state.setdefault("codex", {})
    # 처음 실행한 시점 기준 first_scan_hours 이전에 끝난 세션은 계속 무시한다
    if not state.get("codex_since"):
        state["codex_since"] = time.time() - cfg.get("first_scan_hours", 24) * 3600
    cutoff = state["codex_since"]
    sent, found = 0, 0
    for dirpath, _, files in os.walk(root):
        for fn in files:
            if not fn.endswith(".jsonl"):
                continue
            path = os.path.join(dirpath, fn)
            try:
                mtime = os.path.getmtime(path)
            except OSError:
                continue
            if mtime < cutoff or seen.get(path) == mtime:
                continue
            found += 1
            info = parse_codex(path)
            if not info["last_user"] and not info["last_assistant"]:
                seen[path] = mtime
                continue
            payload = {
                "type": "activity", "tool": cfg.get("codex_tool", "chatgpt_app"), "source": "scan",
                "session_key": "codex:" + str(info["id"]), "cwd": info["cwd"],
                "last_prompt": clip(info["last_user"], 500), "summary": clip(info["last_assistant"], 300),
                "turn_count": info["turns"] or None, "at": iso(mtime),
                "started_at": info["started"],
            }
            payload = {k: v for k, v in payload.items() if v is not None}
            if dry:
                if found <= 5:
                    print("  [Codex]", json.dumps(payload, ensure_ascii=False)[:400])
                continue
            r = post(cfg, payload)
            if r.get("ok"):
                seen[path] = mtime
                sent += 1
            elif not r.get("network"):
                seen[path] = mtime
                log(f"Codex 전송 거부: {r.get('error')} ({fn})")
            else:
                log(f"Codex 전송 실패(다음에 재시도): {r.get('error')}")
                break
    # 사라진 파일 기록 정리
    for p in [p for p in seen if not os.path.exists(p)]:
        seen.pop(p, None)
    return sent, f"변경 {found}건"


# ---------------------------------------------------------------------
# HANDOFF.md
# ---------------------------------------------------------------------
def find_handoffs(roots, depth):
    for root in roots:
        root = os.path.expandvars(root)
        if not os.path.isdir(root):
            continue
        base = root.rstrip("\\/").count(os.sep)
        for dirpath, dirs, files in os.walk(root):
            dirs[:] = [d for d in dirs if d not in SKIP_DIRS and not d.startswith(".")]
            if dirpath.count(os.sep) - base >= depth:
                dirs[:] = []
            for fn in files:
                if fn.lower() == "handoff.md":
                    yield os.path.join(dirpath, fn)


def scan_handoffs(cfg, state, dry):
    seen = state.setdefault("handoff", {})
    sent, found, msgs = 0, 0, []
    now = time.time()
    for path in find_handoffs(cfg.get("watch_roots") or [], int(cfg.get("watch_depth", 3))):
        found += 1
        try:
            with open(path, "rb") as f:
                raw = f.read(60_000)
        except OSError:
            continue
        h = hashlib.sha1(raw).hexdigest()
        prev = seen.get(path) if isinstance(seen.get(path), dict) else {}
        # 같은 내용은 다시 보내지 않는다. 프로젝트 연결이 안 됐던 것은 6시간마다 다시 시도
        if prev.get("h") == h and (prev.get("ok") or now - prev.get("t", 0) < 6 * 3600):
            continue
        payload = {"type": "handoff", "cwd": os.path.dirname(path),
                   "content": raw.decode("utf-8", errors="ignore"),
                   "at": iso(os.path.getmtime(path))}
        if dry:
            print("  [HANDOFF]", path)
            continue
        r = post(cfg, payload)
        if r.get("network"):
            log(f"HANDOFF 전송 실패(다음에 재시도): {r.get('error')}")
            break
        seen[path] = {"h": h, "ok": bool(r.get("ok")), "t": now}
        if r.get("ok"):
            sent += 1
        else:
            msgs.append(f"{os.path.basename(os.path.dirname(path))}: {r.get('error')}")
    return sent, f"발견 {found}개" + (f", 연결 안 됨: {'; '.join(msgs)}" if msgs else "")


# ---------------------------------------------------------------------
def run(dry=False, verbose=False) -> dict:
    cfg = load_config()
    if not cfg.get("token"):
        msg = "연결 키가 없습니다. install을 먼저 실행하세요."
        print(msg) if verbose or dry else log(msg)
        return {"ok": False, "error": msg}
    # 진단(dry-run)은 이전 전송 상태와 관계없이 최근 7일 내용을 보여 준다
    state = load_state() if not dry else {"codex_since": time.time() - 7 * 86400}
    result = {"ok": True}
    if not dry:
        hb = post(cfg, {"type": "heartbeat"})
        result["heartbeat"] = hb
        if not hb.get("ok"):
            log(f"접속 신호 실패: {hb.get('error')}")
            if not hb.get("network"):
                result["ok"] = False
                return result          # 키 문제 등은 이후 단계도 실패하므로 중단
        result["queue"] = flush_queue(cfg)
    result["codex"] = scan_codex(cfg, state, dry)
    result["handoff"] = scan_handoffs(cfg, state, dry)
    if not dry:
        save_state(state)
        log(f"실행 완료: Codex {result['codex'][0]}건({result['codex'][1]}), "
            f"HANDOFF {result['handoff'][0]}건({result['handoff'][1]}), 재전송 {result['queue']}건")
    if verbose:
        print(f"  Codex 세션 전송: {result['codex'][0]}건 ({result['codex'][1]})")
        print(f"  HANDOFF.md 전송: {result['handoff'][0]}건 ({result['handoff'][1]})")
    return result


if __name__ == "__main__":
    try:
        run(dry="--dry-run" in sys.argv, verbose="--verbose" in sys.argv or "--dry-run" in sys.argv)
    except Exception as e:
        log(f"에이전트 오류: {e}")
        if "--verbose" in sys.argv:
            raise
