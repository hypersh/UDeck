# Installation

## 1. Check the server

UDeck needs Apache with `.htaccess` support, PHP 8.0 or later with the `pdo_sqlite` driver, and SQLite 3.26 or later.

1. Upload `tools/server_check.php` to your web root.
2. Open `https://your-domain.com/server_check.php`.
3. Confirm that **pdo_sqlite driver** and **SQLite create/write/read** pass. "Writable outside web root" should also pass so the private folder can live outside the web root.
4. Delete `server_check.php` from the server. It shows server paths and settings.

## 2. Upload

UDeck finds its private folder at `../../udeck_private` relative to the public folder. Place the two folders like this:

```
/home/you/
├─ public_html/              web root (may be named www, htdocs, ...)
│  └─ udeck/                 contents of udeck/
│     ├─ .htaccess
│     ├─ index.php, setup.php, login.php, logout.php, settings.php, help.php
│     ├─ api.php, ingest.php
│     ├─ _bootstrap.php, _partials.php, _help_en.php, _help_ko.php
│     └─ assets/css/udeck.css, assets/js/board.js
└─ udeck_private/            contents of udeck_private/
   ├─ .htaccess
   ├─ config.php             created by you in step 3
   ├─ config.sample.php
   ├─ schema.sql
   ├─ lang/en.php
   ├─ gas/UDeck_Sync.gs      shown on the Help page for copying
   └─ lib/db.php, util.php, auth.php, repo.php, i18n.php
```

- Upload both `.htaccess` files. FTP clients often hide files that start with a dot (in FileZilla: Server → Force showing hidden files).
- `udeck_private/data/` and `udeck_private/sessions/` are created on the first visit. PHP must be able to write to `udeck_private/`.
- If your host cannot store files outside the web root, `udeck_private/.htaccess` still blocks web access, but keeping it outside is strongly recommended.

## 3. Configure

Copy `udeck_private/config.sample.php` to `udeck_private/config.php` and edit:

| Option | Description |
|---|---|
| `setup_key` | Required. A long random value you enter once when setting the password. Setup is refused while it is empty or `CHANGE-ME`. |
| `language` | Interface language until one is chosen in Settings: `en` or `ko`. |
| `remember_seconds` | How long "Keep me logged in" lasts. Default 14 days. |
| `login_max_fail`, `login_window_min` | Login lockout. Default: 5 failures in 15 minutes. |
| `allowed_ips` | Optional allow list in CIDR, e.g. `['100.64.0.0/10']` for Tailscale. |

## 4. First run

1. Open `https://your-domain.com/udeck/`. You are sent to the password setup page.
2. Enter the setup key and a password of at least 12 characters. The server check at the bottom should show no failures.
3. You are logged in and the board opens.
4. Choose the PC this browser is on in the selector at the top. Do this once per PC and browser.
5. In Settings, rename the default workspaces (PCs) and tools, add your own, and fill in each PC's Windows computer name.

## 5. Next steps

- [Google sync](GOOGLE_SYNC.md) for Calendar, Tasks and Gmail
- [PC agent](PC_AGENT.md) for automatic logging and HANDOFF.md
- [Ingest API](INGEST_API.md) if you want to send records from your own tools

## Updating

Upload the new `udeck/` and `udeck_private/` files over the old ones, but keep `udeck_private/config.php`, `data/` and `sessions/`. Database changes are applied automatically on the next page load. Back up `udeck_private/data/udeck.sqlite` before updating.

## Troubleshooting

| Symptom | Check |
|---|---|
| "UDeck private folder (udeck_private) not found" | `udeck_private` must be in the same folder as your web root folder |
| Page has no styling | `assets/` must be inside `udeck/` |
| "No setup key is configured" | Create `config.php` and set `setup_key` |
| "The server could not process this" | Read `udeck_private/data/php_error.log` |
| Logged out repeatedly | Tick "Keep me logged in"; check that `udeck_private/sessions/` exists and is writable |
| Forgot the password | Back up `udeck.sqlite`, then delete the `password_hash` row from the `settings` table with any SQLite tool and set a new password on the setup page |
