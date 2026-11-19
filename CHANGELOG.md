# Changelog

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
