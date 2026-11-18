"""
UDeck 자동 기록 - Claude Code Stop hook
Claude Code가 응답을 마칠 때마다 실행된다. (설정: %USERPROFILE%\\.claude\\settings.json)
표준 입력으로 받은 JSON: session_id, transcript_path, cwd, (last_assistant_message)

원칙: 어떤 경우에도 Claude Code 작업을 막지 않는다. 오류가 나도 조용히 종료한다.
"""
import json
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from udeck_common import (load_config, post_or_queue, log, clip, text_of, is_human_text,
                          read_tail_lines, iso)


def summarize_transcript(path: str):
    """대화 기록에서 마지막 사람 요청, 마지막 답변, 사람 요청 수를 찾는다"""
    last_user, last_assistant, turns = None, None, 0
    if not path or not os.path.exists(path):
        return last_user, last_assistant, turns
    for d in read_tail_lines(path):
        if not isinstance(d, dict) or d.get("isMeta") or d.get("isSidechain"):
            continue
        msg = d.get("message") if isinstance(d.get("message"), dict) else {}
        kind = d.get("type") or msg.get("role")
        if kind == "user":
            t = text_of(msg.get("content"))
            if is_human_text(t):
                last_user = t
                turns += 1
        elif kind == "assistant":
            t = text_of(msg.get("content"))
            if t.strip():
                last_assistant = t
    return last_user, last_assistant, turns


def main():
    try:
        raw = sys.stdin.read()
        data = json.loads(raw) if raw.strip() else {}
    except Exception:
        data = {}
    cfg = load_config()
    if not cfg.get("token"):
        return
    session = data.get("session_id")
    if not session:
        return
    tpath = data.get("transcript_path")
    last_user, last_assistant, turns = summarize_transcript(tpath)
    summary = data.get("last_assistant_message") or last_assistant
    payload = {
        "type": "activity",
        "tool": cfg.get("claude_tool", "claude_code"),
        "session_key": session,
        "source": "hook",
        "cwd": data.get("cwd"),
        "last_prompt": clip(last_user, 500),
        "summary": clip(summary, 300),
        "at": iso(__import__("time").time()),
    }
    if turns:
        payload["turn_count"] = turns
    res = post_or_queue(cfg, payload)
    if not res.get("ok"):
        log(f"hook 전송 실패: {res.get('error')}")


if __name__ == "__main__":
    try:
        main()
    except Exception as e:  # 절대 Claude Code를 방해하지 않는다
        try:
            log(f"hook 오류: {e}")
        except Exception:
            pass
    sys.exit(0)
