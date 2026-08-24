<?php
// cron/notifications.php ??? Processes pending Web Push notification backlog and dispatches payloads to subscriber browser endpoints.
define("NAME", "push_queue");
define("START_TIME", microtime(true));
date_default_timezone_set('Europe/Amsterdam');
$output = "";

require_once(__DIR__ . '/../vendor/autoload.php');
require_once(__DIR__ . '/../dblogin.php');

$datumtijd = date('Y-m-d H:i:s');

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

$stmt_settings = $conn->prepare("SELECT Instelling, Waarde FROM Site_Instellingen");
$stmt_settings->execute();
$res_settings = $stmt_settings->get_result();
$site_settings = [];
while ($r = $res_settings->fetch_assoc()) {
    $site_settings[$r['Instelling']] = $r['Waarde'];
}
$stmt_settings->close();

$publicKey = $site_settings['VAPID_PUBLIC_KEY'] ?? '';
$privateKey = $site_settings['VAPID_PRIVATE_KEY'] ?? '';

if (empty($publicKey) || empty($privateKey)) {
    log2DB("Error: VAPID keys are missing.");
    recordCronLog($conn, NAME, START_TIME, $output, 500);
    $conn->close();
    exit();
}

$auth = [
    'VAPID' => [
        'subject' => 'mailto:admin@' . ($_SERVER['SERVER_NAME'] ?? 'localhost'),
        'publicKey' => $publicKey,
        'privateKey' => $privateKey,
    ],
];

$webPush = new WebPush($auth);

// Get unsent notifications that haven't expired
$stmt_pending = $conn->prepare("SELECT * FROM Notification_Backlog WHERE status = 'pending' AND (send_before IS NULL OR send_before > NOW())");
$stmt_pending->execute();
$res = $stmt_pending->get_result();

if ($res->num_rows === 0) {
    log2DB("No pending notifications.");
    recordCronLog($conn, NAME, START_TIME, $output, 200);
    $conn->close();
    exit();
}

$updateStmt = $conn->prepare("UPDATE Notification_Backlog SET status = ?, sent = NOW() WHERE id = ?");
$delStmt = $conn->prepare("DELETE FROM Notification_Subscriptions WHERE endpoint = ?");

$sentCount = 0;
$failedCount = 0;

while ($notification = $res->fetch_assoc()) {
    $user_id = $notification['user_id'];
    
    // Construct the payload as expected by the Service Worker
    $payload = json_encode([
        'title' => $notification['title'],
        'body'  => $notification['message'],
        'url'   => $notification['url']
    ]);
    
    // Fetch all endpoints for this user
    $stmt = $conn->prepare("SELECT * FROM Notification_Subscriptions WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $subRes = $stmt->get_result();
    
    $success_for_user = false;
    $attempted_for_user = false;

    while ($sub = $subRes->fetch_assoc()) {
        $attempted_for_user = true;
        try {
            $subscription = Subscription::create([
                'endpoint' => $sub['endpoint'],
                'publicKey' => $sub['p256dh'],
                'authToken' => $sub['auth'],
            ]);
            
            $report = $webPush->sendOneNotification($subscription, $payload);
            
            if ($report->isSuccess()) {
                $success_for_user = true;
                $sentCount++;
            } else {
                log2DB("[x] Message failed to sent for subscription {$sub['endpoint']}: {$report->getReason()}\n");
                $failedCount++;
                if ($report->isSubscriptionExpired()) {
                    log2DB("    -> Subscription expired. Removing from database.\n");
                    $delStmt->bind_param("s", $sub['endpoint']);
                    $delStmt->execute();
                }
            }
        } catch (\Exception $e) {
            log2DB("[!] Critical Exception sending to {$sub['endpoint']}: " . $e->getMessage() . "\n");
            $failedCount++;
            $delStmt->bind_param("s", $sub['endpoint']);
            $delStmt->execute();
            log2DB("    -> Subscription removed due to invalid cryptography.\n");
        }
    }
    
    // Mark this individual backlog row as sent or failed
    $final_status = ($attempted_for_user && $success_for_user) ? 'sent' : 'failed';
    
    $updateStmt->bind_param("si", $final_status, $notification['id']);
    $updateStmt->execute();
}

log2DB("Finished processing. Success: {$sentCount}, Failed: {$failedCount}.");
recordCronLog($conn, NAME, START_TIME, $output, 200);
$conn->close();

