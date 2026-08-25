<?php
// Fetches participating scouting group locations and details from the Jotihunt API and updates them in the database.
define("NAME", "subscriptions");
define("JOTI_URL", "https://jotihunt.nl");
define("START_TIME", microtime(true));
date_default_timezone_set('Europe/Amsterdam');
$output = "";
$status_code = 200;

require_once(__DIR__ . '/../dblogin.php');

try {
    log2DB("-GROEPEN<br>");
    $ch = curl_init(JOTI_URL . "/api/2.0/subscriptions");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Jotify/1.0');
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err = curl_error($ch);
    curl_close($ch);

    if ($response === false || !empty($curl_err)) {
        throw new Exception("Curl error: " . $curl_err);
    }
    if ($http_code >= 400) {
        $status_code = ($http_code === 429) ? 429 : 500;
        throw new Exception("HTTP " . $http_code);
    }

    $data = json_decode($response, true);
    if (!is_array($data) || !isset($data["data"])) {
        throw new Exception("Invalid JSON response.");
    }

    $a = 1;
    foreach ($data["data"] as $key => $value) {
        $gid = $key + 1;
        $gname = $value['name'] ?? '';
        $gstreet = $value['street'] ?? '';
        $ghouse = strtoupper(($value['housenumber'] ?? '') . ($value['housenumber_addition'] ?? ''));
        $gpostcode = strtoupper(str_replace(" ", "", $value['postcode'] ?? ''));
        $gcity = $value['city'] ?? '';
        $glat = $value['lat'] ?? 0;
        $glon = $value['long'] ?? 0;
        $garea = $value['area'] ?? '';

        $stmt = $conn->prepare("INSERT INTO Groepen (id, naam, gebruikersnaam, straat, huisnummer, postcode, plaats, lat, lon, url, deelgebied) VALUES (?, ?, 'null', ?, ?, ?, ?, ?, ?, 'null', ?) ON DUPLICATE KEY UPDATE deelgebied = ?");
        $stmt->bind_param("isssssdddss", $gid, $gname, $gstreet, $ghouse, $gpostcode, $gcity, $glat, $glon, $garea, $garea);
        if ($stmt->execute()) {
            log2DB($a . " - " . $gname . "<br>");
        }
        $stmt->close();
        $a++;
    }
    log2DB("-GROEPEN<br>");
} catch (Throwable $e) {
    $status_code = ($status_code !== 200) ? $status_code : 500;
    $output .= "\nException: " . $e->getMessage();
    error_log("cron/subscriptions.php error: " . $e->getMessage());
} finally {
    recordCronLog($conn, NAME, START_TIME, $output, $status_code);
    $conn->close();
}
