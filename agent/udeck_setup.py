"""
UDeck 자동 기록 - 설치·제거·상태 확인
  python udeck_setup.py install     [--token udk_...] [--url ...] [--workspace 코드] [--no-test-record]
  python udeck_setup.py uninstall   [--purge]
  python udeck_setup.py status
  python udeck_setup.py diagnose    보낼 내용을 전송 없이 출력
  python udeck_setup.py watch [폴더…]   HANDOFF.md 감시 폴더 보기·추가 (--remove 폴더 로 제거)

install이 하는 일
  1) 연결 키 확인 (인수 → 클립보드 → 직접 입력 순)
  2) UDeck 연결 시험 (접속 신호)
  3) Claude Code 설정(~/.claude/settings.json)에 Stop hook 추가 (기존 설정은 백업 후 유지)
  4) Windows 작업 스케줄러에 15분마다 에이전트 실행 등록
  5) 에이전트 1회 실행, 시험 기록 1건 전송
"""
import json
import os
import shutil
import subprocess
import sys
import time
from datetime import datetime

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from udeck_common import (AGENT_DIR, CONFIG_PATH, LOG_PATH, VERSION, load_config, save_config,
                          post, computer_name, iso, local_dir)

TASK_NAME = "UDeck Agent"
HOOK_FILE = os.path.join(AGENT_DIR, "udeck_hook.py")
AGENT_FILE = os.path.join(AGENT_DIR, "udeck_agent.py")
IS_WIN = os.name == "nt"


def say(msg=""):
    print(msg, flush=True)


def arg(name, default=None):
    if name in sys.argv:
        i = sys.argv.index(name)
        if i + 1 < len(sys.argv):
            return sys.argv[i + 1]
    return default


def claude_settings_path():
    base = os.environ.get("CLAUDE_CONFIG_DIR") or os.path.join(os.path.expanduser("~"), ".claude")
    return os.path.join(base, "settings.json")


def python_paths():
    exe = sys.executable
    pyw = os.path.join(os.path.dirname(exe), "pythonw.exe")
    return exe, (pyw if IS_WIN and os.path.exists(pyw) else exe)


def fwd(p):  # Claude Code hook 명령은 셸에서 실행되므로 / 경로가 안전하다
    return p.replace("\\", "/")


def read_clipboard():
    try:
        if IS_WIN:
            out = subprocess.run(["powershell", "-NoProfile", "-Command", "Get-Clipboard"],
                                 capture_output=True, text=True, timeout=10, encoding="utf-8", errors="ignore")
            return (out.stdout or "").strip()
    except Exception:
        pass
    return ""


def mask(t):
    return t[:8] + "…" + t[-4:] if t and len(t) > 14 else t


# ---------------------------------------------------------------------
def normalize_url(u: str) -> str:
    u = (u or "").strip()
    if not u:
        return ""
    if not u.startswith(("http://", "https://")):
        u = "https://" + u
    if not u.endswith("ingest.php"):
        u = u.rstrip("/") + "/ingest.php"
    return u


def step_url(cfg):
    url = normalize_url(arg("--url") or cfg.get("url") or "")
    while not url:
        say("  UDeck 주소를 입력하세요. 예: https://your-domain.com/udeck/")
        url = normalize_url(input("  주소: "))
    cfg["url"] = url
    say(f"  {url}")


def step_token(cfg):
    tok = arg("--token") or cfg.get("token") or ""
    if tok and not tok.startswith("udk_"):
        tok = ""
    if not tok:
        clip = read_clipboard()
        if clip.startswith("udk_") and len(clip) > 20 and " " not in clip:
            tok = clip
            say(f"  클립보드에서 연결 키를 찾았습니다: {mask(tok)}")
    while not tok:
        say("  UDeck 설정 → 연결 키에서 '용도: PC 작업 기록, PC: 이 PC'로 만든 키를 붙여 넣으세요.")
        tok = input("  연결 키: ").strip()
        if not tok.startswith("udk_"):
            say("  udk_로 시작하는 키여야 합니다.")
            tok = ""
    cfg["token"] = tok


