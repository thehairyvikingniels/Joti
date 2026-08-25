# Changelog

All notable changes to the Jotify platform are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

