<?php
define("NAME", "jotiPortal"); 
define("START_TIME", microtime(true));
date_default_timezone_set('Europe/Amsterdam');
set_time_limit(120); // scraper needs time to log in and scrape
$output = "";

require_once("../dblogin.php");

$datumtijd = date('Y-m-d H:i:s');

// 1. Voer de Python scraper uit
$command = "/usr/bin/python3 /var/www/Joti/cron/scraper.py 2>&1";
$script_output = shell_exec($command);
$output .= $script_output;

// 2. Isoleer de JSON uit de Python text output
$json_start = strpos($script_output, '{');
$json_end = strrpos($script_output, '}');

// Controleer direct op Python fouten in de console
$status_code = 200;
if (stripos($script_output, 'Error:') !== false || stripos($script_output, 'Exception') !== false || stripos($script_output, 'Traceback') !== false) {
    $status_code = 500;
}

// 3. Verwerk de data in de database als we geldige JSON hebben
if ($json_start !== false && $json_end !== false && $status_code === 200) {
    $json_string = substr($script_output, $json_start, $json_end - $json_start + 1);
    $data = json_decode($json_string, true);

    if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
        
        // Haal het actieve GROUP_ID op uit de instellingen
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

            // Upsert: als de rij voor deze groep nog niet bestaat, wordt hij aangemaakt. Anders geüpdatet.
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
            $stmt_opd = $conn->prepare("UPDATE Opdrachten SET ingestuurd_op = IFNULL(ingestuurd_op, NOW()), toegekende_punten = ? WHERE id = ?");
            if ($stmt_opd) {
                foreach ($data['opdrachten'] as $opd) {
                    if (isset($opd['id']) && $opd['id'] !== null) {
                        $pt = isset($opd['punten']) ? intval($opd['punten']) : 0;
                        $stmt_opd->bind_param("ii", $pt, $opd['id']);
                        $stmt_opd->execute();
                    }
                }
                $stmt_opd->close();
            }
        }

        // UPDATE OR INSERT HUNTS (VOSLOCATIES)
        if (isset($data['hunts']) && is_array($data['hunts'])) {
            
            $start_date = date('Y-m-d');
            $end_date = date('Y-m-d');
            $stmt_dates = $conn->prepare("SELECT Instelling, Waarde FROM Site_Instellingen WHERE Instelling IN ('GAME_STARTDATE', 'GAME_ENDDATE')");
            if ($stmt_dates) {
                $stmt_dates->execute();
                $res_dates = $stmt_dates->get_result();
                while($r = $res_dates->fetch_assoc()){
                    if($r['Instelling'] == 'GAME_STARTDATE') $start_date = date('Y-m-d', strtotime($r['Waarde']));
                    if($r['Instelling'] == 'GAME_ENDDATE') $end_date = date('Y-m-d', strtotime($r['Waarde']));
                }
                $stmt_dates->close();
            }

            foreach ($data['hunts'] as $hunt) {
                if (isset($hunt['huntcode'])) {
                    $hc = $hunt['huntcode'];
                    $deelgebied = $hunt['deelgebied'] ?? '';
                    $hunttijd_str = $hunt['hunttijd'] ?? ''; 
                    $pt = isset($hunt['punten']) ? intval($hunt['punten']) : 0;
                    
                    $status = $hunt['status'] ?? null;
                    if ($status && stripos($status, 'HAPPY HOUR') !== false) {
                        $status = null;
                    }
                    
                    $ingestuurd_op = date('Y-m-d H:i:s');
                    if (strpos($hunttijd_str, ':') !== false) {
                        $date1 = $start_date . ' ' . $hunttijd_str . ':00';
                        $date2 = $end_date . ' ' . $hunttijd_str . ':00';
                        
                        $ts1 = strtotime($date1);
                        $ts2 = strtotime($date2);
                        
                        $now = time();
                        if (abs($now - $ts1) < abs($now - $ts2)) {
                            $ingestuurd_op = $date1;
                        } else {
                            $ingestuurd_op = $date2;
                        }
                    }

                    $hunt_id = null;
                    
                    $stmt_check = $conn->prepare("SELECT id FROM Voslocaties WHERE type='Hunt' AND code = ?");
                    if ($stmt_check) {
                        $stmt_check->bind_param("s", $hc);
                        $stmt_check->execute();
                        $res_check = $stmt_check->get_result();
                        if ($row_check = $res_check->fetch_assoc()) {
                            $hunt_id = $row_check['id'];
                        }
                        $stmt_check->close();
                    }
                    
                    if (!$hunt_id && $deelgebied != '' && $hunttijd_str != '') {
                        $stmt_check2 = $conn->prepare("SELECT id FROM Voslocaties WHERE type='Hunt' AND deelgebied = ? AND DATE_FORMAT(ingestuurd_op, '%H:%i') = ? LIMIT 1");
                        if ($stmt_check2) {
                            $stmt_check2->bind_param("ss", $deelgebied, $hunttijd_str);
                            $stmt_check2->execute();
                            $res_check2 = $stmt_check2->get_result();
                            if ($row_check2 = $res_check2->fetch_assoc()) {
                                $hunt_id = $row_check2['id'];
                            }
                            $stmt_check2->close();
                        }
                    }

                    if ($hunt_id) {
                        $stmt_update_hunt = $conn->prepare("UPDATE Voslocaties SET ingeleverd = 1, toegekende_punten = ?, code = ?, status = ? WHERE id = ?");
                        if ($stmt_update_hunt) {
                            $stmt_update_hunt->bind_param("issi", $pt, $hc, $status, $hunt_id);
                            $stmt_update_hunt->execute();
                            $stmt_update_hunt->close();
                        }
                    } else {
                        $stmt_insert_hunt = $conn->prepare("INSERT INTO Voslocaties (type, deelgebied, code, ingeleverd, toegekende_punten, ingestuurd_op, coordinaat_x, coordinaat_y, status) VALUES ('Hunt', ?, ?, 1, ?, ?, 0.0, 0.0, ?)");
                        if ($stmt_insert_hunt) {
                            $stmt_insert_hunt->bind_param("ssiss", $deelgebied, $hc, $pt, $ingestuurd_op, $status);
                            $stmt_insert_hunt->execute();
                            $stmt_insert_hunt->close();
                        }
                    }
                }
            }
        }
        
        $output .= "\n\n[PHP] Data succesvol geparsed en bijgewerkt in de tabellen (Punten, Opdrachten, Voslocaties).";
    } else {
        $output .= "\n\n[PHP] JSON is gevonden, maar kon niet correct gedecodeerd worden.";
        $status_code = 500;
    }
}

// 4. Logboek bijwerken
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