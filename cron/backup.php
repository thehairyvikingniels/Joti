<?php
// Standalone cron job for automated database and media backups with tiered retention.
// Executed headless by cron/index.php. Do NOT include includes/auth.php or functies.php.

define('NAME', 'auto_backup');
define('START_TIME', microtime(true));
date_default_timezone_set('Europe/Amsterdam');
$output = '';
$status_code = 200;

require_once(__DIR__ . '/../dblogin.php');
require_once(__DIR__ . '/../includes/helpers.php');
require_once(__DIR__ . '/../admin/update_helper.php');

$webroot = realpath(__DIR__ . '/..');
$backupDir = $webroot . '/DB/backups';

try {
    // 1. Create full system automatic backup (.tar.gz)
    $backupResult = create_full_backup($conn, $webroot, $backupDir, 'auto');
    $output .= "Created: {$backupResult['filename']} ({$backupResult['size_formatted']}). ";

    // 2. Prune old automatic backups based on tiered retention policy
    $pruneResult = prune_backups_tiered($backupDir);
    $output .= "Pruned: {$pruneResult['deleted_count']} old backups (Kept: {$pruneResult['kept_count']}).";

    if (!empty($pruneResult['deleted'])) {
        $output .= " Deleted files: " . implode(', ', $pruneResult['deleted']);
    }
} catch (Throwable $e) {
    $status_code = 500;
    $output = "Error in auto_backup: " . $e->getMessage() . " on line " . $e->getLine();
    error_log($output);
} finally {
    recordCronLog($conn, NAME, START_TIME, $output, $status_code);
}
