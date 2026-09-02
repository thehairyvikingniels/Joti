<?php
// Session bootstrap: handles authentication, persistent cookies ("Ingelogd Blijven"), user loading, and site settings.

// 1. Session cookie hardening & defensive session start
ini_set('session.cookie_httponly', '1');
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
if ($isHttps) {
    ini_set('session.cookie_secure', '1');
}
ini_set('session.cookie_samesite', 'Lax');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Check if installation is complete; if not, redirect to installer
if (!file_exists(__DIR__ . '/../dblogin.php') || !file_exists(__DIR__ . '/../.installed')) {
    if (file_exists(__DIR__ . '/../install.php') || file_exists(__DIR__ . '/../install')) {
        $installRedirect = (basename(dirname($_SERVER['SCRIPT_FILENAME'])) === 'admin') ? '../install' : 'install';
        header('Location: ' . $installRedirect);
        exit();
    }
}

// 3. Load database connection & remember-me helper
require_once(__DIR__ . '/../dblogin.php');
require_once(__DIR__ . '/remember_me.php');

// 3. Auth check — validate persistent cookie or redirect unauthenticated users
if (empty($_SESSION['id']) && empty($_SESSION['kiosk_id'])) {
    $rememberUserId = validateRememberToken($conn);
    if ($rememberUserId !== null) {
        $_SESSION['id'] = $rememberUserId;
        session_regenerate_id(true);
    } else {
        $redirect = (basename(dirname($_SERVER['SCRIPT_FILENAME'])) === 'admin') ? '../index' : 'index';
        header('Location: ' . $redirect);
        exit();
    }
}

// 4. Load user data from database
$user_id = (int)($_SESSION['id'] ?? 0);
$username = '';
$user_name = '';
$first_name = '';
$last_name = '';
$email = '';
$telefoon = '';
$telegram_chat_id = '';
$telegram_enabled = 0;
$telegram_link_code = '';
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
        $telefoon = $user_data['phone'] ?? ($user_data['telefoon'] ?? '');
        $telegram_chat_id = $user_data['telegram_chat_id'] ?? '';
        $telegram_enabled = (int)($user_data['telegram_enabled'] ?? 0);
        $telegram_link_code = $user_data['telegram_link_code'] ?? '';
        $profile_picture = $user_data['profile_picture'] ?? '';
        $privilege = (int)$user_data['priv'];
        $user_lat = $user_data['lat'] ?: 51.98769228691746;  // Default: HQ coordinates
        $user_lon = $user_data['lon'] ?: 5.876286397679744;
        $_SESSION['priv'] = $privilege;
    } else {
        // User no longer exists in DB — clear remember token and destroy session
        clearCurrentRememberToken($conn);
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
