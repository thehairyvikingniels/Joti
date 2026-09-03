# Jotify ??? LLM Agent Rules

> These rules govern AI assistant behavior when working on the Jotify codebase.
> This file is local-only (.gitignored) and not shared via version control.

---

## 1. Golden Rules

### 1.1 Never Commit or Push Without Approval
**NEVER** run `git add`, `git commit`, `git push`, or any destructive git operation without the user's **explicit** permission. Always present the changes first and wait for approval.

### 1.2 Follow the Code of Conduct
All code you write **must** comply with `CODE_OF_CONDUCT.md` in the project root. Read it before making changes if you haven't already.

### 1.3 Dutch User, English Code
- The user communicates in **English**. Respond in English unless they switch to Dutch.
- All code, comments, variable names, function names, and commit messages must be in **English**.
- All user-facing UI text (labels, buttons, error messages) must be in **Dutch**.

---

## 2. Project Context

### 2.1 Technology Stack
- **Backend**: PHP 8.x (no framework, procedural MVC pattern)
- **Database**: MySQL/MariaDB via `mysqli` (OOP style, prepared statements only, `utf8mb4` charset)
- **Frontend**: HTML5, Tailwind CSS (via CDN), vanilla JavaScript (ES6+)
- **Maps**: Mapbox GL JS and Leaflet
- **Notifications**: Web Push API via `minishlink/web-push`
- **Server**: Apache with `.htaccess` URL rewriting (`.php` extension hidden)
- **Deployment**: Git-based on the production server at `/var/www/Joti/`

### 2.2 Architecture Overview
- **No framework**: Each `.php` file is a standalone page controller
- **Bootstrap file**: `includes/auth.php` handles session, user loading, and site settings
- **Shared components**: `includes/sidebar.php`, `includes/topbar.php`, `includes/footer.php`
- **AJAX handlers**: `*_helper.php` files process POST/AJAX requests
- **Cron jobs**: `cron/` directory contains scheduled background tasks (executed headless without session auth)
- **Database schema**: `DB/createDB.sql` is the canonical schema reference

### 2.3 Access Levels & Test Accounts
| Level | Name | Test Username | Test Password | Access |
|### 2.5 Jotihunt Game Domain & Terminology
- **The Game**: 26-hour tactical foxhunt (Saturday 10:00 ??? Sunday 12:00, third weekend of October) across Gelderland.
- **Deelgebieden**: Named with **NATO phonetic alphabet** (Alpha, Bravo, Charlie, Delta, Echo, Foxtrot, etc.).
- **Fox Statuses**: `green` (actief/huntable), `orange` (onderweg/huntable), `red` (inactief/immune).
- **60-Minute Immunity**: Hunters cannot hunt the same fox team within 60 minutes of a previous hunt.
- **Scoring**: Own cluster fox = 6 pts; other permitted cluster = 3 pts (double during Happy Hour).
- **Tegenhunt (Counterhunt)**: 450m???500m radius near HQ, Telegram alert, 30-min window (-10 pts start, +20 pts on find).
- **Official API**: `https://jotihunt.nl/api/2.0/` with a strict rate limit of **30 calls/minute** (HTTP 429).

---|---|---|---|---|
| 0 | Gast / Kiosk | `Test0` | `Test0!!!!!` | Read-only, limited pages |
| 1 | Vossenjager | `Test1` | `Test1!!!!!` | Full read/write access to regular hunter pages |
| 2 | Admin | `Test2` | `test2!!!!!` | Admin portal, users, service accounts, database, cronjobs |
| 3 | Superadmin | `Test3` | `Test3!!!!!` | Full access, site settings, global notifications |

