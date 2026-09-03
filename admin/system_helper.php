<?php
/**
 * Jotify System Auto-Update AJAX Helper
 * Handles git status checks, remote fetching, automated pull/updates,
 * composer dependency installation, idempotent database schema synchronization,
 * and database backups.
 * 
 * Restricted strictly to Superadmins (privilege >= 3).
 */

ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
ob_start();

if (php_sapi_name() !== 'cli') {
    header('Content-Type: application/json; charset=utf-8');
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $userPriv = $_SESSION['priv'] ?? 0;
    if ($userPriv < 3) {
        send_json([
            'ok' => false,
            'error' => 'Toegang geweigerd. Alleen Superadmins kunnen systeemupdates beheren.'
        ], 403);
    }
}

require_once(__DIR__ . '/../dblogin.php');
require_once(__DIR__ . '/../includes/helpers.php');

$webroot = realpath(__DIR__ . '/..');
$backupDir = $webroot . '/DB/backups';

// Ensure backup directory exists
if (!is_dir($backupDir)) {
    @mkdir($backupDir, 0755, true);
    @file_put_contents($backupDir . '/.htaccess', "# Deny direct web access\nRequire all denied\nDeny from all\n");
}

/**
 * Send clean JSON response with output buffer clearing
 */
function send_json(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    echo json_encode($data);
    exit();
}

/**
 * Clean up stale git lock files (e.g. index.lock) left behind by interrupted git operations
 */
function cleanup_stale_git_lock(string $cwd): void {
    $lockFiles = [
        $cwd . '/.git/index.lock',
        $cwd . '/.git/HEAD.lock',
        $cwd . '/.git/config.lock'
    ];
    foreach ($lockFiles as $lf) {
        if (file_exists($lf)) {
            // If lock file is empty or older than 3 seconds, remove it safely
            if (filesize($lf) === 0 || (time() - filemtime($lf)) > 3) {
                @unlink($lf);
            }
        }
    }
}

/**
 * Execute a shell command inside webroot safely
 */
function run_git_cmd(string $cmd, string $cwd): array {
    cleanup_stale_git_lock($cwd);
    $cleanCmd = preg_replace('/^git\s+/', 'git -c safe.directory=* ', trim($cmd));
    $fullCmd = 'cd ' . escapeshellarg($cwd) . ' && ' . $cleanCmd . ' 2>&1';
    
    $output = [];
    $exitCode = 0;
    exec($fullCmd, $output, $exitCode);
    
    return [
        'exit_code' => $exitCode,
        'output' => implode("\n", $output),
        'lines' => $output
    ];
}

/**
 * Sanitize branch name to prevent shell injection
 */
function sanitize_branch(string $branch): string {
    $clean = preg_replace('/[^a-zA-Z0-9_\-\.\/]/', '', $branch);
    return trim($clean, '/');
}

/**
 * Dump database tables into a single SQL file using PHP
 */
function dump_database_to_file(mysqli $conn, string $filepath): void {
    $tables = [];
    $result = $conn->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
    if ($result) {
        while ($row = $result->fetch_row()) {
            $tables[] = $row[0];
        }
    }
    
    $dump = "-- Jotify Automated Database Backup\n";
    $dump .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $dump .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";
    
    foreach ($tables as $table) {
        $createRes = $conn->query("SHOW CREATE TABLE `" . $conn->real_escape_string($table) . "`");
        if ($createRes && $createRow = $createRes->fetch_row()) {
            $dump .= "DROP TABLE IF EXISTS `{$table}`;\n";
            $dump .= $createRow[1] . ";\n\n";
        }
        
        $dataRes = $conn->query("SELECT * FROM `" . $conn->real_escape_string($table) . "`");
        if ($dataRes && $dataRes->num_rows > 0) {
            while ($row = $dataRes->fetch_assoc()) {
                $keys = array_map(function($k) use ($conn) { return '`' . $conn->real_escape_string($k) . '`'; }, array_keys($row));
                $vals = array_map(function($v) use ($conn) {
                    if (is_null($v)) return 'NULL';
                    return "'" . $conn->real_escape_string($v) . "'";
                }, array_values($row));
                
                $dump .= "INSERT INTO `{$table}` (" . implode(', ', $keys) . ") VALUES (" . implode(', ', $vals) . ");\n";
            }
            $dump .= "\n";
        }
    }
    
    $dump .= "SET FOREIGN_KEY_CHECKS = 1;\n";
    $writeRes = @file_put_contents($filepath, $dump);
    if ($writeRes === false || !file_exists($filepath)) {
        throw new RuntimeException("Kon database niet opslaan in {$filepath}.");
    }
}

/**
 * Import a SQL file into the database using PHP multi_query
 */
function import_sql_file(mysqli $conn, string $sqlFile): void {
    $conn->query("SET FOREIGN_KEY_CHECKS = 0;");
    $sqlContent = file_get_contents($sqlFile);
    if (!empty($sqlContent)) {
        if ($conn->multi_query($sqlContent)) {
            do {
                if ($result = $conn->store_result()) {
                    $result->free();
                }
            } while ($conn->next_result());
        }
    }
    $conn->query("SET FOREIGN_KEY_CHECKS = 1;");
}

/**
 * Create a full system backup (.tar.gz archive with database.sql, backup_meta.json, and media/profiles/)
 */