def step_connect(cfg):
    r = post(cfg, {"type": "heartbeat"})
    if r.get("ok"):
        return True
    err = r.get("error") or ""
    if "workspace" in err:
        # 연결 키에 PC가 지정되지 않은 경우: 코드 입력
        code = arg("--workspace") or input("  이 PC의 작업공간 코드(설정 화면의 코드, 예: home): ").strip()
        cfg["workspace"] = code
        r = post(cfg, {"type": "heartbeat"})
        if r.get("ok"):
            return True
        err = r.get("error") or err
    say(f"  연결 실패: {err}")
    if r.get("status") == 401:
        say("  연결 키가 맞지 않거나 폐기되었습니다. 설정에서 새로 만들어 다시 실행하세요.")
    if r.get("network"):
        say("  인터넷 연결 또는 주소를 확인하세요: " + cfg["url"])
    return False


def hook_entry(py):
    return {"hooks": [{"type": "command", "command": f'"{fwd(py)}" "{fwd(HOOK_FILE)}"', "timeout": 15}]}


def is_udeck_entry(entry):
    return any("udeck_hook.py" in (h.get("command") or "") for h in (entry.get("hooks") or []) if isinstance(h, dict))


def step_hook(install=True):
    path = claude_settings_path()
    settings = {}
    if os.path.exists(path):
        try:
            with open(path, encoding="utf-8") as f:
                settings = json.load(f)
        except Exception as e:
            say(f"  Claude Code 설정 파일을 읽지 못해 hook 설정을 건너뜁니다: {e}")
            return False
        backup = path + ".udeck-backup-" + datetime.now().strftime("%Y%m%d%H%M%S")
        shutil.copy2(path, backup)
    elif not install:
        return True
    hooks = settings.setdefault("hooks", {})
    stop = [e for e in hooks.get("Stop", []) if isinstance(e, dict) and not is_udeck_entry(e)]
    if install:
        py, _ = python_paths()
        stop.append(hook_entry(py))
    if stop:
        hooks["Stop"] = stop
    else:
        hooks.pop("Stop", None)
    if not hooks:
        settings.pop("hooks", None)
    os.makedirs(os.path.dirname(path), exist_ok=True)
    with open(path, "w", encoding="utf-8") as f:
        json.dump(settings, f, ensure_ascii=False, indent=2)
    return True


def step_task(install=True):
    if not IS_WIN:
        say("  Windows가 아니어서 작업 스케줄러 등록을 건너뜁니다.")
        return False
    if install:
        _, pyw = python_paths()
        tr = f'"{pyw}" "{AGENT_FILE}"'
        cmd = ["schtasks", "/Create", "/F", "/SC", "MINUTE", "/MO", "15", "/TN", TASK_NAME, "/TR", tr]
    else:
        cmd = ["schtasks", "/Delete", "/F", "/TN", TASK_NAME]
    out = subprocess.run(cmd, capture_output=True, text=True, errors="ignore")
    if out.returncode != 0 and install:
        say("  작업 스케줄러 등록 실패: " + (out.stderr or out.stdout).strip())
        return False
    return out.returncode == 0


def task_exists():
    if not IS_WIN:
        return False
    out = subprocess.run(["schtasks", "/Query", "/TN", TASK_NAME], capture_output=True, text=True, errors="ignore")
    return out.returncode == 0


def hook_installed():
    path = claude_settings_path()
    try:
        with open(path, encoding="utf-8") as f:
            s = json.load(f)
        return any(is_udeck_entry(e) for e in s.get("hooks", {}).get("Stop", []) if isinstance(e, dict))
    except Exception:
        return False


