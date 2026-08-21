<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once(__DIR__ . "/dblogin.php");

// Helper function to resolve client IP considering reverse proxy headers
function getClientIP() {
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ipList[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

// Modus 1: Initial Login with ?auth=TOKEN
if (isset($_GET['auth'])) {
    $token = trim($_GET['auth']);

    if (empty($token)) {
        http_response_code(400);
        die("Ongeldige authenticatie token.");
    }

    $stmt = $conn->prepare("SELECT id, auth_token, naam, doel_pagina, rechten, ip_whitelist, refresh_interval FROM kiosk_devices WHERE auth_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $clientIP = getClientIP();

        // Check IP Whitelist if configured
        if (!empty($row['ip_whitelist'])) {
            $allowedIPs = array_map('trim', explode(',', $row['ip_whitelist']));
            if (!in_array($clientIP, $allowedIPs)) {
                http_response_code(403);
                die("Toegang geweigerd: IP-adres ({$clientIP}) staat niet op de whitelist voor deze Kiosk.");
            }
        }

        // Set session variables
        $_SESSION['kiosk_id'] = (int)$row['id'];
        $_SESSION['kiosk_priv'] = (int)$row['rechten'];
        $_SESSION['kiosk_naam'] = $row['naam'];
        $_SESSION['id'] = 0; // Set dummy user id for general session checks
        $_SESSION['priv'] = (int)$row['rechten'];
        $_SESSION['naam'] = $row['naam'];

        // Update laatst_gezien
        $stmt_update = $conn->prepare("UPDATE kiosk_devices SET laatst_gezien = NOW() WHERE id = ?");
        $stmt_update->bind_param("i", $row['id']);
        $stmt_update->execute();
        $stmt_update->close();

        // Redirect to target page
        $target = $row['doel_pagina'];
        if (empty($target)) {
            $target = 'whiteboard.php';
        }
        header("Location: " . $target);
        exit();
    } else {
        http_response_code(401);
        die("Kiosk niet gevonden of token ongeldig.");
    }
}

// Modus 2: Background Polling with ?action=status
if (isset($_GET['action']) && $_GET['action'] === 'status') {
    header('Content-Type: application/json; charset=utf-8');

    if (empty($_SESSION['kiosk_id'])) {
        http_response_code(401);
        echo json_encode(["error" => "Geen actieve kiosk sessie"]);
        exit();
    }

    $kioskId = (int)$_SESSION['kiosk_id'];

    // Update laatst_gezien
    $stmt_update = $conn->prepare("UPDATE kiosk_devices SET laatst_gezien = NOW() WHERE id = ?");
    $stmt_update->bind_param("i", $kioskId);
    $stmt_update->execute();
    $stmt_update->close();

    // Fetch current settings
    $stmt = $conn->prepare("SELECT doel_pagina, refresh_interval, rechten FROM kiosk_devices WHERE id = ?");
    $stmt->bind_param("i", $kioskId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        // Keep session privs updated
        $_SESSION['kiosk_priv'] = (int)$row['rechten'];

        echo json_encode([
            "doel_pagina" => $row['doel_pagina'],
            "refresh_interval" => (int)$row['refresh_interval']
        ]);
        exit();
    } else {
        http_response_code(404);
        echo json_encode(["error" => "Kiosk niet gevonden"]);
        exit();
    }
}

// If accessed directly without auth or action parameter
if (isset($_SESSION['kiosk_id'])) {
    echo "Kiosk actief: " . htmlspecialchars($_SESSION['kiosk_naam'] ?? 'Onbekend');
} else {
    echo "Kiosk API: Gebruik ?auth=TOKEN om in te loggen.";
}
