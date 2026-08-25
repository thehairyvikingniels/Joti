---
name: joti-cron-subsystem
description: >-
  Guidelines and runbook for writing, executing, error-handling, and debugging Jotify standalone cron jobs and admin dashboard countdown timer synchronization.
---

# Jotify Cron Subsystem & Background Tasks

## 1. Standalone Cron Architecture
- Scheduled cron scripts live in `cron/*.php` (e.g. `areas.php`, `articles.php`, `notifications.php`, `subscriptions.php`, `welcome.php`).
- The master runner is `cron/index.php`, invoked periodically (every 20 seconds) via `www-data` crontab.
- Cron jobs query the `Cronjobs` table and execute due tasks asynchronously via CLI (`php <path> > /dev/null 2>&1 &`).

## 2. Critical Rules & Invariants
1. **Never Include Session Bootstrap in Standalone Crons**:
   - Standalone cron scripts run headless in CLI without user sessions.
   - **DO NOT** include `includes/auth.php` or `functies.php` (which imports `auth.php`).
   - `includes/auth.php` terminates unauthenticated CLI scripts with `header('Location: index'); exit();`.
   - Always load database access directly with `require_once(__DIR__ . '/../dblogin.php');`.
2. **Absolute Directory Paths**:
   - Always use `__DIR__` for includes (e.g., `require_once(__DIR__ . '/../dblogin.php');`) so scripts execute cleanly regardless of working directory.
3. **Structured Error Handling & 500 Status Logging**:
   - Wrap all cron jobs in `try ... catch (Throwable $e) ... finally` blocks.
   - On network dropouts, cURL errors, HTTP `>= 400`, or parsing exceptions, set `$status_code = ($http_code === 429 ? 429 : 500)`.
   - Always invoke `recordCronLog($conn, NAME, START_TIME, $output, $status_code)` in the `finally` block.
   - This ensures status `500` is recorded in `Cronlogs` and lights up the red indicator circle in `admin/cronjobs.php`.

## 3. Admin Dashboard & Countdown Timer Contract
- `admin/cronjobs.php` renders cron cards with `data-seconds` and `data-enabled` attributes.
- `js/admin_cronjobs.js` executes a 1-second JavaScript interval timer:
  - Decrements remaining seconds down to 0 (`... sec`).
  - Switches to `"executing..."` with orange pulse animation when seconds `<= 0`.
  - Displays `" - disabled - "` with muted styling when toggled off.
- `admin/cronjobs_helper.php?cronjobs` returns JSON with `raw_enabled`, `raw_seconds`, `exec_status`, and timestamps for 5-second live polling synchronization.
