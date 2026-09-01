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

    // Scrape CDN group logos from HTML page
    $scraped_logos = [];
    $ch_html = curl_init(JOTI_URL . "/subscriptions");
    curl_setopt($ch_html, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_html, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch_html, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    $html = curl_exec($ch_html);
    curl_close($ch_html);

    if ($html) {
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);
        $rows = $xpath->query('//table//tbody//tr');
        foreach ($rows as $row) {
            $imgs = $xpath->query('.//img', $row);
            $img_src = ($imgs->length > 0) ? $imgs->item(0)->getAttribute('src') : '';
            $tds = $xpath->query('.//td', $row);
            if ($tds->length >= 3) {
                $row_name = trim($tds->item(2)->textContent);
                $coords = ($tds->length >= 6) ? trim($tds->item(5)->textContent) : '';
                if ($row_name && $img_src) {
                    $scraped_logos[mb_strtolower($row_name)] = $img_src;
                }
                if ($coords && $img_src) {
                    $parts = explode(',', $coords);
                    if (count($parts) === 2) {
                        $c_key = round((float)trim($parts[0]), 4) . ',' . round((float)trim($parts[1]), 4);
                        $scraped_logos['coord_' . $c_key] = $img_src;
                    }
                }
            }
        }
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

        $c_key = 'coord_' . round((float)$glat, 4) . ',' . round((float)$glon, 4);
        $gurl = $scraped_logos[mb_strtolower($gname)] ?? $scraped_logos[$c_key] ?? 'null';

        $stmt = $conn->prepare("INSERT INTO Groepen (id, naam, gebruikersnaam, straat, huisnummer, postal_code, plaats, lat, lon, url, deelgebied) VALUES (?, ?, 'null', ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE naam = VALUES(naam), straat = VALUES(straat), huisnummer = VALUES(huisnummer), postal_code = VALUES(postal_code), plaats = VALUES(plaats), lat = VALUES(lat), lon = VALUES(lon), url = VALUES(url), deelgebied = ?");
        $stmt->bind_param("isssssddsss", $gid, $gname, $gstreet, $ghouse, $gpostcode, $gcity, $glat, $glon, $gurl, $garea, $garea);
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
