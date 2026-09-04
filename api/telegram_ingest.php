<?php
// api/telegram_ingest.php — Receives incoming Telegram messages from the MTProto listener or custom bot webhooks, parses them, and executes game dispatch.

header('Content-Type: application/json; charset=utf-8');

require_once(__DIR__ . '/../dblogin.php');
require_once(__DIR__ . '/../includes/telegram_parser.php');

// 1. Fetch Telegram security secret & settings
$stmt_settings = $conn->prepare("SELECT Instelling, Waarde FROM Site_Instellingen WHERE Instelling IN ('TELEGRAM_INGEST_SECRET', 'TELEGRAM_GROUP_CHAT_ID', 'TELEGRAM_REGISTRATION_CODE', 'TELEGRAM_FORWARD_MODE')");
$stmt_settings->execute();
$res_settings = $stmt_settings->get_result();
$settings = [];
while ($row = $res_settings->fetch_assoc()) {
    $settings[$row['Instelling']] = $row['Waarde'];
}
$stmt_settings->close();

$configuredSecret = $settings['TELEGRAM_INGEST_SECRET'] ?? '';

// 2. Validate Authorization
$headers = function_exists('getallheaders') ? getallheaders() : [];
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? $headers['Authorization'] ?? $headers['authorization'] ?? '';
$secretHeader = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? $headers['X-Telegram-Bot-Api-Secret-Token'] ?? $headers['x-telegram-bot-api-secret-token'] ?? '';
$providedSecret = '';

if (!empty($secretHeader)) {
    $providedSecret = $secretHeader;
} elseif (preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
    $providedSecret = $matches[1];
} elseif (isset($_REQUEST['secret'])) {
    $providedSecret = $_REQUEST['secret'];
}

if (empty($configuredSecret) || !hash_equals($configuredSecret, $providedSecret)) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized: Invalid or missing Telegram ingest secret.'
    ]);
    exit();
}

$action = $_GET['action'] ?? '';

// 3. ACTION: Get Active Subscribers (For MTProto forwarder)
if ($action === 'subscribers') {
    $subscribers = [];
    
    // Group chat ID
    $groupChat = $settings['TELEGRAM_GROUP_CHAT_ID'] ?? '';
    
    // Individual users
    $stmt_users = $conn->prepare("SELECT id, voornaam, achternaam, gebruikersnaam, priv, telegram_chat_id FROM Gebruikers WHERE priv >= 1 AND telegram_enabled = 1 AND telegram_chat_id IS NOT NULL AND telegram_chat_id != ''");
    $stmt_users->execute();
    $res_users = $stmt_users->get_result();
    while ($u = $res_users->fetch_assoc()) {
        $subscribers[] = [
            'id' => (int)$u['id'],
            'name' => trim($u['voornaam'] . ' ' . $u['achternaam']),
            'username' => $u['gebruikersnaam'],
            'priv' => (int)$u['priv'],
            'chat_id' => $u['telegram_chat_id']
        ];
    }
    $stmt_users->close();

    echo json_encode([
        'success' => true,
        'group_chat' => $groupChat,
        'forward_mode' => $settings['TELEGRAM_FORWARD_MODE'] ?? 'forward',
        'subscribers' => $subscribers
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit();
}

// 4. ACTION: Get Status & Registration Code
if ($action === 'info') {
    echo json_encode([
        'success' => true,
        'registration_code' => $settings['TELEGRAM_REGISTRATION_CODE'] ?? null,
        'forward_mode' => $settings['TELEGRAM_FORWARD_MODE'] ?? 'forward',
        'group_chat_configured' => !empty($settings['TELEGRAM_GROUP_CHAT_ID'])
    ]);
    exit();
}

// 5. Ingest POST Message
$rawBody = file_get_contents('php://input');
$data = json_decode($rawBody, true);

$messageText = '';
$sender = '@Jotihunt_bot';
$msgId = null;
$forwardedTo = null;

if (is_array($data)) {
    // Direct listener format
    if (isset($data['text'])) {
        $messageText = $data['text'];
        $sender = $data['sender'] ?? '@Jotihunt_bot';
        $msgId = isset($data['message_id']) ? (int)$data['message_id'] : null;
        $forwardedTo = isset($data['forwarded_to']) ? (is_array($data['forwarded_to']) ? json_encode($data['forwarded_to']) : (string)$data['forwarded_to']) : null;
    }
    // Standard Telegram Bot Webhook payload format
    elseif (isset($data['message']['text'])) {
        $messageText = $data['message']['text'];
        $msgId = (int)($data['message']['message_id'] ?? 0);
        $from = $data['message']['from'] ?? [];
        $sender = '@' . ($from['username'] ?? ($from['first_name'] ?? 'telegram_user'));
    }
}

if (empty($messageText) && !empty($_POST['text'])) {
    $messageText = $_POST['text'];
    $sender = $_POST['sender'] ?? '@Jotihunt_bot';
    $msgId = isset($_POST['message_id']) ? (int)$_POST['message_id'] : null;
}

if (empty($messageText)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Missing message text.'
    ]);
    exit();
}

// Execute parsing, game database updates, and push notifications
try {
    $result = parseAndDispatchTelegramMessage($conn, $messageText, $sender, $msgId, $forwardedTo);
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Parser exception: ' . $e->getMessage()
    ]);
} finally {
    $conn->close();
}
