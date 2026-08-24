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
$username = '';
$user_name = '';
$first_name = '';
$last_name = '';
$email = '';
$telefoon = '';
$profile_picture = '';
$privilege = 0;
$user_lat = null;
$user_lon = null;

if ($user_id > 0) {
    $user_data = fetchUserById($conn, $user_id);
    if ($user_data) {
        $username = $user_data['gebruikersnaam'] ?? '';
        $user_name = $username;
        $first_name = $user_data['voornaam'];
        $last_name = $user_data['achternaam'];
        $email = $user_data['email'];
        $telefoon = $user_data['telefoon'] ?? '';
        $profile_picture = $user_data['profile_picture'] ?? '';
        $privilege = (int)$user_data['priv'];
        $user_lat = $user_data['lat'] ?: 51.98769228691746;  // Default: HQ coordinates
        $user_lon = $user_data['lon'] ?: 5.876286397679744;
    } else {
        // User no longer exists in DB — destroy session
        session_destroy();
        $redirect = (basename(dirname($_SERVER['SCRIPT_FILENAME'])) === 'admin') ? '../index' : 'index';
        header('Location: ' . $redirect);
        exit();
    }
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
