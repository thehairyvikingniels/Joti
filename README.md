# Jotihunt Web App

### Short description
A small procedural PHP + MySQL web application used by your scout group to run a local Jotihunt. No framework; frontend uses W3.CSS, jQuery and FontAwesome; server side is plain PHP + mysqli.

### Contents (high-level)
- File-based PHP endpoints: index.php, home.php, kaarten.php, functies.php, admin/, api/
- Database schema: DB/maarleveld_one_joti.sql
- Static assets: media/, media/icons/
- Helpers and DB connection: dblogin.php

### Intended audience
For use by all members of the scout group. Other groups may fork the repo and adapt it, or even better, help make the repo .

### Requirements
- PHP 8.2 (other versions untested)
- MySQL / MariaDB
- Webserver or PHP built-in server
- Enable required PHP extensions for mysqli and DOM (used in templates)

### Quick start (local dev)
1. Create the database from the SQL file:
- The canonical schema is DB/createDB.sql

2. Configure DB credentials:
- Edit dblogin.php and set your DB server, user and password.

3. Create an superadmin account:
- Create a user account through the portal UI.
- In the database, set that user's `priv` = 3 to grant admin access.

### Notes about configuration and secrets
- DB credentials are stored in dblogin.php. Change them to match your environment.
- API tokens live in the `Gebruikers.api` column. Token format is validated by api/index.php::checkToken().

### Database & seeds
- The DB schema lives in DB/createDB.sql. Import that to create tables.
- No automated seeding beyond the SQL file; create users and set priv manually as needed.

### API
- A small JSON API is available at api/index.php. Full API documentation is maintained separately:
  https://docs.google.com/document/d/1XO9K8cVwgysytti1LSQa2i3dpYmeWNQ8BS5sSwqNjgY/edit?tab=t.0

### Contributing
- Owner is open to feedback and occasional contributions, but the project is not actively set up for outside contributors.
- If you want to contribute, open an issue or create a PR and I will review.

### License
- Currently closed source. Owner may open-source the project in the future.

### Security notes / known issues
- Many SQL queries are built via string concatenation; input sanitization is not consistent.
- Passwords and tokens use a simple sha1 salt-based scheme in the current codebase.
- Treat credentials and tokens carefully; consider refactoring to prepared statements and modern password hashing for production.

### Useful files to inspect first
- dblogin.php — DB connection, helpers (time2str, latlon_dist, rdtowgs)
- index.php, login.php — entry & authentication
- functies.php — core AJAX/utility endpoints used by the UI
- api/index.php — JSON API and token logic
- admin/ — admin pages and privilege checks
- DB/createDB.sql — DB schema
