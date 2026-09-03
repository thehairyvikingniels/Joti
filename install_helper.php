<?php
/**
 * install_helper.php — AJAX Backend Controller for Jotify Web Setup Wizard
 */

header('Content-Type: application/json; charset=utf-8');

// If already installed, block all access
$lockFile = __DIR__ . '/.installed';
if (file_exists($lockFile)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Jotify is reeds geïnstalleerd. De installer is vergrendeld.']);
    exit();
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Error response helper
function sendError(string $message, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message]);
    exit();
}

// Success response helper
function sendSuccess(array $data = []): void {
    echo json_encode(array_merge(['ok' => true], $data));
    exit();
}

switch ($action) {

    // =========================================================================
    // 1. Check System Requirements
    // =========================================================================
    case 'check_requirements':
        $phpVersion = PHP_VERSION;
        $phpOk = version_compare($phpVersion, '8.1.0', '>=');

        $requiredExtensions = [
            'mysqli'   => 'MySQLi database driver',
            'curl'     => 'cURL HTTP client (Jotihunt & Telegram API)',
            'gd'       => 'GD Image Library (Avatar crop/resize)',
            'mbstring' => 'Multi-byte string handling',
            'xml'      => 'XML / DOMDocument parsing',
            'gmp'      => 'GMP Math Library (Web Push encryption)',
            'intl'     => 'Intl Internationalization',
            'zip'      => 'Zip archive handling',
            'json'     => 'JSON encoding/decoding',
            'session'  => 'PHP Session support'
        ];

        $extensionResults = [];
        $allExtensionsOk = true;
        foreach ($requiredExtensions as $ext => $desc) {
            $loaded = extension_loaded($ext);
            $extensionResults[$ext] = [
                'name' => $ext,
                'description' => $desc,
                'loaded' => $loaded
            ];
            if (!$loaded) {
                // Fallback check: bcmath can satisfy web-push if gmp is missing
                if ($ext === 'gmp' && extension_loaded('bcmath')) {
                    $extensionResults[$ext]['loaded'] = true;
                    $extensionResults[$ext]['note'] = 'Using bcmath fallback';
                } else {
                    $allExtensionsOk = false;
                }
            }
        }

        // Check Directory Write Permissions
        $dirsToCheck = [
            __DIR__ => 'Root Directory (voor dblogin.php & .installed)',
            __DIR__ . '/media' => 'Media directory',
            __DIR__ . '/media/profiles' => 'Profile avatars',
            __DIR__ . '/media/hunts' => 'Hunt photos',
            __DIR__ . '/media/tegenhunt' => 'Counterhunt photos',
            __DIR__ . '/services' => 'Services directory (MTProto session)'
        ];

        $dirResults = [];
        $allDirsOk = true;
        foreach ($dirsToCheck as $dir => $label) {
            if (!file_exists($dir)) {
                @mkdir($dir, 0775, true);
            }
            $isWritable = is_writable($dir);
            $dirResults[] = [
                'path' => $dir,
                'label' => $label,
                'writable' => $isWritable
            ];
            if (!$isWritable) {
                $allDirsOk = false;
            }
        }

        // Memory & Disk
        $diskFree = @disk_free_space(__DIR__);
        $diskFreeMb = $diskFree ? round($diskFree / (1024 * 1024)) : null;
        $diskOk = ($diskFreeMb === null || $diskFreeMb >= 200);

        // Check MariaDB / MySQL Socket
        $mysqlSocketAvailable = file_exists('/run/mysqld/mysqld.sock') || file_exists('/var/run/mysqld/mysqld.sock') || @fsockopen('127.0.0.1', 3306, $errno, $errstr, 1);

        sendSuccess([
            'php_version' => $phpVersion,
            'php_ok' => $phpOk,
            'extensions' => $extensionResults,
            'extensions_ok' => $allExtensionsOk,
            'directories' => $dirResults,
            'directories_ok' => $allDirsOk,
            'disk_free_mb' => $diskFreeMb,
            'disk_ok' => $diskOk,
            'mysql_available' => (bool)$mysqlSocketAvailable,
            'all_passed' => ($phpOk && $allExtensionsOk && $allDirsOk && $diskOk)
        ]);
        break;

    // =========================================================================
    // 2. Setup Database
    // =========================================================================
    case 'setup_database':
        $dbMode       = trim($_POST['db_mode'] ?? 'create_new');
        $dropExisting = !empty($_POST['drop_existing']);
        $dbHost       = trim($_POST['db_host'] ?? 'localhost');
        $dbName       = trim($_POST['db_name'] ?? 'jotihunt');
        $dbUser       = trim($_POST['db_user'] ?? 'jotify');
        $dbPass       = trim($_POST['db_pass'] ?? '');
        $rootUser     = trim($_POST['root_user'] ?? 'root');
        $rootPass     = trim($_POST['root_pass'] ?? '');

        if (empty($dbName) || empty($dbUser) || empty($dbPass)) {
            sendError('Vul alle verplichte databasevelden in.');
        }

        // Validate identifiers to prevent SQL injection in DDL
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $dbName) || !preg_match('/^[a-zA-Z0-9_]+$/', $dbUser)) {
            sendError('Database- en gebruikersnamen mogen alleen letters, cijfers en underscores bevatten.');
        }

        mysqli_report(MYSQLI_REPORT_OFF);

        if ($dbMode === 'create_new') {
            // 1. Connect as root
            $rootConn = @new mysqli($dbHost, $rootUser, $rootPass);

            if ($rootConn->connect_error) {
                // Try connecting via 127.0.0.1 if localhost failed
                $fallbackHost = ($dbHost === 'localhost') ? '127.0.0.1' : 'localhost';
                $rootConn = @new mysqli($fallbackHost, $rootUser, $rootPass);
            }

            if ($rootConn->connect_error) {
                sendError('Kon geen verbinding maken als database beheerder (' . $rootUser . '): ' . $rootConn->connect_error . '. Gebruik eventueel de modus "Bestaande Database Gebruiken".');
            }

            $rootConn->set_charset('utf8mb4');

            // 2. Create Clean Database
            $rootConn->query("DROP DATABASE IF EXISTS `$dbName`;");
            if (!$rootConn->query("CREATE DATABASE `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;")) {
                sendError('Fout bij aanmaken database: ' . $rootConn->error);
            }

            // 3. Create User & Grant Privileges
            $escapedPass = $rootConn->real_escape_string($dbPass);
            $rootConn->query("CREATE USER IF NOT EXISTS '$dbUser'@'localhost' IDENTIFIED BY '$escapedPass';");
            $rootConn->query("ALTER USER '$dbUser'@'localhost' IDENTIFIED BY '$escapedPass';");
            $rootConn->query("GRANT ALL PRIVILEGES ON `$dbName`.* TO '$dbUser'@'localhost';");
            
            // Also grant on 127.0.0.1
            $rootConn->query("CREATE USER IF NOT EXISTS '$dbUser'@'127.0.0.1' IDENTIFIED BY '$escapedPass';");
            $rootConn->query("ALTER USER '$dbUser'@'127.0.0.1' IDENTIFIED BY '$escapedPass';");
            $rootConn->query("GRANT ALL PRIVILEGES ON `$dbName`.* TO '$dbUser'@'127.0.0.1';");
            $rootConn->query("FLUSH PRIVILEGES;");
            $rootConn->close();
        } else {
            // Mode: use_existing — Connect directly with application credentials
            $testConn = @new mysqli($dbHost, $dbUser, $dbPass, $dbName);
            if ($testConn->connect_error) {
                $fallbackHost = ($dbHost === 'localhost') ? '127.0.0.1' : 'localhost';
                $testConn = @new mysqli($fallbackHost, $dbUser, $dbPass, $dbName);
            }

            if ($testConn->connect_error) {
                sendError('Kon niet verbinden met database "' . $dbName . '" als gebruiker "' . $dbUser . '": ' . $testConn->connect_error);
            }

            $testConn->set_charset('utf8mb4');

            // Optional: Drop existing tables if requested for a clean slate
            if ($dropExisting) {
                $testConn->query("SET FOREIGN_KEY_CHECKS = 0;");
                $tablesRes = $testConn->query("SHOW TABLES");
                if ($tablesRes) {
                    while ($row = $tablesRes->fetch_array()) {
                        $tbl = $row[0];
                        $testConn->query("DROP TABLE IF EXISTS `$tbl`");
                    }
                    $tablesRes->free();
                }
                $testConn->query("SET FOREIGN_KEY_CHECKS = 1;");
            }
            $testConn->close();
        }

        // 4. Import DB/createDB.sql schema
        $sqlPath = __DIR__ . '/DB/createDB.sql';
        if (!file_exists($sqlPath)) {
            sendError('Database schema bestand niet gevonden op ' . $sqlPath);
        }

        // Try importing via CLI mariadb / mysql first for 100% fidelity
        $cliHost = ($dbHost === 'localhost') ? '127.0.0.1' : $dbHost;
        $importCmd = "mariadb -h " . escapeshellarg($cliHost) . " -u " . escapeshellarg($dbUser) . " -p" . escapeshellarg($dbPass) . " " . escapeshellarg($dbName) . " < " . escapeshellarg($sqlPath) . " 2>&1";
        exec($importCmd, $importOut, $importRet);

        // Verify connection and tables as application user
        $userConn = @new mysqli($dbHost, $dbUser, $dbPass, $dbName);
        if ($userConn->connect_error) {
            $userConn = @new mysqli('127.0.0.1', $dbUser, $dbPass, $dbName);
        }
        if ($userConn->connect_error) {
            sendError('Verbinding met databasegebruiker mislukt na import: ' . $userConn->connect_error);
        }
        $userConn->set_charset('utf8mb4');

        // Fallback: If CLI import produced no tables, run multi_query
        $resCheck = $userConn->query("SHOW TABLES LIKE 'Site_Instellingen'");
        if (!$resCheck || $resCheck->num_rows === 0) {
            $sqlContent = file_get_contents($sqlPath);
            if ($userConn->multi_query($sqlContent)) {
                do {
                    if ($result = $userConn->store_result()) {
                        $result->free();
                    }
                } while ($userConn->more_results() && $userConn->next_result());
            }
        }
        $userConn->close();

        // 6. Write dblogin.php
        $dbloginContent = "<?php\n"
            . "// Jotify Database Configuration\n"
            . "\$servername = " . var_export($dbHost, true) . ";\n"
            . "\$username = " . var_export($dbUser, true) . ";\n"
            . "\$password = " . var_export($dbPass, true) . ";\n"
            . "\$dbname = " . var_export($dbName, true) . ";\n\n"
            . "date_default_timezone_set('Europe/Amsterdam');\n\n"
            . "// Create connection\n"
            . "\$conn = new mysqli(\$servername, \$username, \$password, \$dbname);\n"
            . "if (\$conn->connect_error) {\n"
            . "    die(\"Database connection failed: \" . \$conn->connect_error);\n"
            . "}\n\n"
            . "\$conn->set_charset(\"utf8mb4\");\n\n"
            . "require_once(__DIR__ . '/includes/globals.php');\n";

        if (file_put_contents(__DIR__ . '/dblogin.php', $dbloginContent) === false) {
            sendError('Kon dblogin.php niet opslaan. Controleer de schrijfrechten op de hoofdmap.');
        }
        chmod(__DIR__ . '/dblogin.php', 0640);

        sendSuccess(['message' => 'Database succesvol geïnstalleerd en geconfigureerd!']);
        break;

    // =========================================================================
    // 3. Create First Superadmin Account
    // =========================================================================
    case 'create_admin':
        if (!file_exists(__DIR__ . '/dblogin.php')) {
            sendError('Database is nog niet geconfigureerd.');
        }
        require_once(__DIR__ . '/dblogin.php');

        $voornaam   = trim($_POST['voornaam'] ?? '');
        $achternaam = trim($_POST['achternaam'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        $username   = trim($_POST['username'] ?? '');
        $password   = trim($_POST['password'] ?? '');
        $phone      = trim($_POST['phone'] ?? '0600000000');

        if (empty($voornaam) || empty($achternaam) || empty($email) || empty($username) || empty($password)) {
            sendError('Vul alle verplichte velden in voor het beheerderaccount.');
        }

        if (strlen($password) < 8) {
            sendError('Wachtwoord moet minimaal 8 tekens lang zijn.');
        }

        // Hash password with bcrypt
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
        $apiKey = bin2hex(random_bytes(8));
        $linkCode = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        $priv = 3; // Superadmin

        $stmt = $conn->prepare("INSERT INTO Gebruikers (voornaam, achternaam, email, gebruikersnaam, wachtwoord, phone, api, priv, telegram_link_code, first_login, last_login) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
        if (!$stmt) {
            sendError('Databasefout: ' . $conn->error);
        }
        $stmt->bind_param("sssssssis", $voornaam, $achternaam, $email, $username, $passwordHash, $phone, $apiKey, $priv, $linkCode);
        
        if (!$stmt->execute()) {
            sendError('Kon beheerder niet aanmaken: ' . $stmt->error);
        }
        $adminId = $stmt->insert_id;
        $stmt->close();

        sendSuccess(['admin_id' => $adminId, 'username' => $username]);
        break;

    // =========================================================================
    // 4. Test External API Keys (Mapbox, Telegram, Firebase)
    // =========================================================================
    case 'test_api_key':
        $type = $_POST['type'] ?? '';
        $key  = trim($_POST['key'] ?? '');

        if (empty($key)) {
            sendError('Geen API sleutel of token opgegeven.');
        }

        if ($type === 'mapbox') {
            $url = "https://api.mapbox.com/geocoding/v5/mapbox.places/Arnhem.json?access_token=" . urlencode($key) . "&limit=1";
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_USERAGENT => 'JotifyInstaller/1.0'
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                sendSuccess(['valid' => true, 'message' => 'Mapbox Access Token is geldig en werkend!']);
            } else {
                sendError("Mapbox validatie mislukt (HTTP $httpCode). Controleer je token.");
            }
        } elseif ($type === 'telegram') {
            $url = "https://api.telegram.org/bot" . urlencode($key) . "/getMe";
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_USERAGENT => 'JotifyInstaller/1.0'
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $data = json_decode($response, true);
            if ($httpCode === 200 && !empty($data['ok'])) {
                $botName = $data['result']['first_name'] ?? 'Bot';
                $botUser = $data['result']['username'] ?? '';
                sendSuccess(['valid' => true, 'message' => "Telegram Bot geverifieerd: $botName (@$botUser)"]);
            } else {
                sendError("Telegram Bot Token is ongeldig (HTTP $httpCode). Controleer het token van @BotFather.");
            }
        } elseif ($type === 'firebase') {
            if (strlen($key) >= 20) {
                sendSuccess(['valid' => true, 'message' => 'Firebase API key formaat is geldig.']);
            } else {
                sendError('Firebase API key lijkt te kort.');
            }
        } else {
            sendError('Onbekend API type.');
        }
        break;

    // =========================================================================
    // 5. Scrape Jotihunt.nl Portal for Group Info & Token
    // =========================================================================
    case 'scrape_jotihunt':
        $jotiUser = trim($_POST['joti_user'] ?? '');
        $jotiPass = trim($_POST['joti_pass'] ?? '');

        if (empty($jotiUser) || empty($jotiPass)) {
            sendError('Vul je Jotihunt.nl gebruikersnaam en wachtwoord in.');
        }

        $scraperPath = __DIR__ . '/cron/scraper.py';
        if (!file_exists($scraperPath)) {
            sendError('Scraper script niet gevonden op ' . $scraperPath);
        }

        $command = "python3 " . escapeshellarg($scraperPath) . " " . escapeshellarg($jotiUser) . " " . escapeshellarg($jotiPass) . " 2>&1";
        $scriptOutput = shell_exec($command);

        $jsonStart = strpos($scriptOutput, '{');
        $jsonEnd   = strrpos($scriptOutput, '}');

        if ($jsonStart !== false && $jsonEnd !== false) {
            $jsonString = substr($scriptOutput, $jsonStart, $jsonEnd - $jsonStart + 1);
            $scrapedData = json_decode($jsonString, true);

            if (is_array($scrapedData) && !isset($scrapedData['error'])) {
                $groupId = $scrapedData['group_id'] ?? null;
                $groupName = $scrapedData['group_name'] ?? null;

                if (empty($groupId) && !empty($groupName) && file_exists(__DIR__ . '/dblogin.php')) {
                    require_once(__DIR__ . '/dblogin.php');
                    $likeName = '%' . $groupName . '%';
                    $stmtF = $conn->prepare("SELECT id, naam, url FROM Groepen WHERE naam LIKE ? LIMIT 1");
                    if ($stmtF) {
                        $stmtF->bind_param("s", $likeName);
                        $stmtF->execute();
                        $resF = $stmtF->get_result();
                        if ($rowF = $resF->fetch_assoc()) {
                            $groupId = (int)$rowF['id'];
                            $groupName = $rowF['naam'];
                        }
                        $stmtF->close();
                    }
                }

                sendSuccess([
                    'scraped' => true,
                    'group_name' => $groupName,
                    'group_url' => $scrapedData['group_url'] ?? null,
                    'group_id' => $groupId,
                    'registration_code' => $scrapedData['telegram_code'] ?? $scrapedData['registration_code'] ?? null,
                    'raw' => $scrapedData
                ]);
            }
        }

        sendError('Kon niet inloggen op Jotihunt.nl of geen gegevens gevonden. Controleer je inloggegevens.');
        break;

    // =========================================================================
    // 5b. Fetch Jotihunt Groups List from API
    // =========================================================================
    case 'fetch_jotihunt_groups':
        $groups = [];
        try {
            $url = "https://jotihunt.nl/api/2.0/subscriptions";
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_USERAGENT => 'JotifyInstaller/1.0'
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($response && $httpCode === 200) {
                $json = json_decode($response, true);
                if (isset($json['data']) && is_array($json['data'])) {
                    foreach ($json['data'] as $idx => $g) {
                        $gid = (int)($g['id'] ?? ($idx + 1));
                        $gname = trim($g['name'] ?? '');
                        $gcity = trim($g['city'] ?? '');
                        $garea = trim($g['area'] ?? '');
                        $glat = (float)($g['lat'] ?? 0);
                        $glon = (float)($g['long'] ?? 0);

                        $groups[] = [
                            'id' => $gid,
                            'name' => $gname,
                            'city' => $gcity,
                            'area' => $garea,
                            'lat' => $glat,
                            'lon' => $glon
                        ];
                    }
                }
            }

            // Sync to Groepen table if database is connected
            if (!empty($groups) && file_exists(__DIR__ . '/dblogin.php')) {
                try {
                    require_once(__DIR__ . '/dblogin.php');
                    if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
                        $stmtG = $conn->prepare("INSERT INTO Groepen (id, naam, gebruikersnaam, straat, huisnummer, postal_code, plaats, lat, lon, url, deelgebied) VALUES (?, ?, 'null', '', '', '', ?, ?, ?, '', ?) ON DUPLICATE KEY UPDATE naam = VALUES(naam), plaats = VALUES(plaats), lat = VALUES(lat), lon = VALUES(lon), deelgebied = VALUES(deelgebied)");
                        if ($stmtG) {
                            foreach ($groups as $grp) {
                                $stmtG->bind_param("issdds", $grp['id'], $grp['name'], $grp['city'], $grp['lat'], $grp['lon'], $grp['area']);
                                $stmtG->execute();
                            }
                            $stmtG->close();
                        }
                    }
                } catch (\Throwable $dbEx) {
                    // Ignore DB sync error during fetch; save_settings will handle it
                }
            }
        } catch (\Throwable $e) {
            // Handle error gracefully
        }

        // If no groups found via API, provide default placeholder group
        if (empty($groups)) {
            $groups[] = [
                'id' => 1,
                'name' => 'Mijn Scoutinggroep (Standaard)',
                'city' => 'Arnhem',
                'area' => 'Alpha',
                'lat' => 52.0,
                'lon' => 5.9
            ];
        }

        sendSuccess(['groups' => $groups, 'count' => count($groups)]);
        break;

    // =========================================================================
    // 5c. Upload Group Logo
    // =========================================================================
    case 'upload_logo':
        if (empty($_FILES['logo_file'])) {
            sendError('Geen bestand geüpload.');
        }

        $file = $_FILES['logo_file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            sendError('Uploadfout code: ' . $file['error']);
        }

        if ($file['size'] > 5 * 1024 * 1024) {
            sendError('Bestand mag maximaal 5 MB zijn.');
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExts = ['png', 'jpg', 'jpeg', 'svg', 'webp', 'gif'];
        if (!in_array($ext, $allowedExts)) {
            sendError('Alleen PNG, JPG, JPEG, SVG, WEBP en GIF bestanden zijn toegestaan.');
        }

        $targetDir = __DIR__ . '/media';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $targetFileName = 'groepslogo_' . time() . '.' . $ext;
        $targetPath = $targetDir . '/' . $targetFileName;
        $relativeUrl = 'media/' . $targetFileName;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            sendError('Kon het logo niet opslaan in media map. Controleer maprechten.');
        }

        sendSuccess([
            'message' => 'Logo succesvol geüpload!',
            'file_url' => $relativeUrl
        ]);
        break;

    // =========================================================================
    // 6. Save Site Settings & Preferences
    // =========================================================================
    case 'save_settings':
        if (!file_exists(__DIR__ . '/dblogin.php')) {
            sendError('Database is nog niet geconfigureerd.');
        }
        require_once(__DIR__ . '/dblogin.php');

        $groupId   = (int)($_POST['group_id'] ?? 1);
        if ($groupId <= 0) $groupId = 1;
        $groupName = trim($_POST['group_name'] ?? 'Mijn Scoutinggroep');
        $groupUrl  = trim($_POST['group_url'] ?? 'https://scouting.nl');
        $logoUrl   = trim($_POST['group_logo_large_url'] ?? 'media/geusje_bevosd.png');

        $settingsToSave = [
            'GROUP_ID'              => (string)$groupId,
            'GROUP_NAME'            => $groupName,
            'GROUP_URL'             => $groupUrl,
            'GROUP_LOGO_LARGE_URL'  => $logoUrl,
            'GROUP_LOGO_SMALL_URL'  => $logoUrl,
            'API_KEY_MAPBOX'        => trim($_POST['api_key_mapbox'] ?? 'jouw_mapbox_api_key_hier'),
            'API_KEY_FIREBASE'      => trim($_POST['api_key_firebase'] ?? 'jouw_firebase_api_key_hier'),
            'TELEGRAM_BOT_TOKEN'    => trim($_POST['telegram_bot_token'] ?? '123456789:ABCdefGHIjklMNOpqrSTUvwxYZ'),
            'TELEGRAM_API_ID'       => trim($_POST['telegram_api_id'] ?? '0'),
            'TELEGRAM_API_HASH'     => trim($_POST['telegram_api_hash'] ?? 'placeholder_api_hash'),
            'TELEGRAM_GROUP_CHAT_ID'=> trim($_POST['telegram_group_chat_id'] ?? '-1001234567890'),
            'TELEGRAM_INGEST_SECRET'=> bin2hex(random_bytes(24)),
            'GAME_STARTDATE'        => trim($_POST['game_startdate'] ?? '2026-10-17T10:00:00+02:00'),
            'GAME_ENDDATE'          => trim($_POST['game_enddate'] ?? '2026-10-18T12:00:00+02:00'),
            'FOXEXCHANGE_STARTDATE' => trim($_POST['foxexchange_startdate'] ?? '2026-10-17T22:45:00+02:00'),
            'FOXEXCHANGE_ENDDATE'   => trim($_POST['foxexchange_enddate'] ?? '2026-10-17T23:15:00+02:00'),
            'FOX_NAMES'             => trim($_POST['fox_names'] ?? 'Alpha,Bravo,Charlie,Delta,Echo,Foxtrot,Golf,Hotel,Oscar'),
            'FOX_COLORS'            => trim($_POST['fox_colors'] ?? '#9829FF,#36D12B,#FF8A00,#F5F02C,#FFA12E,#F52E2B,#FF6F6F,#00BFA5,#333333')
        ];

        // Ensure this group exists in Groepen and Punten tables so queries never fail
        $stmtG = $conn->prepare("INSERT INTO Groepen (id, naam, gebruikersnaam, straat, huisnummer, postal_code, plaats, lat, lon, url, deelgebied) VALUES (?, ?, 'null', 'Dorpsstraat', '1', '1234 AB', 'Arnhem', 52.00000, 5.90000, ?, 'Alpha') ON DUPLICATE KEY UPDATE naam = VALUES(naam), url = VALUES(url)");
        if ($stmtG) {
            $stmtG->bind_param("iss", $groupId, $groupName, $groupUrl);
            $stmtG->execute();
            $stmtG->close();
        }

        $stmtP = $conn->prepare("INSERT INTO Punten (groep_id, hunts, tegenhunts, opdrachten, foto_opdrachten, hints, strafpunten, bonus) VALUES (?, 0, 0, 0, 0, 0, 0, 0) ON DUPLICATE KEY UPDATE groep_id = VALUES(groep_id)");
        if ($stmtP) {
            $stmtP->bind_param("i", $groupId);
            $stmtP->execute();
            $stmtP->close();
        }

        // Store Jotihunt Credentials if provided
        $jotiUser = trim($_POST['joti_user'] ?? '');
        $jotiPass = trim($_POST['joti_pass'] ?? '');
        if (!empty($jotiUser) && !empty($jotiPass)) {
            $settingsToSave['JOTIHUNT_CREDENTIALS'] = json_encode(['username' => $jotiUser, 'password' => $jotiPass]);
        }

        $stmt = $conn->prepare("INSERT INTO Site_Instellingen (Instelling, Waarde, Omschrijving) VALUES (?, ?, '') ON DUPLICATE KEY UPDATE Waarde = VALUES(Waarde)");
        foreach ($settingsToSave as $instelling => $waarde) {
            $stmt->bind_param("ss", $instelling, $waarde);
            $stmt->execute();
        }
        $stmt->close();

        sendSuccess(['message' => 'Instellingen succesvol opgeslagen!']);
        break;

    // =========================================================================
    // 7. Setup Background Crontab & Cronjobs Table
    // =========================================================================
    case 'setup_crontab':
        if (!file_exists(__DIR__ . '/dblogin.php')) {
            sendError('Database is nog niet geconfigureerd.');
        }
        require_once(__DIR__ . '/dblogin.php');

        $enableCron = !empty($_POST['enable_cron']);
        $intervalSeconds = max(10, min(60, (int)($_POST['cron_interval'] ?? 20)));

        // 1. Ensure Cronjobs table has standard jobs
        $defaultJobs = [
            ['areas', 1, 'cron/areas.php', 'Vossen statussen synchroniseren met Jotihunt.nl API', 30],
            ['articles', 1, 'cron/articles.php', 'Nieuws, hints en opdrachten synchroniseren', 60],
            ['notifications', 1, 'cron/notifications.php', 'Push notificaties en Telegram berichten wachtrij verwerken', 40],
            ['subscriptions', 1, 'cron/subscriptions.php', 'Deelnemende scoutinggroepen synchroniseren', 300],
            ['welcome', 0, 'cron/welcome.php', 'Automatisch welkomstbericht bij nadering clubhuis', 60],
            ['jotiPortal', 1, 'cron/scraper_helper.php', 'Punten, hunts en telegram registratiecode scrapen', 180],
            ['auto_backup', 1, 'cron/backup.php', 'Automatische database- en mediaback-up met getrapte bewaartermijn', 3600]
        ];

        $stmt = $conn->prepare("INSERT INTO Cronjobs (name, enabled, URL, description, `interval`) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE enabled = VALUES(enabled), `interval` = VALUES(`interval`)");
        foreach ($defaultJobs as $job) {
            $stmt->bind_param("sissi", $job[0], $job[1], $job[2], $job[3], $job[4]);
            $stmt->execute();
        }
        $stmt->close();

        // 2. Generate crontab for www-data
        if ($enableCron) {
            $webroot = __DIR__;
            $cronIndex = $webroot . '/cron/index.php';
            
            // Build sub-minute cron intervals (e.g. for 20s: 0s, 20s, 40s)
            $crontabLines = ["# Jotify Master Cron Runner (Every {$intervalSeconds}s)"];
            for ($offset = 0; $offset < 60; $offset += $intervalSeconds) {
                if ($offset === 0) {
                    $crontabLines[] = "* * * * * php $cronIndex > /dev/null 2>&1";
                } else {
                    $crontabLines[] = "* * * * * sleep $offset; php $cronIndex > /dev/null 2>&1";
                }
            }
            $crontabContent = implode("\n", $crontabLines) . "\n";

            // Write temporary crontab file and install for current user or www-data
            $tmpCronFile = tempnam(sys_get_temp_dir(), 'jotify_cron');
            file_put_contents($tmpCronFile, $crontabContent);
            
            // Try installing crontab for www-data, fallback to crontab command
            exec("crontab -u www-data " . escapeshellarg($tmpCronFile) . " 2>&1", $out, $ret);
            if ($ret !== 0) {
                exec("crontab " . escapeshellarg($tmpCronFile) . " 2>&1");
            }
            @unlink($tmpCronFile);
        }

        sendSuccess([
            'cron_enabled' => $enableCron,
            'interval' => $intervalSeconds,
            'message' => 'Achtergrondtaken en crontab succesvol ingesteld!'
        ]);
        break;

    // =========================================================================
    // 8. Finalize Installation & Create Lock File
    // =========================================================================
    case 'finalize_installation':
        $lockData = [
            'installed_at' => date('Y-m-d H:i:s'),
            'version' => '2026.09.0',
            'installer' => 'Jotify Automated Web Installer'
        ];

        if (file_put_contents($lockFile, json_encode($lockData, JSON_PRETTY_PRINT)) === false) {
            sendError('Kon installer vergrendelbestand (.installed) niet aanmaken.');
        }

        sendSuccess([
            'message' => 'Jotify installatie is succesvol voltooid!',
            'redirect' => '/login'
        ]);
        break;

    default:
        sendError('Ongeldige actie.');
        break;
}
