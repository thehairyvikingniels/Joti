<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

require_once('../dblogin.php');

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $endpoint = $_GET['endpoint'] ?? '';
    if (!$endpoint) {
        http_response_code(400);
        echo json_encode(['error' => 'Endpoint missing']);
        exit;
    }
    $stmt = $conn->prepare("DELETE FROM Notification_Subscriptions WHERE endpoint = ? AND user_id = ?");
    $stmt->bind_param("si", $endpoint, $_SESSION['id']);
    $stmt->execute();
    echo json_encode(['data' => ['success' => true]]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['endpoint'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid subscription payload']);
    exit;
}

$endpoint = $input['endpoint'];
$device_name = $input['device_name'] ?? 'Onbekend apparaat';
$p256dh = $input['keys']['p256dh'] ?? '';
$auth = $input['keys']['auth'] ?? '';
$user_id = $_SESSION['id'];

// Check if subscription already exists for this endpoint
$stmt = $conn->prepare("SELECT id FROM Notification_Subscriptions WHERE endpoint = ?");
$stmt->bind_param("s", $endpoint);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows > 0) {
    // Update existing
    $row = $res->fetch_assoc();
    $stmt_update = $conn->prepare("UPDATE Notification_Subscriptions SET user_id = ?, device_name = ?, p256dh = ?, auth = ?, created_at = NOW() WHERE id = ?");
    $stmt_update->bind_param("isssi", $user_id, $device_name, $p256dh, $auth, $row['id']);
    $stmt_update->execute();
} else {
    // Insert new
    $stmt_insert = $conn->prepare("INSERT INTO Notification_Subscriptions (user_id, endpoint, device_name, p256dh, auth) VALUES (?, ?, ?, ?, ?)");
    $stmt_insert->bind_param("issss", $user_id, $endpoint, $device_name, $p256dh, $auth);
    $stmt_insert->execute();
}

echo json_encode(['data' => ['success' => true]]);
