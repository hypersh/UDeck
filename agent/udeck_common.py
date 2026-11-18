"""
UDeck 자동 기록 - 공통 모듈
표준 라이브러리만 사용한다. (Python 3.8 이상)

PC별 설정과 상태는 동기화 폴더가 아닌 이 PC의 로컬 폴더에 둔다.
  Windows: %LOCALAPPDATA%\\UDeck\\
  기타:    ~/.udeck/
"""
import json
import os
import platform
import socket
import sys
import time
import urllib.error
import urllib.request
from datetime import datetime, timezone, timedelta

VERSION = "1.0.0"
KST = timezone(timedelta(hours=9))
DEFAULT_URL = ""  # install 때 입력한다. 예: https://your-domain.com/udeck/
AGENT_DIR = os.path.dirname(os.path.abspath(__file__))


def local_dir() -> str:
    base = os.environ.get("UDECK_HOME")
    if not base:
        if os.name == "nt":
            base = os.path.join(os.environ.get("LOCALAPPDATA") or os.path.expanduser("~"), "UDeck")
        else:
            base = os.path.join(os.path.expanduser("~"), ".udeck")
    os.makedirs(base, exist_ok=True)
    return base


CONFIG_PATH = os.path.join(local_dir(), "config.json")
STATE_PATH = os.path.join(local_dir(), "state.json")
QUEUE_PATH = os.path.join(local_dir(), "queue.jsonl")
LOG_PATH = os.path.join(local_dir(), "udeck.log")


def computer_name() -> str:
    return os.environ.get("COMPUTERNAME") or platform.node() or socket.gethostname()


def default_config() -> dict:
    # 이 스크립트 폴더(…\UDeck\agent)의 두 단계 위(예: D:\Work)를 기본 감시 폴더로 쓴다
    watch = os.path.dirname(os.path.dirname(AGENT_DIR))
    return {
        "url": DEFAULT_URL,
        "token": "",
        "workspace": "",                 # 연결 키에 PC가 지정되어 있으면 비워 둔다
        "watch_roots": [watch],          # HANDOFF.md를 찾을 폴더
        "watch_depth": 3,
        "codex_dir": os.path.join(os.path.expanduser("~"), ".codex", "sessions"),
        "codex_tool": "chatgpt_app",     # Codex 세션을 UDeck의 어느 도구로 기록할지
        "claude_tool": "claude_code",
        "first_scan_hours": 24,          # 처음 실행할 때 이 시간 안에 바뀐 세션만 보낸다
    }


def load_config() -> dict:
    cfg = default_config()
    if os.path.exists(CONFIG_PATH):
        try:
            with open(CONFIG_PATH, encoding="utf-8") as f:
                cfg.update(json.load(f))
        except Exception as e:  # 손상된 설정은 기본값으로
            log(f"설정 파일 읽기 실패: {e}")
    return cfg


def save_config(cfg: dict) -> None:
    with open(CONFIG_PATH, "w", encoding="utf-8") as f:
        json.dump(cfg, f, ensure_ascii=False, indent=2)


def load_state() -> dict:
    if os.path.exists(STATE_PATH):
        try:
            with open(STATE_PATH, encoding="utf-8") as f:
                return json.load(f)
        except Exception:
            pass
    return {}


def save_state(state: dict) -> None:
    tmp = STATE_PATH + ".tmp"
    with open(tmp, "w", encoding="utf-8") as f:
        json.dump(state, f, ensure_ascii=False)
    os.replace(tmp, STATE_PATH)


def log(msg: str) -> None:
    line = f"{datetime.now(KST):%Y-%m-%d %H:%M:%S} {msg}\n"
    try:
        # 로그가 1MB를 넘으면 앞부분을 버린다
        if os.path.exists(LOG_PATH) and os.path.getsize(LOG_PATH) > 1_000_000:
            with open(LOG_PATH, encoding="utf-8", errors="ignore") as f:
                keep = f.read()[-200_000:]
            with open(LOG_PATH, "w", encoding="utf-8") as f:
                f.write(keep)
        with open(LOG_PATH, "a", encoding="utf-8") as f:
            f.write(line)
    except Exception:
        pass


def iso(ts: float) -> str:
    return datetime.fromtimestamp(ts, KST).isoformat(timespec="seconds")


