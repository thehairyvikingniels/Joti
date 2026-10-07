<?php
// Fetches participating scouting group locations and details from the Jotihunt API and updates them in the database.
define("NAME", "API_Subscriptions");
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
        if (class_exists('DOMDocument')) {
            $dom = new DOMDocument();
            @$dom->loadHTML($html);
            $xpath = new DOMXPath($dom);

            // Strategy 1: Modern card layout <li class="group ..."> with <p class="... text-jh-primary ..."> and <img ...>
            $cards = $xpath->query('//li[contains(@class, "group")] | //ul[@role="list"]/li');
            foreach ($cards as $card) {
                $img_nodes = $xpath->query('.//img', $card);
                $name_nodes = $xpath->query('.//p[contains(@class, "text-jh-primary")] | .//h3 | .//p[1]', $card);

                $img_src = ($img_nodes->length > 0) ? trim($img_nodes->item(0)->getAttribute('src')) : '';
                $card_name = ($name_nodes->length > 0) ? trim($name_nodes->item(0)->textContent) : '';

                if ($card_name && $img_src) {
                    $key = function_exists('mb_strtolower') ? mb_strtolower($card_name) : strtolower($card_name);
                    $scraped_logos[$key] = $img_src;
                }
            }

            // Strategy 2: Directly inspect <img> tags with alt starting with "Logo van "
            $logo_imgs = $xpath->query('//img[starts-with(@alt, "Logo van ")]');
            foreach ($logo_imgs as $img) {
                $alt = trim($img->getAttribute('alt'));
                $src = trim($img->getAttribute('src'));
                $name = trim(preg_replace('/^Logo van\s+/i', '', $alt));
                $key = function_exists('mb_strtolower') ? mb_strtolower($name) : strtolower($name);
                if ($key && $src && !isset($scraped_logos[$key])) {
                    $scraped_logos[$key] = $src;
                }
            }

            // Strategy 3: Legacy table layout fallback
            $rows = $xpath->query('//table//tbody//tr');
            foreach ($rows as $row) {
                $imgs = $xpath->query('.//img', $row);
                $img_src = ($imgs->length > 0) ? $imgs->item(0)->getAttribute('src') : '';
                $tds = $xpath->query('.//td', $row);
                if ($tds->length >= 3) {
                    $row_name = trim($tds->item(2)->textContent);
                    $coords = ($tds->length >= 6) ? trim($tds->item(5)->textContent) : '';
                    $key = function_exists('mb_strtolower') ? mb_strtolower($row_name) : strtolower($row_name);
                    if ($key && $img_src && !isset($scraped_logos[$key])) {
                        $scraped_logos[$key] = $img_src;
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

        // Strategy 4: Regex fallback if DOMDocument is unavailable or yielded no results
        if (empty($scraped_logos)) {
            if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]+alt=["\']Logo van\s+([^"\']+)["\']/i', $html, $m)) {
                for ($i = 0; $i < count($m[0]); $i++) {
                    $src = trim($m[1][$i]);
                    $name = trim($m[2][$i]);
                    $key = function_exists('mb_strtolower') ? mb_strtolower($name) : strtolower($name);
                    if ($key && $src && !isset($scraped_logos[$key])) {
                        $scraped_logos[$key] = $src;
                    }
                }
            }
            if (preg_match_all('/<img[^>]+alt=["\']Logo van\s+([^"\']+)["\'][^>]+src=["\']([^"\']+)["\']/i', $html, $m)) {
                for ($i = 0; $i < count($m[0]); $i++) {
                    $name = trim($m[1][$i]);
                    $src = trim($m[2][$i]);
                    $key = function_exists('mb_strtolower') ? mb_strtolower($name) : strtolower($name);
                    if ($key && $src && !isset($scraped_logos[$key])) {
                        $scraped_logos[$key] = $src;
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

        $lower_name = function_exists('mb_strtolower') ? mb_strtolower($gname) : strtolower($gname);
        $c_key = 'coord_' . round((float)$glat, 4) . ',' . round((float)$glon, 4);
        $gurl = $scraped_logos[$lower_name] ?? $scraped_logos[$c_key] ?? '';

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
