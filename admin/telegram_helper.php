<?php
// admin/telegram_helper.php — AJAX handler for Telegram message simulation, feed polling, and configuration updates.

require_once(__DIR__ . '/../includes/auth.php');
require_once(__DIR__ . '/../includes/telegram_parser.php');
require_once(__DIR__ . '/../includes/telegram_bot.php');

header('Content-Type: application/json; charset=utf-8');

if ($privilege < 2) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Onvoldoende rechten']);
    exit();
}

$action = $_REQUEST['action'] ?? '';

if ($action === 'simulate') {
    $text = trim($_POST['message'] ?? '');
    if (empty($text)) {
        echo json_encode(['success' => false, 'error' => 'Geen bericht ingevoerd']);
        exit();
    }

    try {
        $result = parseAndDispatchTelegramMessage($conn, $text, 'Admin Simulator');
        echo json_encode($result);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit();
}

if ($action === 'feed') {
    $limit = min(50, max(10, (int)($_GET['limit'] ?? 25)));
    $stmt = $conn->prepare("SELECT id, telegram_message_id, sender, message_text, parsed_type, parsed_payload, forwarded_to, processed, created_at FROM Telegram_Messages ORDER BY id DESC LIMIT ?");
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    $messages = [];
    while ($row = $res->fetch_assoc()) {
        $messages[] = [
            'id' => (int)$row['id'],
            'telegram_message_id' => $row['telegram_message_id'],
            'sender' => $row['sender'],
            'message_text' => $row['message_text'],
            'parsed_type' => $row['parsed_type'],
            'parsed_payload' => json_decode($row['parsed_payload'], true),
            'forwarded_to' => $row['forwarded_to'],
            'processed' => (bool)$row['processed'],
            'created_at' => $row['created_at'],
            'relative_time' => time2str($row['created_at'])
        ];
    }
    $stmt->close();
    echo json_encode(['success' => true, 'messages' => $messages]);
    exit();
}

if ($action === 'update_config') {
    $groupChat = trim($_POST['telegram_group_chat_id'] ?? '');
    $forwardMode = in_array($_POST['telegram_forward_mode'] ?? '', ['forward', 'relay']) ? $_POST['telegram_forward_mode'] : 'forward';
    
    $stmt1 = $conn->prepare("INSERT INTO Site_Instellingen (Instelling, Waarde, Omschrijving) VALUES ('TELEGRAM_GROUP_CHAT_ID', ?, 'Telegram centrale groepsapp chat ID') ON DUPLICATE KEY UPDATE Waarde = VALUES(Waarde)");
    $stmt1->bind_param("s", $groupChat);
    $stmt1->execute();
    $stmt1->close();

    $stmt2 = $conn->prepare("INSERT INTO Site_Instellingen (Instelling, Waarde, Omschrijving) VALUES ('TELEGRAM_FORWARD_MODE', ?, 'Telegram doorstuurmodus') ON DUPLICATE KEY UPDATE Waarde = VALUES(Waarde)");
    $stmt2->bind_param("s", $forwardMode);
    $stmt2->execute();
    $stmt2->close();

    echo json_encode(['success' => true, 'message' => 'Telegram configuratie opgeslagen']);
    exit();
}

if ($action === 'webhook_info') {
    $bot = new TelegramBot($conn);
    $info = $bot->getWebhookInfo();
    $me = $bot->getMe();
    echo json_encode([
        'success' => true,
        'configured' => $bot->isConfigured(),
        'me' => $me,
        'webhook' => $info
    ]);
    exit();
}

if ($action === 'set_webhook') {
    $bot = new TelegramBot($conn);
    if (!$bot->isConfigured()) {
        echo json_encode(['success' => false, 'error' => 'Bot token is niet geconfigureerd in Site Instellingen']);
        exit();
    }

    $stmt_sec = $conn->prepare("SELECT Waarde FROM Site_Instellingen WHERE Instelling = 'TELEGRAM_INGEST_SECRET'");
    $stmt_sec->execute();
    $secRow = $stmt_sec->get_result()->fetch_assoc();
    $stmt_sec->close();
    $secret = $secRow['Waarde'] ?? null;

    $webhookUrl = 'https://joti.maarleveld.app/api/telegram_webhook.php';
    $res = $bot->setWebhook($webhookUrl, $secret);

    echo json_encode([
        'success' => ($res['ok'] ?? false),
        'response' => $res
    ]);
    exit();
}

if ($action === 'delete_webhook') {
    $bot = new TelegramBot($conn);
    $res = $bot->deleteWebhook(false);
    echo json_encode([
        'success' => ($res['ok'] ?? false),
        'response' => $res
    ]);
    exit();
}

if ($action === 'test_bot') {
    $bot = new TelegramBot($conn);
    $me = $bot->getMe();
    echo json_encode([
        'success' => ($me['ok'] ?? false),
        'response' => $me
    ]);
    exit();
}

echo json_encode(['success' => false, 'error' => 'Ongeldige actie']);
