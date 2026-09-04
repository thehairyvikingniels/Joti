# Changelog

All notable changes to the Jotify platform are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [v2026.09.0] - 2026-09-04

### Highlights
- **Operatie Pre-Flight Readiness Hub (`admin/readiness.php`)**: Comprehensive mission-readiness command center with 11 automated diagnostic probes, operational checklist tracking, and non-destructive season archiving.
- **Background Cron Subsystem Hardening**: Resolved forever-executing cron jobs, fixed foreign key constraint crashes, and eliminated the legacy `+ 7200` timezone offset so background tasks execute cleanly on their scheduled intervals.
- **Automated LAMP Deployment & Web Setup Wizard**: End-to-end `install.sh` bootstrapper and 6-step interactive web wizard (`install.php`) with automated live validation tests.
- **System Administration & Self-Healing Maintenance**: Live resource metrics, in-app Git updater with schema migrations, and automated tiered backup pruning (`cron/backup.php`).

### Added
- **Pre-Flight Readiness Hub (`admin/readiness.php`, `admin/readiness_helper.php`, `js/admin_readiness.js`)**:
  - 11 real-time integration probes: Official Jotihunt API 2.0, automated portal scraper session & CSRF verification (`cron/scraper.py`), MariaDB schema & charset, background cron status, Telegram bot API & broadcast channel, Mapbox style layers, Web Push VAPID keys, disk storage (>5GB) & directory write permissions, fleet & hunter readiness, scouting group HQ coordinates & counterhunt detection, and network security / HTTPS.
  - Interactive Pre-Flight operational checklist with categories (`HQ & Meldkamer`, `Vloot & Jagers`, `Communicatie`, `Algemeen`), modal task addition, and state tracking in MariaDB table `Preflight_Checklist`.
  - Non-destructive Season Archiving and clean slate reset engine: creates a standalone `.tar.gz` bundle of database and uploaded media assets, archives operational tables (including annual scouting groups `Groepen`), registers metadata in `Archived_Editions`, and safeguards user accounts, vehicles, and settings under a Superadmin confirmation keyword lock.
  - Full theme adaptability across all color palettes and custom user themes.
- **Automated Installer & Web Setup Wizard (`install.sh`, `install.php`, `install_helper.php`, `js/install.js`)**:
  - One-click bash bootstrapper for automated Apache, PHP 8.x, MariaDB, Composer, Python3, and Pip package installation.
  - 6-step interactive web setup wizard with live requirement checks, database connection & schema import, admin account creation, API key validation buttons (Mapbox, Telegram, Firebase, VAPID), crontab generation, and environment summary.
- **System Dashboard & Maintenance (`admin/system.php`, `admin/system_helper.php`)**:
  - Live system resource monitoring (CPU cores, RAM usage, storage breakdown, load averages).
  - One-click in-app Git auto-updater with local modifications stashing and automatic database migrations.
  - Full-system backup generator and one-click database/media restore.
  - Automated tiered backup pruning daemon (`cron/backup.php`) with 24-hour hourly, 7-day daily, 4-week weekly, and monthly retention policy.
- **UI Refinements**:
  - Moved user profile picture to the sidebar with a direct clickable shortcut to `instellingen.php`.
  - Streamlined topbar layout.

### Fixed
- **Stuck Cronjob Execution**:
  - Fixed orphaned duplicate `'notifications'` entry in `Cronjobs` table by standardizing on `'push_queue'` (35s) across installer, database seeds, and runner scripts.
  - Fixed fatal crash in `cron/welcome.php` caused by parameter count mismatch in parameterless SQL query and `NAME` foreign key mismatch (`welcome_push` vs `welcome`).
  - Removed legacy `+ 7200` timezone offset from `cron/index.php`, preventing background cronjobs from being erroneously fired on every 20-second crontab tick.
  - Fixed casing issue in `admin/cronjobs.php` toggle handler to preserve exact table primary keys.

---

## [v2026.08.1] - 2026-08-25

