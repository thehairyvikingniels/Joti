<?php
// Syncs photo assignments from the Jotihunt API into the database and triggers push notifications.
define("NAME", "API_PhotoAssign");
define("JOTI_URL", "https://jotihunt.nl");
define("START_TIME", microtime(true));
date_default_timezone_set('Europe/Amsterdam');
$output = "";
$status_code = 200;

require_once(__DIR__ . "/../dblogin.php");
require_once(__DIR__ . "/../includes/helpers.php");

try {
    log2DB("-PHOTO_ASSIGNMENTS</br>");

    $ch = curl_init(JOTI_URL . "/api/2.0/photoAssignments");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Jotify/1.0');
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err = curl_error($ch);
    curl_close($ch);

    if ($response === false || !empty($curl_err)) {
        throw new Exception("Curl error connecting to Jotihunt API: " . $curl_err);
    }

    if ($http_code >= 400) {
        $status_code = ($http_code === 429) ? 429 : 500;
        throw new Exception("Jotihunt API returned HTTP " . $http_code . ": " . substr($response, 0, 200));
    }

    $data = json_decode($response, true);
    if (!is_array($data) || !isset($data['data'])) {
        throw new Exception("Invalid JSON response from Jotihunt photoAssignments API.");
    }

    $count = 0;
    foreach ($data['data'] as $item) {
        $title = trim($item['title'] ?? '');
        $description = trim($item['description'] ?? '');
        $startAtRaw = $item['start_at'] ?? null;
        $endAtRaw = $item['end_at'] ?? null;
        $externalId = isset($item['id']) ? (int)$item['id'] : null;

        if (empty($title) || empty($startAtRaw) || empty($endAtRaw)) {
            continue;
        }

        $startAt = date("Y-m-d H:i:s", strtotime($startAtRaw));
        $endAt = date("Y-m-d H:i:s", strtotime($endAtRaw));

        log2DB("Foto-opdracht: " . htmlspecialchars($title) . "</br>");

        // Insert or update on duplicate (titel, start_at)
        $stmt = $conn->prepare("
            INSERT INTO Fotoopdrachten (external_id, titel, omschrijving, start_at, end_at)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                external_id = COALESCE(VALUES(external_id), external_id),
                omschrijving = VALUES(omschrijving),
                end_at = VALUES(end_at)
        ");

        if ($stmt) {
            $stmt->bind_param("issss", $externalId, $title, $description, $startAt, $endAt);
            $stmt->execute();

            if ($stmt->affected_rows === 1) {
                // Brand new photo assignment
                $insertedId = $stmt->insert_id;
                send_push_notification(
                    'ALL',
                    'Nieuwe Foto-opdracht',
                    $title,
                    "/fotoopdrachten#opdracht-{$insertedId}",
                    'cron/fotoopdrachten',
                    null,
                    'opdrachten'
                );
                $output .= "Nieuwe foto-opdracht toegevoegd: {$title} (ID: {$insertedId})\n";
            }
            $stmt->close();
            $count++;
        }
    }

    $output .= "Succesvol {$count} foto-opdrachten verwerkt.\n";
    log2DB("-PHOTO_ASSIGNMENTS</br>");
} catch (Throwable $e) {
    $status_code = ($status_code !== 200) ? $status_code : 500;
    $output .= "\nException: " . $e->getMessage();
    error_log("cron/fotoopdrachten.php error: " . $e->getMessage());
} finally {
    recordCronLog($conn, NAME, START_TIME, $output, $status_code);
    $conn->close();
}
?>
