<?php
// Session bootstrap: handles authentication, user loading, and site settings.

// 1. Defensive session start
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Auth check — redirect unauthenticated users
if (empty($_SESSION['id']) && empty($_SESSION['kiosk_id'])) {
    // Determine correct redirect path (admin/ pages go up one level)
    $redirect = (basename(dirname($_SERVER['SCRIPT_FILENAME'])) === 'admin') ? '../index' : 'index';
    header('Location: ' . $redirect);
    exit();
}

// 3. Load database connection (which also loads globals.php -> $site_settings, fox names/colors)
require_once(__DIR__ . '/../dblogin.php');

// 4. Load user data from database
$user_id = (int)$_SESSION['id'];
$first_name = '';
$last_name = '';
$email = '';
$telefoon = '';
$profile_picture = '';
$privilege = 0;
$user_lat = null;
$user_lon = null;

if ($user_id > 0) {
    $stmt = $conn->prepare('SELECT * FROM Gebruikers WHERE id = ?');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $first_name = $row['voornaam'];
        $last_name = $row['achternaam'];
        $email = $row['email'];
        $telefoon = $row['telefoon'] ?? '';
        $profile_picture = $row['profile_picture'] ?? '';
        $privilege = (int)$row['priv'];
        $user_lat = $row['lat'] ?: 51.98769228691746;  // Default: HQ coordinates
        $user_lon = $row['lon'] ?: 5.876286397679744;
    } else {
        // User no longer exists in DB — destroy session
        session_destroy();
        $redirect = (basename(dirname($_SERVER['SCRIPT_FILENAME'])) === 'admin') ? '../index' : 'index';
        header('Location: ' . $redirect);
        exit();
    }
    $stmt->close();
}

// 5. Kiosk session fallback
$is_kiosk = isset($_SESSION['kiosk_id']);
if ($is_kiosk && $user_id === 0) {
    $privilege = (int)($_SESSION['kiosk_priv'] ?? 0);
    $first_name = $_SESSION['kiosk_naam'] ?? 'Kiosk';
    $last_name = '';
    $user_lat = 51.98769228691746;
    $user_lon = 5.876286397679744;
}

// 6. Load shared helpers
require_once(__DIR__ . '/helpers.php');

// 7. Backward compatibility aliases (temporary, for gradual migration)
$vn = $first_name;
$an = $last_name;
$priv = $privilege;
$siteSettings = $site_settings ?? [];
$usr_lat = $user_lat;
$usr_lon = $user_lon;
