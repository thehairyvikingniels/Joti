<?php
// Pre-Flight Readiness Hub AJAX backend: diagnostic probes, checklist manager, and edition archival & reset engine.
require_once(__DIR__ . '/../includes/auth.php');
require_once(__DIR__ . '/../includes/helpers.php');
require_once(__DIR__ . '/../includes/db.php');
require_once(__DIR__ . '/system_helper.php');

if (!isset($_SESSION['id']) || (int)($_SESSION['priv'] ?? 0) < 2) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Onbevoegd. Minimaal beheerdersrechten (niveau 2) vereist.']);
    exit();
}

$webroot = realpath(__DIR__ . '/..');
$backupDir = $webroot . '/DB/backups';
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

header('Content-Type: application/json; charset=utf-8');

/**
 * Operational game tables to inspect and reset
 */
const OPERATIONAL_TABLES = [
    'Groepen' => 'Scoutinggroepen & IDs',
    'Hints' => 'Hints & Coördinaten',
    'Opdrachten' => 'Opdrachten & Inzendingen',
    'Nieuws' => 'Nieuwsberichten',
    'Punten' => 'Scores & Klassement',
    'Voslocaties' => 'Vossenlocaties & Coördinaten',
    'Voslog' => 'Vossenstatus & Logboek',
    'Toewijzingen' => 'Vossentoewijzingen',
    'Auto_Positie' => 'Voertuig GPS Coördinaten',
    'Auto_Toewijzingen' => 'Voertuig Vossentoewijzingen',
    'Auto_Bijrijders' => 'Voertuig Bezetting & Koppelingen',
    'Tegenhunt_Breadcrumbs' => 'Tegenhunt GPS Sporen',
    'Tegenhunt_Sessions' => 'Tegenhunt Sessies',
    'Notification_Backlog' => 'Push Notificatie Backlog',
    'Telegram_Messages' => 'Telegram Berichten Geschiedenis'
];

try {
    switch ($action) {
        case 'get_overview':
            handle_get_overview($conn);
            break;

        case 'run_diagnostics':
            handle_run_diagnostics($conn, $webroot);
            break;

        case 'toggle_check':
            handle_toggle_check($conn);
            break;

        case 'add_check_item':
            handle_add_check_item($conn);
            break;

        case 'delete_check_item':
            handle_delete_check_item($conn);
            break;

        case 'reset_checklist':
            handle_reset_checklist($conn);
            break;

        case 'archive_and_reset':
            handle_archive_and_reset($conn, $webroot, $backupDir);
            break;

        case 'download_edition':
            handle_download_edition($backupDir);
            break;

        default:
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => "Onbekende actie: '{$action}'."]);
            break;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
exit();

/**
 * Return summary statistics, table counts, checklist items, and archived editions
 */
