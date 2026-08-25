<?php
// Syncs news articles, assignments, and hints from the Jotihunt API into the database and triggers push notifications.
define("NAME", "articles");
define("JOTI_URL", "https://jotihunt.nl");
define("START_TIME", microtime(true));
date_default_timezone_set('Europe/Amsterdam');
$output = "";
$status_code = 200;

require_once(__DIR__ . "/../dblogin.php");

try {
    log2DB("-ARTICLES</br>");

    $ch = curl_init(JOTI_URL . "/api/2.0/articles");
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
        throw new Exception("Invalid JSON response from Jotihunt articles API.");
    }

    foreach ($data['data'] as $item) {
        log2DB($item['id'] . " - " . $item['title'] . "</br>");

        $news = $conn->prepare("INSERT INTO Nieuws (id, titel, inhoud, datum) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE id = id");
        $assignment = $conn->prepare("INSERT INTO Opdrachten (id, titel, inhoud, datum, eindtijd, maxpunten) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE id = id");
        $hint = $conn->prepare("INSERT INTO Hints (id, titel, inhoud, datum) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE id = id");

        if ($item['type'] == 'news') {
            $id = $item['id'];
            $title = $item['title'];
            $content = $item['message']['content'] ?? '';
            $pubTime = date("Y-m-d H:i:s", strtotime($item['publish_at']));

            $news->bind_param("isss", $id, $title, $content, $pubTime);
            $news->execute();
            if ($news->affected_rows === 1) {
                send_push_notification('ALL', 'Nieuwsbericht', $title, "/nieuws#nieuws-{$id}", 'cron/articles', null, 'nieuws');
            }
        } else if ($item['type'] == 'assignment') {
            $id = $item['id'];
            $title = $item['title'];
            $content = $item['message']['content'] ?? '';
            $pubTime = date("Y-m-d H:i:s", strtotime($item['publish_at']));
            $endTime = date("Y-m-d H:i:s", strtotime($item['message']['end_time']));
            $maxPoints = $item['message']['max_points'] ?? 0;

            $assignment->bind_param("issssi", $id, $title, $content, $pubTime, $endTime, $maxPoints);
            $assignment->execute();
            if ($assignment->affected_rows === 1) {
                send_push_notification('ALL', 'Nieuwe Opdracht', $title, "/opdrachten#opdracht-{$id}", 'cron/articles', null, 'opdrachten');
            }
        } else if ($item['type'] == 'hint') {
            $id = $item['id'];
            $title = $item['title'];
            $content = $item['message']['content'] ?? '';
            $pubTime = date("Y-m-d H:i:s", strtotime($item['publish_at']));

            $hint->bind_param("isss", $id, $title, $content, $pubTime);
            $hint->execute();
            if ($hint->affected_rows === 1) {
                send_push_notification('ALL', 'Nieuwe Hint', $title, "/hints#hint-{$id}", 'cron/articles', null, 'hints');
            }
        }
        $news->close();
        $assignment->close();
        $hint->close();
    }

    log2DB("-ARTICLES</br>");
} catch (Throwable $e) {
    $status_code = ($status_code !== 200) ? $status_code : 500;
    $output .= "\nException: " . $e->getMessage();
    error_log("cron/articles.php error: " . $e->getMessage());
} finally {
    recordCronLog($conn, NAME, START_TIME, $output, $status_code);
    $conn->close();
}