def clip(s, n: int):
    if s is None:
        return None
    s = " ".join(str(s).split())
    return s[:n] if s else None


def post(cfg: dict, payload: dict, timeout: float = 8.0) -> dict:
    """UDeck으로 전송. 반환: 서버 응답(dict). 네트워크 오류는 {'ok': False, 'network': True}"""
    if cfg.get("workspace") and "workspace" not in payload:
        payload = dict(payload, workspace=cfg["workspace"])
    data = json.dumps(payload, ensure_ascii=False).encode("utf-8")
    req = urllib.request.Request(
        cfg["url"], data=data, method="POST",
        headers={"Content-Type": "application/json; charset=utf-8",
                 "X-UDeck-Token": cfg.get("token", ""),
                 "User-Agent": f"UDeckAgent/{VERSION} ({computer_name()})"})
    try:
        with urllib.request.urlopen(req, timeout=timeout) as r:
            return json.loads(r.read().decode("utf-8"))
    except urllib.error.HTTPError as e:
        try:
            body = json.loads(e.read().decode("utf-8"))
        except Exception:
            body = {"ok": False, "error": f"HTTP {e.code}"}
        body.setdefault("ok", False)
        body["status"] = e.code
        return body
    except Exception as e:
        return {"ok": False, "network": True, "error": str(e)}


def post_or_queue(cfg: dict, payload: dict) -> dict:
    """전송 실패(네트워크)면 대기열에 넣어 다음 에이전트 실행 때 다시 보낸다"""
    res = post(cfg, payload)
    if not res.get("ok") and res.get("network"):
        try:
            with open(QUEUE_PATH, "a", encoding="utf-8") as f:
                f.write(json.dumps(payload, ensure_ascii=False) + "\n")
        except Exception:
            pass
    return res


def flush_queue(cfg: dict) -> int:
    if not os.path.exists(QUEUE_PATH):
        return 0
    with open(QUEUE_PATH, encoding="utf-8") as f:
        items = [json.loads(l) for l in f if l.strip()]
    left, sent = [], 0
    for p in items[-500:]:
        r = post(cfg, p)
        if r.get("ok") or not r.get("network"):
            sent += 1          # 성공했거나, 다시 보내도 소용없는 오류(형식 등)
        else:
            left.append(p)
    with open(QUEUE_PATH, "w", encoding="utf-8") as f:
        for p in left:
            f.write(json.dumps(p, ensure_ascii=False) + "\n")
    return sent


# ---------------------------------------------------------------------
# JSONL 대화 기록에서 텍스트 꺼내기 (Claude Code, Codex 공용)
# ---------------------------------------------------------------------
SKIP_PREFIX = ("<environment_context", "<user_instructions", "<command-", "<local-command",
               "<system-reminder", "Caveat:", "<user-prompt-submit-hook")


def text_of(content) -> str:
    """메시지 content(문자열 또는 블록 목록)에서 사람이 쓴 텍스트만 꺼낸다"""
    if isinstance(content, str):
        return content
    out = []
    if isinstance(content, list):
        for b in content:
            if isinstance(b, dict) and b.get("type") in ("text", "input_text", "output_text"):
                out.append(b.get("text") or "")
            elif isinstance(b, str):
                out.append(b)
    return "\n".join(t for t in out if t)


def is_human_text(t: str) -> bool:
    t = (t or "").strip()
    return bool(t) and not t.startswith(SKIP_PREFIX)


def read_tail_lines(path: str, max_bytes: int = 600_000):
    """큰 파일은 끝부분만 읽는다"""
    size = os.path.getsize(path)
    with open(path, "rb") as f:
        if size > max_bytes:
            f.seek(size - max_bytes)
            f.readline()  # 잘린 첫 줄 버림
        data = f.read().decode("utf-8", errors="ignore")
    for line in data.splitlines():
        line = line.strip()
        if line:
            try:
                yield json.loads(line)
            except Exception:
                continue


def read_head_lines(path: str, n: int = 20):
    with open(path, "rb") as f:
        for _ in range(n):
            line = f.readline()
            if not line:
                break
            try:
                yield json.loads(line.decode("utf-8", errors="ignore"))
            except Exception:
                continue
