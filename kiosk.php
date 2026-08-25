<?php
// Manages kiosk authentication via tokenized URLs, enforces IP whitelists, and serves a JSON polling status endpoint.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once(__DIR__ . "/dblogin.php");
require_once(__DIR__ . "/includes/helpers.php");

// Mode 1: Initial Login with ?auth=TOKEN
if (isset($_GET['auth'])) {
    $token = trim($_GET['auth']);

    if (empty($token)) {
        http_response_code(400);
        header("Location: index?error=" . urlencode("Ongeldige authenticatie token."));
        exit();
    }

    $stmt = $conn->prepare("SELECT id, auth_token, naam, doel_pagina, rechten, ip_whitelist, refresh_interval FROM Kiosk_Accounts WHERE auth_token = ?");
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
                echo "<!DOCTYPE html><html lang='nl'><head><meta charset='UTF-8'><title>Toegang Geweigerd</title><script src='https://cdn.tailwindcss.com'></script></head><body class='bg-gray-100 flex items-center justify-center min-h-screen p-4'><div class='bg-white p-8 rounded-xl shadow-lg max-w-md text-center'><div class='text-red-500 text-5xl mb-4 font-bold'>&times;</div><h1 class='text-xl font-bold text-gray-800 mb-2'>Toegang Geweigerd</h1><p class='text-gray-600 text-sm'>IP-adres <code class='bg-gray-100 px-2 py-1 rounded text-red-600'>" . htmlspecialchars($clientIP) . "</code> staat niet op de whitelist voor deze Kiosk.</p></div></body></html>";
                exit();
            }
        }

        // Set session variables
        $_SESSION['kiosk_id'] = (int)$row['id'];
        $_SESSION['kiosk_priv'] = (int)$row['rechten'];
        $_SESSION['kiosk_naam'] = $row['naam'];
        $_SESSION['id'] = 0; // Dummy user id for general session checks
        $_SESSION['priv'] = (int)$row['rechten'];
        $_SESSION['naam'] = $row['naam'];

        // Update laatst_gezien
        $stmt_update = $conn->prepare("UPDATE Kiosk_Accounts SET laatst_gezien = NOW(), laatst_ip = ? WHERE id = ?");
        $stmt_update->bind_param("si", $clientIP, $row['id']);
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
        header("Location: index?error=" . urlencode("Kiosk niet gevonden of token ongeldig."));
        exit();
    }
}

// Mode 2: Background Polling with ?action=status
if (isset($_GET['action']) && $_GET['action'] === 'status') {
    header('Content-Type: application/json; charset=utf-8');

    if (empty($_SESSION['kiosk_id'])) {
        http_response_code(401);
        echo json_encode(["error" => "Geen actieve kiosk sessie"]);
        exit();
    }

    $stmt = $conn->prepare("SELECT id, auth_token, doel_pagina, refresh_interval, laatst_gezien FROM Kiosk_Accounts WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['kiosk_id']);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $clientIP = getClientIP();
        $stmt_touch = $conn->prepare("UPDATE Kiosk_Accounts SET laatst_gezien = NOW(), laatst_ip = ? WHERE id = ?");
        $stmt_touch->bind_param("si", $clientIP, $_SESSION['kiosk_id']);
        $stmt_touch->execute();
        $stmt_touch->close();

        echo json_encode([
            "status" => "active",
            "target_page" => $row['doel_pagina'] ?: "whiteboard.php",
            "refresh_interval" => (int)$row['refresh_interval'],
            "config_version" => strtotime($row['laatst_gezien'] ?? 'now')
        ]);
        exit();
    } else {
        http_response_code(404);
        echo json_encode(["error" => "Kiosk niet gevonden"]);
        exit();
    }
}