# ---------------------------------------------------------------------
def install():
    say(f"UDeck 자동 기록 설치 v{VERSION}  (컴퓨터: {computer_name()})")
    cfg = load_config()

    say("\n[1/5] UDeck 주소와 연결 키")
    step_url(cfg)
    step_token(cfg)

    say("\n[2/5] UDeck 연결 시험")
    if not step_connect(cfg):
        save_config(cfg)
        say("\n설치를 중단했습니다. 문제를 해결한 뒤 다시 실행하세요.")
        return 1
    save_config(cfg)
    say(f"  연결 성공: {cfg['url']}")
    say(f"  설정 저장: {CONFIG_PATH}")

    say("\n[3/5] Claude Code hook")
    if step_hook(True):
        say(f"  추가 완료: {claude_settings_path()} (기존 설정은 .udeck-backup-* 로 백업)")

    say("\n[4/5] 15분마다 자동 실행 등록")
    if step_task(True):
        say(f"  작업 스케줄러 '{TASK_NAME}' 등록 완료")

    say("\n[5/5] 첫 실행")
    import udeck_agent
    say(f"  감시 폴더: {', '.join(cfg['watch_roots'])}")
    say(f"  Codex 기록 폴더: {cfg['codex_dir']} ({'있음' if os.path.isdir(cfg['codex_dir']) else '없음'})")
    udeck_agent.run(verbose=True)
    if "--no-test-record" not in sys.argv:
        r = post(cfg, {"type": "activity", "tool": cfg.get("claude_tool", "claude_code"), "source": "hook",
                       "session_key": f"udeck-install-test-{computer_name()}", "cwd": AGENT_DIR,
                       "summary": "UDeck 자동 기록 설치 시험 기록입니다. 확인 후 삭제해도 됩니다.",
                       "at": iso(time.time())})
        say("  시험 기록 전송: " + ("성공. 보드의 이 PC, Claude Code 칸을 확인하세요." if r.get("ok") else f"실패 ({r.get('error')})"))

    say("\n설치가 끝났습니다.")
    say("  Claude Code는 다음 응답부터 자동 기록됩니다. 이미 열려 있는 Claude Code 창은 다시 시작하세요.")
    say(f"  기록 파일: {LOG_PATH}")
    return 0


def uninstall():
    say("UDeck 자동 기록 제거")
    say("  작업 스케줄러: " + ("삭제" if step_task(False) else "등록되어 있지 않음"))
    step_hook(False)
    say("  Claude Code hook: 제거")
    if "--purge" in sys.argv:
        shutil.rmtree(local_dir(), ignore_errors=True)
        say("  설정·상태 파일 삭제: " + local_dir())
    else:
        say("  설정 파일은 남겨 둡니다(다시 설치할 때 연결 키 재사용). 지우려면 --purge")
    return 0


def status():
    cfg = load_config()
    say(f"UDeck 자동 기록 상태 v{VERSION}  (컴퓨터: {computer_name()})")
    say(f"  주소: {cfg['url']}")
    say(f"  연결 키: {mask(cfg.get('token')) or '없음'}")
    say(f"  작업 스케줄러: {'등록됨' if task_exists() else '없음'}")
    say(f"  Claude Code hook: {'설치됨' if hook_installed() else '없음'}")
    if cfg.get("token"):
        r = post(cfg, {"type": "heartbeat"})
        say(f"  UDeck 연결: {'정상' if r.get('ok') else '실패 - ' + str(r.get('error'))}")
    if os.path.exists(LOG_PATH):
        with open(LOG_PATH, encoding="utf-8", errors="ignore") as f:
            lines = f.readlines()[-8:]
        say("  최근 기록:")
        for l in lines:
            say("    " + l.rstrip())
    return 0


def diagnose():
    import udeck_agent
    cfg = load_config()
    say("보낼 내용 미리보기 (전송하지 않음)")
    say(f"  Codex 기록 폴더: {cfg['codex_dir']}")
    say(f"  감시 폴더: {', '.join(cfg['watch_roots'])}")
    udeck_agent.run(dry=True)
    return 0


def watch():
    cfg = load_config()
    roots = list(cfg.get("watch_roots") or [])
    args = [a for a in sys.argv[2:] if a != "--remove"]
    if "--remove" in sys.argv:
        norm = {os.path.normcase(os.path.abspath(a)) for a in args}
        roots = [r for r in roots if os.path.normcase(os.path.abspath(r)) not in norm]
    else:
        for a in args:
            p = os.path.abspath(a)
            if not os.path.isdir(p):
                say(f"  폴더가 없습니다: {p}")
                continue
            if os.path.normcase(p) not in {os.path.normcase(r) for r in roots}:
                roots.append(p)
    if args:
        cfg["watch_roots"] = roots
        save_config(cfg)
    say("HANDOFF.md 감시 폴더 (하위 " + str(cfg.get("watch_depth", 3)) + "단계까지):")
    for r in roots:
        say(f"  {r}  {'(없음)' if not os.path.isdir(r) else ''}")
    return 0


if __name__ == "__main__":
    cmd = sys.argv[1] if len(sys.argv) > 1 else "status"
    fn = {"install": install, "uninstall": uninstall, "status": status, "diagnose": diagnose,
          "watch": watch}.get(cmd)
    if not fn:
        say(__doc__)
        sys.exit(2)
    sys.exit(fn())