function handle_get_overview(mysqli $conn): void {
    // 1. Checklist stats and items
    $stmt = $conn->prepare("
        SELECT c.*, 
               u.voornaam, u.achternaam, u.gebruikersnaam
        FROM Preflight_Checklist c
        LEFT JOIN Gebruikers u ON c.checked_by = u.id
        ORDER BY c.category ASC, c.sort_order ASC, c.id ASC
    ");
    $stmt->execute();
    $res = $stmt->get_result();

    $items = [];
    $totalCount = 0;
    $checkedCount = 0;

    while ($row = $res->fetch_assoc()) {
        $totalCount++;
        $isChecked = (bool)$row['is_checked'];
        if ($isChecked) {
            $checkedCount++;
        }

        $checkedByName = null;
        if (!empty($row['voornaam'])) {
            $checkedByName = trim($row['voornaam'] . ' ' . ($row['achternaam'] ?? ''));
        } elseif (!empty($row['gebruikersnaam'])) {
            $checkedByName = $row['gebruikersnaam'];
        }

        $items[] = [
            'id' => (int)$row['id'],
            'category' => $row['category'],
            'title' => $row['title'],
            'description' => $row['description'],
            'is_checked' => $isChecked,
            'checked_by' => $row['checked_by'] ? (int)$row['checked_by'] : null,
            'checked_by_name' => $checkedByName,
            'checked_at' => $row['checked_at'] ? date('d-m-Y H:i', strtotime($row['checked_at'])) : null,
            'is_custom' => (bool)$row['is_custom']
        ];
    }
    $stmt->close();

    $checklistPercent = $totalCount > 0 ? (int)round(($checkedCount / $totalCount) * 100) : 100;

    // 2. Operational table row counts
    $operationalCounts = [];
    $totalOperationalRows = 0;
    foreach (OPERATIONAL_TABLES as $tbl => $label) {
        $cnt = 0;
        $q = $conn->query("SELECT COUNT(*) as c FROM `{$tbl}`");
        if ($q) {
            $r = $q->fetch_assoc();
            $cnt = (int)($r['c'] ?? 0);
        }
        $operationalCounts[] = [
            'table' => $tbl,
            'label' => $label,
            'count' => $cnt
        ];
        $totalOperationalRows += $cnt;
    }

    // 3. Archived Editions registry
    $archivedEditions = [];
    $eq = $conn->query("
        SELECT ae.*, u.voornaam, u.achternaam, u.gebruikersnaam 
        FROM Archived_Editions ae
        LEFT JOIN Gebruikers u ON ae.archived_by = u.id
        ORDER BY ae.archived_at DESC
    ");
    if ($eq) {
        while ($er = $eq->fetch_assoc()) {
            $byName = !empty($er['voornaam']) ? trim($er['voornaam'] . ' ' . $er['achternaam']) : ($er['gebruikersnaam'] ?? 'Onbekend');
            $archivedEditions[] = [
                'id' => (int)$er['id'],
                'edition_name' => $er['edition_name'],
                'edition_year' => (int)$er['edition_year'],
                'backup_filename' => $er['backup_filename'],
                'file_size_formatted' => function_exists('bitbyte2string') ? bitbyte2string((float)$er['file_size']) : round($er['file_size'] / (1024 * 1024), 1) . ' MB',
                'row_counts' => json_decode($er['row_counts'] ?? '[]', true) ?: [],
                'archived_by_name' => $byName,
                'archived_at' => date('d-m-Y H:i', strtotime($er['archived_at']))
            ];
        }
    }

    echo json_encode([
        'ok' => true,
        'checklist' => [
            'items' => $items,
            'total' => $totalCount,
            'checked' => $checkedCount,
            'percentage' => $checklistPercent
        ],
        'operational' => [
            'tables' => $operationalCounts,
            'total_rows' => $totalOperationalRows
        ],
        'archived_editions' => $archivedEditions
    ]);
}

/**
 * Execute all 10 live health checks and return diagnostic results with latencies
 */
function handle_run_diagnostics(mysqli $conn, string $webroot): void {
    $checks = [];

    // 1. Officiële Jotihunt API (2.0)
    $start = microtime(true);
    $ch = curl_init('https://jotihunt.nl/api/2.0/areas');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 4,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT => 'Jotify-Readiness-Hub/1.0'
    ]);
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);
    $latencyApi = round((microtime(true) - $start) * 1000);

    if ($httpCode === 200) {
        $checks['jotihunt_api'] = [
            'status' => $latencyApi < 1500 ? 'ok' : 'warning',
            'title' => 'Officiële Jotihunt API (2.0)',
            'message' => "Bereikbaar (HTTP 200 OK) in {$latencyApi}ms",
            'details' => 'De officiële spelstatus API van Jotihunt.nl antwoordt normaal.',
            'latency_ms' => $latencyApi
        ];
    } else {
        $checks['jotihunt_api'] = [
            'status' => 'error',
            'title' => 'Officiële Jotihunt API (2.0)',
            'message' => $curlErr ? "Verbindingsfout: {$curlErr}" : "Onverwachte HTTP code {$httpCode}",
            'details' => 'Controleer de uitgaande internetverbinding en DNS-instellingen van de server.',
            'latency_ms' => $latencyApi
        ];
    }

    // 1b. Jotihunt.nl Portaal & Inloggegevens (Scraper)
    $startScraper = microtime(true);
    $jotiCredsRaw = $GLOBALS['site_settings']['JOTIHUNT_CREDENTIALS'] ?? '';
    if (empty($jotiCredsRaw)) {
        $stmt_cred = $conn->prepare("SELECT Waarde FROM Site_Instellingen WHERE Instelling = 'JOTIHUNT_CREDENTIALS'");
        if ($stmt_cred) {
            $stmt_cred->execute();
            $res_cred = $stmt_cred->get_result();
            if ($row_c = $res_cred->fetch_assoc()) {
                $jotiCredsRaw = $row_c['Waarde'];
            }
            $stmt_cred->close();
        }
    }
    $jotiCreds = json_decode($jotiCredsRaw, true);
    $jotiUser = $jotiCreds['username'] ?? '';
    $jotiPass = $jotiCreds['password'] ?? '';

    if (empty($jotiUser) || empty($jotiPass)) {
        $checks['jotihunt_credentials'] = [
            'status' => 'warning',
            'title' => 'Jotihunt.nl Portaal Inloggegevens',
            'message' => 'Nog geen inloggegevens geconfigureerd in Instellingen',
            'details' => 'Stel je Jotihunt.nl accountgegevens in via de instellingenpagina.',
            'latency_ms' => null
        ];
    } else {
        $scraperPath = realpath($webroot . '/cron/scraper.py');
        if (!$scraperPath || !file_exists($scraperPath)) {
            $checks['jotihunt_credentials'] = [
                'status' => 'error',
                'title' => 'Jotihunt.nl Portaal Inloggegevens',
                'message' => 'Scraper script ontbreekt op de server',
                'details' => 'cron/scraper.py werd niet gevonden.',
                'latency_ms' => null
            ];
        } else {
            $cmd = "python3 " . escapeshellarg($scraperPath) . " " . escapeshellarg($jotiUser) . " " . escapeshellarg($jotiPass) . " 2>&1";
            $output = shell_exec($cmd);
            $latencyScraper = round((microtime(true) - $startScraper) * 1000);

            if (str_contains($output, 'Data succesvol opgehaald') || str_contains($output, 'telegram_code')) {
                $jsonStart = strpos($output, '{');
                $jsonEnd   = strrpos($output, '}');
                $parsed = null;
                if ($jsonStart !== false && $jsonEnd !== false) {
                    $jsonStr = substr($output, $jsonStart, $jsonEnd - $jsonStart + 1);
                    $parsed = json_decode($jsonStr, true);
                }
                $grpName = !empty($parsed['group_name']) ? $parsed['group_name'] : $jotiUser;
                $tCode = !empty($parsed['telegram_code']) ? " (Telegram code: {$parsed['telegram_code']})" : '';

                $checks['jotihunt_credentials'] = [
                    'status' => 'ok',
                    'title' => 'Jotihunt.nl Portaal Inloggegevens',
                    'message' => "Inloggen geslaagd als {$grpName}{$tCode}",
                    'details' => 'Automatisch geverifieerd via portaal scraper (sessie & CSRF OK).',
                    'latency_ms' => $latencyScraper
                ];
            } else {
                $checks['jotihunt_credentials'] = [
                    'status' => 'error',
                    'title' => 'Jotihunt.nl Portaal Inloggegevens',
                    'message' => 'Inloggen op jotihunt.nl mislukt',
                    'details' => 'Controleer gebruikersnaam en wachtwoord in Instellingen.',
                    'latency_ms' => $latencyScraper
                ];
            }
        }
    }

    // 2. Database & Schema
    $start = microtime(true);
    $dbPing = $conn->query("SELECT @@character_set_database AS cs, @@collation_database AS cl");
    $dbData = $dbPing ? $dbPing->fetch_assoc() : null;
    $latencyDb = round((microtime(true) - $start) * 1000);

    $missingTables = [];
    foreach (['Gebruikers', 'Groepen', 'Hints', 'Opdrachten', 'Punten', 'Voslocaties', 'Voslog', 'Site_Instellingen', 'Auto', 'Auto_Positie', 'Preflight_Checklist'] as $t) {
        $chk = $conn->query("SHOW TABLES LIKE '{$t}'");
        if (!$chk || $chk->num_rows === 0) {
            $missingTables[] = $t;
        }
    }

    if (empty($missingTables) && $dbData) {
        $csOk = str_contains(strtolower($dbData['cs'] ?? ''), 'utf8mb4');
        $checks['database'] = [
            'status' => $csOk ? 'ok' : 'warning',
            'title' => 'Database & Schema',
            'message' => "MariaDB verbonden in {$latencyDb}ms ({$dbData['cs']})",
            'details' => $csOk ? 'Alle vereiste tabellen aanwezig met utf8mb4 ondersteuning.' : 'Let op: charset is niet utf8mb4.',
            'latency_ms' => $latencyDb
        ];
    } else {
        $checks['database'] = [
            'status' => 'error',
            'title' => 'Database & Schema',
            'message' => "Ontbrekende tabellen: " . implode(', ', $missingTables),
            'details' => 'Voer createDB.sql opnieuw uit om ontbrekende tabellen aan te maken.',
            'latency_ms' => $latencyDb
        ];
    }

    // 3. Essentiële Cronjobs
    $keyCrons = ['areas', 'articles', 'auto_backup', 'subscriptions', 'push_queue'];
    $cronRes = $conn->query("SELECT name, enabled, `interval` FROM Cronjobs");
    $activeCrons = [];
    if ($cronRes) {
        while ($cr = $cronRes->fetch_assoc()) {
            if ((int)$cr['enabled'] === 1) {
                $activeCrons[$cr['name']] = $cr;
            }
        }
    }
    $missingCrons = array_diff($keyCrons, array_keys($activeCrons));

    // Check last run from Cronlogs
    $lastLogRes = $conn->query("SELECT MAX(exec_time) as last_run FROM Cronlogs");
    $lastRunRow = $lastLogRes ? $lastLogRes->fetch_assoc() : null;
    $lastCronRun = $lastRunRow['last_run'] ?? null;
    $cronStale = empty($lastCronRun) || (time() - strtotime($lastCronRun)) > 900;

    if (empty($missingCrons) && !$cronStale) {
        $checks['cronjobs'] = [
            'status' => 'ok',
            'title' => 'Achtergrondtaken (Cronjobs)',
            'message' => count($activeCrons) . ' actieve crons, laatste uitvoering ' . date('H:i:s', strtotime($lastCronRun)),
            'details' => 'Alle essentiële speltaken draaien volgens schema.',
            'latency_ms' => null
        ];
    } elseif (empty($missingCrons) && $cronStale) {
        $checks['cronjobs'] = [
            'status' => 'warning',
            'title' => 'Achtergrondtaken (Cronjobs)',
            'message' => 'Crons zijn ingeschakeld maar hebben > 15 min niet gelopen',
            'details' => 'Controleer of de systeem-crontab (cron/index.php) actief is op de server.',
            'latency_ms' => null
        ];
    } else {
        $checks['cronjobs'] = [
            'status' => 'error',
            'title' => 'Achtergrondtaken (Cronjobs)',
            'message' => 'Inactieve essentiële crons: ' . implode(', ', $missingCrons),
            'details' => 'Schakel de ontbrekende crons in via het Cronjobs beheerpaneel.',
            'latency_ms' => null
        ];
    }

    // 4. Telegram Bot Subsystem
    $tgToken = $GLOBALS['site_settings']['TELEGRAM_BOT_TOKEN'] ?? '';
    $tgGroupChat = $GLOBALS['site_settings']['TELEGRAM_GROUP_CHAT_ID'] ?? '';
    if (!empty($tgToken) && !str_starts_with($tgToken, 'placeholder') && !str_starts_with($tgToken, '123456789')) {
        $start = microtime(true);
        $tgCh = curl_init("https://api.telegram.org/bot{$tgToken}/getMe");
        curl_setopt_array($tgCh, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 4,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        $tgResp = curl_exec($tgCh);
        $tgCode = curl_getinfo($tgCh, CURLINFO_HTTP_CODE);
        curl_close($tgCh);
        $tgLatency = round((microtime(true) - $start) * 1000);

        $tgJson = json_decode($tgResp, true);
        if ($tgCode === 200 && ($tgJson['ok'] ?? false)) {
            $botUsername = $tgJson['result']['username'] ?? 'Bot';
            $hasGroup = !empty($tgGroupChat) && $tgGroupChat !== '-1001234567890';
            $checks['telegram_bot'] = [
                'status' => $hasGroup ? 'ok' : 'warning',
                'title' => 'Telegram Bot Subsystem',
                'message' => "@{$botUsername} verbonden ({$tgLatency}ms)",
                'details' => $hasGroup ? "Meldgroep ID: {$tgGroupChat} gekoppeld." : "Let op: Centrale meldgroep ID staat nog op standaardwaarde.",
                'latency_ms' => $tgLatency
            ];
        } else {
            $checks['telegram_bot'] = [
                'status' => 'error',
                'title' => 'Telegram Bot Subsystem',
                'message' => 'Ongeldig Telegram Bot Token (HTTP ' . $tgCode . ')',
                'details' => 'Controleer het bot token in Instellingen > Telegram.',
                'latency_ms' => $tgLatency
            ];
        }
    } else {
        $checks['telegram_bot'] = [
            'status' => 'warning',
            'title' => 'Telegram Bot Subsystem',
            'message' => 'Niet geconfigureerd (placeholder token)',
            'details' => 'Stel een geldig Bot Token in via @BotFather in de instellingen als Telegram notificaties gewenst zijn.',
            'latency_ms' => null
        ];
    }

    // 5. Mapbox Kaarten API
    $mapboxToken = $GLOBALS['site_settings']['API_KEY_MAPBOX'] ?? '';
    if (!empty($mapboxToken) && str_starts_with($mapboxToken, 'pk.')) {
        $start = microtime(true);
        $mbUrl = 'https://api.mapbox.com/styles/v1/mapbox/streets-v11?access_token=' . urlencode($mapboxToken);
        $mbCh = curl_init($mbUrl);
        curl_setopt_array($mbCh, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 4,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        curl_exec($mbCh);
        $mbCode = curl_getinfo($mbCh, CURLINFO_HTTP_CODE);
        curl_close($mbCh);
        $mbLatency = round((microtime(true) - $start) * 1000);

        if ($mbCode === 200) {
            $checks['mapbox'] = [
                'status' => 'ok',
                'title' => 'Mapbox Kaarten API',
                'message' => "Token gevalideerd (HTTP 200 OK, {$mbLatency}ms)",
                'details' => 'Kaartlagen en vector tiles laden zonder autorisatiefouten.',
                'latency_ms' => $mbLatency
            ];
        } else {
            $checks['mapbox'] = [
                'status' => 'error',
                'title' => 'Mapbox Kaarten API',
                'message' => "Autorisatiefout (HTTP {$mbCode})",
                'details' => 'Mapbox access token is geweigerd of verlopen. Controleer Instellingen.',
                'latency_ms' => $mbLatency
            ];
        }
    } else {
        $checks['mapbox'] = [
            'status' => 'error',
            'title' => 'Mapbox Kaarten API',
            'message' => 'Geen geldig public access token ingesteld (moet beginnen met pk.)',
            'details' => 'Stel een Mapbox token in via Instellingen om interactieve jachtkaarten te kunnen laden.',
            'latency_ms' => null
        ];
    }

    // 6. Web Push Notificaties (VAPID)
    $vapidPublic = $GLOBALS['site_settings']['VAPID_PUBLIC_KEY'] ?? '';
    $subRes = $conn->query("SELECT COUNT(*) as cnt FROM Notification_Subscriptions");
    $subCount = $subRes ? (int)($subRes->fetch_assoc()['cnt'] ?? 0) : 0;

    if (!empty($vapidPublic) && strlen($vapidPublic) > 20) {
        $checks['web_push'] = [
            'status' => $subCount > 0 ? 'ok' : 'warning',
            'title' => 'Web Push Notificaties',
            'message' => "VAPID sleutels actief, {$subCount} browser abonnement(en)",
            'details' => $subCount > 0 ? 'Push server klaar voor realtime meldingen aan meldkamer en jagers.' : 'Geen actieve browser-abonnementen. Schakel notificaties in via instellingen.',
            'latency_ms' => null
        ];
    } else {
        $checks['web_push'] = [
            'status' => 'warning',
            'title' => 'Web Push Notificaties',
            'message' => 'VAPID sleutels ontbreken',
            'details' => 'Genereer VAPID sleutels in de instellingen voor Web Push ondersteuning.',
            'latency_ms' => null
        ];
    }

    // 7. Schijfruimte & Rechten
    $freeBytes = @disk_free_space($webroot);
    $totalBytes = @disk_total_space($webroot);
    $freeGb = $freeBytes ? round($freeBytes / (1024 * 1024 * 1024), 2) : 0;
    
    $writableDirs = [
        'DB/backups' => $webroot . '/DB/backups',
        'media/hunts' => $webroot . '/media/hunts',
        'media/profiles' => $webroot . '/media/profiles',
        'media/tegenhunt' => $webroot . '/media/tegenhunt'
    ];
    $unwritable = [];
    foreach ($writableDirs as $name => $path) {
        if (!is_dir($path) || !is_writable($path)) {
            $unwritable[] = $name;
        }
    }

    if ($freeGb >= 2.0 && empty($unwritable)) {
        $checks['disk_storage'] = [
            'status' => 'ok',
            'title' => 'Schijfruimte & Rechten',
            'message' => "{$freeGb} GB beschikbaar, alle mediamappen schrijfbaar",
            'details' => 'Voldoende capaciteit voor foto-inzendingen, GPS tracks en back-ups.',
            'latency_ms' => null
        ];
    } elseif (!empty($unwritable)) {
        $checks['disk_storage'] = [
            'status' => 'error',
            'title' => 'Schijfruimte & Rechten',
            'message' => "Niet-schrijfbare mappen: " . implode(', ', $unwritable),
            'details' => 'Zorg dat www-data schrijfrechten heeft (chmod 775).',
            'latency_ms' => null
        ];
    } else {
        $checks['disk_storage'] = [
            'status' => 'warning',
            'title' => 'Schijfruimte & Rechten',
            'message' => "Weinig schijfruimte vrij ({$freeGb} GB)",
            'details' => 'Aanbevolen minimum voor 26 uur Jotihunt is ten minste 2 GB.',
            'latency_ms' => null
        ];
    }

    // 8. Jagersvloot & Kiosken
    $uRes = $conn->query("SELECT priv, COUNT(*) as cnt FROM Gebruikers GROUP BY priv");
    $userRoles = [0 => 0, 1 => 0, 2 => 0, 3 => 0];
    if ($uRes) {
        while ($ur = $uRes->fetch_assoc()) {
            $userRoles[(int)$ur['priv']] = (int)$ur['cnt'];
        }
    }
    $hunters = $userRoles[1] ?? 0;
    $admins = ($userRoles[2] ?? 0) + ($userRoles[3] ?? 0);

    $carRes = $conn->query("SELECT COUNT(*) as cnt FROM Auto");
    $cars = $carRes ? (int)($carRes->fetch_assoc()['cnt'] ?? 0) : 0;

    $kioskRes = $conn->query("SELECT COUNT(*) as cnt FROM Kiosk_Accounts");
    $kiosks = $kioskRes ? (int)($kioskRes->fetch_assoc()['cnt'] ?? 0) : 0;

    if ($hunters > 0 && $cars > 0) {
        $checks['fleet_kiosks'] = [
            'status' => 'ok',
            'title' => 'Jagersvloot & Kiosken',
            'message' => "{$cars} geregistreerde auto('s), {$hunters} jagers, {$admins} beheerders",
            'details' => "Kiosk accounts actief: {$kiosks}. Vlootconfiguratie gereed.",
            'latency_ms' => null
        ];
    } else {
        $checks['fleet_kiosks'] = [
            'status' => 'warning',
            'title' => 'Jagersvloot & Kiosken',
            'message' => "{$cars} auto's, {$hunters} jagers geregistreerd",
            'details' => 'Registreer auto\'s in het wagenpark en maak accounts aan voor de jagersploegen.',
            'latency_ms' => null
        ];
    }

    // 9. Tegenhunt Parameters & HQ
    $groupId = (int)($GLOBALS['site_settings']['GROUP_ID'] ?? 0);
    $groupData = null;
    if ($groupId > 0) {
        $gStmt = $conn->prepare("SELECT id, naam, lat, lon, plaats FROM Groepen WHERE id = ?");
        $gStmt->bind_param("i", $groupId);
        $gStmt->execute();
        $groupData = $gStmt->get_result()->fetch_assoc();
        $gStmt->close();
    }

    if ($groupData && (float)$groupData['lat'] != 0.0 && (float)$groupData['lon'] != 0.0) {
        $lat = round((float)$groupData['lat'], 4);
        $lon = round((float)$groupData['lon'], 4);
        $checks['tegenhunt'] = [
            'status' => 'ok',
            'title' => 'Tegenhunt Parameters & HQ',
            'message' => "HQ gekoppeld: {$groupData['naam']} ({$lat}, {$lon})",
            'details' => "Automatische tegenhunt radius (500m) berekent rondom basislocatie {$groupData['plaats']}.",
            'latency_ms' => null
        ];
    } else {
        $checks['tegenhunt'] = [
            'status' => 'warning',
            'title' => 'Tegenhunt Parameters & HQ',
            'message' => 'Scoutinggroep HQ niet ingesteld of coördinaten zijn 0,0',
            'details' => 'Controleer GROUP_ID in Instellingen en verifieer de locatie in de Groepen tabel.',
            'latency_ms' => null
        ];
    }

    // 10. Netwerk & Beveiliging
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
        || ($_SERVER['SERVER_PORT'] ?? 0) == 443 
        || (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    $clientIp = function_exists('getClientIP') ? getClientIP() : ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
    $isCf = !empty($_SERVER['HTTP_CF_CONNECTING_IP']) || !empty($_SERVER['HTTP_CF_RAY']);
    $proxyLabel = $isCf ? 'Cloudflare Tunnel' : (!empty($_SERVER['HTTP_X_FORWARDED_FOR']) ? 'Reverse Proxy' : 'Direct');

    $checks['network_security'] = [
        'status' => $isHttps ? 'ok' : 'warning',
        'title' => 'Netwerk & Beveiliging',
        'message' => ($isHttps ? 'HTTPS actief' : 'Geen HTTPS') . " via {$proxyLabel} (Client IP: {$clientIp})",
        'details' => $isHttps ? 'Veilige TLS encryptie actief en IP-forwarding headers correct verwerkt.' : 'Let op: verbinding is niet versleuteld.',
        'latency_ms' => null
    ];

    // Compute overall score
    $scorePoints = 0;
    foreach ($checks as $chk) {
        if ($chk['status'] === 'ok') $scorePoints += 10;
        elseif ($chk['status'] === 'warning') $scorePoints += 5;
    }
    $diagScore = min(100, $scorePoints);

    echo json_encode([
        'ok' => true,
        'score' => $diagScore,
        'checks' => $checks
    ]);
}

/**
 * Toggle a checklist item's completion status
 */
function handle_toggle_check(mysqli $conn): void {
    $id = (int)($_POST['id'] ?? 0);
    $checked = (int)($_POST['checked'] ?? 0);

    if ($id <= 0) {
        throw new InvalidArgumentException("Ongeldig taak ID.");
    }

    $userId = (int)($_SESSION['id'] ?? 0);
    if ($checked) {
        $stmt = $conn->prepare("UPDATE Preflight_Checklist SET is_checked = 1, checked_by = ?, checked_at = NOW() WHERE id = ?");
        $stmt->bind_param("ii", $userId, $id);
    } else {
        $stmt = $conn->prepare("UPDATE Preflight_Checklist SET is_checked = 0, checked_by = NULL, checked_at = NULL WHERE id = ?");
        $stmt->bind_param("i", $id);
    }

    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected >= 0) {
        $userFullName = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '')) ?: ($_SESSION['username'] ?? 'Beheerder');
        
        if (function_exists('recordAuditLog')) {
            recordAuditLog(
                $conn, 
                'system', 
                'checklist_toggle', 
                "Checklist taak #{$id} gemarkeerd als " . ($checked ? 'voltooid' : 'open') . " door {$userFullName}",
                ['severity' => 'info', 'target_type' => 'checklist', 'target_id' => (string)$id]
            );
        }

        echo json_encode([
            'ok' => true,
            'id' => $id,
            'is_checked' => (bool)$checked,
            'checked_by_name' => $checked ? $userFullName : null,
            'checked_at' => $checked ? date('d-m-Y H:i') : null
        ]);
    } else {
        throw new RuntimeException("Bijwerken van checklist taak is mislukt.");
    }
}

