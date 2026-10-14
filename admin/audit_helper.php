<?php
declare(strict_types=1);

/**
 * admin/audit_helper.php
 *
 * Backend AJAX controller for Admin Audit & Telemetry Hub.
 * Handles log fetching with parameterized filters, pagination, live polling,
 * 24-hour summary stats, user listing, and CSV export.
 */

require_once(__DIR__ . '/../includes/auth.php');

// Access Control: Minimum level 2 (Admin) required
if (($privilege ?? 0) < 2) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Geen toegang']);
    exit();
}

$action = $_GET['action'] ?? ($_POST['action'] ?? 'get_logs');

// 1. STATS: 24h Telemetry Metrics
if ($action === 'get_stats') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $stats = [
            'total_24h' => 0,
            'assignments_24h' => 0,
            'security_24h' => 0,
            'active_users_24h' => 0,
            'retention' => [
                'info' => '3 dagen',
                'warning' => '14 dagen',
                'critical' => '30 dagen'
            ]
        ];

        // Total events in last 24h
        $res = $conn->query("SELECT COUNT(*) as cnt FROM Audit_Logs WHERE created_at >= NOW() - INTERVAL 24 HOUR");
        if ($res && $row = $res->fetch_assoc()) {
            $stats['total_24h'] = (int)$row['cnt'];
        }

        // Assignments & Whiteboard in last 24h
        $res = $conn->query("SELECT COUNT(*) as cnt FROM Audit_Logs WHERE category IN ('assignment', 'whiteboard') AND created_at >= NOW() - INTERVAL 24 HOUR");
        if ($res && $row = $res->fetch_assoc()) {
            $stats['assignments_24h'] = (int)$row['cnt'];
        }

        // Security / Warning alerts in last 24h
        $res = $conn->query("SELECT COUNT(*) as cnt FROM Audit_Logs WHERE severity IN ('warning', 'error', 'security') AND created_at >= NOW() - INTERVAL 24 HOUR");
        if ($res && $row = $res->fetch_assoc()) {
            $stats['security_24h'] = (int)$row['cnt'];
        }

        // Distinct active users in last 24h
        $res = $conn->query("SELECT COUNT(DISTINCT actor_user_id) as cnt FROM Audit_Logs WHERE actor_user_id IS NOT NULL AND created_at >= NOW() - INTERVAL 24 HOUR");
        if ($res && $row = $res->fetch_assoc()) {
            $stats['active_users_24h'] = (int)$row['cnt'];
        }

        echo json_encode(['success' => true, 'stats' => $stats]);
    } catch (\Throwable $e) {
        error_log("audit_helper get_stats error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Fout bij ophalen statistieken']);
    }
    exit();
}

// 2. USERS: User list for filter dropdown
if ($action === 'get_users') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $users = [];
        $res = $conn->query("SELECT id, gebruikersnaam, voornaam, achternaam FROM Gebruikers ORDER BY voornaam ASC, achternaam ASC, gebruikersnaam ASC");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $fullName = trim(($row['voornaam'] ?? '') . ' ' . ($row['achternaam'] ?? ''));
                if (empty($fullName)) {
                    $fullName = $row['gebruikersnaam'];
                }
                $users[] = [
                    'id' => (int)$row['id'],
                    'username' => $row['gebruikersnaam'],
                    'display_name' => $fullName
                ];
            }
        }
        echo json_encode(['success' => true, 'users' => $users]);
    } catch (\Throwable $e) {
        error_log("audit_helper get_users error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Fout bij ophalen gebruikers']);
    }
    exit();
}