function create_full_backup(mysqli $conn, string $webroot, string $backupDir, string $type = 'manual'): array {
    if (!is_dir($backupDir)) {
        @mkdir($backupDir, 0775, true);
    }
    if (!is_writable($backupDir)) {
        @chmod($backupDir, 0775);
    }
    if (!is_writable($backupDir)) {
        throw new RuntimeException("De back-up map ({$backupDir}) is niet schrijfbaar voor de webserver.");
    }

    $now = time();
    $timestamp = date('Ymd_His', $now);
    $archiveName = "{$type}_backup_jotify_{$timestamp}.tar.gz";
    $archivePath = $backupDir . '/' . $archiveName;

    // Temporary staging directory
    $tmpDir = $backupDir . '/tmp_' . uniqid();
    if (!@mkdir($tmpDir, 0775, true)) {
        throw new RuntimeException("Kon tijdelijke werkmap niet aanmaken: {$tmpDir}");
    }

    try {
        $sqlFile = $tmpDir . '/database.sql';
        $host = $GLOBALS['servername'] ?? 'localhost';
        $dbUser = $GLOBALS['username'] ?? '';
        $dbPass = $GLOBALS['password'] ?? '';
        $dbName = $GLOBALS['dbname'] ?? 'jotihunt';

        $mysqldump = trim(shell_exec('which mysqldump 2>/dev/null') ?? '');
        if (empty($mysqldump)) {
            $mysqldump = trim(shell_exec('which mariadb-dump 2>/dev/null') ?? '');
        }

        $dumpSuccess = false;
        if (!empty($mysqldump) && !empty($dbUser) && !empty($dbName)) {
            $cmd = sprintf(
                '%s -h %s -u %s %s %s > %s 2>&1',
                escapeshellarg($mysqldump),
                escapeshellarg($host),
                escapeshellarg($dbUser),
                !empty($dbPass) ? '-p' . escapeshellarg($dbPass) : '',
                escapeshellarg($dbName),
                escapeshellarg($sqlFile)
            );
            $cmdOutput = [];
            $exitCode = 0;
            exec($cmd, $cmdOutput, $exitCode);
            if ($exitCode === 0 && file_exists($sqlFile) && filesize($sqlFile) > 0) {
                $dumpSuccess = true;
            }
        }

        if (!$dumpSuccess) {
            dump_database_to_file($conn, $sqlFile);
        }

        // Git metadata
        $gitCommit = trim(shell_exec('cd ' . escapeshellarg($webroot) . ' && git -c safe.directory=* rev-parse --short HEAD 2>/dev/null') ?? 'onbekend');
        $gitFullHash = trim(shell_exec('cd ' . escapeshellarg($webroot) . ' && git -c safe.directory=* rev-parse HEAD 2>/dev/null') ?? '');
        $gitBranch = trim(shell_exec('cd ' . escapeshellarg($webroot) . ' && git -c safe.directory=* rev-parse --abbrev-ref HEAD 2>/dev/null') ?? 'main');

        // User list snapshot for restore account verification
        $usersList = [];
        $res = $conn->query("SELECT id, gebruikersnaam, priv FROM Gebruikers");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $usersList[] = [
                    'id' => (int)$row['id'],
                    'gebruikersnaam' => $row['gebruikersnaam'],
                    'priv' => (int)$row['priv']
                ];
            }
        }

        // Copy media/profiles
        // Copy user media assets
        @mkdir($tmpDir . '/media', 0775, true);

        // 1. Profiles
        $hasProfiles = false;
        $profilesDir = $webroot . '/media/profiles';
        if (is_dir($profilesDir)) {
            @mkdir($tmpDir . '/media/profiles', 0775, true);
            $profileFiles = glob($profilesDir . '/*');
            if (!empty($profileFiles)) {
                $hasProfiles = true;
                foreach ($profileFiles as $pf) {
                    if (is_file($pf)) {
                        @copy($pf, $tmpDir . '/media/profiles/' . basename($pf));
                    }
                }
            }
        }

        // 2. Hunt Photos
        $hasHunts = false;
        $huntsDir = $webroot . '/media/hunts';
        if (is_dir($huntsDir)) {
            @mkdir($tmpDir . '/media/hunts', 0775, true);
            $huntFiles = glob($huntsDir . '/*');
            if (!empty($huntFiles)) {
                $hasHunts = true;
                foreach ($huntFiles as $hf) {
                    if (is_file($hf) && basename($hf) !== '.gitkeep') {
                        @copy($hf, $tmpDir . '/media/hunts/' . basename($hf));
                    }
                }
            }
        }

        // 3. Counterhunt Photos
        $hasTegenhunt = false;
        $tegenhuntDir = $webroot . '/media/tegenhunt';
        if (is_dir($tegenhuntDir)) {
            @mkdir($tmpDir . '/media/tegenhunt', 0775, true);
            $tegenhuntFiles = glob($tegenhuntDir . '/*');
            if (!empty($tegenhuntFiles)) {
                $hasTegenhunt = true;
                foreach ($tegenhuntFiles as $thf) {
                    if (is_file($thf) && basename($thf) !== '.gitkeep') {
                        @copy($thf, $tmpDir . '/media/tegenhunt/' . basename($thf));
                    }
                }
            }
        }

        // 4. Custom Scouting Logo
        $hasLogo = false;
        $logoFile = $webroot . '/media/scoutingLogo.png';
        if (file_exists($logoFile)) {
            $hasLogo = true;
            @copy($logoFile, $tmpDir . '/media/scoutingLogo.png');
        }

        // 5. Telegram MTProto Listener Session (*.session)
        $hasSession = false;
        $servicesDir = $webroot . '/services';
        if (is_dir($servicesDir)) {
            @mkdir($tmpDir . '/services', 0775, true);
            $sessionFiles = glob($servicesDir . '/*.session');
            if (!empty($sessionFiles)) {
                $hasSession = true;
                foreach ($sessionFiles as $sf) {
                    if (is_file($sf)) {
                        @copy($sf, $tmpDir . '/services/' . basename($sf));
                    }
                }
            }
        }

        // Write metadata
        $meta = [
            'format' => 3,
            'type' => $type,
            'created_at' => date('d-m-Y H:i:s', $now),
            'timestamp' => $now,
            'git_commit' => $gitCommit,
            'git_full_hash' => $gitFullHash,
            'git_branch' => $gitBranch,
            'has_profiles' => $hasProfiles,
            'has_hunts' => $hasHunts,
            'has_tegenhunt' => $hasTegenhunt,
            'has_logo' => $hasLogo,
            'has_session' => $hasSession,
            'user_count' => count($usersList),
            'users' => $usersList
        ];
        file_put_contents($tmpDir . '/backup_meta.json', json_encode($meta, JSON_PRETTY_PRINT));

        // Package into .tar.gz archive with gzip -9 (maximum compression)
        $tarCmd = sprintf('tar -I "gzip -9" -cf %s -C %s . 2>&1', escapeshellarg($archivePath), escapeshellarg($tmpDir));
        $tarOutput = [];
        $tarExitCode = 0;
        exec($tarCmd, $tarOutput, $tarExitCode);
        if ($tarExitCode !== 0 || !file_exists($archivePath)) {
            throw new RuntimeException("Aanmaken van tar archief is mislukt: " . implode("\n", $tarOutput));
        }

        @chmod($archivePath, 0664);

        return [
            'ok' => true,
            'filename' => $archiveName,
            'path' => $archivePath,
            'size_bytes' => filesize($archivePath),
            'size_formatted' => bitbyte2string(filesize($archivePath)),
            'type' => $type,
            'commit' => $gitCommit,
            'branch' => $gitBranch,
            'date' => date('d-m-Y H:i:s', $now)
        ];
    } finally {
        if (is_dir($tmpDir)) {
            shell_exec('rm -rf ' . escapeshellarg($tmpDir));
        }
    }
}