/**
 * Add a custom task to the checklist
 */
function handle_add_check_item(mysqli $conn): void {
    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? 'general');
    $description = trim($_POST['description'] ?? '');

    if (empty($title)) {
        throw new InvalidArgumentException("Taaktitel mag niet leeg zijn.");
    }

    if (!in_array($category, ['dispatch', 'fleet', 'comms', 'general'], true)) {
        $category = 'general';
    }

    $stmt = $conn->prepare("
        INSERT INTO Preflight_Checklist (category, title, description, is_checked, is_custom, sort_order) 
        VALUES (?, ?, ?, 0, 1, 999)
    ");
    $stmt->bind_param("sss", $category, $title, $description);
    $stmt->execute();
    $newId = $stmt->insert_id;
    $stmt->close();

    if (function_exists('recordAuditLog')) {
        recordAuditLog(
            $conn,
            'system',
            'checklist_add',
            "Nieuwe checklist taak toegevoegd: '{$title}' ({$category})",
            ['severity' => 'info', 'target_type' => 'checklist', 'target_id' => (string)$newId]
        );
    }

    echo json_encode([
        'ok' => true,
        'message' => 'Taak succesvol toegevoegd.',
        'item' => [
            'id' => $newId,
            'category' => $category,
            'title' => $title,
            'description' => $description,
            'is_checked' => false,
            'checked_by_name' => null,
            'checked_at' => null,
            'is_custom' => true
        ]
    ]);
}

