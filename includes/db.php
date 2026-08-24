<?php
declare(strict_types=1);

/**
 * includes/db.php
 *
 * Centralized data access functions and query helpers for Jotify.
 * All functions use prepared statements with strict typing and parameter binding.
 */

/**
 * Execute a prepared SQL statement and return the mysqli_result (or bool for non-SELECT queries).
 *
 * @param mysqli $conn The active database connection.
 * @param string $sql The SQL query with ? placeholders.
 * @param array $params Optional array of parameters to bind.
 * @param string $types Optional type string (e.g. 'isi'). If omitted, inferred automatically.
 * @return mysqli_result|bool
 */
function dbQuery(mysqli $conn, string $sql, array $params = [], string $types = ''): mysqli_result|bool {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log('dbQuery prepare failed: ' . $conn->error . ' in query: ' . $sql);
        return false;
    }

    if (!empty($params)) {
        if ($types === '') {
            $types = '';
            foreach ($params as $param) {
                if (is_int($param)) {
                    $types .= 'i';
                } elseif (is_float($param)) {
                    $types .= 'd';
                } else {
                    $types .= 's';
                }
            }
        }
        $stmt->bind_param($types, ...$params);
    }

    if (!$stmt->execute()) {
        error_log('dbQuery execute failed: ' . $stmt->error . ' in query: ' . $sql);
        $stmt->close();
        return false;
    }

    $result = $stmt->get_result();
    if ($result === false && $stmt->errno === 0) {
        $stmt->close();
        return true;
    }

    $stmt->close();
    return $result;
}

/**
 * Fetch all matching rows as an associative array.
 *
 * @param mysqli $conn The active database connection.
 * @param string $sql The SQL query with ? placeholders.
 * @param array $params Optional parameters to bind.
 * @param string $types Optional type string.
 * @return array<int, array<string, mixed>>
 */
function dbFetchAll(mysqli $conn, string $sql, array $params = [], string $types = ''): array {
    $result = dbQuery($conn, $sql, $params, $types);
    if ($result instanceof mysqli_result) {
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    return [];
}

/**
 * Fetch a single row as an associative array, or null if not found.
 *
 * @param mysqli $conn The active database connection.
 * @param string $sql The SQL query with ? placeholders.
 * @param array $params Optional parameters to bind.
 * @param string $types Optional type string.
 * @return array<string, mixed>|null
 */
function dbFetchOne(mysqli $conn, string $sql, array $params = [], string $types = ''): ?array {
    $result = dbQuery($conn, $sql, $params, $types);
    if ($result instanceof mysqli_result) {
        $row = $result->fetch_assoc();
        return $row ?: null;
    }
    return null;
}

/**
 * Fetch all site settings as a key-value dictionary from Site_Instellingen.
 *
 * @param mysqli $conn The active database connection.
 * @return array<string, string>
 */
function fetchSiteSettings(mysqli $conn): array {
    $rows = dbFetchAll($conn, 'SELECT Instelling, Waarde FROM Site_Instellingen');
    $settings = [];
    foreach ($rows as $row) {
        $settings[$row['Instelling']] = $row['Waarde'];
    }
    return $settings;
}

/**
 * Fetch a single user by ID.
 *
 * @param mysqli $conn The active database connection.
 * @param int $userId The ID of the user.
 * @return array<string, mixed>|null
 */
function fetchUserById(mysqli $conn, int $userId): ?array {
    return dbFetchOne($conn, 'SELECT * FROM Gebruikers WHERE id = ?', [$userId], 'i');
}