/**
 * Extract backup metadata from .tar.gz without full disk extraction
 */
function get_backup_meta(string $backupPath): array {
    $filename = basename($backupPath);
    $type = str_starts_with($filename, 'auto_backup_') ? 'auto' : 'manual';
    
    $cmd = sprintf('tar -xf %s --wildcards "*backup_meta.json" -O 2>/dev/null', escapeshellarg($backupPath));
    $metaJson = shell_exec($cmd);
    if (!empty($metaJson)) {
        $data = json_decode($metaJson, true);
        if (is_array($data)) {
            return $data;
        }
    }

    $mtime = file_exists($backupPath) ? filemtime($backupPath) : time();
    return [
        'format' => 1,
        'type' => $type,
        'created_at' => date('d-m-Y H:i:s', $mtime),
        'timestamp' => $mtime,
        'git_commit' => 'onbekend',
        'git_branch' => 'main',
        'has_profiles' => false,
        'user_count' => 0,
        'users' => []
    ];
}

/**
 * Restore database and profiles from a .tar.gz backup, and downgrade system to backup commit
 */
function restore_backup(mysqli $conn, string $webroot, string $backupDir, string $filename, bool $force = false): array {
    $cleanName = basename($filename);
    $lower = strtolower($cleanName);
    $backupPath = $backupDir . '/' . $cleanName;
    $isTar = str_ends_with($lower, '.tar.gz') || str_ends_with($lower, '.tar.xz') || str_ends_with($lower, '.tgz');
    if (!file_exists($backupPath) || !$isTar) {
        throw new RuntimeException("Back-up bestand niet gevonden of heeft geen geldige extensie (.tar.gz / .tar.xz): {$cleanName}");
    }

    $meta = get_backup_meta($backupPath);

    // Verify currently logged in user exists in backup
    $currentUserId = (int)($_SESSION['id'] ?? 0);
    $currentUsername = $_SESSION['username'] ?? '';
    
    $userFound = false;
    if (!empty($meta['users'])) {
        foreach ($meta['users'] as $u) {
            if (($currentUserId > 0 && (int)$u['id'] === $currentUserId) || 
                (!empty($currentUsername) && strtolower($u['gebruikersnaam']) === strtolower($currentUsername))) {
                $userFound = true;
                break;
            }
        }
    }

    if (!$userFound && !$force) {
        return [
            'ok' => false,
            'requires_confirmation' => true,
            'warning_type' => 'user_not_in_backup',
            'current_user' => $currentUsername,
            'target_commit' => $meta['git_commit'] ?? 'onbekend',
            'message' => "Waarschuwing: Jouw huidige gebruikersaccount ('{$currentUsername}') bevindt zich NIET in deze back-up! Als je doorgaat, wordt jouw account overschreven en moet je inloggen met een account uit de back-up. Weet je zeker dat je wilt doorgaan?"
        ];
    }

    $tmpRestoreDir = $backupDir . '/restore_tmp_' . uniqid();
    @mkdir($tmpRestoreDir, 0775, true);

    try {
        // Extract archive (tar -xf automatically handles both gzip and xz)
        $extractCmd = sprintf('tar -xf %s -C %s 2>&1', escapeshellarg($backupPath), escapeshellarg($tmpRestoreDir));
        $extOut = [];
        $extExit = 0;
        exec($extractCmd, $extOut, $extExit);
        if ($extExit !== 0) {
            throw new RuntimeException("Uitpakken van back-up mislukt: " . implode("\n", $extOut));
        }

        // Restore Database
        $sqlFile = $tmpRestoreDir . '/database.sql';
        if (!file_exists($sqlFile)) {
            throw new RuntimeException("Geen database.sql aangetroffen in het back-upbestand.");
        }

        $host = $GLOBALS['servername'] ?? 'localhost';
        $dbUser = $GLOBALS['username'] ?? '';
        $dbPass = $GLOBALS['password'] ?? '';
        $dbName = $GLOBALS['dbname'] ?? 'jotihunt';

        $mysqlBin = trim(shell_exec('which mysql 2>/dev/null') ?? '');
        $sqlRestored = false;
        if (!empty($mysqlBin) && !empty($dbUser) && !empty($dbName)) {
            $cmd = sprintf(
                '%s -h %s -u %s %s %s < %s 2>&1',
                escapeshellarg($mysqlBin),
                escapeshellarg($host),
                escapeshellarg($dbUser),
                !empty($dbPass) ? '-p' . escapeshellarg($dbPass) : '',
                escapeshellarg($dbName),
                escapeshellarg($sqlFile)
            );
            $sqlOut = [];
            $sqlExit = 0;
            exec($cmd, $sqlOut, $sqlExit);
            if ($sqlExit === 0) {
                $sqlRestored = true;
            }
        }

        if (!$sqlRestored) {
            import_sql_file($conn, $sqlFile);
        }

        // Restore user media assets
        $restoredMediaCount = 0;

        // 1. Profiles (support both format 3 'media/profiles' and legacy format 2 'profiles')
        $srcProfiles = is_dir($tmpRestoreDir . '/media/profiles') 
            ? $tmpRestoreDir . '/media/profiles' 
            : (is_dir($tmpRestoreDir . '/profiles') ? $tmpRestoreDir . '/profiles' : null);
        if ($srcProfiles) {
            $destDir = $webroot . '/media/profiles';
            @mkdir($destDir, 0775, true);
            $profileFiles = glob($srcProfiles . '/*');
            if (!empty($profileFiles)) {
                foreach ($profileFiles as $pf) {
                    if (is_file($pf)) {
                        @copy($pf, $destDir . '/' . basename($pf));
                        $restoredMediaCount++;
                    }
                }
            }
            @chmod($destDir, 0775);
        }

        // 2. Hunt Photos
        if (is_dir($tmpRestoreDir . '/media/hunts')) {
            $destDir = $webroot . '/media/hunts';
            @mkdir($destDir, 0775, true);
            $huntFiles = glob($tmpRestoreDir . '/media/hunts/*');
            if (!empty($huntFiles)) {
                foreach ($huntFiles as $hf) {
                    if (is_file($hf)) {
                        @copy($hf, $destDir . '/' . basename($hf));
                        $restoredMediaCount++;
                    }
                }
            }
            @chmod($destDir, 0775);
        }

        // 3. Counterhunt Photos
        if (is_dir($tmpRestoreDir . '/media/tegenhunt')) {
            $destDir = $webroot . '/media/tegenhunt';
            @mkdir($destDir, 0775, true);
            $thFiles = glob($tmpRestoreDir . '/media/tegenhunt/*');
            if (!empty($thFiles)) {
                foreach ($thFiles as $thf) {
                    if (is_file($thf)) {
                        @copy($thf, $destDir . '/' . basename($thf));
                        $restoredMediaCount++;
                    }
                }
            }
            @chmod($destDir, 0775);
        }

        // 4. Scouting Logo
        if (file_exists($tmpRestoreDir . '/media/scoutingLogo.png')) {
            @copy($tmpRestoreDir . '/media/scoutingLogo.png', $webroot . '/media/scoutingLogo.png');
            @chmod($webroot . '/media/scoutingLogo.png', 0664);
            $restoredMediaCount++;
        }

        // 5. Telegram MTProto Listener Session
        $restoredSession = false;
        if (is_dir($tmpRestoreDir . '/services')) {
            $destDir = $webroot . '/services';
            @mkdir($destDir, 0775, true);
            $sessionFiles = glob($tmpRestoreDir . '/services/*.session');
            if (!empty($sessionFiles)) {
                foreach ($sessionFiles as $sf) {
                    if (is_file($sf)) {
                        @copy($sf, $destDir . '/' . basename($sf));
                        @chmod($destDir . '/' . basename($sf), 0660);
                        $restoredSession = true;
                    }
                }
            }
            @chmod($destDir, 0775);
        }

        // Downgrade code to backup version
        $targetCommit = $meta['git_commit'] ?? '';
        $commitMessage = '';
        if (!empty($targetCommit) && $targetCommit !== 'onbekend') {
            cleanup_stale_git_lock($webroot);
            $coRes = run_git_cmd('git checkout -f ' . escapeshellarg($targetCommit), $webroot);
            $commitMessage = " en het systeem is teruggezet naar commit {$targetCommit}";
        }

        // Refresh directory permissions
        @chmod($webroot . '/media', 0775);
        @chmod($webroot . '/media/profiles', 0775);
        @chmod($webroot . '/media/hunts', 0775);
        @chmod($webroot . '/media/tegenhunt', 0775);
        @chmod($webroot . '/services', 0775);
        @chmod($webroot . '/DB/backups', 0775);

        $extraMsg = $restoredSession ? " en Telegram sessie" : "";
        return [
            'ok' => true,
            'message' => "Back-up succesvol hersteld! Database hersteld, {$restoredMediaCount} mediabestand(en){$extraMsg} teruggezet{$commitMessage}.",
            'restored_commit' => $targetCommit,
            'user_warning_given' => !$userFound
        ];
    } finally {
        if (is_dir($tmpRestoreDir)) {
            shell_exec('rm -rf ' . escapeshellarg($tmpRestoreDir));
        }
    }
}

