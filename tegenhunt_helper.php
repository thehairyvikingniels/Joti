<?php
// tegenhunt_helper.php ??? AJAX router and API endpoint for Tegenhunt session management, live GPS breadcrumbs, and sticker submission.
require_once(__DIR__ . '/includes/auth.php');
require_once(__DIR__ . '/includes/helpers.php');
require_once(__DIR__ . '/includes/db.php');

header('Content-Type: application/json; charset=utf-8');

$groupId = intval($site_settings['GROUP_ID'] ?? 0);
$groupCoords = getMyGroupCoordinates($conn, $groupId);
$activeTegenhunt = getActiveTegenhunt($conn);
$userId = $_SESSION['id'] ?? 0;
$userPriv = $_SESSION['priv'] ?? 0;

// 1. Status Polling Endpoint
if (isset($_GET['status'])) {
    echo json_encode([
        'active' => ($activeTegenhunt !== null),
        'session' => $activeTegenhunt,
        'home' => $groupCoords,
        'user' => [
            'id' => $userId,
            'first_name' => $_SESSION['voornaam'] ?? '',
            'priv' => $userPriv
        ]
    ]);
    exit();
}

// 2. Start Tegenhunt Session (Admins Priv 2+ or Token API)
if (isset($_POST['start']) || isset($_GET['start'])) {
    if ($userPriv < 2) {
        http_response_code(403);
        echo json_encode(['error' => 'Geen admin rechten.']);
        exit();
    }

    $direction = strtoupper(trim($_POST['direction'] ?? $_GET['direction'] ?? 'N'));
    $compassMap = [
        'N' => 0, 'NNO' => 22, 'NNE' => 22, 'NO' => 45, 'NE' => 45,
        'ONO' => 67, 'ENE' => 67, 'O' => 90, 'E' => 90, 'OZO' => 112,
        'ESE' => 112, 'ZO' => 135, 'SE' => 135, 'ZZO' => 157, 'SSE' => 157,
        'Z' => 180, 'S' => 180, 'ZZW' => 202, 'SSW' => 202, 'ZW' => 225,
        'SW' => 225, 'WZW' => 247, 'WSW' => 247, 'W' => 270, 'WNW' => 292,
        'NW' => 315, 'NNW' => 337
    ];
    $degrees = isset($_POST['degrees']) ? intval($_POST['degrees']) : ($compassMap[$direction] ?? 0);
    $message = trim($_POST['message'] ?? $_GET['message'] ?? '');
    $durationMin = intval($_POST['duration'] ?? $_GET['duration'] ?? 30);
    if ($durationMin <= 0 || $durationMin > 120) $durationMin = 30;

    // Expire any existing active sessions
    $conn->query("UPDATE Tegenhunt_Sessions SET status = 'expired' WHERE status = 'active'");

    $startTime = date('Y-m-d H:i:s');
    $endTime = date('Y-m-d H:i:s', time() + ($durationMin * 60));

    $stmt = $conn->prepare("INSERT INTO Tegenhunt_Sessions (start_time, end_time, wind_direction, compass_degrees, message, status) VALUES (?, ?, ?, ?, ?, 'active')");
    $stmt->bind_param("sssis", $startTime, $endTime, $direction, $degrees, $message);
    
    if ($stmt->execute()) {
        $sessionId = $stmt->insert_id;
        $stmt->close();

        // Broadcast Web Push Notification
        send_push_notification(
            'ALL',
            'TEGENHUNT GESTART',
            "Richting: {$direction}! Zoek binnen 450m.",
            '/tegenhunt',
            'tegenhunt/start',
            null,
            'tegenhunt'
        );

        echo json_encode(['success' => true, 'session_id' => $sessionId]);
    } else {
        echo json_encode(['error' => 'Database fout: ' . $stmt->error]);
        $stmt->close();
    }
    exit();
}

