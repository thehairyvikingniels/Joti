## Quick context (what this project is)

- Simple, procedural PHP web app for a local "Jotihunt" service. No framework. Frontend uses W3.CSS, jQuery and FontAwesome; server-side is plain PHP + MySQL (mysqli).
- Pages are file-based endpoints (e.g. `index.php`, `login.php`, `functies.php`, `kaarten.php`). Admin UI lives under `admin/`.
- Database schema is in `DB/maarleveld_one_joti.sql` and includes tables like `Gebruikers`, `Groepen`, `Voslocaties`, `Voslog`, `Auto`, etc.

## Big-picture architecture & data flows

- Routing: there is no router. Each file (for example `functies.php`, `home.php`) reads $_GET/$_POST and performs DB queries or renders HTML.
- DB connection: every PHP page that uses DB `require("dblogin.php")` which creates `$conn` (mysqli) and exposes helpers like `time2str()` and `latlon_dist()`.
- Sessions & auth: `session_start()` is used widely. Authentication state is via `$_SESSION['id']` and `$_SESSION['priv']` (priv: 0 = user, 1 = elevated, 2+ = admin). Admin pages check `priv` (see `admin/*.php`).
- Frontend <-> server: frontend JS calls file endpoints directly (examples: `XMLHttpRequest` to `functies.php?lat=...&lon=...`, and form posts to `login.php`).
## Project-specific conventions & patterns

- Procedural, file-per-endpoint pattern. Expect logic and SQL mixed in the same file.
- DB queries are built via string concatenation in many places. Example pattern: `$sql = "SELECT * FROM Gebruikers WHERE id='".$_SESSION['id']."'";` (see `admin/database.php`, `functies.php`).
- Minimal input sanitization is present in a few places (e.g. `mysqli_real_escape_string()` used in `login.php`) but not consistent.
- Password handling: passwords are hashed with `sha1($pw . "niels als salt")` in older code.

## Integration points & external deps

- External CDNs: W3.CSS, jQuery, jQuery UI, Google Fonts and FontAwesome are referenced directly in HTML templates.
- Database: MySQL/MariaDB. Schema: `DB/maarleveld_one_joti.sql`.
- Media & icons: static assets live under `media/` and `media/icons/`. Some duplicate plugin assets in `plugins/includes/`.

## Developer workflows (how to run / debug locally)

1. PHP + MySQL required. Quick local run (document root = repository root):

```sh
# start PHP built-in server (for quick UI testing)
php -S localhost:8000 -t .
```

2. Import DB (example):

```sh
# create local DB and import schema
mysql -u root -p your_database_name < DB/maarleveld_one_joti.sql
# update credentials in dblogin.php to match your local DB
```

3. Debugging tips
- DB credentials are hard-coded in `dblogin.php`. Adjust for local development.
- To see PHP errors during dev, enable error reporting at top of entry files (or edit `dblogin.php` while developing):
  ini_set('display_errors', 1); error_reporting(E_ALL);
- Sessions: make sure cookies are enabled in the browser; session checks redirect to `/index` if not set (see `admin/index` behavior).

## Quick examples of common edits

- To change user privilege checks: update where `$_SESSION['priv']` is compared (see `admin/database.php`, `admin/index.php` and `login.php`).
- To add a DB column: update `DB/maarleveld_one_joti.sql` (and run ALTER TABLE locally), then update queries that SELECT/INSERT that table.

## Files to look first when editing/triaging

- `dblogin.php` — DB connection, helper functions and timezone; essential to bootstrapping.
- `index.php`, `login.php` — app entry and authentication.
- `functies.php` — core AJAX/utility endpoints used by the UI.
- `functies.php` — central AJAX/utility actions used across pages.
- `admin/` — admin flows and privilege checks.
- `DB/maarleveld_one_joti.sql` — canonical DB schema.

## Safety notes (discoverable, not prescriptive)

- The codebase constructs SQL via string concatenation in many places and uses a simple sha1-based salt for passwords/tokens. Treat production secrets carefully; when writing code, follow existing patterns unless asked to refactor for security.

If any section is unclear or you want the file to be expanded with code examples (e.g. a short recipe for adding new API endpoints or a small checklist for local setup), tell me which part and I will iterate.