// Helper: Build WHERE clause and bound parameters for filters
function buildAuditFilters(array $input): array {
    $conditions = [];
    $params = [];
    $types = '';

    // Severity
    $severity = trim($input['severity'] ?? '');
    if (!empty($severity) && in_array($severity, ['info', 'warning', 'error', 'security'], true)) {
        $conditions[] = "severity = ?";
        $params[] = $severity;
        $types .= 's';
    }

    // Category
    $category = trim($input['category'] ?? '');
    if (!empty($category) && $category !== 'all') {
        $conditions[] = "category = ?";
        $params[] = $category;
        $types .= 's';
    }

    // Actor User ID
    $actorId = isset($input['actor_id']) && $input['actor_id'] !== '' ? (int)$input['actor_id'] : null;
    if ($actorId !== null && $actorId > 0) {
        $conditions[] = "actor_user_id = ?";
        $params[] = $actorId;
        $types .= 'i';
    }

    // Subject User ID
    $subjectId = isset($input['subject_id']) && $input['subject_id'] !== '' ? (int)$input['subject_id'] : null;
    if ($subjectId !== null && $subjectId > 0) {
        $conditions[] = "subject_user_id = ?";
        $params[] = $subjectId;
        $types .= 'i';
    }

    // Session ID
    $sessionId = isset($input['session_id']) && $input['session_id'] !== '' ? (int)$input['session_id'] : null;
    if ($sessionId !== null && $sessionId > 0) {
        $conditions[] = "session_id = ?";
        $params[] = $sessionId;
        $types .= 'i';
    }

    // Search query across details, usernames, target_label, ip
    $search = trim($input['search'] ?? '');
    if (!empty($search)) {
        $like = '%' . $search . '%';
        $conditions[] = "(details LIKE ? OR actor_username LIKE ? OR subject_username LIKE ? OR target_label LIKE ? OR ip_address LIKE ?)";
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $types .= 'sssss';
    }

    // Since ID (for live polling)
    $sinceId = isset($input['since_id']) && $input['since_id'] !== '' ? (int)$input['since_id'] : null;
    if ($sinceId !== null && $sinceId > 0) {
        $conditions[] = "id > ?";
        $params[] = $sinceId;
        $types .= 'i';
    }

    $where = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
    return [$where, $params, $types];
}

// 3. EXPORT CSV
if ($action === 'export_csv') {
    [$where, $params, $types] = buildAuditFilters($_GET);

    $sql = "SELECT id, created_at, severity, category, action, actor_username, subject_username, target_type, target_label, details, ip_address, metadata 
            FROM Audit_Logs {$where} 
            ORDER BY id DESC LIMIT 5000";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $filename = 'jotify_audit_logs_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Output UTF-8 BOM for Microsoft Excel compatibility
    echo "\xEF\xBB\xBF";

    $fp = fopen('php://output', 'w');
    fputcsv($fp, ['ID', 'Tijdstip', 'Niveau', 'Categorie', 'Actie', 'Acteur', 'Betrokkene', 'Doel Type', 'Doel Label', 'Details', 'IP Adres', 'Metadata']);

    while ($row = $result->fetch_assoc()) {
        fputcsv($fp, [
            $row['id'],
            $row['created_at'],
            $row['severity'],
            $row['category'],
            $row['action'],
            $row['actor_username'] ?? '',
            $row['subject_username'] ?? '',
            $row['target_type'] ?? '',
            $row['target_label'] ?? '',
            $row['details'],
            $row['ip_address'] ?? '',
            $row['metadata'] ?? ''
        ]);
    }

    fclose($fp);
    $stmt->close();
    exit();
}

