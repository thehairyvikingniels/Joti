<?php
// includes/telegram_parser.php — Parses incoming Jotihunt Telegram messages, extracts game events, and triggers automated updates.

require_once(__DIR__ . '/db.php');
require_once(__DIR__ . '/helpers.php');

/**
 * Parses raw text from @Jotihunt_bot or group chats and executes game triggers.
 *
 * @param mysqli $conn Active database connection
 * @param string $text Raw text of the Telegram message
 * @param string $sender Sender identifier (default '@Jotihunt_bot')
 * @param int|null $msg_id Telegram message ID
 * @param string|null $forwarded_to List of forwarded recipient chat IDs
 * @return array Parsed details and actions taken
 */
function parseAndDispatchTelegramMessage(
    mysqli $conn,
    string $text,
    string $sender = '@Jotihunt_bot',
    ?int $msg_id = null,
    ?string $forwarded_to = null
): array {
    $text = trim($text);
    $type = 'unknown';
    $summary = 'Onbekend berichttype';
    $details = [];
    $push_sent = false;

    // 1. FOX STATUS CHANGE
    // Example: "Status van Charlie is gewijzigd in orange"
    if (preg_match('/Status van\s+([A-Za-z]+)\s+is gewijzigd in\s+(green|orange|red)/i', $text, $matches)) {
        $type = 'fox_status';
        $foxName = ucfirst(strtolower($matches[1]));
        $statusStr = strtolower($matches[2]);
        
        $statusCode = 0; // red
        $statusDutch = 'Rood';
        if ($statusStr === 'orange') {
            $statusCode = 1;
            $statusDutch = 'Oranje';
        } elseif ($statusStr === 'green') {
            $statusCode = 2;
            $statusDutch = 'Groen';
        }

        $details = [
            'fox' => $foxName,
            'status' => $statusStr,
            'status_code' => $statusCode,
            'status_dutch' => $statusDutch
        ];
        $summary = "Vosstatus {$foxName} gewijzigd in {$statusDutch}";

        // Insert into Voslog
        $stmt = $conn->prepare("INSERT INTO Voslog (datumtijd, vos, status) VALUES (NOW(), ?, ?)");
        if ($stmt) {
            $stmt->bind_param("si", $foxName, $statusCode);
            $stmt->execute();
            $stmt->close();
        }

        // Broadcast Web Push
        send_push_notification(
            'ALL',
            "Vosstatus {$foxName}",
            "De status van {$foxName} is nu {$statusDutch}.",
            '/vossen',
            'telegram/parser',
            null,
            'vosstatus'
        );
        $push_sent = true;

    // 2. HUNT APPROVAL
    // Example: "De hunt met code BnsVqJy is voorlopig goedgekeurd en levert 3 punt(en) op."
    } elseif (preg_match('/De hunt met code\s+([A-Za-z0-9]+)\s+is\s+(?:voorlopig\s+)?goedgekeurd\s+en levert\s+(\d+)\s+punt\(en\)\s+op/i', $text, $matches)) {
        $type = 'hunt_status';
        $huntCode = trim($matches[1]);
        $points = (int)$matches[2];

        $details = [
            'hunt_code' => $huntCode,
            'status' => 'Goedgekeurd',
            'points' => $points
        ];
        $summary = "Hunt {$huntCode} goedgekeurd (+{$points} pt)";

        // Update Voslocaties
        $stmt = $conn->prepare("UPDATE Voslocaties SET status = 'Goedgekeurd', toegekende_punten = ?, ingeleverd = 1 WHERE code = ? AND type = 'Hunt'");
        if ($stmt) {
            $stmt->bind_param("is", $points, $huntCode);
            $stmt->execute();
            $stmt->close();
        }

        // Broadcast Web Push
        send_push_notification(
            'ALL',
            "Hunt Goedgekeurd (+{$points} pt)",
            "Hunt {$huntCode} is goedgekeurd (+{$points} pt)!",
            '/voslocaties',
            'telegram/parser',
            null,
            'locatiestatus'
        );
        $push_sent = true;

    // 3. HUNT REJECTION
    // Example: "De hunt met code BnsVqJy is afgekeurd"
    } elseif (preg_match('/De hunt met code\s+([A-Za-z0-9]+)\s+is\s+afgekeurd/i', $text, $matches)) {
        $type = 'hunt_status';
        $huntCode = trim($matches[1]);

        $details = [
            'hunt_code' => $huntCode,
            'status' => 'Afgekeurd',
            'points' => 0
        ];
        $summary = "Hunt {$huntCode} afgekeurd";

        // Update Voslocaties
        $stmt = $conn->prepare("UPDATE Voslocaties SET status = 'Afgekeurd', toegekende_punten = 0 WHERE code = ? AND type = 'Hunt'");
        if ($stmt) {
            $stmt->bind_param("s", $huntCode);
            $stmt->execute();
            $stmt->close();
        }

        // Broadcast Web Push
        send_push_notification(
            'ALL',
            "Hunt Afgekeurd",
            "De hunt met code {$huntCode} is helaas afgekeurd.",
            '/voslocaties',
            'telegram/parser',
            null,
            'locatiestatus'
        );
        $push_sent = true;

    // 4. ASSIGNMENT GRADED
    // Example: "Jullie inzending voor de opdracht 'Light of Eärendil ✨' is beoordeeld, jullie hebben daarvoor 3 punt(en) gekregen"
    } elseif (preg_match('/Jullie inzending voor de opdracht\s+[\'"](.+?)[\'"]\s+is beoordeeld,\s+jullie hebben daarvoor\s+(\d+)\s+punt\(en\)\s+gekregen/iu', $text, $matches)) {
        $type = 'assignment_graded';
        $assignmentTitle = trim($matches[1]);
        $points = (int)$matches[2];

        // Optional jury feedback
        $feedback = null;
        if (preg_match('/Opmerkingen:\s*(.+)/is', $text, $fbMatch)) {
            $feedback = trim($fbMatch[1]);
        }

        $details = [
            'title' => $assignmentTitle,
            'points' => $points,
            'feedback' => $feedback
        ];
        $summary = "Opdracht '{$assignmentTitle}' beoordeeld (+{$points} pt)";

        // Try updating matching Opdrachten row
        $searchPattern = '%' . $assignmentTitle . '%';
        $stmt = $conn->prepare("UPDATE Opdrachten SET ingestuurd_op = NOW() WHERE titel LIKE ? AND ingestuurd_op IS NULL");
        if ($stmt) {
            $stmt->bind_param("s", $searchPattern);
            $stmt->execute();
            $stmt->close();
        }

        // Update group opdrachten points in Punten table
        $stmt_pts = $conn->prepare("UPDATE Punten SET opdrachten = opdrachten + ?, last_updated = NOW() WHERE groep_id = (SELECT CAST(Waarde AS UNSIGNED) FROM Site_Instellingen WHERE Instelling = 'GROUP_ID' LIMIT 1)");
        if ($stmt_pts) {
            $stmt_pts->bind_param("i", $points);
            $stmt_pts->execute();
            $stmt_pts->close();
        }

        $pushBody = "Jullie hebben {$points} punten gekregen voor '{$assignmentTitle}'.";
        if (!empty($feedback)) {
            $pushBody .= " Jury: " . substr($feedback, 0, 100);
        }

        send_push_notification(
            'ALL',
            "Opdracht Beoordeeld (+{$points} pt)",
            $pushBody,
            '/opdrachten',
            'telegram/parser',
            null,
            'opdrachten'
        );
        $push_sent = true;

    // 5. HAPPY HOUR
    } elseif (preg_match('/\bHAPPY\s*HOUR\b/i', $text)) {
        $type = 'happy_hour';
        $summary = "HAPPY HOUR Actief! Dubbele punten!";
        $details = ['active' => true, 'raw_text' => $text];

        $stmt = $conn->prepare("INSERT INTO Site_Instellingen (Instelling, Waarde, Omschrijving) VALUES ('HAPPY_HOUR', '1', 'Happy Hour actieve status') ON DUPLICATE KEY UPDATE Waarde = '1'");
        if ($stmt) {
            $stmt->execute();
            $stmt->close();
        }

        send_push_notification(
            'ALL',
            "🚨 HAPPY HOUR ACTIEF!",
            "Er is een Happy Hour actief! Dubbele punten voor vossenjachten.",
            '/kaarten',
            'telegram/parser',
            null,
            'vosstatus'
        );
        $push_sent = true;

    // 6. TEGENHUNT ALARM
    } elseif (preg_match('/\btegenhunt\b/i', $text)) {
        $type = 'tegenhunt';
        
        // Extract compass degrees or wind direction
        $degrees = 0;
        $windDirection = 'Z'; // Default Zuid

        if (preg_match('/(\d{1,3})\s*(?:°|graden|deg)/i', $text, $degMatch)) {
            $degrees = (int)$degMatch[1] % 360;
            $windDirection = degreesToWindDirection($degrees);
        } elseif (preg_match('/\b(Noord|Noordoost|Oost|Zuidoost|Zuid|Zuidwest|West|Noordwest|NNO|ONO|OZO|ZZO|ZZW|WZW|WNW|NNW|N|NO|O|ZO|Z|ZW|W|NW)\b/i', $text, $dirMatch)) {
            $windDirection = normalizeWindDirection($dirMatch[1]);
            $degrees = windDirectionToDegrees($windDirection);
        }

        $details = [
            'wind_direction' => $windDirection,
            'compass_degrees' => $degrees,
            'raw_message' => $text
        ];
        $summary = "⚠️ TEGENHUNT ALARM! Richting {$windDirection} ({$degrees}°)";

        // Create Tegenhunt session with 30-min window
        $stmt = $conn->prepare("INSERT INTO Tegenhunt_Sessions (start_time, end_time, wind_direction, compass_degrees, message, status) VALUES (NOW(), DATE_ADD(NOW(), INTERVAL 30 MINUTE), ?, ?, ?, 'active')");
        if ($stmt) {
            $stmt->bind_param("sis", $windDirection, $degrees, $text);
            $stmt->execute();
            $stmt->close();
        }

        send_push_notification(
            'ALL',
            "⚠️ TEGENHUNT ALARM!",
            "Er is een tegenhunt geplaatst! Richting: {$windDirection} ({$degrees}°). 30 minuten window!",
            '/tegenhunt',
            'telegram/parser',
            null,
            'tegenhunt'
        );
        $push_sent = true;
    } else {
        $details = ['raw_text' => $text];
    }

    // 7. Log into Telegram_Messages table
    $inserted_id = null;
    $json_payload = json_encode($details, JSON_UNESCAPED_UNICODE);
    $stmt_log = $conn->prepare("INSERT INTO Telegram_Messages (telegram_message_id, sender, message_text, parsed_type, parsed_payload, forwarded_to, processed) VALUES (?, ?, ?, ?, ?, ?, 1)");
    if ($stmt_log) {
        $stmt_log->bind_param("isssss", $msg_id, $sender, $text, $type, $json_payload, $forwarded_to);
        $stmt_log->execute();
        $inserted_id = $stmt_log->insert_id;
        $stmt_log->close();
    }

    return [
        'success' => true,
        'type' => $type,
        'summary' => $summary,
        'details' => $details,
        'push_sent' => $push_sent,
        'message_id' => $inserted_id
    ];
}

