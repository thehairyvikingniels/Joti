# Jotify — LLM Agent Rules

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
- **Database**: MySQL/MariaDB via `mysqli` (OOP style, prepared statements only)
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
- **Cron jobs**: `cron/` directory contains scheduled background tasks
- **Database schema**: `DB/createDB.sql` is the canonical schema reference

### 2.3 Access Levels
| Level | Name | Access |
|---|---|---|
| 0 | Gast / Kiosk | Read-only, limited pages |
| 1 | Vossenjager | Full read/write access to regular pages |
| 2 | Admin | Admin panel access |
| 3 | Superadmin | Site settings, system configuration |

### 2.4 Key Files
- `dblogin.php` — Database credentials (gitignored, never touch)
- `includes/auth.php` — Session bootstrap (require_once on every page)
- `includes/helpers.php` — Utility functions
- `includes/globals.php` — Site settings and global constants
- `functies.php` — Legacy AJAX router (being refactored)
- `kiosk.php` — Kiosk authentication and status API

---

## 3. Coding Behavior

### 3.1 Before Writing Code
1. Read `CODE_OF_CONDUCT.md` if you haven't in this session
2. Understand the existing file structure before creating new files
3. Check if a similar function already exists before writing a new one
4. If the change is non-trivial, present a plan before implementing

### 3.2 When Writing PHP
- Start every page with `require_once('includes/auth.php');` (or `require_once('../includes/auth.php');` for admin pages)
- Use `$conn` for database access (provided by `dblogin.php` via auth.php)
- Use prepared statements for ALL queries — no exceptions
- Use `htmlspecialchars()` when outputting any user-provided data
- Use `require_once` for critical includes, `include_once` for optional UI components
- Single quotes by default, double quotes only for string interpolation
- 4-space indentation, no tabs

### 3.3 When Writing JavaScript
- `const` by default, `let` when needed, never `var`
- `fetch()` for HTTP requests, never `XMLHttpRequest`
- No IE compatibility code
- Extract scripts > 30 lines into separate `.js` files in `js/` or `includes/`

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

---

## 4. File Placement Rules

### 4.1 Where to Put New Code
| Type of code | Location |
|---|---|
| New page | Root directory (`*.php`) |
| Admin page | `admin/*.php` |
| AJAX/POST handler | `*_helper.php` (next to its page) |
| Reusable PHP function | `includes/helpers.php` or `includes/db.php` |
| Shared UI component | `includes/*.php` |
| JavaScript (shared) | `js/*.js` or `includes/*.js` |
| CSS (shared) | `includes/*.css` |
| API endpoint | `api/*.php` |
| Cron job | `cron/*.php` |
| Database schema | `DB/createDB.sql` |
| Static assets | `media/` |

### 4.2 Where NOT to Put Code
- **No function definitions** in `footer.php`, `sidebar.php`, `topbar.php`, or `theme.php`
- **No database queries** in UI component files
- **No HTML rendering** in helper files or `includes/db.php`
- **No reusable functions** in page view files
- **No inline SQL** with string concatenation anywhere

---

## 5. Communication Style

### 5.1 Language
- Respond to the user in **English** (their preferred language)
- Use English in code, comments, and technical documentation

### 5.2 Presenting Changes
- After making changes, provide a concise summary of what was done
- Highlight anything the user needs to manually verify
- If changes affect the database, explicitly state what was altered

### 5.3 Error Handling
- When encountering errors, diagnose before guessing fixes
- Check PHP syntax with `php -l` after editing PHP files
- Test via `curl` or browser when possible

---

## 6. Naming Reference (Quick Lookup)

### Database Table Name Mapping (Legacy → New)
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

### Common Variable Name Mapping (Legacy → New)
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
