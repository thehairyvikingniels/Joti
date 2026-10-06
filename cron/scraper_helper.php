<?php
// Runs the Python portal scraper, updates group points, assignments, and hunt statuses, and dispatches notifications.
define("NAME", "jotiPortal"); 
define("START_TIME", microtime(true));
date_default_timezone_set('Europe/Amsterdam');
$output = "";

require_once(__DIR__ . '/../dblogin.php');
require_once(__DIR__ . '/../includes/db.php');
require_once(__DIR__ . '/../includes/helpers.php');

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
    // Haal lokale voertuigen op om mee te geven aan de scraper voor automatische aanmelding en vergelijking
    $local_vehicles = [];
    $stmt_v = $conn->query("
        SELECT a.kenteken, a.hunter_type, a.naam,
               COALESCE(NULLIF(a.telefoon, ''), NULLIF(g.phone, ''), '0600000000') as telefoon,
               COALESCE(
                   NULLIF(TRIM(CONCAT(COALESCE(g.voornaam, ''), ' ', COALESCE(g.achternaam, ''))), ''),
                   NULLIF(a.naam, ''),
                   a.kenteken
               ) as hunter_naam,
               a.hunter_code, a.hunter_portal_id
        FROM Auto a
        LEFT JOIN Gebruikers g ON a.eigenaar = g.id
    ");
    if ($stmt_v) {
        while ($r = $stmt_v->fetch_assoc()) {
            $clean_plate = strtoupper(str_replace(['-', ' '], '', $r['kenteken']));
            $local_vehicles[] = [
                'kenteken' => $r['kenteken'],
                'clean_plate' => $clean_plate,
                'type' => $r['hunter_type'] ?? 'car',
                'naam' => $r['hunter_naam'],
                'telefoon' => $r['telefoon'],
                'hunter_code' => $r['hunter_code'],
                'hunter_portal_id' => $r['hunter_portal_id']
            ];
        }
    }

    $vehicles_json = json_encode($local_vehicles);
    $command = "python3 " . escapeshellarg(__DIR__ . '/scraper.py') . " "
        . escapeshellarg($joti_user) . " "
        . escapeshellarg($joti_pass) . " --local-vehicles "
        . escapeshellarg($vehicles_json) . " 2>&1";

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

        // UPDATE FOTO OPDRACHTEN
        if (isset($data['foto_opdrachten']) && is_array($data['foto_opdrachten'])) {
            $stmt_fo_id = $conn->prepare("UPDATE Fotoopdrachten SET ingestuurd_op = COALESCE(ingestuurd_op, NOW()), toegekende_punten = ?, opmerkingen = COALESCE(?, opmerkingen) WHERE external_id = ?");
            $stmt_fo_title = $conn->prepare("UPDATE Fotoopdrachten SET ingestuurd_op = COALESCE(ingestuurd_op, NOW()), toegekende_punten = ?, opmerkingen = COALESCE(?, opmerkingen), external_id = COALESCE(external_id, ?) WHERE titel = ?");

            foreach ($data['foto_opdrachten'] as $fo) {
                $fTitle = trim($fo['titel'] ?? '');
                $fId = isset($fo['id']) && $fo['id'] !== null ? (int)$fo['id'] : null;
                $fPts = isset($fo['punten']) ? (int)$fo['punten'] : 0;
                $fOpmerking = !empty($fo['opmerkingen']) ? trim($fo['opmerkingen']) : null;

                $matched = false;
                if ($fId && $stmt_fo_id) {
                    $stmt_fo_id->bind_param("isi", $fPts, $fOpmerking, $fId);
                    $stmt_fo_id->execute();
                    if ($stmt_fo_id->affected_rows > 0) {
                        $matched = true;
                    }
                }

                if (!$matched && !empty($fTitle) && $stmt_fo_title) {
                    $stmt_fo_title->bind_param("isis", $fPts, $fOpmerking, $fId, $fTitle);
                    $stmt_fo_title->execute();
                }
            }

            if ($stmt_fo_id) $stmt_fo_id->close();
            if ($stmt_fo_title) $stmt_fo_title->close();
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

        // UPDATE TELEGRAM REGISTRATION CODE
        if (!empty($data['telegram_code'])) {
            $stmt_tg = $conn->prepare("INSERT INTO Site_Instellingen (Instelling, Waarde, Omschrijving) VALUES ('TELEGRAM_REGISTRATION_CODE', ?, 'Telegram bot registratiecode') ON DUPLICATE KEY UPDATE Waarde = VALUES(Waarde)");
            if ($stmt_tg) {
                $stmt_tg->bind_param("s", $data['telegram_code']);
                $stmt_tg->execute();
                $stmt_tg->close();
                $output .= "\n[PHP] Telegram registratiecode bijgewerkt: " . $data['telegram_code'];
            }
        }

        // UPDATE & SYNC HUNTERS / VEHICLES
        if (isset($data['hunters']) && is_array($data['hunters'])) {
            $stmt_hunt_upsert = $conn->prepare("
                INSERT INTO Jotihunt_Hunters (portal_id, type, naam, telefoon, code, kenteken, pdf_file, last_scraped_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    type = VALUES(type),
                    naam = VALUES(naam),
                    telefoon = VALUES(telefoon),
                    code = VALUES(code),
                    kenteken = VALUES(kenteken),
                    pdf_file = VALUES(pdf_file),
                    last_scraped_at = NOW()
            ");

            $portal_ids = [];
            foreach ($data['hunters'] as $h) {
                if (isset($h['portal_id']) && $h['portal_id']) {
                    $pid = (int)$h['portal_id'];
                    $htype = $h['type'] ?? 'car';
                    $hname = trim($h['name'] ?? '');
                    $hphone = trim($h['phone'] ?? '');
                    $hcode = trim($h['code'] ?? '');
                    $hplate = strtoupper(trim($h['license_plate'] ?? ''));
                    $hpdf = $h['pdf_file'] ?? null;

                    $portal_ids[] = $pid;

                    if ($stmt_hunt_upsert) {
                        $stmt_hunt_upsert->bind_param("issssss", $pid, $htype, $hname, $hphone, $hcode, $hplate, $hpdf);
                        $stmt_hunt_upsert->execute();
                    }
                }
            }
            if ($stmt_hunt_upsert) {
                $stmt_hunt_upsert->close();
            }

            // Verwijder eventuele portaal hunters die niet meer op het portaal voorkomen
            if (!empty($portal_ids)) {
                $placeholders = implode(',', array_fill(0, count($portal_ids), '?'));
                $types = str_repeat('i', count($portal_ids));
                $stmt_del_missing = $conn->prepare("DELETE FROM Jotihunt_Hunters WHERE portal_id NOT IN ($placeholders)");
                if ($stmt_del_missing) {
                    $stmt_del_missing->bind_param($types, ...$portal_ids);
                    $stmt_del_missing->execute();
                    $stmt_del_missing->close();
                }
            }

            // Koppel aan lokaal wagenpark (Auto)
            $local_cars_res = $conn->query("SELECT kenteken, eigenaar, hunter_type, naam, telefoon, hunter_code, hunter_portal_id FROM Auto");
            $local_cars = [];
            if ($local_cars_res) {
                while ($c = $local_cars_res->fetch_assoc()) {
                    $clean_plate = strtoupper(str_replace(['-', ' '], '', $c['kenteken']));
                    $local_cars[$clean_plate] = $c;
                }
            }

            $matched_count = 0;
            $unmatched_portal = 0;

            $stmt_update_auto = $conn->prepare("
                UPDATE Auto 
                SET hunter_portal_id = ?, 
                    hunter_code = ?, 
                    hunter_type = CASE WHEN hunter_type = 'scooter' THEN 'scooter' ELSE COALESCE(NULLIF(?, ''), hunter_type) END, 
                    pdf_path = COALESCE(?, pdf_path)
                WHERE REPLACE(REPLACE(kenteken, '-', ''), ' ', '') = ?
                   OR hunter_portal_id = ?
            ");

            foreach ($data['hunters'] as $h) {
                $pid = (int)($h['portal_id'] ?? 0);
                $htype = $h['type'] ?? 'car';
                $hcode = trim($h['code'] ?? '');
                $hplate = strtoupper(trim($h['license_plate'] ?? ''));
                $clean_hplate = str_replace(['-', ' '], '', $hplate);
                $hpdf = $h['pdf_file'] ?? null;

                $found_match = false;
                if (!empty($clean_hplate) && isset($local_cars[$clean_hplate])) {
                    $found_match = true;
                } else {
                    foreach ($local_cars as $c) {
                        if ((int)$c['hunter_portal_id'] === $pid) {
                            $found_match = true;
                            break;
                        }
                    }
                }

                if ($found_match && $stmt_update_auto) {
                    $stmt_update_auto->bind_param("issssi", $pid, $hcode, $htype, $hpdf, $clean_hplate, $pid);
                    $stmt_update_auto->execute();
                    $matched_count++;
                } else {
                    $unmatched_portal++;
                }
            }
            if ($stmt_update_auto) {
                $stmt_update_auto->close();
            }

            // Vul ontbrekende RDW gegevens aan voor lokale voertuigen
            $cars_without_rdw = $conn->query("SELECT kenteken, rdw_kleur, aantal_zitplaatsen FROM Auto WHERE (rdw_kleur IS NULL OR aantal_zitplaatsen IS NULL) AND hunter_type IN ('car', 'motorcycle', 'scooter')");
            if ($cars_without_rdw && $cars_without_rdw->num_rows > 0) {
                $stmt_up_rdw = $conn->prepare("UPDATE Auto SET rdw_kleur = COALESCE(?, rdw_kleur), aantal_zitplaatsen = COALESCE(?, aantal_zitplaatsen) WHERE kenteken = ?");
                while ($c_row = $cars_without_rdw->fetch_assoc()) {
                    $clean = preg_replace('/[^A-Za-z0-9]/', '', $c_row['kenteken']);
                    if (strlen($clean) >= 4 && !str_starts_with($clean, 'FIETS') && !str_starts_with($clean, 'VOET') && !str_starts_with($clean, 'HELI') && !str_starts_with($clean, 'UNIT')) {
                        $ctx = stream_context_create(['http' => ['timeout' => 2]]);
                        $rdw_json = @file_get_contents("https://opendata.rdw.nl/resource/m9d7-ebf2.json?kenteken=" . strtoupper($clean), false, $ctx);
                        if ($rdw_json) {
                            $decoded = json_decode($rdw_json, true);
                            if (!empty($decoded[0])) {
                                $color_val = null;
                                if (!empty($decoded[0]['eerste_kleur'])) {
                                    $c_candidate = strtoupper(trim($decoded[0]['eerste_kleur']));
                                    if (!in_array($c_candidate, ['N.V.T.', 'NIET GEREGISTREERD', 'DIVERSEN'], true)) {
                                        $color_val = $c_candidate;
                                    }
                                }
                                $seats_val = !empty($decoded[0]['aantal_zitplaatsen']) ? (int)$decoded[0]['aantal_zitplaatsen'] : null;
                                if ($color_val !== null || $seats_val !== null) {
                                    $stmt_up_rdw->bind_param("sis", $color_val, $seats_val, $c_row['kenteken']);
                                    $stmt_up_rdw->execute();
                                }
                            }
                        }
                    }
                }
                if ($stmt_up_rdw) {
                    $stmt_up_rdw->close();
                }
            }

            $unreg_res = $conn->query("SELECT COUNT(*) as cnt FROM Auto WHERE hunter_code IS NULL OR hunter_code = ''");
            $unreg_local = $unreg_res ? (int)$unreg_res->fetch_assoc()['cnt'] : 0;
            $total_local = count($local_cars);
            $total_portal = count($data['hunters']);

            $newly_registered = (int)($data['newly_registered_count'] ?? 0);

            $output .= "\n\n[PHP] Hunter synchronisatie voltooid:";
            $output .= "\n      - Totaal in lokaal wagenpark: $total_local";
            $output .= "\n      - Totaal op Jotihunt.nl portaal: $total_portal";
            $output .= "\n      - Nieuw aangemeld op portaal tijdens deze run: $newly_registered";
            $output .= "\n      - Gekoppeld / Geregistreerd: $matched_count";
            $output .= "\n      - Lokaal nog zonder code: $unreg_local";
        }
        
        $output .= "\n\n[PHP] Data succesvol geparsed en bijgewerkt.";
    } else {
        $output .= "\n\n[PHP] JSON is gevonden, maar kon niet correct gedecodeerd worden.";
        $status_code = 500;
    }
}

// Log the execution details into Cronlogs
recordCronLog($conn, NAME, START_TIME, $output, $status_code);
$conn->close();
?>