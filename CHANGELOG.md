# Changelog

## 1.2.2 (2026-11-19)

- Notes can have several lines. Enter adds a line break; Ctrl + Enter (⌘ + Enter on a Mac) or the Add button adds or saves the note. Line breaks are shown as typed, and the input grows with the text

## 1.2.1 (2026-11-19)

- Tasks now follow the "My order" sorting of Google Tasks: lists in Google's order, items by position, subtasks indented under their parent. Requires the updated Apps Script (`gas/UDeck_Sync.gs` v1.1); tasks sent by the old script keep the previous order
- HANDOFF.md next step is also read from English markers: `Next step: …` or the first item under `## Next steps`
- Docs: complete Korean installation and setup manual (`docs/MANUAL.ko.md`); how to update the Apps Script in `docs/GOOGLE_SYNC.md` and the in-app Help
- Database schema version 7 (adds `google_items.sort_key`)

## 1.2 (2026-11-19)

- **Workspaces by project.** Each workspace row now shows one box per project worked on there (most recent first) instead of one column per tool. A box shows the project, latest work, the tools used as short-name icons, progress (bar and %), the last time and a 7-day strip. The same project appears in every workspace where it was worked on. ◀ ▶ scroll when the boxes do not fit
- **Progress.** Work records have an optional progress value (0 to 100), entered with a slider or a number. The ingest API accepts `progress` too
- **Project list.** New columns: rank, devices, tools and progress (replacing the work flow). Sort by importance, deadline or date added. Importance is set by dragging the rank number (or with the arrow keys)
- Tool icons show a short label (Codex, GPT, Claude, Claude C, Gemini, Antigravity, or your own), editable as "Icon label" in Settings
- Workspaces can be PCs or people; the settings wording changed accordingly
- The priority field (high, normal, low) was removed from the project form; the importance order replaces it
- Database schema version 6 (adds `activities.progress` and `tools.short_name`; fills `projects.sort_order` from the old priority and creation order)

## 1.1 (2026-11-19)

- The three columns are now named **List board** (schedule, tasks, mail), **Status board** (workspaces, projects, recent records) and **Black board** (notes, AI assistant)
- **AI assistant** below the notes: rule-based, no external API. Shows urgent items (overdue or due-today tasks, events in the next 3 hours, near deadlines, starred mail), suggests what to pick up today from the most recent project work, and adds a proverb or short line. Recalculates every 15 minutes or on Refresh
- **Deadline types** for projects: no deadline, self-set (ochre D-day) or fixed (red D-day pill)
- Workspace matrix shows every tool that has records, up to 3 at a time, with ◀ ▶ buttons to scroll to more; the PC column stays fixed
- Responsive layout: three columns down to about 1200px (16-inch laptops); two columns below that, with the Black board above the Status board on the right; on phones, tabs switch between the three boards
- Database schema version 4 (adds `projects.due_type`; existing due dates become self-set)

## 1.0 (2026-11-19)

First public release.

- Board with schedule (next 2 days, this week, this month), tasks grouped by list, mail, notes, workspace matrix, projects and recent records
- Three-column layout with a wide notes column; responsive to phones; light and dark themes
- Manual work records and projects, with main workspace, work folder, stage, next step and due date
- Automatic project assignment from the work folder, matching the innermost folder first
- Ingest API with hashed connection keys for the PC agent and Google Apps Script
- PC agent for Windows: installer, Claude Code `Stop` hook, Codex session scan, `HANDOFF.md` watch, offline queue
- Google Apps Script for Calendar, Tasks and Gmail, with per-account data
- Settings for workspaces, tools, connection keys, password, language and the received data log
- English and Korean interface and built-in help
- Single-user login with lockout, CSRF protection, private data outside the web root, required setup key
- SQLite schema with automatic migrations (schema version 3)
