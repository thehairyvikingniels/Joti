<?php
// Terminates the current user session, clears remember-me persistent tokens and cookies, and redirects to the landing page.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once('dblogin.php');
require_once('includes/db.php');
require_once('includes/remember_me.php');

if (!empty($_SESSION['id'])) {
    $uname = $_SESSION['gebruikersnaam'] ?? "Gebruiker #{$_SESSION['id']}";
    recordAuditLog($conn, 'auth', 'logout', "Gebruiker '{$uname}' is uitgelogd", [
        'actor_user_id' => (int)$_SESSION['id'],
        'actor_username' => $uname,
        'severity' => 'info'
    ]);
}

// Clear persistent token for this device from DB and expire cookie
clearCurrentRememberToken($conn);

$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

header("Location: /");
exit();
?>