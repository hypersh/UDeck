# PC agent

The `agent/` folder records work on each PC automatically. It is not a background program: the Claude Code hook runs for a moment after each reply, and the agent runs for a few seconds every 15 minutes through Windows Task Scheduler.

## What it records

| Source | When | Shown on the board |
|---|---|---|
| Claude Code | After every reply (`Stop` hook) | That PC's Claude Code cell; project assigned from the work folder |
| Codex (`%USERPROFILE%\.codex\sessions`) | Every 15 minutes, changed sessions only | That PC's ChatGPT cell (configurable) |
| `HANDOFF.md` | Every 15 minutes, when the content changes | The project's handoff note and next step |
| Online signal | Every 15 minutes | "online" at the top of the board |

Sent: PC, tool, session ID, work folder path, the first 500 characters of the last request, the first 300 characters of the last reply, time, and HANDOFF.md content. Not sent: source code, data files, full replies or conversations.

On first install, only Codex sessions changed in the last 24 hours are sent. Records created while offline are queued and sent on the next run.

## Install (once per PC)

Requirements: Windows 10/11 and Python 3.8 or later. No extra packages.

1. In UDeck, open **Settings → Connection keys**. Create a key with purpose **PC work logging** and select **this PC**. Click **Copy key**. Use a separate key for each PC.
2. Put the whole `agent` folder on the PC (a synced folder works well), and double-click `install.cmd`.
3. Enter your UDeck address when asked, for example `https://your-domain.com/udeck/`. The key is read from the clipboard.
4. The installer then:
   1. tests the connection,
   2. adds the hook to `%USERPROFILE%\.claude\settings.json` (the old file is backed up and other settings are kept),
   3. registers the scheduled task **UDeck Agent** (every 15 minutes, no window),
   4. runs once and sends a test record.
5. Check the board: the test record appears in this PC's Claude Code cell. Delete it from Recent records (Edit → Delete).
6. Restart any open Claude Code window so the hook takes effect.

## Files

| File | Purpose |
|---|---|
| `install.cmd` / `install.ps1` | Install |
| `status.cmd` / `status.ps1` | Status and a preview of what would be sent |
| `uninstall.cmd` / `uninstall.ps1` | Remove the scheduled task and the hook |
| `udeck_hook.py` | Claude Code `Stop` hook |
| `udeck_agent.py` | Scheduled agent |
| `udeck_setup.py` | Install, uninstall, status, diagnose and watch commands |
| `udeck_common.py` | Shared code |

## Commands

```powershell
python agent\udeck_setup.py status                 # connection, task, hook, recent log
python agent\udeck_setup.py diagnose               # preview of the last 7 days, nothing is sent
python agent\udeck_setup.py watch                  # list HANDOFF.md watch folders
python agent\udeck_setup.py watch D:\Projects      # add a watch folder
python agent\udeck_setup.py watch --remove D:\Projects
python agent\udeck_setup.py uninstall [--purge]    # --purge also deletes local settings
```

## Local settings

Per-PC settings and state are kept in `%LOCALAPPDATA%\UDeck\` (`config.json`, `state.json`, `queue.jsonl`, `udeck.log`), not in the agent folder, so several PCs can share one synced copy of `agent/`. Microsoft Store Python may redirect this folder to `%LOCALAPPDATA%\Packages\PythonSoftwareFoundation.Python…\LocalCache\Local\UDeck\`.

| Option in `config.json` | Default | Description |
|---|---|---|
| `url` | asked at install | UDeck ingest address |
| `watch_roots` | two levels above the agent folder | Folders searched for `HANDOFF.md` |
| `watch_depth` | `3` | Search depth |
| `codex_tool` | `chatgpt_app` | UDeck tool code for Codex sessions |
| `claude_tool` | `claude_code` | UDeck tool code for Claude Code |
| `first_scan_hours` | `24` | Age limit for the first Codex scan |

## HANDOFF.md

A handoff note in a project folder. When it changes, UDeck updates the project:

- **Next step**: the text after a `Next step:` line, or the first item under a `## Next steps` heading. The Korean markers `다음 할 일:` and `## 다음 할 일` work too (English markers since 1.2.1).
- **Handoff note**: the whole file, shown in the project's edit form.

```markdown
# HANDOFF
## Current status
Second revision of the background section done.

## Next steps
- Draft the budget table

## Notes
- Last worked on: Laptop, Claude Code
```

The file must be inside a watch folder, and a folder name in its path must match a project's **work folder name** in UDeck (the innermost match wins). To let AI tools keep it current, add to `CLAUDE.md` / `AGENTS.md`:

```markdown
## Work handoff
- At the start of a session, read HANDOFF.md first.
- At the end of a session, update "Current status" and "Next steps" in HANDOFF.md.
```

## Troubleshooting

| Symptom | Check |
|---|---|
| Claude Code work not recorded | Restart Claude Code; `status.cmd` should show the hook as installed |
| PC never "online" | Task Scheduler → UDeck Agent → last run result 0x0 |
| HTTP 401 | Key wrong or revoked; create a new key and run `install.cmd` again |
| HTTP 403 | The key's purpose is Google sync, not PC work logging |
| HANDOFF.md ignored | Inside a watch folder? Folder name matches a project's work folder name? |
