---
name: joti-system-maintenance
description: >-
  Operational runbook and architectural standards for Jotify in-app Git auto-updates, live system resource monitoring, full-system .tar.gz backups, one-click restores, and setup wizard migrations.
---

# Jotify System Maintenance, Updates & Backup Subsystem

## 1. System Dashboard & Resource Monitoring (`admin/system.php`)

The system management dashboard replaced the standalone updater and provides real-time infrastructure visibility:
- **Metrics Tracked**:
  - **CPU Utilization**: Instantaneous usage and 5-minute rolling average via `/proc/stat` or `sys_getloadavg()`.
  - **Memory (RAM)**: Active memory usage, cached memory, and total physical RAM via `/proc/meminfo`.
  - **Network Throughput**: Inbound and outbound rates in bits/sec, calculated via delta snapshots of `/proc/net/dev`.
  - **Disk Storage**: Used and available filesystem capacity for the web root partition (`disk_total_space()`, `disk_free_space()`).
- **Formatting Standard**: Always use `bitbyte2string()` from `includes/helpers.php` (supports byte and bit modes with dynamic 800-unit thresholding).
- **Polling & UI**: `js/admin_system.js` refreshes telemetry asynchronously via `admin/system_helper.php?action=get_system_stats`.

---

## 2. In-App Git Auto-Updater

Jotify includes a self-updating mechanism that allows administrators (`priv >= 2`) to update and switch branches directly from the browser:

### Workflow & Pre-Flight Checks
1. **Fetch & Tag Discovery**: `git fetch --tags origin` queries upstream tags (`vYYYY.MM.PATCH`) and branch tips (`main`, `dev`, `autoinstall`).
2. **Safety Checks**:
   - Verify working directory cleanliness (`git status --porcelain`).
   - Verify write permissions on web root for `www-data`.
3. **Automated Pre-Update Backup**:
   - Automatically generates a complete database and asset backup archive before pulling code.
4. **Git Checkout & Pull**:
   - Executes `git checkout <target_branch_or_tag>` and `git pull origin <branch>`.
5. **Post-Update Migrations**:
   - Runs `composer install --no-dev --optimize-autoloader` if `composer.lock` changed.
   - Applies any pending database migrations or `ALTER TABLE` operations.
   - Clears PHP OPcache (`opcache_reset()`).

---

## 3. Comprehensive Backup Subsystem (Format Version 3)

### Archive Packaging & Compression
- **Format**: Compressed archive (`.tar.gz` with maximum `gzip -9` compression; auto-detects `.tar.xz` on restore).
- **Location**: Backups are stored in `DB/backups/`.
- **Archive Contents**:
  ```
  backup_YYYY-MM-DD_HH-ii-ss.tar.gz
  ├── database.sql                  # Complete MySQL database dump
  ├── backup_meta.json              # Version (3), timestamp, commit hash, file manifest
  ├── media/
  │   ├── profiles/                 # User avatars
  │   ├── hunts/                    # Hunt approval photos
  │   ├── tegenhunt/                # Counterhunt stickers and photos
  │   └── scoutingLogo.png          # Active scouting group logo
  └── services/
      └── *.session                 # Telethon MTProto userbot session files
  ```

### Backup Operations
- **Creation**: Triggered via UI in `admin/system.php` or CLI via `cron/backup.php`. Uses native `mysqldump` with a robust PHP fallback query dumper.
- **Restoration**: One-click restore unpacks the archive, disables foreign key checks, executes `database.sql`, restores media assets and session files, and updates permissions.
- **Upload / Download**: Admins can download `.tar.gz` archives or upload an external backup file for instant restoration.
- **Pruning**: `cron/backup.php` runs tiered retention pruning:
  - Last 24 hours: hourly backups retained.
  - Last 7 days: 1 backup per day.
  - Last 4 weeks: 1 backup per week.
  - Older: 1 backup per month.

---

## 4. First-Time Setup Wizard Migration (`install.php` Step 2)

The 6-step setup wizard integrates backup restoration as a primary onboarding path:
- **Mode Selection**:
  1. *Nieuwe Database Aanmaken* (clean schema import from `DB/createDB.sql`).
  2. *Bestaande Database Gebruiken* (connect to existing tables without wiping).
  3. *Back-up Herstellen (Migratie)* (upload existing `.tar.gz` archive).
- **Migration Execution**:
  - Unpacks the archive into a temporary folder.
  - Restores the database schema and all table rows.
  - Restores all media directories and Telethon Telegram sessions.
  - Pre-fills Step 3 (Admin account) and Step 4 (Site & API settings) with restored database values.
  - Allows skipping admin account creation if users already exist in the database.
- **Web Server Configuration**: `install.sh` configures `upload_max_filesize = 64M` and `post_max_size = 64M` in Apache PHP `php.ini` to support large backup archive uploads.

---

## 5. Backward Compatibility & Routing
- Old update URL `/admin/update` issues a 301 permanent redirect to `/admin/system`.
- `admin/update_helper.php` seamlessly forwards requests to `admin/system_helper.php`.