/**
 * Delete a custom checklist item
 */
function handle_delete_check_item(mysqli $conn): void {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        throw new InvalidArgumentException("Ongeldig taak ID.");
    }

    $stmt = $conn->prepare("DELETE FROM Preflight_Checklist WHERE id = ? AND is_custom = 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $deleted = $stmt->affected_rows > 0;
    $stmt->close();

    if (!$deleted) {
        throw new RuntimeException("Kan deze taak niet verwijderen (alleen zelf toegevoegde taken kunnen worden verwijderd).");
    }

    if (function_exists('recordAuditLog')) {
        recordAuditLog(
            $conn,
            'system',
            'checklist_delete',
            "Checklist taak #{$id} verwijderd",
            ['severity' => 'info', 'target_type' => 'checklist', 'target_id' => (string)$id]
        );
    }

    echo json_encode(['ok' => true, 'id' => $id]);
}

/**
 * Reset all checklist tasks to unchecked state
 */
function handle_reset_checklist(mysqli $conn): void {
    $conn->query("UPDATE Preflight_Checklist SET is_checked = 0, checked_by = NULL, checked_at = NULL");

    if (function_exists('recordAuditLog')) {
        recordAuditLog(
            $conn,
            'system',
            'checklist_reset',
            "Preflight checklist gereset naar beginstatus voor nieuw seizoen",
            ['severity' => 'warning']
        );
    }

    echo json_encode(['ok' => true, 'message' => 'Checklist succesvol gereset naar beginstatus.']);
}