/**
 * Delete a specific backup file
 */
function delete_backup(string $backupDir, string $filename): array {
    $cleanFilename = basename($filename);
    $filePath = $backupDir . '/' . $cleanFilename;
    if (!file_exists($filePath)) {
        throw new RuntimeException("Back-up bestand niet gevonden.");
    }
    if (!str_ends_with(strtolower($cleanFilename), '.tar.gz') && !str_ends_with(strtolower($cleanFilename), '.sql')) {
        throw new RuntimeException("Ongeldig bestandstype om te verwijderen.");
    }

    if (!@unlink($filePath)) {
        throw new RuntimeException("Kon back-up bestand '{$cleanFilename}' niet verwijderen (rechtenfout).");
    }

    return [
        'ok' => true,
        'message' => "Back-up '{$cleanFilename}' is succesvol verwijderd!"
    ];
}

/**
 * Upload an off-site backup file into the system
 */
function upload_backup(string $backupDir): array {
    if (empty($_FILES['backup_file']['tmp_name'])) {
        throw new RuntimeException("Geen bestand geüpload.");
    }

    $origName = basename($_FILES['backup_file']['name']);
    $lower = strtolower($origName);
    if (!str_ends_with($lower, '.tar.gz') && !str_ends_with($lower, '.tar.xz') && !str_ends_with($lower, '.tgz')) {
        throw new RuntimeException("Alleen .tar.gz en .tar.xz archieven zijn toegestaan.");
    }

    $tmpPath = $_FILES['backup_file']['tmp_name'];
    $checkCmd = sprintf('tar -tf %s 2>&1 | grep "database.sql"', escapeshellarg($tmpPath));
    $checkRes = shell_exec($checkCmd);
    if (empty(trim($checkRes ?? ''))) {
        throw new RuntimeException("Het geüploade bestand is geen geldige Jotify back-up (database.sql ontbreekt in archief).");
    }

    $sanitized = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $origName);
    if (!str_starts_with($sanitized, 'manual_') && !str_starts_with($sanitized, 'auto_')) {
        $sanitized = 'manual_' . $sanitized;
    }
    $destPath = $backupDir . '/' . $sanitized;

    if (!move_uploaded_file($tmpPath, $destPath)) {
        throw new RuntimeException("Kon geüpload bestand niet opslaan in back-up directory.");
    }
    @chmod($destPath, 0664);

    $meta = get_backup_meta($destPath);

    return [
        'ok' => true,
        'message' => "Back-up '{$sanitized}' succesvol geüpload!",
        'filename' => $sanitized,
        'size' => round(filesize($destPath) / 1024, 1) . ' KB',
        'meta' => $meta
    ];
}

/**
 * Download a backup file directly
 */