/**
 * Normalizes Dutch wind direction strings into standard NATO compass codes.
 */
function normalizeWindDirection(string $dir): string {
    $map = [
        'noord' => 'N', 'noordoost' => 'NO', 'oost' => 'O', 'zuidoost' => 'ZO',
        'zuid' => 'Z', 'zuidwest' => 'ZW', 'west' => 'W', 'noordwest' => 'NW'
    ];
    $lower = strtolower($dir);
    return $map[$lower] ?? strtoupper($dir);
}

/**
 * Converts standard compass wind directions to degrees (0° - 360°).
 */
function windDirectionToDegrees(string $dir): int {
    $map = [
        'N' => 0, 'NNO' => 22, 'NO' => 45, 'ONO' => 67,
        'O' => 90, 'OZO' => 112, 'ZO' => 135, 'ZZO' => 157,
        'Z' => 180, 'ZZW' => 202, 'ZW' => 225, 'WZW' => 247,
        'W' => 270, 'WNW' => 292, 'NW' => 315, 'NNW' => 337
    ];
    return $map[strtoupper($dir)] ?? 0;
}

/**
 * Converts compass degrees (0° - 360°) to nearest cardinal direction.
 */
function degreesToWindDirection(int $deg): string {
    $deg = $deg % 360;
    $directions = [
        0 => 'N', 22 => 'NNO', 45 => 'NO', 67 => 'ONO',
        90 => 'O', 112 => 'OZO', 135 => 'ZO', 157 => 'ZZO',
        180 => 'Z', 202 => 'ZZW', 225 => 'ZW', 247 => 'WZW',
        270 => 'W', 292 => 'WNW', 315 => 'NW', 337 => 'NNW'
    ];
    $closest = 'N';
    $minDiff = 360;
    foreach ($directions as $angle => $dir) {
        $diff = abs($deg - $angle);
        if ($diff < $minDiff) {
            $minDiff = $diff;
            $closest = $dir;
        }
    }
    return $closest;
}

