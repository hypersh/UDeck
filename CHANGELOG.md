# Changelog

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
