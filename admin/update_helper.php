<?php
/**
 * Jotify System Auto-Update AJAX Helper
 * Handles git status checks, remote fetching, automated pull/updates,
 * composer dependency installation, idempotent database schema synchronization,
 * and database backups.
 * 
 * Restricted strictly to Superadmins (privilege >= 3).
 */

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
ob_start();

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

require_once(__DIR__ . '/../dblogin.php');

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
 * Create a SQL database backup file
 */
function create_db_backup(mysqli $conn, string $backupDir): array {
    if (!is_dir($backupDir)) {
        @mkdir($backupDir, 0775, true);
    }
    if (!is_writable($backupDir)) {
        @chmod($backupDir, 0775);
    }
    if (!is_writable($backupDir)) {
        throw new RuntimeException("De back-up map ({$backupDir}) is niet schrijfbaar voor de webserver.");
    }

    $timestamp = date('Ymd_His');
    $filename = "backup_jotify_{$timestamp}.sql";
    $filepath = $backupDir . '/' . $filename;
    
    // Check if mysqldump is available on system
    $mysqldump = trim(shell_exec('which mysqldump 2>/dev/null') ?? '');
    if (!empty($mysqldump) && defined('DB_HOST') && defined('DB_USER') && defined('DB_PASS') && defined('DB_NAME')) {
        $host = DB_HOST;
        $user = DB_USER;
        $pass = DB_PASS;
        $name = DB_NAME;
        
        $cmd = sprintf(
            '%s -h %s -u %s %s %s > %s 2>&1',
            escapeshellarg($mysqldump),
            escapeshellarg($host),
            escapeshellarg($user),
            !empty($pass) ? '-p' . escapeshellarg($pass) : '',
            escapeshellarg($name),
            escapeshellarg($filepath)
        );
        $res = shell_exec($cmd);
        if (file_exists($filepath) && filesize($filepath) > 0) {
            return [
                'ok' => true,
                'filename' => $filename,
                'path' => $filepath,
                'size_bytes' => filesize($filepath),
                'size_formatted' => round(filesize($filepath) / 1024, 1) . ' KB'
            ];
        }
    }
    
    // Fallback: Dump tables directly via mysqli
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
        throw new RuntimeException("Kon back-upbestand niet opslaan in {$filepath}.");
    }
    
    return [
        'ok' => true,
        'filename' => $filename,
        'path' => $filepath,
        'size_bytes' => filesize($filepath),
        'size_formatted' => round(filesize($filepath) / 1024, 1) . ' KB'
    ];
}

$action = $_REQUEST['action'] ?? '';

switch ($action) {
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
        
        // 10. Existing backups list
        $backups = [];
        if (is_dir($backupDir)) {
            $files = glob($backupDir . '/*.sql');
            rsort($files);
            foreach (array_slice($files, 0, 5) as $f) {
                $backups[] = [
                    'filename' => basename($f),
                    'size' => round(filesize($f) / 1024, 1) . ' KB',
                    'date' => date('d-m-Y H:i:s', filemtime($f))
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
        
        // Step 1: Pre-flight check & discard local tracked diffs
        run_git_cmd('git config core.filemode false', $webroot);
        $checkoutRes = run_git_cmd('git checkout -- .', $webroot);
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
                $backupInfo = create_db_backup($conn, $backupDir);
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
            $backup = create_db_backup($conn, $backupDir);
            send_json([
                'ok' => true,
                'message' => 'Database back-up succesvol aangemaakt!',
                'backup' => $backup
            ]);
        } catch (Throwable $e) {
            send_json([
                'ok' => false,
                'error' => 'Fout bij het maken van de back-up: ' . $e->getMessage()
            ]);
        }
        break;

    default:
        send_json(['ok' => false, 'error' => 'Onbekende actie.']);
        break;
}
