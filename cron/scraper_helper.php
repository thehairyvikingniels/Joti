<?php
define("NAME", "jotiPortal"); 
define("START_TIME", microtime(true));
date_default_timezone_set('Europe/Amsterdam');
$output = "";

require_once("../dblogin.php");
require_once("../functies.php");

$datumtijd = date('Y-m-d H:i:s');
$status_code = 200;

// Fetch site settings
$joti_user = "";
$joti_pass = "";
$stmt_cred = $conn->prepare("SELECT Waarde FROM Site_Instellingen WHERE Instelling = 'JOTIHUNT_CREDENTIALS'");
if ($stmt_cred) {
    $stmt_cred->execute();
    $result_cred = $stmt_cred->get_result();
    if ($row = $result_cred->fetch_assoc()) {
        $creds = json_decode($row['Waarde'], true);
        if (isset($creds['username']) && isset($creds['password'])) {
            $joti_user = $creds['username'];
            $joti_pass = $creds['password'];
        }
    }
    $stmt_cred->close();
}

$json_start = false;
$json_end = false;

// Execute scraper.py with credentials
if (empty($joti_user) || empty($joti_pass)) {
    $output .= "Error: Kon inloggegevens niet ophalen uit Site_Instellingen (JOTIHUNT_CREDENTIALS).";
    $status_code = 500;
} else {
    // Geef de inloggegevens veilig mee als argumenten
    $command = "python3 /var/www/Joti/cron/scraper.py " . escapeshellarg($joti_user) . " " . escapeshellarg($joti_pass) . " 2>&1";
    $script_output = shell_exec($command);
    $output .= $script_output;

    // Isoleer de JSON uit de Python text output
    $json_start = strpos($script_output, '{');
    $json_end = strrpos($script_output, '}');

    if (stripos($script_output, 'Error:') !== false || stripos($script_output, 'Exception') !== false || stripos($script_output, 'Traceback') !== false) {
        $status_code = 500;
    }
}

