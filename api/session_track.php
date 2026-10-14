<?php
declare(strict_types=1);

/**
 * api/session_track.php
 *
 * Fast, lightweight telemetry ingestion endpoint.
 * Receives initial device/viewport/network handshakes and batched user interaction events.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$userId = (int)($_SESSION['id'] ?? ($_SESSION['user_id'] ?? 0));
if ($userId <= 0) {
    http_response_code(204);
    exit();
}

require_once(__DIR__ . '/../dblogin.php');
require_once(__DIR__ . '/../includes/helpers.php');
require_once(__DIR__ . '/../includes/db.php');

$sessionId = !empty($_SESSION['tracker_session_id']) ? (int)$_SESSION['tracker_session_id'] : null;
if ($sessionId === null) {
    $sessionId = ensureTrackedSession($conn, $userId);
}

if (!$sessionId) {
    http_response_code(204);
    exit();
}

$rawInput = file_get_contents('php://input');
if (empty($rawInput)) {
    http_response_code(400);
    echo json_encode(['error' => 'Empty payload']);
    exit();
}

$data = json_decode($rawInput, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit();
}

// 1. Process Handshake (Device, Screen, Viewport, Network)
if (!empty($data['handshake']) && is_array($data['handshake'])) {
    $hs = $data['handshake'];

    $vw = isset($hs['viewport_width']) ? max(0, min(10000, (int)$hs['viewport_width'])) : null;
    $vh = isset($hs['viewport_height']) ? max(0, min(10000, (int)$hs['viewport_height'])) : null;
    $sw = isset($hs['screen_width']) ? max(0, min(10000, (int)$hs['screen_width'])) : null;
    $sh = isset($hs['screen_height']) ? max(0, min(10000, (int)$hs['screen_height'])) : null;
    $pr = isset($hs['pixel_ratio']) ? max(0.1, min(10.0, (float)$hs['pixel_ratio'])) : null;
    $nt = isset($hs['network_type']) ? substr(trim((string)$hs['network_type']), 0, 32) : null;
    $dl = (isset($hs['network_downlink']) && is_numeric($hs['network_downlink'])) ? (float)$hs['network_downlink'] : null;
    $rtt = (isset($hs['network_rtt']) && is_numeric($hs['network_rtt'])) ? (int)$hs['network_rtt'] : null;
    $platform = isset($hs['platform']) ? substr(trim((string)$hs['platform']), 0, 64) : null;
    $lang = isset($hs['language']) ? substr(trim((string)$hs['language']), 0, 32) : null;
    $tp = isset($hs['touch_points']) ? max(0, min(20, (int)$hs['touch_points'])) : null;

    $stmtHs = $conn->prepare(
        "UPDATE User_Sessions 
         SET viewport_width = ?, viewport_height = ?, screen_width = ?, screen_height = ?, 
             pixel_ratio = ?, network_type = ?, network_downlink = ?, network_rtt = ?, 
             platform = ?, language = ?, touch_points = ?, last_activity = NOW(3) 
         WHERE id = ? AND user_id = ?"
    );
    if ($stmtHs) {
        $stmtHs->bind_param(
            "iiiidsdissiii",
            $vw, $vh, $sw, $sh,
            $pr, $nt, $dl, $rtt,
            $platform, $lang, $tp,
            $sessionId, $userId
        );
        $stmtHs->execute();
        $stmtHs->close();
        $_SESSION['tracker_handshake_done'] = true;
    }
}

// 2. Process Telemetry Events (Batch up to 25 events)
$events = [];
if (!empty($data['events']) && is_array($data['events'])) {
    $events = $data['events'];
} elseif (!empty($data['event']) && is_array($data['event'])) {
    $events = [$data['event']];
}

if (!empty($events)) {
    $allowedTypes = ['page_view', 'click', 'form_submit', 'tab_switch', 'modal', 'visibility'];
    $batchSentAt = isset($data['batch_sent_at']) && is_numeric($data['batch_sent_at']) ? (float)$data['batch_sent_at'] : null;
    $serverNow = microtime(true);

    $stmtEv = $conn->prepare(
        "INSERT INTO Session_Events 
         (session_id, event_type, page_url, referrer, target_tag, target_id, target_label, metadata, created_at) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );

    if ($stmtEv) {
        $count = 0;
        foreach ($events as $ev) {
            if (!is_array($ev) || $count >= 25) {
                break;
            }

            $type = (string)($ev['event_type'] ?? '');
            if (!in_array($type, $allowedTypes, true)) {
                continue;
            }

            $pageUrl = substr(trim((string)($ev['page_url'] ?? '')), 0, 255);
            if (empty($pageUrl)) {
                continue;
            }

            $referrer = isset($ev['referrer']) ? substr(trim((string)$ev['referrer']), 0, 255) : null;
            $targetTag = isset($ev['target_tag']) ? substr(strtolower(trim((string)$ev['target_tag'])), 0, 32) : null;
            $targetId = isset($ev['target_id']) ? substr(trim((string)$ev['target_id']), 0, 128) : null;
            $targetLabel = isset($ev['target_label']) ? substr(trim((string)$ev['target_label']), 0, 255) : null;

            $metaJson = null;
            if (!empty($ev['metadata']) && is_array($ev['metadata'])) {
                $metaJson = json_encode($ev['metadata'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            // Calculate millisecond-accurate timestamp
            if (isset($ev['client_timestamp']) && is_numeric($ev['client_timestamp']) && $batchSentAt !== null) {
                $offsetSec = max(0.0, ($batchSentAt - (float)$ev['client_timestamp']) / 1000.0);
                $eventTs = max(0.0, $serverNow - $offsetSec);
                $sec = (int)$eventTs;
                $ms = (int)round(($eventTs - $sec) * 1000);
                if ($ms >= 1000) { $sec += 1; $ms = 0; }
                $createdAt = date('Y-m-d H:i:s', $sec) . '.' . sprintf('%03d', $ms);
            } else {
                $createdAt = (new DateTime())->format('Y-m-d H:i:s.v');
            }

            $stmtEv->bind_param("issssssss", $sessionId, $type, $pageUrl, $referrer, $targetTag, $targetId, $targetLabel, $metaJson, $createdAt);
            $stmtEv->execute();
            $count++;
        }
        $stmtEv->close();
    }

    $conn->query("UPDATE User_Sessions SET last_activity = NOW(3) WHERE id = " . (int)$sessionId);
    $_SESSION['tracker_last_seen'] = time();
}

echo json_encode(['ok' => true]);