// 4. GET LOGS (Paginated or Live Polling)
if ($action === 'get_logs' || empty($action)) {
    header('Content-Type: application/json; charset=utf-8');

    try {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(200, max(10, (int)($_GET['limit'] ?? 50)));
        $offset = ($page - 1) * $limit;

        [$where, $params, $types] = buildAuditFilters($_GET);

        // Count total rows matching filter
        $countSql = "SELECT COUNT(*) as total FROM Audit_Logs {$where}";
        $countStmt = $conn->prepare($countSql);
        if (!empty($params)) {
            $countStmt->bind_param($types, ...$params);
        }
        $countStmt->execute();
        $totalRows = (int)($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
        $countStmt->close();

        // Fetch matching rows
        $querySql = "SELECT id, session_id, severity, category, action, actor_user_id, actor_username, subject_user_id, subject_username, target_type, target_id, target_label, details, metadata, ip_address, created_at 
                     FROM Audit_Logs {$where} 
                     ORDER BY id DESC 
                     LIMIT ? OFFSET ?";

        $queryParams = $params;
        $queryTypes = $types . 'ii';
        $queryParams[] = $limit;
        $queryParams[] = $offset;

        $stmt = $conn->prepare($querySql);
        $stmt->bind_param($queryTypes, ...$queryParams);
        $stmt->execute();
        $result = $stmt->get_result();

        $logs = [];
        $maxId = 0;
        while ($row = $result->fetch_assoc()) {
            $rowId = (int)$row['id'];
            if ($rowId > $maxId) {
                $maxId = $rowId;
            }

            // Parse JSON metadata safely
            $meta = null;
            if (!empty($row['metadata'])) {
                $meta = json_decode($row['metadata'], true);
            }

            $createdDt = new DateTime($row['created_at']);

            $logs[] = [
                'id' => $rowId,
                'session_id' => $row['session_id'] !== null ? (int)$row['session_id'] : null,
                'severity' => $row['severity'],
                'category' => $row['category'],
                'action' => $row['action'],
                'actor_user_id' => $row['actor_user_id'] !== null ? (int)$row['actor_user_id'] : null,
                'actor_username' => $row['actor_username'],
                'subject_user_id' => $row['subject_user_id'] !== null ? (int)$row['subject_user_id'] : null,
                'subject_username' => $row['subject_username'],
                'target_type' => $row['target_type'],
                'target_id' => $row['target_id'],
                'target_label' => $row['target_label'],
                'details' => $row['details'],
                'metadata' => $meta,
                'ip_address' => $row['ip_address'],
                'created_at' => $row['created_at'],
                'time_formatted' => $createdDt->format('d-m-Y H:i:s.v'),
                'time_short' => $createdDt->format('H:i:s.v')
            ];
        }
        $stmt->close();

        $totalPages = (int)ceil($totalRows / $limit);
        if ($totalPages < 1) $totalPages = 1;

        echo json_encode([
            'success' => true,
            'logs' => $logs,
            'total' => $totalRows,
            'page' => $page,
            'limit' => $limit,
            'pages' => $totalPages,
            'max_id' => $maxId
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } catch (\Throwable $e) {
        error_log("audit_helper get_logs error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Fout bij ophalen audit logs: ' . $e->getMessage()]);
    }
    exit();
}

// Helper for readable duration format
if (!function_exists('formatDurationSeconds')) {
    function formatDurationSeconds(int $seconds): string {
        if ($seconds < 60) {
            return $seconds . 's';
        }
        $minutes = (int)floor($seconds / 60);
        $remSeconds = $seconds % 60;
        if ($minutes < 60) {
            return $remSeconds > 0 ? "{$minutes}m {$remSeconds}s" : "{$minutes}m";
        }
        $hours = (int)floor($minutes / 60);
        $remMinutes = $minutes % 60;
        return $remMinutes > 0 ? "{$hours}u {$remMinutes}m" : "{$hours}u";
    }
}

// 4. SESSIONS: Fetch tracked user sessions with metadata and event counts
if ($action === 'get_sessions') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        // Opportunistic cleanup of stale sessions (> 30 mins)
        $conn->query("UPDATE User_Sessions SET status = 'expired', ended_at = last_activity WHERE status = 'active' AND last_activity < (NOW() - INTERVAL 30 MINUTE)");

        $page = max(1, (int)($_GET['page'] ?? ($_POST['page'] ?? 1)));
        $limit = max(1, min(100, (int)($_GET['limit'] ?? ($_POST['limit'] ?? 25))));
        $offset = ($page - 1) * $limit;

        $conditions = [];
        $params = [];
        $types = '';

        // Search filter (username, first name, last name, IP address, platform)
        $search = trim((string)($_GET['search'] ?? ($_POST['search'] ?? '')));
        if (!empty($search)) {
            $like = '%' . $search . '%';
            $conditions[] = "(g.gebruikersnaam LIKE ? OR g.voornaam LIKE ? OR g.achternaam LIKE ? OR s.ip_address LIKE ? OR s.platform LIKE ?)";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $types .= 'sssss';
        }

        // Auth Method filter
        $authMethod = trim((string)($_GET['auth_method'] ?? ($_POST['auth_method'] ?? '')));
        if (!empty($authMethod) && $authMethod !== 'all' && in_array($authMethod, ['password', 'remember_me', 'php_session'], true)) {
            $conditions[] = "s.auth_method = ?";
            $params[] = $authMethod;
            $types .= 's';
        }

        // Status filter
        $status = trim((string)($_GET['status'] ?? ($_POST['status'] ?? '')));
        if (!empty($status) && $status !== 'all' && in_array($status, ['active', 'logged_out', 'expired'], true)) {
            $conditions[] = "s.status = ?";
            $params[] = $status;
            $types .= 's';
        }

        // User filter
        $userId = isset($_GET['user_id']) && $_GET['user_id'] !== '' ? (int)$_GET['user_id'] : (isset($_POST['user_id']) && $_POST['user_id'] !== '' ? (int)$_POST['user_id'] : null);
        if ($userId !== null && $userId > 0) {
            $conditions[] = "s.user_id = ?";
            $params[] = $userId;
            $types .= 'i';
        }

        // Specific Session ID filter
        $sessionId = isset($_GET['session_id']) && $_GET['session_id'] !== '' ? (int)$_GET['session_id'] : (isset($_POST['session_id']) && $_POST['session_id'] !== '' ? (int)$_POST['session_id'] : null);
        if ($sessionId !== null && $sessionId > 0) {
            $conditions[] = "s.id = ?";
            $params[] = $sessionId;
            $types .= 'i';
        }

        $where = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

        // Count total matching sessions
        $countSql = "SELECT COUNT(*) as total FROM User_Sessions s JOIN Gebruikers g ON s.user_id = g.id {$where}";
        $countStmt = $conn->prepare($countSql);
        if (!empty($params)) {
            $countStmt->bind_param($types, ...$params);
        }
        $countStmt->execute();
        $totalRows = (int)($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
        $countStmt->close();

        // Count currently active sessions
        $activeRes = $conn->query("SELECT COUNT(*) as active_cnt FROM User_Sessions WHERE status = 'active'");
        $activeCount = (int)($activeRes ? $activeRes->fetch_assoc()['active_cnt'] ?? 0 : 0);

        // Fetch paginated sessions
        $querySql = "SELECT s.*, 
                            g.gebruikersnaam, g.voornaam, g.achternaam,
                            (SELECT COUNT(*) FROM Session_Events WHERE session_id = s.id) AS event_count,
                            (SELECT COUNT(*) FROM Audit_Logs WHERE session_id = s.id) AS audit_count
                     FROM User_Sessions s
                     JOIN Gebruikers g ON s.user_id = g.id
                     {$where}
                     ORDER BY s.started_at DESC, s.id DESC
                     LIMIT ? OFFSET ?";

        $queryParams = $params;
        $queryTypes = $types . 'ii';
        $queryParams[] = $limit;
        $queryParams[] = $offset;

        $stmt = $conn->prepare($querySql);
        $stmt->bind_param($queryTypes, ...$queryParams);
        $stmt->execute();
        $res = $stmt->get_result();

        $sessions = [];
        $nowTs = time();
        while ($row = $res->fetch_assoc()) {
            $startTs = parseToTimestamp($row['started_at']) ?? strtotime($row['started_at']);
            $lastTs = parseToTimestamp($row['last_activity']) ?? strtotime($row['last_activity']);
            $endTs = !empty($row['ended_at']) ? (parseToTimestamp($row['ended_at']) ?? strtotime($row['ended_at'])) : null;

            if ($row['status'] === 'active') {
                $durationSec = max(0, $nowTs - $startTs);
            } elseif ($endTs !== null) {
                $durationSec = max(0, $endTs - $startTs);
            } else {
                $durationSec = max(0, $lastTs - $startTs);
            }

            $userDisplayName = trim(($row['voornaam'] ?? '') . ' ' . ($row['achternaam'] ?? ''));
            if (empty($userDisplayName)) {
                $userDisplayName = $row['gebruikersnaam'];
            }

            $startDt = new DateTime($row['started_at']);
            $lastDt = new DateTime($row['last_activity']);
            $endDt = !empty($row['ended_at']) ? new DateTime($row['ended_at']) : null;

            $sessions[] = [
                'id' => (int)$row['id'],
                'user_id' => (int)$row['user_id'],
                'username' => $row['gebruikersnaam'],
                'user_display_name' => $userDisplayName,
                'php_session_id' => $row['php_session_id'] ? substr($row['php_session_id'], 0, 10) . '...' : null,
                'auth_method' => $row['auth_method'],
                'ip_address' => $row['ip_address'],
                'forwarded_for' => $row['forwarded_for'],
                'user_agent' => $row['user_agent'],
                'viewport_width' => $row['viewport_width'] !== null ? (int)$row['viewport_width'] : null,
                'viewport_height' => $row['viewport_height'] !== null ? (int)$row['viewport_height'] : null,
                'screen_width' => $row['screen_width'] !== null ? (int)$row['screen_width'] : null,
                'screen_height' => $row['screen_height'] !== null ? (int)$row['screen_height'] : null,
                'pixel_ratio' => $row['pixel_ratio'] !== null ? (float)$row['pixel_ratio'] : null,
                'network_type' => $row['network_type'],
                'network_downlink' => $row['network_downlink'] !== null ? (float)$row['network_downlink'] : null,
                'network_rtt' => $row['network_rtt'] !== null ? (int)$row['network_rtt'] : null,
                'platform' => $row['platform'],
                'language' => $row['language'],
                'touch_points' => $row['touch_points'] !== null ? (int)$row['touch_points'] : null,
                'started_at' => $row['started_at'],
                'started_at_formatted' => $startDt->format('d-m-Y H:i:s.v'),
                'started_ago' => time2str($row['started_at']),
                'last_activity' => $row['last_activity'],
                'last_activity_formatted' => $lastDt->format('d-m-Y H:i:s.v'),
                'last_activity_ago' => time2str($row['last_activity']),
                'ended_at' => $row['ended_at'],
                'ended_at_formatted' => $endDt ? $endDt->format('d-m-Y H:i:s.v') : null,
                'status' => $row['status'],
                'duration_seconds' => $durationSec,
                'duration_formatted' => formatDurationSeconds($durationSec),
                'event_count' => (int)$row['event_count'],
                'audit_count' => (int)$row['audit_count']
            ];
        }
        $stmt->close();

        $totalPages = (int)ceil($totalRows / $limit);
        if ($totalPages < 1) $totalPages = 1;

        echo json_encode([
            'success' => true,
            'sessions' => $sessions,
            'total' => $totalRows,
            'active_count' => $activeCount,
            'page' => $page,
            'limit' => $limit,
            'pages' => $totalPages
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } catch (\Throwable $e) {
        error_log("audit_helper get_sessions error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Fout bij ophalen sessies: ' . $e->getMessage()]);
    }
    exit();
}

// 5. SESSION TIMELINE: Combined chronological timeline of Session_Events and linked Audit_Logs
if ($action === 'get_session_timeline') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $sessionId = (int)($_GET['session_id'] ?? ($_POST['session_id'] ?? 0));
        if ($sessionId <= 0) {
            echo json_encode(['success' => false, 'error' => 'Geen geldig session_id opgegeven']);
            exit();
        }

        // Fetch session info
        $stmtSess = $conn->prepare(
            "SELECT s.*, g.gebruikersnaam, g.voornaam, g.achternaam 
             FROM User_Sessions s 
             JOIN Gebruikers g ON s.user_id = g.id 
             WHERE s.id = ? LIMIT 1"
        );
        $stmtSess->bind_param("i", $sessionId);
        $stmtSess->execute();
        $sessRow = $stmtSess->get_result()->fetch_assoc();
        $stmtSess->close();

        if (!$sessRow) {
            echo json_encode(['success' => false, 'error' => 'Sessie niet gevonden']);
            exit();
        }

        $userDisplayName = trim(($sessRow['voornaam'] ?? '') . ' ' . ($sessRow['achternaam'] ?? ''));
        if (empty($userDisplayName)) {
            $userDisplayName = $sessRow['gebruikersnaam'];
        }

        $startTs = parseToTimestamp($sessRow['started_at']) ?? strtotime($sessRow['started_at']);
        $lastTs = parseToTimestamp($sessRow['last_activity']) ?? strtotime($sessRow['last_activity']);
        $endTs = !empty($sessRow['ended_at']) ? (parseToTimestamp($sessRow['ended_at']) ?? strtotime($sessRow['ended_at'])) : null;

        if ($sessRow['status'] === 'active') {
            $durationSec = max(0, time() - $startTs);
        } elseif ($endTs !== null) {
            $durationSec = max(0, $endTs - $startTs);
        } else {
            $durationSec = max(0, $lastTs - $startTs);
        }

            $startDt = new DateTime($sessRow['started_at']);
            $lastDt = new DateTime($sessRow['last_activity']);
            $endDt = !empty($sessRow['ended_at']) ? new DateTime($sessRow['ended_at']) : null;

            $sessionData = [
                'id' => (int)$sessRow['id'],
                'user_id' => (int)$sessRow['user_id'],
                'username' => $sessRow['gebruikersnaam'],
                'user_display_name' => $userDisplayName,
                'auth_method' => $sessRow['auth_method'],
                'ip_address' => $sessRow['ip_address'],
                'forwarded_for' => $sessRow['forwarded_for'],
                'user_agent' => $sessRow['user_agent'],
                'viewport_width' => $sessRow['viewport_width'],
                'viewport_height' => $sessRow['viewport_height'],
                'screen_width' => $sessRow['screen_width'],
                'screen_height' => $sessRow['screen_height'],
                'pixel_ratio' => $sessRow['pixel_ratio'],
                'network_type' => $sessRow['network_type'],
                'network_downlink' => $sessRow['network_downlink'],
                'network_rtt' => $sessRow['network_rtt'],
                'platform' => $sessRow['platform'],
                'language' => $sessRow['language'],
                'touch_points' => $sessRow['touch_points'],
                'started_at' => $sessRow['started_at'],
                'started_at_formatted' => $startDt->format('d-m-Y H:i:s.v'),
                'started_ago' => time2str($sessRow['started_at']),
                'last_activity' => $sessRow['last_activity'],
                'last_activity_formatted' => $lastDt->format('d-m-Y H:i:s.v'),
                'last_activity_ago' => time2str($sessRow['last_activity']),
                'ended_at' => $sessRow['ended_at'],
                'ended_at_formatted' => $endDt ? $endDt->format('d-m-Y H:i:s.v') : null,
                'status' => $sessRow['status'],
                'duration_formatted' => formatDurationSeconds($durationSec)
            ];

            $timeline = [];

            // 1. Fetch Session_Events (DESC)
            $stmtEv = $conn->prepare(
                "SELECT id, session_id, event_type, page_url, referrer, target_tag, target_id, target_label, metadata, created_at 
                 FROM Session_Events 
                 WHERE session_id = ? 
                 ORDER BY created_at DESC, id DESC"
            );
            $stmtEv->bind_param("i", $sessionId);
            $stmtEv->execute();
            $evRes = $stmtEv->get_result();
            while ($ev = $evRes->fetch_assoc()) {
                $meta = !empty($ev['metadata']) ? json_decode($ev['metadata'], true) : null;
                $evDt = new DateTime($ev['created_at']);
                $timeline[] = [
                    'type' => 'event',
                    'id' => (int)$ev['id'],
                    'event_type' => $ev['event_type'],
                    'page_url' => $ev['page_url'],
                    'referrer' => $ev['referrer'],
                    'target_tag' => $ev['target_tag'],
                    'target_id' => $ev['target_id'],
                    'target_label' => $ev['target_label'],
                    'metadata' => $meta,
                    'created_at' => $ev['created_at'],
                    'time_formatted' => $evDt->format('H:i:s.v'),
                    'date_formatted' => $evDt->format('d-m-Y H:i:s.v'),
                    'time_ago' => time2str($ev['created_at'])
                ];
            }
            $stmtEv->close();

            // 2. Fetch linked Audit_Logs (DESC)
            $stmtAudit = $conn->prepare(
                "SELECT id, session_id, created_at, severity, category, action, actor_user_id, actor_username, subject_user_id, subject_username, target_type, target_id, target_label, details, metadata, ip_address 
                 FROM Audit_Logs 
                 WHERE session_id = ? 
                 ORDER BY created_at DESC, id DESC"
            );
            $stmtAudit->bind_param("i", $sessionId);
            $stmtAudit->execute();
            $auditRes = $stmtAudit->get_result();
            while ($al = $auditRes->fetch_assoc()) {
                $meta = !empty($al['metadata']) ? json_decode($al['metadata'], true) : null;
                $alDt = new DateTime($al['created_at']);
                $timeline[] = [
                    'type' => 'audit',
                    'id' => (int)$al['id'],
                    'severity' => $al['severity'],
                    'category' => $al['category'],
                    'action' => $al['action'],
                    'actor_username' => $al['actor_username'],
                    'subject_username' => $al['subject_username'],
                    'target_type' => $al['target_type'],
                    'target_label' => $al['target_label'],
                    'details' => $al['details'],
                    'metadata' => $meta,
                    'ip_address' => $al['ip_address'],
                    'created_at' => $al['created_at'],
                    'time_formatted' => $alDt->format('H:i:s.v'),
                    'date_formatted' => $alDt->format('d-m-Y H:i:s.v'),
                    'time_ago' => time2str($al['created_at'])
                ];
            }
            $stmtAudit->close();

            // 3. Sort merged timeline chronologically DESC (newest at top)
            usort($timeline, function($a, $b) {
                $cmp = strcmp($b['created_at'], $a['created_at']);
                if ($cmp !== 0) {
                    return $cmp;
                }
                return $b['id'] <=> $a['id'];
            });

        echo json_encode([
            'success' => true,
            'session' => $sessionData,
            'timeline' => $timeline,
            'total_items' => count($timeline)
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } catch (\Throwable $e) {
        error_log("audit_helper get_session_timeline error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Fout bij ophalen sessie tijdlijn: ' . $e->getMessage()]);
    }
    exit();
}

