# Google sync (Apps Script)

A Google Apps Script reads your Calendar, Tasks and Gmail with your own account's permission and sends a summary to UDeck every 10 minutes. UDeck stores no Google password or OAuth token.

## What is sent

| Item | Contents |
|---|---|
| Events | Calendars that are checked (visible) in Google Calendar, from 7 days before this month to 7 days after it. Declined events are skipped. |
| Tasks | Open tasks from all lists, with list name, due date and position in Google Tasks' "My order" (script v1.1 or later) |
| Mail | Inbox threads that are unread or starred, last 7 days, up to 30: subject, sender name, date. No message bodies. |

## Setup

1. In UDeck, open **Settings → Connection keys**, create a key with purpose **Google sync**, and copy it. It is shown only once.
2. Go to [script.google.com](https://script.google.com) and create a **New project**. Check the profile picture at the top right: the script runs as that account.
3. Replace the default code with [`gas/UDeck_Sync.gs`](../gas/UDeck_Sync.gs). The same code is on UDeck's Help page with a copy button.
4. Next to **Services**, click **+** and add **Google Tasks API** (identifier `Tasks`).
5. In **Project Settings** (gear icon), set the time zone, then add two **Script properties**:

   | Property | Value |
   |---|---|
   | `UDECK_URL` | `https://your-domain.com/udeck/ingest.php` |
   | `UDECK_TOKEN` | the key from step 1 |

6. Save (`Ctrl + S`), pick `testSync` in the function list and click **Run**.
7. Approve the permissions. Because you wrote the script yourself, Google shows "Google hasn't verified this app"; choose **Advanced → Go to UDeck Sync** and **Allow**.
8. The execution log should show `"ok":true` with the account and item counts.
9. Run `installTrigger` to send every 10 minutes. Run `removeTrigger` to stop.

## Updating the script

When a UDeck release changes the Apps Script (see the [changelog](../CHANGELOG.md)), replace the code in every account that sends data:

1. Sign in to [script.google.com](https://script.google.com) with that account and open the UDeck sync project.
2. Open `Code.gs` and note the `send` value in `CONFIG` at the top.
3. Click in the editor, press `Ctrl + A`, then `Delete`, and paste the new [`gas/UDeck_Sync.gs`](../gas/UDeck_Sync.gs).
4. Set `send` back to the value you noted and save with `Ctrl + S`. The second line shows the script version.
5. Run `testSync` and check for `"ok":true`. Allow the permissions again if asked.

Script properties and the trigger are stored apart from the code, so they stay as they are.

## Several Google accounts

An Apps Script reads only the data of the account that owns it. If your mail, calendar and tasks are in different accounts, create one project per account and change `send` at the top of the code:

```javascript
// Personal account: mail only
send: { events: false, tasks: false, mails: true },

// Work account: events and tasks
send: { events: true, tasks: true, mails: false },
```

UDeck stores data per account, so the projects never overwrite each other. Links on the board open the right account.

Work or school Workspace accounts may block unverified scripts or external requests. In that case, share the calendar with a personal account or forward the mail, and collect it from there.

## Options (`CONFIG` at the top of the code)

| Option | Default | Description |
|---|---|---|
| `send` | all `true` | What this account sends |
| `eventPadDays` | `7` | Extra days before and after this month |
| `mailQuery` | `in:inbox (is:unread OR is:starred) newer_than:7d` | Gmail search syntax. Starred only: `in:inbox is:starred` |
| `mailMax` | `30` | Number of threads |
| `taskMax` | `100` | Tasks per list |
| `excludeCalendars` | `[]` | Calendar names to skip |
| `skipDeclined` | `true` | Skip declined events |

## Diagnosing empty results

Run `diagnoseUDeck`. The log lists the account in use, each calendar with its visibility and event count, task counts per list, and mail counts for the current query and for the whole inbox.

## Quotas

The script runs 144 times a day and each run takes a few seconds, well within the daily trigger runtime that Google allows consumer accounts (90 minutes at the time of writing).