### ???? Highlights
- **100% Code of Conduct & AGENTS.md Compliance**: Comprehensive architectural refactoring across views, components, scripts, database layer, and error handling.
- **Robust Background Cron Subsystem**: Fixed standalone cron execution, added automated status 500 error logging, and restored the real-time countdown timer in the admin dashboard.
- **UTF-8 Character Encoding**: Full `utf8mb4` support across database connections and views, resolving all special character encoding issues.

### Added
- **Modular Frontend Architecture (`js/`)**:
  - `js/app.js`: Global responsive sidebar navigation and live topbar immunity countdown badges.
  - `js/kaarten.js`: Map state management, game half and layer toggles, and fullscreen sync.
  - `js/assignments.js`: Real-time assignment claiming, conflict resolution modal, and avatar hover tooltips.
  - `js/voslocaties.js`: Unified geolocation picker, coordinate type switcher (Lat/Lon, RD, Scouting Groups), and Mapbox modal picker.
  - `js/whiteboard.js`: Touch and desktop drag-and-drop tactical whiteboard engine.
  - `js/maps.js`: Modular Mapbox GIS engine for scout huts, hunter vehicles, user markers, fox locations, tracks, and dynamic search radius circles.
  - `js/home.js`: Live AJAX event log loader, vehicle tracking, and announcement countdown modal.
  - `js/admin_cronjobs.js`: Live JavaScript countdown timer to 0 (`executing...` on `<= 0`, `" - disabled - "` when inactive) and 5s backend status synchronization.
  - `js/admin_database.js`, `js/admin_serviceaccounts.js`, `js/admin_settings.js`, `js/instellingen.js`, `js/offline.js`.
- **Data Access & View Layer Separation**:
  - `includes/db.php`: Centralized parameterized query helpers and data access functions.
  - `includes/whiteboard_components.php`: Dedicated renderer functions for whiteboard user badges and vehicle cards.
  - `includes/helpers.php`: Comprehensive helper functions with full type signatures, PHPDoc blocks, `log2DB()`, and `recordCronLog()`.
- **Kiosk Mode & PWA Offline Support**:
  - Offline fallback screen (`offline.php`) with connectivity polling and automatic redirect.
  - Kiosk token authentication and IP whitelisting.
- **Web Push Notifications**:
  - Integrated push notification engine for fox status changes, new articles, hints, assignments, and proximity welcome messages.

### Fixed
- **Special Characters Encoding ([#29](https://github.com/thehairyvikingniels/Joti/issues/29))**: Added `$conn->set_charset("utf8mb4")` in `dblogin.php` and `includes/globals.php`, preventing corrupted accents (`??`, `???`, `??`, `??`).
- **Huntcode Input ([#16](https://github.com/thehairyvikingniels/Joti/issues/16))**: Made huntcode fully optional across location submission, client scripts, and admin database editor.
- **Cronjob Execution & Error Logging**: Decoupled session-authentication blockers from standalone cron scripts (`areas.php`, `articles.php`, etc.), added `try-catch-finally` error handling, and ensured status 500 errors log with red indicator icons in the admin portal.
- **Admin Cron Countdown Timer**: Resolved missing element selectors and API parameters in `js/admin_cronjobs.js`.
- **Profile Picture Upload**: Fixed directory creation and write permissions for user profile avatars (`media/profiles/`).
- **User Settings & Vehicles**: Fixed username display issue in `instellingen.php` and car deletion in `autos.php`.
- **Security & Syntax ([#42](https://github.com/thehairyvikingniels/Joti/issues/42), [#38](https://github.com/thehairyvikingniels/Joti/issues/38))**: Sanitized all URL parameters (`index.php`), appended explicit `exit()` after all `header("Location: ...")` redirects, and eliminated bare `die()` statements.

---

## [v2026.08.0] - 2026-08-17
- Initial baseline release for August 2026 event cycle.

## [v2026.07.0] - 2026-07-03
- July 2026 preparation release with UI updates and database optimizations.

