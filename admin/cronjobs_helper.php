<?php
// AJAX endpoint returning cron job statuses and execution logs in JSON format, and handling cron job toggle requests.
require_once(__DIR__ . '/../includes/auth.php');
// API Endpoint: Haal alle cronjobs op
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
               )";
               
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $i = 0;
        while ($row = $result->fetch_assoc()) {
            $name = ucfirst($row['name']);
            $interval = number_format($row['interval'] / 60, 1, ',') . " min";
            
            // Fallback voor cronjobs die nog nooit gedraaid hebben
            $exec_time = $row['exec_time'] ? date("d/m H:i:s", strtotime($row['exec_time'])) : "Nooit";
            $exec_length = $row['exec_length'] ? number_format($row['exec_length'] / 1000, 2, ',') . " sec" : "0,00 sec";
            $exec_status = $row['exec_stat'];
            $exec_output = $row['exec_output'];

            if ($row['enabled'] == 1) {
                $enabled = '<i class="fas fa-toggle-on fa-fw"></i>';
            } else {
                $enabled = '<i class="fas fa-toggle-off fa-fw"></i>';
            }

            switch ($exec_status) {
                case 200: // succes
                    $stat_color = "w3-text-green";
                    break;
                case 429: // too many requests
                    $stat_color = "w3-text-yellow";
                    break;
                case 500: // script error
                    $stat_color = "w3-text-red";
                    break;
                default:
                    $stat_color = ($exec_status === null) ? "w3-text-grey" : "w3-text-red";
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
                    $return[$i]['exec_next'] = "executing...";
                } else {
                    $return[$i]['exec_next'] .= " sec";
                }
            } else {
                $return[$i]['exec_next'] = " - disabled - ";
            }
            
            $i++;
        }
    }
    $stmt->close();
    
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($return, true);
    exit();
}

// API Endpoint: Toggle de status van een cronjob
if (isset($_GET['toggleCron'])) {
    $cron_name = $_GET['toggleCron'];
    
    // Haal eerst de huidige status op via een prepared statement
    $stmt_check = $conn->prepare("SELECT enabled FROM Cronjobs WHERE name = ?");
    $stmt_check->bind_param("s", $cron_name);
    $stmt_check->execute();
    $result = $stmt_check->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        // Wissel de status (als het 1 is wordt het 0, anders 1)
        $new_enabled = ($row['enabled'] == 1) ? 0 : 1;
        $stmt_check->close();

        // Update de database
        $stmt_upd = $conn->prepare("UPDATE Cronjobs SET enabled = ? WHERE name = ?");
        $stmt_upd->bind_param("is", $new_enabled, $cron_name);

        if ($stmt_upd->execute()) {
            recordAuditLog($conn, 'cron', 'cron_toggle', [
                'severity' => 'info',
                'target_type' => 'cron',
                'target_id' => $cron_name,
                'target_label' => ucfirst($cron_name),
                'details' => "Cronjob {$cron_name} " . ($new_enabled === 1 ? "ingeschakeld" : "uitgeschakeld"),
                'metadata' => [
                    'cron_name' => $cron_name,
                    'enabled' => $new_enabled
                ]
            ]);
            echo "Record updated successfully";
        } else {
            echo "Error: " . $stmt_upd->error;
        }
        $stmt_upd->close();
    } else {
        $stmt_check->close();
        echo "Cronjob not found";
    }
    exit();
}