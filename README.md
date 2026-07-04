# Jotihunt Web App

### Short description
A small procedural PHP + MySQL web application used by a scout group to run a local Jotihunt helper site. No framework; frontend uses W3.CSS, jQuery and FontAwesome; server side is plain PHP + mysqli.

### Contents (high-level)
- File-based PHP endpoints: index.php, home.php, kaarten.php, functies.php, admin/
- Database schema: DB/maarleveld_one_joti.sql
- Static assets: media/, media/icons/
- Helpers and DB connection: dblogin.php

### Intended audience
For use by all members of a scout group and associates. Groups may fork the repo and adapt it, or even better, help make this project better.

### Requirements
- PHP 8.2 (other versions untested)
- MySQL / MariaDB
- Webserver or PHP built-in server
- Enable required PHP extensions for mysqli, DOM (used in templates), and cURL (used for Mapbox routing APIs in maps.php)

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
- No external API is currently provided by the system.

### Database & seeds
- The DB schema lives in DB/createDB.sql. Import that to create tables.
- No automated seeding beyond the SQL file; create users and set priv manually as needed.

### Contributing
- Owner is open to feedback and occasional contributions, but the project is not actively set up for outside contributors.
- If you want to contribute, open an issue or create a PR and I will review.

### License
- Licensed under the Creative Commons Attribution-NonCommercial-ShareAlike 4.0 International (CC BY-NC-SA 4.0).
- This means you can freely modify and host the project, but you cannot use it for commercial purposes (scout groups are fine!). Any modifications must also be released under the same license.

### Useful files to inspect first
- dblogin.php — DB connection
- index.php, login.php — entry & authentication
- functies.php — core AJAX/utility endpoints used by the UI
- admin/ — admin pages and privilege checks
- DB/createDB.sql — DB schema
