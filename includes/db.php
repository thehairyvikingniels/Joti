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

/**
 * Queue a push notification to be sent by the cronjob.
 *
 * @param mixed $toUser User ID (int/string), 'ALL', or an array of user IDs.
 * @param string $title The notification title.
 * @param string $message The notification message body.
 * @param string $url The URL to open when clicked.
 * @param string $initiator The script or context queueing this.
 * @param string|null $sendBefore ISO date string or null.
 * @param string|null $channel The notification channel/preference key.
 * @return bool True if queued successfully, false otherwise.
 */
function send_push_notification(
    mixed $toUser,
    string $title,
    string $message,
    string $url = '/',
    string $initiator = 'system',
    ?string $sendBefore = null,
    ?string $channel = null
): bool {
    global $conn;
    if (!$conn instanceof mysqli) {
        return false;
    }

    $users = [];
    if ($toUser === 'ALL') {
        $rows = dbFetchAll($conn, 'SELECT DISTINCT s.user_id, g.notification_prefs FROM Notification_Subscriptions s JOIN Gebruikers g ON s.user_id = g.id');
        foreach ($rows as $row) {
            $users[$row['user_id']] = $row['notification_prefs'];
        }
    } else {
        $targetIds = is_array($toUser) ? $toUser : [$toUser];
        if (empty($targetIds)) {
            return false;
        }

        $placeholders = implode(',', array_fill(0, count($targetIds), '?'));
        $types = str_repeat('i', count($targetIds));
        $rows = dbFetchAll($conn, "SELECT id, notification_prefs FROM Gebruikers WHERE id IN ($placeholders)", $targetIds, $types);
        foreach ($rows as $row) {
            $users[$row['id']] = $row['notification_prefs'];
        }
    }

    if (empty($users)) {
        return false;
    }

    $stmt = $conn->prepare('INSERT INTO Notification_Backlog (user_id, title, message, url, initiator, send_before) VALUES (?, ?, ?, ?, ?, ?)');
    if (!$stmt) {
        return false;
    }

    foreach ($users as $uId => $prefsJson) {
        if ($channel !== null) {
            $prefs = $prefsJson ? json_decode((string)$prefsJson, true) : [];
            $defaultVal = in_array($channel, ['welkomsberichten', 'assignment_changes', 'tegenhunt'], true);
            $enabled = isset($prefs[$channel]) ? (bool)$prefs[$channel] : $defaultVal;

            if (!$enabled) {
                continue;
            }
        }

        $uInt = (int)$uId;
        $stmt->bind_param('isssss', $uInt, $title, $message, $url, $initiator, $sendBefore);
        $stmt->execute();
    }
    $stmt->close();
    return true;
}

/**
 * Fetch ordered GPS coordinate points for fox teams within game boundaries.
 *
 * @param mysqli $conn Active database connection
 * @param array<string> $foxTeams List of fox team names
 * @param string $timeFilterSql Additional SQL fragment for time filtering
 * @param string $timeFilterTypes Binding types for time filter
 * @param array $timeFilterParams Parameters for time filter
 * @return array<string, array<int, array{lat: float, lon: float, time: DateTime}>>
 */
function fetchFoxPathPoints(
    mysqli $conn,
    array $foxTeams,
    string $timeFilterSql = '',
    string $timeFilterTypes = '',
    array $timeFilterParams = []
): array {
    $results = [];
    foreach ($foxTeams as $deelgebied) {
        $sql = "SELECT coordinaat_x, coordinaat_y, ingestuurd_op FROM Voslocaties WHERE deelgebied = ? AND coordinaat_x BETWEEN 51.5 AND 52.6 AND coordinaat_y BETWEEN 5.0 AND 6.8 " . $timeFilterSql . " ORDER BY ingestuurd_op ASC";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $types = "s" . $timeFilterTypes;
            $params = array_merge([$deelgebied], $timeFilterParams);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();
            $points = [];
            if ($res && $res->num_rows > 0) {
                while ($row = $res->fetch_assoc()) {
                    $points[] = [
                        'lat' => (float)$row['coordinaat_x'],
                        'lon' => (float)$row['coordinaat_y'],
                        'time' => new DateTime($row['ingestuurd_op'])
                    ];
                }
            }
            $stmt->close();
            $results[$deelgebied] = $points;
        }
    }
    return $results;
}

/**
 * Retrieve dynamic home coordinates and address for the current group from Groepen table.
 *
 * @param mysqli $conn
 * @param int $groupId
 * @return array|null
 */
function getMyGroupCoordinates(mysqli $conn, int $groupId): ?array {
    $stmt = $conn->prepare("SELECT id, naam, lat, lon, straat, huisnummer, postal_code, plaats, deelgebied FROM Groepen WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $groupId);
        $stmt->execute();
        $res = $stmt->get_result();
        $group = $res->fetch_assoc();
        $stmt->close();
        if ($group && !empty($group['lat']) && !empty($group['lon'])) {
            return [
                'id' => (int)$group['id'],
                'naam' => $group['naam'],
                'lat' => (float)$group['lat'],
                'lon' => (float)$group['lon'],
                'address' => trim(($group['straat'] ?? '') . ' ' . ($group['huisnummer'] ?? '') . ', ' . ($group['plaats'] ?? '')),
                'deelgebied' => $group['deelgebied'] ?? ''
            ];
        }
    }
    return null;
}

/**
 * Fetch the currently active Tegenhunt session if one exists within the 30-minute window.
 *
 * @param mysqli $conn
 * @return array|null
 */
function getActiveTegenhunt(mysqli $conn): ?array {
    $now = date('Y-m-d H:i:s');
    $stmt = $conn->prepare("SELECT t.*, u.voornaam AS finder_first_name, u.achternaam AS finder_last_name 
        FROM Tegenhunt_Sessions t 
        LEFT JOIN Gebruikers u ON t.found_by_user_id = u.id 
        WHERE t.status = 'active' AND t.end_time > ? 
        ORDER BY t.start_time DESC LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("s", $now);
        $stmt->execute();
        $res = $stmt->get_result();
        $session = $res->fetch_assoc();
        $stmt->close();
        if ($session) {
            $session['remaining_seconds'] = max(0, strtotime($session['end_time']) - time());
            return $session;
        }
    }
    return null;
}

/**
 * Record a high-accuracy GPS breadcrumb for an active Tegenhunt session.
 *
 * @param mysqli $conn
 * @param int $sessionId
 * @param int $userId
 * @param float $lat
 * @param float $lon
 * @param float $accuracy
 * @return bool
 */
function recordTegenhuntBreadcrumb(mysqli $conn, int $sessionId, int $userId, float $lat, float $lon, float $accuracy): bool {
    if ($accuracy > 25.0) {
        return false; // Discard inaccurate GPS points
    }
    $stmt = $conn->prepare("INSERT INTO Tegenhunt_Breadcrumbs (session_id, user_id, lat, lon, accuracy, recorded_at) VALUES (?, ?, ?, ?, ?, NOW())");
    if ($stmt) {
        $stmt->bind_param("iiddd", $sessionId, $userId, $lat, $lon, $accuracy);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }
    return false;
}