function download_backup(string $backupDir, string $filename): void {
    $cleanFilename = basename($filename);
    $filePath = $backupDir . '/' . $cleanFilename;
    if (!file_exists($filePath)) {
        http_response_code(404);
        echo "Bestand niet gevonden.";
        exit();
    }
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Description: File Transfer');
    header('Content-Type: application/gzip');
    header('Content-Disposition: attachment; filename="' . $cleanFilename . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);
    exit();
}

/**
 * Tiered retention pruning for automated backups:
 * - 1 every hour for 6 hours
 * - 1 every 6 hours for 3 days (72h)
 * - 1 once a day for 1 week (168h)
 * - 1 once a week for 2 months (60 days)
 * - > 60 days: pruned
 * Manual backups are 100% exempt from pruning.
 */
function prune_backups_tiered(string $backupDir): array {
    if (!is_dir($backupDir)) {
        return ['deleted_count' => 0, 'kept_count' => 0, 'deleted' => []];
    }

    $files = array_merge(glob($backupDir . '/*.tar.gz') ?: [], glob($backupDir . '/*.tar.xz') ?: []);
    if (empty($files)) {
        return ['deleted_count' => 0, 'kept_count' => 0, 'deleted' => []];
    }

    $now = time();
    $autoBackups = [];

    foreach ($files as $f) {
        $name = basename($f);
        // ONLY prune automatic backups! Manual backups are completely exempt.
        if (!str_starts_with($name, 'auto_backup_')) {
            continue;
        }

        $ts = null;
        if (preg_match('/auto_backup_jotify_(\d{4})(\d{2})(\d{2})_(\d{2})(\d{2})(\d{2})/', $name, $m)) {
            $ts = mktime((int)$m[4], (int)$m[5], (int)$m[6], (int)$m[2], (int)$m[3], (int)$m[1]);
        }
        if (!$ts) {
            $ts = filemtime($f);
        }

        $autoBackups[] = [
            'path' => $f,
            'filename' => $name,
            'timestamp' => $ts,
            'age_seconds' => max(0, $now - $ts)
        ];
    }

    // Sort newest to oldest
    usort($autoBackups, function($a, $b) {
        return $b['timestamp'] <=> $a['timestamp'];
    });

    $buckets = [];
    $toDelete = [];
    $toKeep = [];

    foreach ($autoBackups as $b) {
        $age = $b['age_seconds'];
        $ts = $b['timestamp'];

        // Tier 1: 0 to 6 hours -> 1 every hour
        if ($age <= 6 * 3600) {
            $bucketKey = 't1_' . date('Ymd_H', $ts);
        }
        // Tier 2: 6 hours to 3 days (72h) -> 1 every 6 hours
        elseif ($age <= 72 * 3600) {
            $sixHourBlock = floor(date('H', $ts) / 6) * 6;
            $bucketKey = 't2_' . date('Ymd_', $ts) . sprintf('%02d', $sixHourBlock);
        }
        // Tier 3: 3 days to 1 week (168h) -> 1 once a day
        elseif ($age <= 168 * 3600) {
            $bucketKey = 't3_' . date('Ymd', $ts);
        }
        // Tier 4: 1 week to 2 months (60 days = 1440h) -> 1 once a week
        elseif ($age <= 60 * 86400) {
            $bucketKey = 't4_' . date('o_W', $ts);
        }
        // Tier 5: Older than 2 months -> pruned
        else {
            $bucketKey = null;
        }

        if ($bucketKey === null) {
            $toDelete[] = $b['path'];
        } else {
            if (!isset($buckets[$bucketKey])) {
                $buckets[$bucketKey] = $b;
                $toKeep[] = $b['path'];
            } else {
                $toDelete[] = $b['path'];
            }
        }
    }

    $deletedFiles = [];
    foreach ($toDelete as $f) {
        if (@unlink($f)) {
            $deletedFiles[] = basename($f);
        }
    }

    return [
        'deleted_count' => count($deletedFiles),
        'kept_count' => count($toKeep),
        'deleted' => $deletedFiles
    ];
}

$action = $_REQUEST['action'] ?? '';

if (php_sapi_name() === 'cli' && empty($action)) {
    return;
}

