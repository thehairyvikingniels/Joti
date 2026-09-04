---
name: joti-telegram-subsystem
description: >-
  Guidelines, architecture, operational runbook, and debugging procedures for the Jotify Telegram Bot API, live location streaming, and MTProto listener daemon.
---

# Jotify Telegram Subsystem Runbook

## 1. Dual Architecture Overview

Jotify uses a hybrid, bidirectional Telegram architecture:
1. **Telegram Bot API (`@JotifyScoutBot`)**:
   - Webhook endpoint: `api/telegram_webhook.php`
   - Client class: `includes/telegram_bot.php`
   - Handles:
     - **Interactive Commands**: `/start <code>`, `/status`, `/vossen`, `/score`, `/help`
     - **Permanent Live Location Streaming**: Receives `edited_message.location` every ~30s
     - **Outbound Notifications**: Dispatched via `cron/notifications.php` (`push_queue`, 35–40s interval)
     - **Forwarded Game Alerts**: Auto-parses forwarded `@Jotihunt_bot` messages from hunters
2. **MTProto Userbot Daemon (`services/telegram_listener.py`)**:
   - Runs as a headless systemd daemon (`jotify-telegram-listener.service`)
   - Uses Telethon with `TELEGRAM_API_ID` and `TELEGRAM_API_HASH` from `my.telegram.org`
   - Connects as the team's official paired user account
   - Intercepts incoming messages from `@Jotihunt_bot` in real time (<300ms)
   - Instantly forwards messages to active hunter subscribers and posts to `api/telegram_ingest.php`

---

## 2. Critical Invariants & Rules

1. **Session Files Must Never Be Committed**:
   - Telethon creates `jotihunt_user.session` upon authentication.
   - `.gitignore` must always contain `*.session`, `*.session-journal`, and `services/*.session`.
2. **Timezone & Location Alignment**:
   - `parseToTimestamp()` in `helpers.php` treats strings matching `YYYY-MM-DD HH:MM:SS` as UTC.
   - In MySQL on the server, `NOW()` evaluates in UTC.
   - **Always use MySQL `NOW()`** (not PHP `date()`) when updating `geotijd` or `Auto_Positie.datumtijd`, otherwise relative times in `/status` will report future offsets (e.g. "Over 1 uur").
3. **Silent Live Location Ingest**:
   - Streaming updates arrive as `edited_message.location`.
   - Never send text replies to `edited_message` updates to avoid spamming the user's chat every 30 seconds.
   - Only reply with a confirmation message on the initial `message.location` share.
4. **Credential Auto-Discovery**:
   - `services/telegram_listener.py` dynamically extracts database credentials from `../dblogin.php` and queries `Site_Instellingen`.
   - Never hardcode API tokens or hashes in source code or `DB/createDB.sql` (use placeholders in SQL schemas).
5. **Hunter Privilege Boundary**:
   - Only users with `priv >= 1` (Vossenjager, Admin, Superadmin) can link Telegram accounts or receive game data.

---

## 3. Operational Runbook & Commands

### Managing the MTProto Daemon (systemd)
```bash
# Check service status
systemctl status jotify-telegram-listener

# View live streaming logs
journalctl -u jotify-telegram-listener -f

# Restart daemon (e.g. after code update)
systemctl restart jotify-telegram-listener
```

### Initial MTProto Account Authentication
To generate the session file for a new team account:
```bash
python3 /var/www/Joti/services/setup_session.py
```
Prompts for:
1. Phone number (`+31...`)
2. Telegram login verification code
3. 2FA cloud password (if enabled)

### Webhook Diagnostics & Testing
- Query webhook status via CLI:
  ```bash
  curl -s https://joti.maarleveld.app/admin/telegram_helper.php?action=webhook_info
  ```
- Register or reset webhook:
  ```bash
  curl -s -X POST https://joti.maarleveld.app/admin/telegram_helper.php -d "action=set_webhook"
  ```
- Or use the UI buttons in **Admin → Telegram**: `[Token Testen]`, `[Webhook Registreren]`, `[Webhook Verwijderen]`.

---

## 4. Message Parsing Contracts (`includes/telegram_parser.php`)

Supported game message patterns from `@Jotihunt_bot`:
| Pattern | Parsed Type | Triggered Game Action |
|---|---|---|
| `Status van <Fox> is gewijzigd in <green\|orange\|red>` | `fox_status` | Inserts into `Voslog`, queues push alert |
| `De hunt met code <code> is voorlopig goedgekeurd...` | `hunt_status` | Updates `Voslocaties` status to 'Goedgekeurd', points |
| `De hunt met code <code> is afgekeurd` | `hunt_status` | Updates `Voslocaties` status to 'Afgekeurd' |
| `Jullie inzending voor de opdracht '<Title>' is beoordeeld...` | `assignment_graded` | Updates `Opdrachten`, increments `Punten.opdrachten` |
| `... HAPPY HOUR ...` | `happy_hour` | Updates `Site_Instellingen.HAPPY_HOUR` = 1 |
| `... tegenhunt ... [richting / graden]` | `tegenhunt` | Inserts `Tegenhunt_Sessions` (30m window) |
