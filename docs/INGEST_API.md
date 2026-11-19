# Ingest API

The PC agent, the Claude Code hook and the Google Apps Script all send data to one endpoint. You can use it from your own scripts too.

## Common

- `POST https://your-domain.com/udeck/ingest.php`
- Headers: `Content-Type: application/json` and `X-UDeck-Token: udk_...` (`Authorization: Bearer udk_...` also works)
- Create keys in **Settings → Connection keys**. A key is shown only once and stored as a hash.
  - Purpose **PC work logging**: may send `activity`, `handoff`, `heartbeat`. If the key is tied to a PC, records always go to that PC.
  - Purpose **Google sync**: may send `google` only.
- Response: `{"ok": true, ...}` or `{"ok": false, "error": "..."}`. Error messages follow the interface language.
- Times: ISO 8601 (`2026-11-19T22:30:00+09:00`) or `2026-11-19 22:30:00` (read as KST). If omitted, the time of receipt is used.

## `activity` — work record

Sending again with the same PC, tool and `session_key` updates the existing record instead of adding one; the turn count goes up by one unless `turn_count` is given.

| Field | Required | Description |
|---|---|---|
| `type` | yes | `"activity"` |
| `workspace` | if the key has no PC | Workspace code from Settings, e.g. `home` |
| `tool` | yes | Tool code from Settings, e.g. `claude_code`, `chatgpt_app` |
| `session_key` | yes | Session ID of the tool |
| `cwd` | | Work folder. Matched against projects' work folder names, innermost folder first |
| `summary` | | Progress summary (up to 1,000 characters) |
| `progress` | | Progress in percent, 0 to 100. Kept when later updates omit it |
| `last_prompt` | | Last request (up to 500 characters) |
| `title`, `stage` | | Title and stage name |
| `next_action` | | Becomes the project's next step |
| `turn_count` | | Set the turn count explicitly |
| `at`, `started_at` | | Record time and session start |
| `ended` | | `true` marks the session as ended |
| `source` | | `hook` (default) or `scan` |
| `meta` | | Any JSON object, stored as is |

## `handoff` — HANDOFF.md

| Field | Required | Description |
|---|---|---|
| `type` | yes | `"handoff"` |
| `cwd` or `project` | yes | Folder that holds the file, or the project slug |
| `content` | yes | The whole file (up to 20,000 characters) |

The project's next step is taken from a `다음 할 일: …` line or the first item under a `## 다음 할 일` heading. If no project matches, the response is `ok: false` with an explanation.

## `heartbeat` — online signal

```json
{"type": "heartbeat", "workspace": "home"}
```

A PC with a signal in the last 20 minutes is shown as online.

## `google` — events, tasks, mail

Data is stored per `account`. Each list you send (`events`, `tasks`, `mails`) replaces that account's previous list of the same kind; lists you do not send, and other accounts' data, are left alone. `unread` is stored per account and the board shows the total.

```json
{
  "type": "google",
  "account": "me@example.com",
  "unread": 12,
  "events": [{"id": "abc_1795046400000", "calendar": "Work", "title": "Lecture", "detail": "Room 302",
              "start": "2026-11-19T09:00:00+09:00", "end": "2026-11-19T10:15:00+09:00",
              "all_day": false, "link": "https://calendar.google.com/..."}],
  "tasks":  [{"id": "t1", "list": "Research", "title": "Review abstract",
              "notes": "", "due": "2026-11-20", "status": "needsAction", "link": "https://tasks.google.com/"}],
  "mails":  [{"id": "m1", "label": "INBOX", "from": "Research office", "subject": "Program schedule",
              "date": "2026-11-19T08:41:00+09:00", "status": "unread", "link": "https://mail.google.com/..."}]
}
```

Mail `status` `starred` shows a star. Do not send message bodies.

## Example (PowerShell)

```powershell
$token = "udk_your_key"
$body = @{
  type = "activity"; tool = "claude_code"; session_key = "test-1"
  cwd = "D:\Work\Grant_2027"; summary = "API test record"
} | ConvertTo-Json
Invoke-RestMethod -Method Post -Uri "https://your-domain.com/udeck/ingest.php" `
  -Headers @{ "X-UDeck-Token" = $token } -ContentType "application/json; charset=utf-8" `
  -Body ([System.Text.Encoding]::UTF8.GetBytes($body))
```

## Example (curl)

```bash
curl -X POST https://your-domain.com/udeck/ingest.php \
  -H "X-UDeck-Token: udk_your_key" -H "Content-Type: application/json" \
  -d '{"type":"activity","tool":"claude_code","session_key":"test-1","summary":"API test record"}'
```