/**
 * Broadcasts an outbound administrative notification to all active Telegram subscribers
 * and the configured central group chat via Telegram Bot API and logs it into Telegram_Messages.
 *
 * @param mysqli $conn Active database connection
 * @param string $title Notification title
 * @param string $message Notification body message
 * @param string $url Link URL
 * @return int Number of target chats reached/queued
 */
function send_telegram_broadcast_notification(mysqli $conn, string $title, string $message, string $url = '/'): int {
    // 1. Fetch Telegram site settings
    $stmt = $conn->prepare("SELECT Instelling, Waarde FROM Site_Instellingen WHERE Instelling IN ('TELEGRAM_BOT_TOKEN', 'TELEGRAM_GROUP_CHAT_ID')");
    $stmt->execute();
    $res = $stmt->get_result();
    $settings = [];
    while ($r = $res->fetch_assoc()) {
        $settings[$r['Instelling']] = $r['Waarde'];
    }
    $stmt->close();

    $botToken = $settings['TELEGRAM_BOT_TOKEN'] ?? '';
    $groupChat = $settings['TELEGRAM_GROUP_CHAT_ID'] ?? '';

    // 2. Gather target chat IDs
    $targets = [];
    if (!empty($groupChat) && $groupChat !== 'placeholder_group_id' && $groupChat !== '-1001234567890') {
        $targets[] = $groupChat;
    }

    $stmt_u = $conn->prepare("SELECT telegram_chat_id FROM Gebruikers WHERE telegram_enabled = 1 AND priv >= 1 AND telegram_chat_id IS NOT NULL AND telegram_chat_id != ''");
    $stmt_u->execute();
    $res_u = $stmt_u->get_result();
    while ($u = $res_u->fetch_assoc()) {
        $cid = trim($u['telegram_chat_id']);
        if (!empty($cid) && !in_array($cid, $targets)) {
            $targets[] = $cid;
        }
    }
    $stmt_u->close();

    if (empty($targets)) {
        return 0;
    }

    $formattedText = "📢 *{$title}*\n\n{$message}";
    if (!empty($url) && $url !== '/') {
        $cleanUrl = str_starts_with($url, '/') ? $url : '/' . $url;
        $formattedText .= "\n\n🔗 [Open Jotify](https://joti.maarleveld.app{$cleanUrl})";
    }

    $sentCount = 0;
    // 3. Outbound dispatch via Telegram Bot API if configured
    if (!empty($botToken) && !str_starts_with($botToken, 'placeholder') && !str_starts_with($botToken, '123456789:ABC')) {
        foreach ($targets as $chatId) {
            $apiUrl = "https://api.telegram.org/bot" . urlencode($botToken) . "/sendMessage";
            $postData = http_build_query([
                'chat_id' => $chatId,
                'text' => $formattedText,
                'parse_mode' => 'Markdown'
            ]);
            $opts = [
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                    'content' => $postData,
                    'timeout' => 3
                ]
            ];
            $context = stream_context_create($opts);
            @file_get_contents($apiUrl, false, $context);
            $sentCount++;
        }
    } else {
        $sentCount = count($targets);
    }

    // 4. Log broadcast into Telegram_Messages
    $payload = json_encode(['title' => $title, 'message' => $message, 'url' => $url, 'targets' => $targets], JSON_UNESCAPED_UNICODE);
    $targetStr = implode(', ', $targets);
    $stmt_log = $conn->prepare("INSERT INTO Telegram_Messages (sender, message_text, parsed_type, parsed_payload, forwarded_to, processed) VALUES ('Jotify Admin', ?, 'admin_broadcast', ?, ?, 1)");
    if ($stmt_log) {
        $stmt_log->bind_param("sss", $formattedText, $payload, $targetStr);
        $stmt_log->execute();
        $stmt_log->close();
    }

    return $sentCount;
}

