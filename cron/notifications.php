<?php
// cron/notifications.php
define("NAME", "push_queue");
define("START_TIME", microtime(true));
date_default_timezone_set('Europe/Amsterdam');
$output = "";

require_once(__DIR__ . '/../vendor/autoload.php');
require_once(__DIR__ . '/../dblogin.php');

$datumtijd = date('Y-m-d H:i:s');

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

function log2DB(string $entry) {
    global $output;
    echo $entry;
    $output .= $entry."\n";
}

$res_settings = $conn->query("SELECT Instelling, Waarde FROM Site_Instellingen");
$siteSettings = [];
while ($r = $res_settings->fetch_assoc()) {
    $siteSettings[$r['Instelling']] = $r['Waarde'];
}

$publicKey = $siteSettings['VAPID_PUBLIC_KEY'] ?? '';
$privateKey = $siteSettings['VAPID_PRIVATE_KEY'] ?? '';

if (empty($publicKey) || empty($privateKey)) {
    log2DB("Error: VAPID keys are missing.");
    end_cron();
    exit;
}

$auth = [
    'VAPID' => [
        'subject' => 'mailto:admin@' . $_SERVER['SERVER_NAME'],
        'publicKey' => $publicKey,
        'privateKey' => $privateKey,
    ],
];

$webPush = new WebPush($auth);

// Get unsent notifications that haven't expired
$query = "SELECT * FROM Notification_Backlog WHERE status = 'pending' AND (send_before IS NULL OR send_before > NOW())";
$res = $conn->query($query);

if ($res->num_rows === 0) {
    log2DB("No pending notifications.");
    end_cron();
    exit;
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
            
            // sendOneNotification runs synchronously and handles the exception within its own internal scope, 
            // returning a MessageSentReport object. However, exceptions inside the VAPID cryptography 
            // might still escape, so we catch them.
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
            // If the key is totally invalid, remove the subscription so it doesn't loop forever
            $delStmt->bind_param("s", $sub['endpoint']);
            $delStmt->execute();
            log2DB("    -> Subscription removed due to invalid cryptography.\n");
        }
    }
    
    // Mark this individual backlog row as sent or failed
    // If no endpoints existed, mark as failed.
    $final_status = ($attempted_for_user && $success_for_user) ? 'sent' : 'failed';
    
    $updateStmt->bind_param("si", $final_status, $notification['id']);
    $updateStmt->execute();
}

log2DB("Finished processing. Success: {$sentCount}, Failed: {$failedCount}.");
end_cron();

function end_cron() {
    global $conn, $datumtijd, $output;
    
    if (!defined('END_TIME')) {
        define("END_TIME", microtime(true));
    }
    
    $duration = intval((END_TIME - START_TIME)*1000);
    $output_clean = addslashes($output);
    
    // prepare and bind
    $stmt = $conn->prepare("INSERT INTO Cronlogs (name, exec_time, exec_length, exec_stat, exec_output) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssiis", $p1, $p2, $p3, $p4, $p5);
    $p1 = NAME;
    $p2 = $datumtijd;
    $p3 = $duration;
    $p4 = 200;
    $p5 = $output_clean;
    $stmt->execute();
    $stmt->close();
    $conn->close();
}
