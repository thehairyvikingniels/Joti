# Jotify Web App

### Short description
A small procedural PHP + MySQL web application used by a scout group to run a local Jotify helper site. No framework; frontend uses W3.CSS, jQuery and FontAwesome; server side is plain PHP + mysqli.

### Contents (high-level)
- File-based PHP endpoints: index.php, home.php, kaarten.php, functies.php, admin/
- Database schema: DB/maarleveld_one_joti.sql
- Static assets: media/, media/icons/
- Helpers and DB connection: dblogin.php

### Intended audience
For use by all members of a scout group and associates. Groups may fork the repo and adapt it, or even better, help make this project better.

### Requirements
- PHP 8.2 (other versions untested)
- Composer (for installing dependencies like web-push)
- MySQL / MariaDB
- Webserver or PHP built-in server
- Enable required PHP extensions for mysqli, DOM (used in templates), gmp/bcmath (for web-push), and cURL (used for Mapbox routing APIs in maps.php)

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

### Telegram Integration Setup (Bot API & MTProto Daemon)

Jotify includes a complete bidirectional Telegram integration:
1. **Interactive Telegram Bot (`@JotifyScoutBot`)**: Handles permanent GPS live location streaming, outbound game alerts (via the 40-second cron queue), 1-click hunter self-registration, and bot commands (`/status`, `/vossen`, `/score`, `/help`).
2. **MTProto Listener Daemon (`services/telegram_listener.py`)**: Intercepts official game alerts in real time (<300ms) from `@Jotihunt_bot` on the team's paired Telegram user account.

#### 1. Telegram Bot API Setup
1. Open [@BotFather](https://t.me/BotFather) on Telegram and create your bot using `/newbot`.
2. Copy the HTTP API token provided by BotFather.
3. In Jotify, go to **Admin → Instellingen** and save the token under `TELEGRAM_BOT_TOKEN`.
4. Go to **Admin → Telegram**:
   - Click **Token Testen** to verify authorization.
   - Click **Webhook Registreren** to register `https://your-domain/api/telegram_webhook.php` with Telegram.
5. Hunters can now link their accounts in **Instellingen → Telegram** using the 1-click link button or `/start <CODE>`.
6. Hunters can share their real-time location continuously by sending a **Live Locatie** via the Telegram paperclip 📎 menu in the bot chat.

#### 2. MTProto User Account Daemon Setup
To automatically intercept official messages sent by `@Jotihunt_bot` to your team account:
1. Go to [my.telegram.org](https://my.telegram.org) → **API development tools** and create an app.
2. Note your `App api_id` and `App api_hash`.
3. Save these in **Admin → Instellingen** under `TELEGRAM_API_ID` and `TELEGRAM_API_HASH` (or in `Site_Instellingen`).
4. Install Python dependencies on your server:
   ```bash
   pip3 install telethon
   ```
5. Authenticate the user account once via terminal:
   ```bash
   python3 /var/www/Joti/services/setup_session.py
   ```
   Follow the prompt to enter your phone number and login verification code.
6. Install and start the background daemon:
   ```bash
   cp /var/www/Joti/services/jotify-telegram-listener.service /etc/systemd/system/
   systemctl daemon-reload
   systemctl enable --now jotify-telegram-listener
   ```
7. Check the daemon status:
   ```bash
   systemctl status jotify-telegram-listener
   journalctl -u jotify-telegram-listener -f
   ```

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
