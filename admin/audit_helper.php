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
        $res = $conn->query("SELECT id, gebr, vn, an FROM Gebruikers ORDER BY vn ASC, an ASC, gebr ASC");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $fullName = trim(($row['vn'] ?? '') . ' ' . ($row['an'] ?? ''));
                if (empty($fullName)) {
                    $fullName = $row['gebr'];
                }
                $users[] = [
                    'id' => (int)$row['id'],
                    'username' => $row['gebr'],
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
    $querySql = "SELECT id, severity, category, action, actor_user_id, actor_username, subject_user_id, subject_username, target_type, target_id, target_label, details, metadata, ip_address, created_at 
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

        $logs[] = [
            'id' => $rowId,
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
            'time_formatted' => date('d-m-Y H:i:s', strtotime($row['created_at']))
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
