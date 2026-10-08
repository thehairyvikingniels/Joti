<?php
// admin/cronjobs_helper.php
// AJAX endpoint returning cron job statuses, execution logs, master runner health, and handling cron toggles.

require_once(__DIR__ . '/../includes/auth.php');

if ($privilege < 2) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Geen toegang']);
    exit();
}

/**
 * Retrieve master cron runner execution heartbeat and status from Site_Instellingen.
 *
 * @param mysqli $conn
 * @return array<string, mixed>
 */
function getMasterCronStatus(mysqli $conn): array {
    $stmt = $conn->prepare("SELECT Instelling, Waarde FROM Site_Instellingen WHERE Instelling IN ('CRON_MASTER_LAST_RUN', 'CRON_MASTER_INFO')");
    $stmt->execute();
    $res = $stmt->get_result();
    $settings = [];
    while ($r = $res->fetch_assoc()) {
        $settings[$r['Instelling']] = $r['Waarde'];
    }
    $stmt->close();

    $lastRun = $settings['CRON_MASTER_LAST_RUN'] ?? null;
    $rawInfo = $settings['CRON_MASTER_INFO'] ?? null;
    $info = $rawInfo ? json_decode($rawInfo, true) : null;

    if ($lastRun) {
        $secondsAgo = time() - strtotime($lastRun);
        if ($secondsAgo <= 90) {
            $status = 'healthy';
        } elseif ($secondsAgo <= 300) {
            $status = 'warning';
        } else {
            $status = 'error';
        }
    } else {
        $secondsAgo = null;
        $status = 'error';
    }

    return [
        'last_run' => $lastRun,
        'last_run_formatted' => $lastRun ? date('d-m-Y H:i:s', strtotime($lastRun)) : 'Nooit',
        'seconds_ago' => $secondsAgo,
        'status' => $status,
        'is_healthy' => ($status === 'healthy'),
        'info' => $info
    ];
}

// API Endpoint: Trigger master cron/index.php immediately
if (isset($_GET['run_master']) || isset($_POST['run_master'])) {
    $scriptPath = realpath(__DIR__ . '/../cron/index.php');
    if ($scriptPath && file_exists($scriptPath)) {
        exec('php ' . escapeshellarg($scriptPath) . ' > /dev/null 2>&1');
    }

    recordAuditLog($conn, 'cron', 'manual_master_run', [
        'severity' => 'info',
        'target_type' => 'cron',
        'target_id' => 'master',
        'target_label' => 'Master Cron Runner',
        'details' => 'Handmatige uitvoering van master cron runner getriggerd'
    ]);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'master_cron' => getMasterCronStatus($conn)
    ]);
    exit();
}

// API Endpoint: Haal recente logs op voor een specifieke cronjob
if (isset($_GET['logs'])) {
    $cronName = trim((string)$_GET['logs']);
    $limit = (int)($_GET['limit'] ?? 15);
    if ($limit < 1 || $limit > 50) $limit = 15;

    $stmt = $conn->prepare('
        SELECT exec_time, exec_length, exec_stat, exec_output 
        FROM Cronlogs 
        WHERE name = ? 
        ORDER BY exec_time DESC 
        LIMIT ?
    ');
    $stmt->bind_param('si', $cronName, $limit);
    $stmt->execute();
    $res = $stmt->get_result();

    $logs = [];
    while ($row = $res->fetch_assoc()) {
        $logs[] = [
            'exec_time' => $row['exec_time'],
            'exec_time_formatted' => date('d-m-Y H:i:s', strtotime($row['exec_time'])),
            'exec_length_ms' => (int)$row['exec_length'],
            'exec_length_formatted' => number_format($row['exec_length'] / 1000, 2, ',', '.') . ' sec',
            'exec_stat' => (int)$row['exec_stat'],
            'exec_output' => $row['exec_output'] ?? ''
        ];
    }
    $stmt->close();

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'name' => $cronName,
        'count' => count($logs),
        'logs' => $logs
    ]);
    exit();
}