### 2.4 Key Files
- `dblogin.php` — Database credentials (gitignored, must set `$conn->set_charset("utf8mb4")`)
- `includes/auth.php` — Session bootstrap (require_once on every page controller)
- `includes/helpers.php` — Shared utility functions and cron loggers
- `includes/db.php` — Parameterized data access layer
- `includes/globals.php` — Site settings and global constants
- `includes/telegram_bot.php` — Telegram Bot API client class (cURL, webhooks, messages)
- `includes/telegram_parser.php` — Parser for Jotihunt game messages and broadcast dispatcher
- `api/telegram_webhook.php` — Inbound webhook for commands and continuous live GPS streaming
- `services/telegram_listener.py` — MTProto background listener daemon (Telethon)
- `kiosk.php` — Kiosk authentication and status API
- `install.sh` — Bash bootstrapper for automated LAMP server deployment
- `install.php` & `install_helper.php` — 6-step interactive web setup wizard & AJAX backend

---

## 3. Coding Behavior

### 3.1 Before Writing Code
1. Read `CODE_OF_CONDUCT.md` if you haven't in this session
2. Understand the existing file structure before creating new files
3. Check if a similar function already exists before writing a new one
4. If the change is non-trivial, present a plan before implementing

### 3.2 When Writing PHP
- Start every page controller with `require_once('includes/auth.php');` (or `require_once('../includes/auth.php');` for admin pages)
- Standalone cron scripts in `cron/` must **NEVER** include `includes/auth.php` or `functies.php`
- Use `$conn` for database access (provided by `dblogin.php`)
- Always ensure `$conn->set_charset("utf8mb4");` is configured
- Use prepared statements for ALL queries — no exceptions
- Use `htmlspecialchars()` when outputting any user-provided data
- Use `require_once` for critical includes, `include_once` for optional UI components
- Single quotes by default, double quotes only for string interpolation
- 4-space indentation, no tabs

### 3.3 When Writing JavaScript
- `const` by default, `let` when needed, never `var`
- `fetch()` for HTTP requests, never `XMLHttpRequest`
- No IE compatibility code
- Extract scripts > 30 lines into separate modular `.js` files in `js/`

### 3.4 When Writing SQL
- Always use `$conn->prepare()` with bound parameters
- SQL keywords in UPPERCASE
- Table names: `PascalCase_With_Underscores`
- Column names: `snake_case`

### 3.5 Database Changes
- When adding/modifying tables or columns:
  1. Run the `ALTER TABLE` on the live database via SSH
  2. Update `DB/createDB.sql` to reflect the new schema
  3. Use English names for new tables and columns
- Never drop tables or columns without explicit user approval
- Don't add user content like API keys and names to `DB/createDB.sql` as the repository is public. Use placeholders if needed.

### 3.6 Auto-Installer Maintenance
Whenever introducing or altering major system components, database tables, site settings, API keys, background daemons, or system dependencies:
1. **System Packages & Dependencies (`install.sh`)**: Ensure all required apt packages, PHP extensions, Python packages (`pip3`), Composer packages, and Apache modules are present in `install.sh`.
2. **Web Setup Wizard (`install.php`, `install_helper.php`, `js/install.js`)**:
   - Update Step 1 (Requirements Check) if new PHP extensions or writable directories are required.
   - Update Step 2 (Database Setup) if schema import or database user privileges need adjustments.
   - Update Step 4 (Site & API Settings) if new API keys (e.g. Mapbox, Firebase, Telegram) or `Site_Instellingen` rows are introduced. Include live validation test buttons in `install.php` / `js/install.js` / `install_helper.php` where applicable.
   - Update Step 5 (Crontab & Background Tasks) if new recurring background scripts or default `Cronjobs` entries are added.
3. **Database Schema (`DB/createDB.sql`)**: Ensure table definitions maintain `PRIMARY KEY` and `AUTO_INCREMENT` directly on table creation so foreign keys resolve without order dependency.
4. **Documentation (`README.md`)**: Ensure hardware requirements (e.g., minimum 8 GB disk space) and the single-line installation command remain accurate.

---

## 4. File Placement Rules