// 3. Stop / Cancel Tegenhunt Session (Admins Priv 2+)
if (isset($_POST['stop']) || isset($_GET['stop'])) {
    if ($userPriv < 2) {
        http_response_code(403);
        echo json_encode(['error' => 'Geen admin rechten.']);
        exit();
    }
    $status = ($_POST['status'] ?? $_GET['status'] ?? 'cancelled') === 'found' ? 'found' : 'cancelled';
    $conn->query("UPDATE Tegenhunt_Sessions SET status = '{$status}' WHERE status = 'active'");
    echo json_encode(['success' => true]);
    exit();
}

// 4. Ingest Searcher GPS Breadcrumb
if (isset($_GET['lat']) && isset($_GET['lon'])) {
    $lat = floatval($_GET['lat']);
    $lon = floatval($_GET['lon']);
    $accuracy = floatval($_GET['accuracy'] ?? 10.0);
    $time = date('Y-m-d H:i:s');

    // Update Gebruikers location
    $stmt_u = $conn->prepare("UPDATE Gebruikers SET lat=?, lon=?, geotijd=? WHERE id=?");
    $stmt_u->bind_param("sssi", $lat, $lon, $time, $userId);
    $stmt_u->execute();
    $stmt_u->close();

    // If active Tegenhunt session exists, record breadcrumb if accuracy <= 25m
    if ($activeTegenhunt && $accuracy <= 25.0) {
        recordTegenhuntBreadcrumb($conn, (int)$activeTegenhunt['id'], $userId, $lat, $lon, $accuracy);
    }

    echo json_encode(['success' => true]);
    exit();
}

