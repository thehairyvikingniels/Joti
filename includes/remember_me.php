<?php
// includes/remember_me.php - Persistent login token management ("Ingelogd Blijven")

/**
 * Generates and stores a secure persistent login token for a user, then sets the cookie.
 *
 * @param mysqli $conn Active database connection
 * @param int $userId ID of the authenticated user
 * @param int $priv User privilege level (0-3)
 * @param array $siteSettings Key-value site settings dictionary
 * @param string|null $userAgent Optional user-agent string for logging
 * @return bool True if token was generated and set, false if disabled
 */
function generateRememberToken(mysqli $conn, int $userId, int $priv, array $siteSettings, ?string $userAgent = null): bool {
    // Opportunistically prune expired tokens (~5% chance)
    if (random_int(1, 20) === 1) {
        $conn->query("DELETE FROM Gebruikers_Tokens WHERE expiry < NOW()");
    }

    // 1. Determine duration based on privilege role (default 720 hours = 30 days)
    $settingKey = ($priv >= 2) ? 'REMEMBER_ME_HOURS_ADMIN' : 'REMEMBER_ME_HOURS';
    $defaultHours = 720;
    $durationHours = (int)($siteSettings[$settingKey] ?? $defaultHours);

    // If configured as 0, remember-me is disabled for this role
    if ($durationHours <= 0) {
        return false;
    }

    $durationSeconds = $durationHours * 3600;

    // 2. Generate cryptographically secure selector (16 bytes) and validator (32 bytes)
    $selector = bin2hex(random_bytes(16));
    $validator = bin2hex(random_bytes(32));
    $hashedValidator = hash('sha256', $validator);
    $truncatedUserAgent = $userAgent ? substr($userAgent, 0, 255) : null;

    // 3. Store hashed validator in database with MySQL-calculated expiry
    $stmt = $conn->prepare("INSERT INTO Gebruikers_Tokens (user_id, selector, hashed_validator, expiry, user_agent) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? HOUR), ?)");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param("issss", $userId, $selector, $hashedValidator, $durationHours, $truncatedUserAgent);
    $success = $stmt->execute();
    $stmt->close();

    if (!$success) {
        return false;
    }

    // 4. Set persistent HTTP cookie
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    $cookieValue = $selector . ':' . $validator;

    setcookie('jotify_remember', $cookieValue, [
        'expires' => time() + $durationSeconds,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    return true;
}

/**
 * Validates the persistent remember-me cookie, slides its expiration forward, and returns the user ID if valid.
 *
 * @param mysqli $conn Active database connection
 * @return int|null User ID if token is valid, null otherwise
 */
function validateRememberToken(mysqli $conn): ?int {
    if (empty($_COOKIE['jotify_remember']) || !is_string($_COOKIE['jotify_remember'])) {
        return null;
    }

    $parts = explode(':', $_COOKIE['jotify_remember'], 2);
    if (count($parts) !== 2) {
        return null;
    }

    $selector = $parts[0];
    $validator = $parts[1];

    if (empty($selector) || empty($validator)) {
        return null;
    }

    // Query unexpired token along with user privilege for sliding window extension
    $stmt = $conn->prepare("SELECT t.id, t.user_id, t.hashed_validator, u.priv 
                           FROM Gebruikers_Tokens t 
                           JOIN Gebruikers u ON t.user_id = u.id 
                           WHERE t.selector = ? AND t.expiry > NOW() 
                           LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param("s", $selector);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $stmt->close();
        if (hash_equals($row['hashed_validator'], hash('sha256', $validator))) {
            $userId = (int)$row['user_id'];
            $priv = (int)$row['priv'];

            // Determine configured duration for this user role
            global $site_settings;
            $settings = $site_settings ?? (function_exists('fetchSiteSettings') ? fetchSiteSettings($conn) : []);
            $settingKey = ($priv >= 2) ? 'REMEMBER_ME_HOURS_ADMIN' : 'REMEMBER_ME_HOURS';
            $defaultHours = 720;
            $durationHours = (int)($settings[$settingKey] ?? $defaultHours);

            if ($durationHours <= 0) {
                clearCurrentRememberToken($conn);
                return null;
            }

            // Slide DB token expiry forward
            $updStmt = $conn->prepare("UPDATE Gebruikers_Tokens SET expiry = DATE_ADD(NOW(), INTERVAL ? HOUR) WHERE selector = ?");
            if ($updStmt) {
                $updStmt->bind_param("is", $durationHours, $selector);
                $updStmt->execute();
                $updStmt->close();
            }

            // Refresh persistent HTTP cookie expiration
            $durationSeconds = $durationHours * 3600;
            $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
            $cookieValue = $selector . ':' . $validator;

            setcookie('jotify_remember', $cookieValue, [
                'expires' => time() + $durationSeconds,
                'path' => '/',
                'domain' => '',
                'secure' => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);

            return $userId;
        }
    } else {
        $stmt->close();
    }

    return null;
}

/**
 * Clears the current device's persistent remember token from DB and expires cookie.
 *
 * @param mysqli $conn Active database connection
 */
function clearCurrentRememberToken(mysqli $conn): void {
    if (!empty($_COOKIE['jotify_remember']) && is_string($_COOKIE['jotify_remember'])) {
        $parts = explode(':', $_COOKIE['jotify_remember'], 2);
        if (!empty($parts[0])) {
            $selector = $parts[0];
            $stmt = $conn->prepare("DELETE FROM Gebruikers_Tokens WHERE selector = ?");
            if ($stmt) {
                $stmt->bind_param("s", $selector);
                $stmt->execute();
                $stmt->close();
            }
        }
    }

    // Expire cookie
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    setcookie('jotify_remember', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

/**
 * Invalidates ALL persistent tokens for a given user (e.g. on password change or logout everywhere).
 *
 * @param mysqli $conn Active database connection
 * @param int $userId Target user ID
 */
function clearAllRememberTokensForUser(mysqli $conn, int $userId): void {
    $stmt = $conn->prepare("DELETE FROM Gebruikers_Tokens WHERE user_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->close();
    }

    // Also expire local cookie if active
    if (!empty($_COOKIE['jotify_remember'])) {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
        setcookie('jotify_remember', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'domain' => '',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
}