### 4.1 Where to Put New Code
| Type of code | Location |
|---|---|
| New page | Root directory (`*.php`) |
| Admin page | `admin/*.php` |
| AJAX/POST handler | `*_helper.php` (next to its page) |
| Server installer / bootstrapper | `install.sh`, `install.php`, `install_helper.php` |
| Reusable PHP function | `includes/helpers.php` or `includes/db.php` |
| Shared UI component | `includes/*.php` |
| JavaScript (shared) | `js/*.js` |
| CSS (shared) | `includes/*.css` |
| API endpoint | `api/*.php` |
| Cron job | `cron/*.php` |
| Background daemon / service | `services/*.py` |
| Database schema | `DB/createDB.sql` |
| Static assets | `media/` |

### 4.2 Where NOT to Put Code
- **No function definitions** in `footer.php`, `sidebar.php`, `topbar.php`, or `theme.php`
- **No database queries** in UI component files
- **No HTML rendering** in helper files or `includes/db.php`
- **No reusable functions** in page view files
- **No inline SQL** with string concatenation anywhere

---

## 5. Communication & Quality Standards

### 5.1 Language
- Respond to the user in **English** (their preferred language)
- Use English in code, comments, and technical documentation

### 5.2 Presenting Changes
- After making changes, provide a concise summary of what was done
- Highlight anything the user needs to manually verify
- If changes affect the database, explicitly state what was altered

### 5.3 Verification & Testing Standards
- When encountering errors, diagnose before guessing fixes
- Check PHP syntax with `php -l` across all modified files
- Perform automated multi-role Chromium browser testing with Selenium (`Test0`, `Test1`, `Test2`, `Test3`) before declaring features ready for commit/release

---

## 6. Naming Reference (Quick Lookup)

### Database Table Name Mapping (Legacy ??? New)
| Legacy (Dutch) | New (English) |
|---|---|
| `Gebruikers` | `Users` |
| `Groepen` | `Groups` |
| `Opdrachten` | `Assignments` |
| `Punten` | `Points` |
| `Hints` | `Hints` |
| `Nieuws` | `News` |
| `Voslocaties` | `Fox_Locations` |
| `Voslog` | `Fox_Log` |
| `Auto` | `Cars` |
| `Auto_Bijrijders` | `Car_Passengers` |
| `Auto_Positie` | `Car_Positions` |
| `Auto_Toewijzingen` | `Car_Assignments` |
| `Site_Instellingen` | `Site_Settings` |
| `Whiteboard_Categorieen` | `Whiteboard_Categories` |
| `Toewijzingen` | `Fox_Assignments` |
| `Kiosk_Accounts` | `Kiosk_Accounts` |
| `Cronjobs` | `Cronjobs` |
| `Cronlogs` | `Cron_Logs` |
| `Notification_Subscriptions` | `Notification_Subscriptions` |
| `Notification_Backlog` | `Notification_Backlog` |
| `Telegram_Messages` | `Telegram_Messages` |

### Common Variable Name Mapping (Legacy ??? New)
| Legacy | New | Context |
|---|---|---|
| `$vn` | `$first_name` | User's first name |
| `$an` | `$last_name` | User's last name |
| `$gebr` | `$username` | Username |
| `$priv` | `$privilege` | User privilege level |
| `$hintaantal` | `$hint_count` | Number of hints |
| `$huntaantal` | `$hunt_count` | Number of hunts |
| `$groepaantal` | `$group_count` | Number of groups |
| `$puntentotaal` | `$total_points` | Total score |
| `$plaats` | `$rank` | Leaderboard position |
| `$doel_pagina` | `$target_page` | Kiosk target page |
| `$rechten` | `$permissions` | Kiosk permission level |
| `$laatst_gezien` | `$last_seen` | Last activity timestamp |
| `$siteSettings` | `$site_settings` | Global site settings array |
| `$vossen_names` | `$fox_names` | Fox name array |
| `$vossen_colors` | `$fox_colors` | Fox color hex array |