// Parse the JSON and update the database
if ($json_start !== false && $json_end !== false && $status_code === 200) {
    $json_string = substr($script_output, $json_start, $json_end - $json_start + 1);
    $data = json_decode($json_string, true);

    if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
        
        $group_id = 0;
        $stmt_settings = $conn->prepare("SELECT Waarde FROM Site_Instellingen WHERE Instelling = 'GROUP_ID'");
        if ($stmt_settings) {
            $stmt_settings->execute();
            $result_settings = $stmt_settings->get_result();
            if ($row = $result_settings->fetch_assoc()) {
                $group_id = intval($row['Waarde']);
            }
            $stmt_settings->close();
        }

        // UPDATE PUNTEN
        if (isset($data['punten']['categorieen'])) {
            $cat = $data['punten']['categorieen'];
            $h = $cat['Hunts'] ?? 0;
            $th = $cat['Tegenhunts'] ?? 0;
            $op = $cat['Opdrachten'] ?? 0;
            $fo = $cat['Foto opdrachten'] ?? 0;
            $hi = $cat['Hints'] ?? 0;
            $sp = $cat['Strafpunten'] ?? 0;

            $stmt_punten = $conn->prepare("
                INSERT INTO Punten (groep_id, hunts, tegenhunts, opdrachten, foto_opdrachten, hints, strafpunten, last_updated) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW()) 
                ON DUPLICATE KEY UPDATE 
                    hunts = VALUES(hunts), tegenhunts = VALUES(tegenhunts), opdrachten = VALUES(opdrachten), 
                    foto_opdrachten = VALUES(foto_opdrachten), hints = VALUES(hints), strafpunten = VALUES(strafpunten), 
                    last_updated = NOW()
            ");
            if ($stmt_punten) {
                $stmt_punten->bind_param("iiiiiii", $group_id, $h, $th, $op, $fo, $hi, $sp);
                $stmt_punten->execute();
                $stmt_punten->close();
            }
        }

        // UPDATE OPDRACHTEN
        if (isset($data['opdrachten']) && is_array($data['opdrachten'])) {
            $stmt_opd = $conn->prepare("UPDATE Opdrachten SET ingestuurd_op = NOW() WHERE id = ? AND ingestuurd_op IS NULL");
            if ($stmt_opd) {
                foreach ($data['opdrachten'] as $opd) {
                    if (isset($opd['id']) && $opd['id'] !== null) {
                        $stmt_opd->bind_param("i", $opd['id']);
                        $stmt_opd->execute();
                    }
                }
                $stmt_opd->close();
            }
        }

        // UPDATE OR INSERT HUNTS
        if (isset($data['hunts']) && is_array($data['hunts'])) {
            $stmt_check = $conn->prepare("SELECT id, status FROM Voslocaties WHERE code = ? AND type = 'Hunt'");
            $stmt_insert = $conn->prepare("INSERT INTO Voslocaties (ingestuurd_op, type, deelgebied, ingeleverd, coordinaat_x, coordinaat_y, code, toegekende_punten, status) VALUES (?, 'Hunt', ?, 1, 0.000000, 0.000000, ?, ?, ?)");
            $stmt_update = $conn->prepare("UPDATE Voslocaties SET ingeleverd = 1, toegekende_punten = ?, status = ? WHERE code = ? AND type = 'Hunt'");

            if ($stmt_check && $stmt_insert && $stmt_update) {
                foreach ($data['hunts'] as $hunt) {
                    if (isset($hunt['huntcode']) && !empty($hunt['huntcode'])) {
                        $code = $hunt['huntcode'];
                        $gebied = $hunt['deelgebied'] ?? 'Onbekend';
                        $tijd = !empty($hunt['hunttijd']) ? $hunt['hunttijd'] : date('Y-m-d H:i:s');
                        
                        $punten = isset($hunt['punten']) ? intval($hunt['punten']) : 0;
                        $status = $hunt['status'] ?? '';

                        $stmt_check->bind_param("s", $code);
                        $stmt_check->execute();
                        $result = $stmt_check->get_result();

                        if ($result->num_rows > 0) {
                            $row = $result->fetch_assoc();
                            $stmt_update->bind_param("iss", $punten, $status, $code);
                            $stmt_update->execute();
                            
                            // Check if status changed
                            if ($row['status'] !== $status && $status !== '') {
                                send_push_notification(
                                    'ALL',
                                    "Hunt Status Gewijzigd",
                                    "De status van hunt $code is nu '$status'.",
                                    '/voslocaties',
                                    'cron/scraper',
                                    null,
                                    'locatiestatus'
                                );
                            }
                        } else {
                            $stmt_insert->bind_param("sssis", $tijd, $gebied, $code, $punten, $status);
                            $stmt_insert->execute();
                        }
                    }
                }
                $stmt_check->close();
                $stmt_insert->close();
                $stmt_update->close();
            }
        }
        
        $output .= "\n\n[PHP] Data succesvol geparsed en bijgewerkt.";
    } else {
        $output .= "\n\n[PHP] JSON is gevonden, maar kon niet correct gedecodeerd worden.";
        $status_code = 500;
    }
}

// Log the execution details into Cronlogs
define("END_TIME", microtime(true));
$duration = intval((END_TIME - START_TIME) * 1000);
$output_escaped = addslashes($output);

$stmt = $conn->prepare("INSERT INTO Cronlogs (name, exec_time, exec_length, exec_stat, exec_output) VALUES (?, ?, ?, ?, ?)");
if ($stmt) {
    $stmt->bind_param("ssiis", $p1, $p2, $p3, $p4, $p5);
    $p1 = NAME;
    $p2 = $datumtijd;
    $p3 = $duration;
    $p4 = $status_code;
    $p5 = $output_escaped;
    $stmt->execute();
    $stmt->close();
}

$conn->close();
?>