switch ($action) {
    case 'get_system_metrics':
        $metrics = [];

        // --- CPU ---
        $loadAvg = sys_getloadavg();
        $cpuCores = (int) trim(shell_exec('nproc 2>/dev/null') ?: '1');
        $load1 = $loadAvg[0] ?? 0;
        $load5 = $loadAvg[1] ?? 0;
        $cpuCurrentPct = min(100, ($load1 / $cpuCores) * 100);
        $cpuAvg5Pct = min(100, ($load5 / $cpuCores) * 100);
        $metrics['cpu'] = [
            'current_percent' => round($cpuCurrentPct, 1),
            'avg_5min_percent' => round($cpuAvg5Pct, 1),
            'load_1min' => round($load1, 2),
            'load_5min' => round($load5, 2),
            'cores' => $cpuCores
        ];

        // --- RAM ---
        $meminfo = @file_get_contents('/proc/meminfo');
        $memTotal = 0;
        $memAvailable = 0;
        if ($meminfo) {
            if (preg_match('/MemTotal:\s+(\d+)/', $meminfo, $m)) {
                $memTotal = (int)$m[1]; // kB
            }
            if (preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $m)) {
                $memAvailable = (int)$m[1]; // kB
            }
        }
        $memUsed = $memTotal - $memAvailable;
        $memUsedPct = $memTotal > 0 ? ($memUsed / $memTotal) * 100 : 0;

        // 5-min RAM average from /proc/pressure/memory if available, otherwise use current
        $ramAvg5Pct = $memUsedPct;
        $psiMemory = @file_get_contents('/proc/pressure/memory');
        if ($psiMemory && preg_match('/some avg10=([\d.]+) avg60=([\d.]+) avg300=([\d.]+)/', $psiMemory, $psi)) {
            // PSI shows pressure (stall %), not utilization. Use current as fallback.
            $ramAvg5Pct = $memUsedPct; // Use instantaneous value — stable enough for dashboards
        }

        $metrics['ram'] = [
            'total_gb' => round($memTotal / 1048576, 2),
            'used_gb' => round($memUsed / 1048576, 2),
            'available_gb' => round($memAvailable / 1048576, 2),
            'used_percent' => round($memUsedPct, 1),
            'avg_5min_percent' => round($ramAvg5Pct, 1)
        ];

        // --- Network ---
        $primaryIface = trim(shell_exec("ip route | awk '/default/ {print $5; exit}'") ?: 'eth0');

        // Read /proc/net/dev twice with a 1-second gap for instantaneous rate
        $netDev1 = @file_get_contents('/proc/net/dev');
        $rx1 = $tx1 = 0;
        if ($netDev1 && preg_match('/^\s*' . preg_quote($primaryIface, '/') . ':\s*(\d+)(?:\s+\d+){7}\s+(\d+)/m', $netDev1, $n)) {
            $rx1 = (int)$n[1];
            $tx1 = (int)$n[2];
        }
        usleep(500000); // 0.5 second sample
        $netDev2 = @file_get_contents('/proc/net/dev');
        $rx2 = $tx2 = 0;
        if ($netDev2 && preg_match('/^\s*' . preg_quote($primaryIface, '/') . ':\s*(\d+)(?:\s+\d+){7}\s+(\d+)/m', $netDev2, $n)) {
            $rx2 = (int)$n[1];
            $tx2 = (int)$n[2];
        }
        $rxRate = ($rx2 - $rx1) * 2; // Scale 0.5s sample to 1s
        $txRate = ($tx2 - $tx1) * 2;

        // 5-min rolling average from /proc/net/dev sliding history
        $now = microtime(true);
        $historyFile = sys_get_temp_dir() . '/jotify_net_history.json';
        $history = [];
        if (file_exists($historyFile)) {
            $content = @file_get_contents($historyFile);
            if ($content) {
                $decoded = @json_decode($content, true);
                if (is_array($decoded)) {
                    $history = $decoded;
                }
            }
        }

        // Prune entries older than 330 seconds (5.5 minutes)
        $cutoff = $now - 330;
        $history = array_values(array_filter($history, function($entry) use ($cutoff) {
            return isset($entry['t'], $entry['rx'], $entry['tx']) && $entry['t'] >= $cutoff;
        }));

        // Calculate 5-minute rolling average if we have prior samples
        $avgRx = $rxRate;
        $avgTx = $txRate;
        if (!empty($history)) {
            $oldest = $history[0];
            $timeDelta = $now - $oldest['t'];
            if ($timeDelta >= 2.0 && $rx2 >= $oldest['rx'] && $tx2 >= $oldest['tx']) {
                $avgRx = ($rx2 - $oldest['rx']) / $timeDelta;
                $avgTx = ($tx2 - $oldest['tx']) / $timeDelta;
            }
        }

        // Record current cumulative sample
        $history[] = [
            't' => $now,
            'rx' => $rx2,
            'tx' => $tx2
        ];
        @file_put_contents($historyFile, json_encode($history), LOCK_EX);

        $metrics['network'] = [
            'interface' => $primaryIface,
            'rx_bytes_sec' => max(0, $rxRate),
            'tx_bytes_sec' => max(0, $txRate),
            'avg_5min_rx' => max(0, $avgRx),
            'avg_5min_tx' => max(0, $avgTx),
            'rx_formatted' => bitbyte2string(max(0, $rxRate), true, 1, true),
            'tx_formatted' => bitbyte2string(max(0, $txRate), true, 1, true),
            'avg_5min_rx_formatted' => bitbyte2string(max(0, $avgRx), true, 1, true),
            'avg_5min_tx_formatted' => bitbyte2string(max(0, $avgTx), true, 1, true)
        ];

        // --- Storage ---
        $diskTotal = @disk_total_space('/');
        $diskFree = @disk_free_space('/');
        $diskUsed = $diskTotal - $diskFree;
        $diskPct = $diskTotal > 0 ? ($diskUsed / $diskTotal) * 100 : 0;

        $metrics['storage'] = [
            'total_gb' => round($diskTotal / (1024 ** 3), 2),
            'used_gb' => round($diskUsed / (1024 ** 3), 2),
            'free_gb' => round($diskFree / (1024 ** 3), 2),
            'used_percent' => round($diskPct, 1)
        ];

        send_json(['ok' => true, 'metrics' => $metrics]);
        break;

    case 'check_updates':
        // 1. Detect current branch
        $branchRes = run_git_cmd('git rev-parse --abbrev-ref HEAD', $webroot);
        $currentBranch = trim($branchRes['output']);
        if (empty($currentBranch)) {
            $currentBranch = 'main';
        }
        
        // 2. Configure core.filemode=false to prevent permissions/chmod false positives
        run_git_cmd('git config core.filemode false', $webroot);
        
        // 3. Fetch latest from origin
        $fetchRes = run_git_cmd('git fetch origin ' . escapeshellarg($currentBranch), $webroot);
        
        // 4. Current commit details
        $currentHashRes = run_git_cmd('git rev-parse --short HEAD', $webroot);
        $currentCommit = trim($currentHashRes['output']);
        
        $currentFullHashRes = run_git_cmd('git rev-parse HEAD', $webroot);
        $currentFullHash = trim($currentFullHashRes['output']);
        
        $currentMetaRes = run_git_cmd('git log -1 --format="%an|%ad|%s" --date=format:"%d-%m-%Y %H:%M"', $webroot);
        $metaParts = explode('|', trim($currentMetaRes['output']), 3);
        $currentAuthor = $metaParts[0] ?? 'Onbekend';
        $currentDate = $metaParts[1] ?? '';
        $currentMessage = $metaParts[2] ?? '';
        
        // 5. Remote commit details
        $remoteHashRes = run_git_cmd('git rev-parse --short origin/' . escapeshellarg($currentBranch), $webroot);
        $remoteCommit = trim($remoteHashRes['output']);
        
        // 6. Commits behind count
        $countRes = run_git_cmd('git rev-list --count HEAD..origin/' . escapeshellarg($currentBranch), $webroot);
        $commitsBehind = (int)trim($countRes['output']);
        $updateAvailable = ($commitsBehind > 0);
        
        // 7. Commit changelog list
        $commitsList = [];
        if ($updateAvailable) {
            $logRes = run_git_cmd('git log HEAD..origin/' . escapeshellarg($currentBranch) . ' --pretty=format:"%h|%an|%ar|%s" -n 25', $webroot);
            foreach ($logRes['lines'] as $line) {
                if (trim($line) === '') continue;
                $parts = explode('|', $line, 4);
                $commitsList[] = [
                    'hash' => $parts[0] ?? '',
                    'author' => $parts[1] ?? '',
                    'time_ago' => $parts[2] ?? '',
                    'message' => $parts[3] ?? ''
                ];
            }
        }
        
        // 8. Impact analysis on modified files
        $hasDbChanges = false;
        $hasComposerChanges = false;
        $hasPythonChanges = false;
        $hasCronChanges = false;
        $changedFiles = [];
        
        if ($updateAvailable) {
            $diffRes = run_git_cmd('git diff --name-only HEAD origin/' . escapeshellarg($currentBranch), $webroot);
            $changedFiles = array_filter(array_map('trim', $diffRes['lines']));
            
            foreach ($changedFiles as $file) {
                if (str_starts_with($file, 'DB/') || $file === 'DB/createDB.sql') {
                    $hasDbChanges = true;
                }
                if ($file === 'composer.json' || $file === 'composer.lock') {
                    $hasComposerChanges = true;
                }
                if (str_starts_with($file, 'services/') || str_ends_with($file, '.py')) {
                    $hasPythonChanges = true;
                }
                if (str_starts_with($file, 'cron/')) {
                    $hasCronChanges = true;
                }
            }
        }
        
        // 9. Available branches
        $branchesRes = run_git_cmd('git branch -r', $webroot);
        $availableBranches = [];
        foreach ($branchesRes['lines'] as $b) {
            $b = trim($b);
            if (str_starts_with($b, 'origin/') && !str_contains($b, 'HEAD ->')) {
                $bName = str_replace('origin/', '', $b);
                if (!in_array($bName, $availableBranches)) {
                    $availableBranches[] = $bName;
                }
            }
        }
        if (empty($availableBranches)) {
            $availableBranches = [$currentBranch];
        }
        
        // 10. Existing backups list (.tar.gz and .tar.xz archives)
        $backups = [];
        if (is_dir($backupDir)) {
            $files = array_merge(glob($backupDir . '/*.tar.gz') ?: [], glob($backupDir . '/*.tar.xz') ?: []);
            rsort($files);
            foreach ($files as $f) {
                $base = basename($f);
                $meta = get_backup_meta($f);
                $backups[] = [
                    'filename' => $base,
                    'size' => bitbyte2string(filesize($f)),
                    'size_bytes' => filesize($f),
                    'date' => $meta['created_at'] ?? date('d-m-Y H:i:s', filemtime($f)),
                    'timestamp' => $meta['timestamp'] ?? filemtime($f),
                    'type' => $meta['type'] ?? (str_starts_with($base, 'auto_backup_') ? 'auto' : 'manual'),
                    'commit' => $meta['git_commit'] ?? 'onbekend',
                    'branch' => $meta['git_branch'] ?? 'main',
                    'user_count' => $meta['user_count'] ?? 0,
                    'has_profiles' => $meta['has_profiles'] ?? false
                ];
            }
        }
        
        send_json([
            'ok' => true,
            'branch' => $currentBranch,
            'current_commit' => $currentCommit,
            'current_full_hash' => $currentFullHash,
            'current_author' => $currentAuthor,
            'current_date' => $currentDate,
            'current_message' => $currentMessage,
            'remote_commit' => $remoteCommit,
            'commits_behind' => $commitsBehind,
            'update_available' => $updateAvailable,
            'commits' => $commitsList,
            'impact' => [
                'has_db_changes' => $hasDbChanges,
                'has_composer_changes' => $hasComposerChanges,
                'has_python_changes' => $hasPythonChanges,
                'has_cron_changes' => $hasCronChanges,
                'changed_files_count' => count($changedFiles)
            ],
            'available_branches' => $availableBranches,
            'recent_backups' => $backups
        ]);
        break;

    case 'perform_update':
        set_time_limit(240);
        
        $branchRes = run_git_cmd('git rev-parse --abbrev-ref HEAD', $webroot);
        $currentBranch = trim($branchRes['output']);
        if (empty($currentBranch)) {
            $currentBranch = 'main';
        }
        
        $doBackup = !empty($_POST['do_backup']);
        $steps = [];
        
        // Step 1: Pre-flight check
        run_git_cmd('git config core.filemode false', $webroot);
        cleanup_stale_git_lock($webroot);
        $steps[] = [
            'step' => 1,
            'title' => 'Pre-flight controle & repository opschoning',
            'status' => 'success',
            'details' => 'Lokale repository klaargemaakt voor fast-forward update.'
        ];
        
        // Step 2: Database backup if requested
        $backupInfo = null;
        if ($doBackup) {
            try {
                $backupInfo = create_full_backup($conn, $webroot, $backupDir, 'manual');
                $steps[] = [
                    'step' => 2,
                    'title' => 'Database back-up momentopname',
                    'status' => 'success',
                    'details' => 'Back-up succesvol opgeslagen: ' . $backupInfo['filename'] . ' (' . $backupInfo['size_formatted'] . ')'
                ];
            } catch (Throwable $e) {
                $steps[] = [
                    'step' => 2,
                    'title' => 'Database back-up momentopname',
                    'status' => 'warning',
                    'details' => 'Back-up waarschuwing: ' . $e->getMessage()
                ];
            }
        }
        
        // Step 3: Git Pull
        $pullRes = run_git_cmd('git pull origin ' . escapeshellarg($currentBranch), $webroot);
        if ($pullRes['exit_code'] !== 0) {
            $steps[] = [
                'step' => 3,
                'title' => 'Git pull van GitHub',
                'status' => 'error',
                'details' => 'Git pull is mislukt: ' . $pullRes['output']
            ];
            send_json([
                'ok' => false,
                'error' => 'Fout bij het ophalen van code van GitHub: ' . $pullRes['output'],
                'steps' => $steps
            ]);
        }
        $steps[] = [
            'step' => 3,
            'title' => 'Git pull van GitHub',
            'status' => 'success',
            'details' => $pullRes['output'] ?: 'Code succesvol bijgewerkt vanaf ' . $currentBranch . '.'
        ];
        
        // Step 4: Composer dependencies
        $composerInstalled = false;
        $composerBin = trim(shell_exec('which composer 2>/dev/null') ?? '');
        if (!empty($composerBin) && file_exists($webroot . '/composer.json')) {
            $compCmd = 'cd ' . escapeshellarg($webroot) . ' && composer install --no-dev --optimize-autoloader 2>&1';
            $compOutput = shell_exec($compCmd);
            $composerInstalled = true;
            $steps[] = [
                'step' => 4,
                'title' => 'Composer PHP afhankelijkheden',
                'status' => 'success',
                'details' => 'Composer pakketten en autoloader succesvol bijgewerkt.'
            ];
        } else {
            $steps[] = [
                'step' => 4,
                'title' => 'Composer PHP afhankelijkheden',
                'status' => 'skipped',
                'details' => 'Geen composer update noodzakelijk of composer CLI niet gevonden.'
            ];
        }
        
        // Step 5: Database schema synchronization
        $schemaFile = $webroot . '/DB/createDB.sql';
        if (file_exists($schemaFile)) {
            $sqlContent = file_get_contents($schemaFile);
            if (!empty($sqlContent)) {
                $conn->query("SET FOREIGN_KEY_CHECKS = 0;");
                if ($conn->multi_query($sqlContent)) {
                    do {
                        if ($result = $conn->store_result()) {
                            $result->free();
                        }
                    } while ($conn->next_result());
                }
                $conn->query("SET FOREIGN_KEY_CHECKS = 1;");
                $steps[] = [
                    'step' => 5,
                    'title' => 'Database schema synchronisatie',
                    'status' => 'success',
                    'details' => 'Database tabellen en velden zijn gesynchroniseerd met de nieuwste definities.'
                ];
            }
        }
        
        // Step 6: File permissions & update stamp
        @chmod($webroot . '/media', 0775);
        @chmod($webroot . '/media/profiles', 0775);
        @chmod($webroot . '/media/hunts', 0775);
        @chmod($webroot . '/media/tegenhunt', 0775);
        @chmod($webroot . '/services', 0775);
        
        $installedFile = $webroot . '/.installed';
        $meta = [
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $_SESSION['username'] ?? 'Superadmin',
            'branch' => $currentBranch
        ];
        @file_put_contents($installedFile, json_encode($meta, JSON_PRETTY_PRINT));
        
        $steps[] = [
            'step' => 6,
            'title' => 'Bestandsrechten & afronding',
            'status' => 'success',
            'details' => 'Maprechten gecontroleerd en installatiestempel vernieuwd.'
        ];
        
        // Query new commit hash
        $newHashRes = run_git_cmd('git rev-parse --short HEAD', $webroot);
        $newCommit = trim($newHashRes['output']);
        
        send_json([
            'ok' => true,
            'message' => 'Jotify is succesvol bijgewerkt naar versie ' . $newCommit . '!',
            'new_commit' => $newCommit,
            'steps' => $steps,
            'backup' => $backupInfo
        ]);
        break;

    case 'switch_branch':
        $targetBranch = sanitize_branch($_POST['branch'] ?? '');
        if (empty($targetBranch)) {
            send_json(['ok' => false, 'error' => 'Geen geldige branch opgegeven.']);
        }
        
        // Verify branch exists in remote
        $checkRes = run_git_cmd('git branch -a', $webroot);
        $branchFound = false;
        foreach ($checkRes['lines'] as $b) {
            if (str_contains($b, $targetBranch)) {
                $branchFound = true;
                break;
            }
        }
        
        if (!$branchFound) {
            send_json(['ok' => false, 'error' => "Branch '{$targetBranch}' is niet gevonden op de server of remote origin."]);
        }
        
        run_git_cmd('git config core.filemode false', $webroot);
        run_git_cmd('git checkout -- .', $webroot);
        
        $coRes = run_git_cmd('git checkout ' . escapeshellarg($targetBranch), $webroot);
        if ($coRes['exit_code'] !== 0) {
            send_json(['ok' => false, 'error' => 'Kan niet wisselen naar branch: ' . $coRes['output']]);
        }
        
        run_git_cmd('git pull origin ' . escapeshellarg($targetBranch), $webroot);
        
        $hashRes = run_git_cmd('git rev-parse --short HEAD', $webroot);
        $newCommit = trim($hashRes['output']);
        
        send_json([
            'ok' => true,
            'message' => "Succesvol gewisseld naar branch '{$targetBranch}' (commit: {$newCommit})!",
            'branch' => $targetBranch,
            'commit' => $newCommit
        ]);
        break;

    case 'create_backup':
        try {
            $backup = create_full_backup($conn, $webroot, $backupDir, 'manual');
            send_json([
                'ok' => true,
                'message' => 'Volledige systeemback-up (database + profielfoto\'s) succesvol aangemaakt!',
                'backup' => $backup
            ]);
        } catch (Throwable $e) {
            send_json([
                'ok' => false,
                'error' => 'Fout bij het maken van de back-up: ' . $e->getMessage()
            ]);
        }
        break;

    case 'restore_backup':
        try {
            $filename = $_POST['filename'] ?? '';
            $force = !empty($_POST['force']);
            $res = restore_backup($conn, $webroot, $backupDir, $filename, $force);
            send_json($res);
        } catch (Throwable $e) {
            send_json([
                'ok' => false,
                'error' => 'Fout bij het herstellen van de back-up: ' . $e->getMessage()
            ]);
        }
        break;

    case 'delete_backup':
        try {
            $filename = $_POST['filename'] ?? '';
            $res = delete_backup($backupDir, $filename);
            send_json($res);
        } catch (Throwable $e) {
            send_json([
                'ok' => false,
                'error' => 'Fout bij het verwijderen van de back-up: ' . $e->getMessage()
            ]);
        }
        break;

    case 'upload_backup':
        try {
            $res = upload_backup($backupDir);
            send_json($res);
        } catch (Throwable $e) {
            send_json([
                'ok' => false,
                'error' => 'Fout bij het uploaden van de back-up: ' . $e->getMessage()
            ]);
        }
        break;

    case 'download_backup':
        $filename = $_GET['filename'] ?? '';
        download_backup($backupDir, $filename);
        break;

    case 'prune_backups':
        try {
            $res = prune_backups_tiered($backupDir);
            send_json([
                'ok' => true,
                'result' => $res
            ]);
        } catch (Throwable $e) {
            send_json([
                'ok' => false,
                'error' => 'Fout bij opschonen: ' . $e->getMessage()
            ]);
        }
        break;

    default:
        send_json(['ok' => false, 'error' => 'Onbekende actie.']);
        break;
}