// 5. Fetch Breadcrumbs for Active Session
if (isset($_GET['breadcrumbs'])) {
    if (!$activeTegenhunt) {
        echo json_encode(['searchers' => []]);
        exit();
    }

    $sessionId = (int)$activeTegenhunt['id'];
    $stmt = $conn->prepare("
        SELECT b.user_id, g.voornaam, g.achternaam, g.profile_picture, b.lat, b.lon, b.accuracy, b.recorded_at
        FROM Tegenhunt_Breadcrumbs b
        JOIN Gebruikers g ON b.user_id = g.id
        WHERE b.session_id = ?
        ORDER BY b.recorded_at ASC
    ");
    $stmt->bind_param("i", $sessionId);
    $stmt->execute();
    $res = $stmt->get_result();
    
    $searchers = [];
    $colors = ['#ef4444', '#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#06b6d4', '#84cc16'];
    
    while ($row = $res->fetch_assoc()) {
        $uid = (int)$row['user_id'];
        if (!isset($searchers[$uid])) {
            $colorIdx = $uid % count($colors);
            $searchers[$uid] = [
                'user_id' => $uid,
                'name' => ucfirst($row['voornaam']),
                'full_name' => ucfirst($row['voornaam']) . ' ' . ucfirst(substr($row['achternaam'], 0, 1)) . '.',
                'profile_picture' => $row['profile_picture'],
                'color' => $colors[$colorIdx],
                'latest_lat' => (float)$row['lat'],
                'latest_lon' => (float)$row['lon'],
                'latest_time' => $row['recorded_at'],
                'trail' => []
            ];
        }
        $searchers[$uid]['latest_lat'] = (float)$row['lat'];
        $searchers[$uid]['latest_lon'] = (float)$row['lon'];
        $searchers[$uid]['latest_time'] = $row['recorded_at'];
        $searchers[$uid]['trail'][] = [(float)$row['lon'], (float)$row['lat']];
    }
    $stmt->close();

    // Also include active users from Gebruikers who reported within 15 minutes
    $stmt_active_users = $conn->query("
        SELECT id, voornaam, achternaam, profile_picture, lat, lon, geotijd 
        FROM Gebruikers 
        WHERE lat IS NOT NULL AND lon IS NOT NULL AND geotijd >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
    ");
    if ($stmt_active_users) {
        while ($u = $stmt_active_users->fetch_assoc()) {
            $uid = (int)$u['id'];
            if (!isset($searchers[$uid])) {
                $colorIdx = $uid % count($colors);
                $searchers[$uid] = [
                    'user_id' => $uid,
                    'name' => ucfirst($u['voornaam']),
                    'full_name' => ucfirst($u['voornaam']) . ' ' . ucfirst(substr($u['achternaam'], 0, 1)) . '.',
                    'profile_picture' => $u['profile_picture'],
                    'color' => $colors[$colorIdx],
                    'latest_lat' => (float)$u['lat'],
                    'latest_lon' => (float)$u['lon'],
                    'latest_time' => $u['geotijd'],
                    'trail' => [[(float)$u['lon'], (float)$u['lat']]]
                ];
            }
        }
    }

    echo json_encode(['searchers' => array_values($searchers)]);
    exit();
}

// 6. Sticker Found Submission Flow
if (isset($_POST['submit_found'])) {
    if (!$activeTegenhunt) {
        http_response_code(400);
        echo json_encode(['error' => 'Er is momenteel geen actieve tegenhunt sessie.']);
        exit();
    }

    $code = strtoupper(trim($_POST['code'] ?? ''));
    $lat = !empty($_POST['lat']) ? floatval($_POST['lat']) : ($groupCoords['lat'] ?? 0);
    $lon = !empty($_POST['lon']) ? floatval($_POST['lon']) : ($groupCoords['lon'] ?? 0);
    $photoUrl = null;

    if (empty($code)) {
        http_response_code(400);
        echo json_encode(['error' => 'Geen stickercode ingevuld.']);
        exit();
    }

    // Handle photo upload if present
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/media/tegenhunt/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            $filename = 'tegenhunt_' . time() . '_' . substr(md5(uniqid()), 0, 8) . '.' . $ext;
            $dest = $uploadDir . $filename;
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $dest)) {
                $photoUrl = 'media/tegenhunt/' . $filename;
            }
        }
    }

    $sessionId = (int)$activeTegenhunt['id'];
    $now = date('Y-m-d H:i:s');
    $userDisplayName = !empty($first_name) ? ucfirst($first_name) : (!empty($_SESSION['voornaam']) ? ucfirst($_SESSION['voornaam']) : (!empty($username) ? $username : 'Iemand'));

    // 1. Insert into Voslocaties
    $groupDeelgebied = !empty($groupCoords['deelgebied']) ? substr($groupCoords['deelgebied'], 0, 8) : 'Alpha';
    $stmt_vl = $conn->prepare("INSERT INTO Voslocaties (ingestuurd_op, type, deelgebied, ingeleverd, coordinaat_x, coordinaat_y, code, opmerking, foto, ingeleverd_door) VALUES (?, 'Tegenhunt', ?, 0, ?, ?, ?, 'Tegenhunt', ?, ?)");
    $stmt_vl->bind_param("ssddssi", $now, $groupDeelgebied, $lat, $lon, $code, $photoUrl, $userId);
    $stmt_vl->execute();
    $stmt_vl->close();

    // 2. Mark Tegenhunt Session Found
    $stmt_th = $conn->prepare("UPDATE Tegenhunt_Sessions SET status = 'found', found_by_user_id = ?, found_code = ?, found_lat = ?, found_lon = ?, found_photo_url = ? WHERE id = ?");
    $stmt_th->bind_param("isddsi", $userId, $code, $lat, $lon, $photoUrl, $sessionId);
    $stmt_th->execute();
    $stmt_th->close();

    // 3. Broadcast Sticker Found Web Push Alert
    send_push_notification(
        'ALL',
        'STICKER GEVONDEN',
        "Tegenhunt: {$userDisplayName} heeft de sticker gevonden!",
        '/tegenhunt',
        'tegenhunt/found',
        null,
        'tegenhunt'
    );

    echo json_encode([
        'success' => true,
        'finder' => $userDisplayName,
        'code' => $code,
        'message' => "Sticker {$code} succesvol ingeleverd door {$userDisplayName}!"
    ]);
    exit();
}

http_response_code(400);
echo json_encode(['error' => 'Ongeldig verzoek.']);

