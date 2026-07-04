<?php
define("NAME", "jotiPortal"); 
define("START_TIME", microtime(true));
date_default_timezone_set('Europe/Amsterdam');
$output = "";

require_once("../dblogin.php");

$datumtijd = date('Y-m-d H:i:s');

// 1. Voer de Python scraper uit
$command = "python3 /var/www/Joti/cron/scraper.py 2>&1";
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

        // UPDATE HUNTS (VOSLOCATIES)
        if (isset($data['hunts']) && is_array($data['hunts'])) {
            $stmt_hunt = $conn->prepare("UPDATE Voslocaties SET ingeleverd = 1, toegekende_punten = ? WHERE code = ? AND type = 'Hunt'");
            if ($stmt_hunt) {
                foreach ($data['hunts'] as $hunt) {
                    if (isset($hunt['huntcode'])) {
                        $pt = isset($hunt['punten']) ? intval($hunt['punten']) : 0;
                        $stmt_hunt->bind_param("is", $pt, $hunt['huntcode']);
                        $stmt_hunt->execute();
                    }
                }
                $stmt_hunt->close();
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