/**
 * Create dedicated standalone edition backup package (.tar.gz), record in Archived_Editions,
 * and wipe operational game tables for a fresh season start.
 * RESTRICTED TO SUPERADMINS (priv >= 3).
 */
function handle_archive_and_reset(mysqli $conn, string $webroot, string $backupDir): void {
    $userPriv = (int)($_SESSION['priv'] ?? 0);
    if ($userPriv < 3) {
        http_response_code(403);
        throw new RuntimeException("Alleen Superadmins (niveau 3) mogen een seizoensarchivering en data-reset uitvoeren.");
    }

    $editionName = trim($_POST['edition_name'] ?? '');
    $editionYear = (int)($_POST['edition_year'] ?? date('Y'));
    $confirmText = trim($_POST['confirm_text'] ?? '');

    if (empty($editionName)) {
        throw new InvalidArgumentException("Vul een geldige editienaam in (bijv. 'Jotihunt 2025').");
    }
    if ($editionYear < 2000 || $editionYear > 2100) {
        throw new InvalidArgumentException("Ongeldig editiejaar.");
    }
    if ($confirmText !== 'RESET') {
        throw new InvalidArgumentException("Typ exact 'RESET' in ter bevestiging van de data-reset.");
    }

    // 1. Snapshot row counts before wipe
    $rowCounts = [];
    foreach (OPERATIONAL_TABLES as $tbl => $label) {
        $res = $conn->query("SELECT COUNT(*) as cnt FROM `{$tbl}`");
        $rowCounts[$tbl] = $res ? (int)($res->fetch_assoc()['cnt'] ?? 0) : 0;
    }

    // 2. Remember own scouting group details before wipe so it survives
    $ownGroupId = (int)($GLOBALS['site_settings']['GROUP_ID'] ?? 0);
    $ownGroupData = null;
    if ($ownGroupId > 0) {
        $gq = $conn->prepare("SELECT * FROM Groepen WHERE id = ?");
        $gq->bind_param("i", $ownGroupId);
        $gq->execute();
        $ownGroupData = $gq->get_result()->fetch_assoc();
        $gq->close();
    }

    // 3. Create dedicated edition archive package (.tar.gz)
    $cleanSlug = preg_replace('/[^a-zA-Z0-9_\-]/', '_', strtolower($editionName));
    $editionType = "edition_{$cleanSlug}";
    $backupRes = create_full_backup($conn, $webroot, $backupDir, $editionType);

    if (empty($backupRes['ok']) || empty($backupRes['filename'])) {
        throw new RuntimeException("Maken van editie back-up archief is mislukt.");
    }

    $backupFilename = $backupRes['filename'];
    $backupPath = $backupRes['path'];
    $backupSize = filesize($backupPath);

    // 4. Record archived edition in Archived_Editions
    $userId = (int)($_SESSION['id'] ?? 0);
    $rcJson = json_encode($rowCounts);
    $insStmt = $conn->prepare("
        INSERT INTO Archived_Editions (edition_name, edition_year, backup_filename, file_size, row_counts, archived_by, archived_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    $insStmt->bind_param("sisisi", $editionName, $editionYear, $backupFilename, $backupSize, $rcJson, $userId);
    $insStmt->execute();
    $editionId = $insStmt->insert_id;
    $insStmt->close();

    // 5. Clean slate: truncate operational tables safely with foreign key checks toggled
    $conn->query("SET FOREIGN_KEY_CHECKS = 0;");
    foreach (array_keys(OPERATIONAL_TABLES) as $tbl) {
        $conn->query("TRUNCATE TABLE `{$tbl}`;");
    }
    $conn->query("SET FOREIGN_KEY_CHECKS = 1;");

    // 6. Restore own group record if it existed, or default group 1
    if ($ownGroupData) {
        $rgStmt = $conn->prepare("
            INSERT INTO Groepen (id, naam, lat, lon, deelgebied, gebruikersnaam, straat, huisnummer, postal_code, plaats, url)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $rgStmt->bind_param(
            "isddsssssss",
            $ownGroupData['id'],
            $ownGroupData['naam'],
            $ownGroupData['lat'],
            $ownGroupData['lon'],
            $ownGroupData['deelgebied'],
            $ownGroupData['gebruikersnaam'],
            $ownGroupData['straat'],
            $ownGroupData['huisnummer'],
            $ownGroupData['postal_code'],
            $ownGroupData['plaats'],
            $ownGroupData['url']
        );
        $rgStmt->execute();
        $rgStmt->close();

        // Also restore 0-point row for own group in Punten
        $pStmt = $conn->prepare("INSERT INTO Punten (groep_id, hunts, tegenhunts, opdrachten, foto_opdrachten, hints, strafpunten, bonus) VALUES (?, 0, 0, 0, 0, 0, 0, 0)");
        $pStmt->bind_param("i", $ownGroupData['id']);
        $pStmt->execute();
        $pStmt->close();
    } else {
        // Default group fallback
        $conn->query("INSERT IGNORE INTO Groepen (`id`, `naam`, `lat`, `lon`, `deelgebied`, `gebruikersnaam`, `straat`, `huisnummer`, `postal_code`, `plaats`, `url`) VALUES (1, 'Mijn Scoutinggroep', 52.00000, 5.90000, 'Alpha', 'placeholder', 'Dorpsstraat', '1', '1234 AB', 'Arnhem', '');");
        $conn->query("INSERT IGNORE INTO Punten (`groep_id`, `hunts`, `tegenhunts`, `opdrachten`, `foto_opdrachten`, `hints`, `strafpunten`, `bonus`) VALUES (1, 0, 0, 0, 0, 0, 0, 0);");
    }

    // 7. Prune old hunt and counterhunt media files (keep .gitkeep)
    $mediaDirs = [$webroot . '/media/hunts', $webroot . '/media/tegenhunt'];
    $prunedMediaCount = 0;
    foreach ($mediaDirs as $md) {
        if (is_dir($md)) {
            $files = glob($md . '/*');
            if ($files) {
                foreach ($files as $f) {
                    if (is_file($f) && basename($f) !== '.gitkeep') {
                        @unlink($f);
                        $prunedMediaCount++;
                    }
                }
            }
        }
    }

    // 8. Log security audit log
    if (function_exists('recordAuditLog')) {
        recordAuditLog(
            $conn,
            'system',
            'archive_season',
            "Seizoensarchief '{$editionName}' ({$editionYear}) aangemaakt: bestand {$backupFilename} ({$backupRes['size_formatted']}). Operationele tabellen gereset en {$prunedMediaCount} foto's opgeschoond.",
            ['severity' => 'security', 'target_type' => 'edition', 'target_id' => (string)$editionId]
        );
    }

    echo json_encode([
        'ok' => true,
        'message' => "Editie '{$editionName}' succesvol gearchiveerd! Data gereset voor het nieuwe jachtseizoen.",
        'backup_filename' => $backupFilename,
        'backup_size' => $backupRes['size_formatted'],
        'pruned_media_count' => $prunedMediaCount,
        'archived_rows' => $rowCounts
    ]);
}

/**
 * Handle direct download of an archived edition .tar.gz
 */
function handle_download_edition(string $backupDir): void {
    $filename = basename($_GET['file'] ?? '');
    if (empty($filename) || !str_ends_with(strtolower($filename), '.tar.gz')) {
        http_response_code(400);
        echo "Ongeldig bestandsformaat.";
        exit();
    }

    $filePath = $backupDir . '/' . $filename;
    if (!file_exists($filePath)) {
        http_response_code(404);
        echo "Editie archiefbestand niet gevonden.";
        exit();
    }

    download_backup($backupDir, $filename);
}