// API Endpoint: Haal alle cronjobs en master cron status op
if (isset($_GET['cronjobs'])) {
    $return = array();
    $sql = "SELECT cj.name, cj.enabled, cj.URL, cj.description, cj.interval, cl.exec_time, cl.exec_length, cl.exec_stat, cl.exec_output
            FROM Cronjobs cj 
            LEFT JOIN Cronlogs cl ON cj.name = cl.name
            WHERE cl.exec_time IS NULL
               OR cl.exec_time = (
                   SELECT MAX(cl2.exec_time)
                   FROM Cronlogs cl2
                   WHERE cl2.name = cj.name
               )
            ORDER BY cj.name ASC";
               
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $i = 0;
        while ($row = $result->fetch_assoc()) {
            $name = $row['name'];
            $interval = number_format($row['interval'] / 60, 1, ',') . ' min';
            
            // Fallback voor cronjobs die nog nooit gedraaid hebben
            $exec_time = $row['exec_time'] ? date('d/m H:i:s', strtotime($row['exec_time'])) : 'Nooit';
            $exec_length = $row['exec_length'] ? number_format($row['exec_length'] / 1000, 2, ',') . ' sec' : '0,00 sec';
            $exec_status = $row['exec_stat'];
            $exec_output = $row['exec_output'];

            if ($row['enabled'] == 1) {
                $enabled = '<i class="fas fa-toggle-on fa-fw"></i>';
            } else {
                $enabled = '<i class="fas fa-toggle-off fa-fw"></i>';
            }

            switch ($exec_status) {
                case 200: // succes
                    $stat_color = 'text-green-500';
                    break;
                case 429: // too many requests
                    $stat_color = 'text-yellow-500';
                    break;
                case 500: // script error
                    $stat_color = 'text-red-500';
                    break;
                default:
                    $stat_color = ($exec_status === null) ? 'text-gray-400' : 'text-red-500';
                    break;
            }

            $return[$i]['enabled'] = $enabled;
            $return[$i]['stat_color'] = $stat_color;
            $return[$i]['description'] = $row['description'];
            $return[$i]['url'] = $row['URL'];
            $return[$i]['name'] = $name;
            $return[$i]['interval'] = $interval;
            $return[$i]['exec_time'] = $exec_time;
            $return[$i]['exec_length'] = $exec_length;
            $return[$i]['exec_status'] = $exec_status;
            
            // Bereken de volgende executie
            if ($row['exec_time']) {
                $exec_next_val = $row['interval'] + strtotime($row['exec_time']) - time();
            } else {
                // Als hij nog nooit gedraaid heeft, mag hij direct
                $exec_next_val = 0; 
            }
            
            $return[$i]['raw_enabled'] = (int)$row['enabled'];
            $return[$i]['raw_seconds'] = (int)$exec_next_val;
            $return[$i]['exec_next'] = $exec_next_val;

            if ($row['enabled'] == 1) {
                if ($return[$i]['exec_next'] <= 0) {
                    $return[$i]['exec_next'] = 'executing...';
                } else {
                    $return[$i]['exec_next'] .= ' sec';
                }
            } else {
                $return[$i]['exec_next'] = ' - disabled - ';
            }
            
            $i++;
        }
    }
    $stmt->close();
    
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'cronjobs' => $return,
        'master_cron' => getMasterCronStatus($conn)
    ]);
    exit();
}

// API Endpoint: Toggle de status van een cronjob
if (isset($_GET['toggleCron'])) {
    $cron_name = trim((string)$_GET['toggleCron']);
    
    // Haal eerst de huidige status op via een prepared statement
    $stmt_check = $conn->prepare('SELECT enabled FROM Cronjobs WHERE name = ?');
    $stmt_check->bind_param('s', $cron_name);
    $stmt_check->execute();
    $result = $stmt_check->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        // Wissel de status (als het 1 is wordt het 0, anders 1)
        $new_enabled = ($row['enabled'] == 1) ? 0 : 1;
        $stmt_check->close();

        // Update de database
        $stmt_upd = $conn->prepare('UPDATE Cronjobs SET enabled = ? WHERE name = ?');
        $stmt_upd->bind_param('is', $new_enabled, $cron_name);

        if ($stmt_upd->execute()) {
            recordAuditLog($conn, 'cron', 'cron_toggle', [
                'severity' => 'info',
                'target_type' => 'cron',
                'target_id' => $cron_name,
                'target_label' => ucfirst($cron_name),
                'details' => "Cronjob {$cron_name} " . ($new_enabled === 1 ? 'ingeschakeld' : 'uitgeschakeld'),
                'metadata' => [
                    'cron_name' => $cron_name,
                    'enabled' => $new_enabled
                ]
            ]);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => true, 'enabled' => $new_enabled]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $stmt_upd->error]);
        }
        $stmt_upd->close();
    } else {
        $stmt_check->close();
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Cronjob not found']);
    }
    exit();
}