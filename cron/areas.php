<?php
// Fetches fox area status updates from the Jotihunt API, logs changes, and sends push notifications on status updates.
define("NAME", "areas");
define("JOTI_URL", "https://jotihunt.nl");
define("START_TIME", microtime(true));
date_default_timezone_set('Europe/Amsterdam');
$output = "";
$status_code = 200;

require_once(__DIR__ . "/../dblogin.php");

try {
    log2DB("-VOSSEN<br>");
    $sql = "SELECT datumtijd FROM Voslog ORDER BY datumtijd DESC LIMIT 1";
    $result = mysqli_query($conn, $sql);

    $lastupdate = '2000-01-01 00:00:00';
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $lastupdate = $row['datumtijd'];
    }

    $ch = curl_init(JOTI_URL . "/api/2.0/areas");
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
    if (!is_array($data) || !isset($data["data"])) {
        throw new Exception("Invalid JSON response from Jotihunt areas API.");
    }

    $statusChanged = 0;
    $updatedAt = time();
    foreach ($data["data"] as $value) {
        if (strtotime($lastupdate) < strtotime($value["updated_at"])) {
            log2DB("Status " . $value['name'] . " has changed to " . $value['status'] . "</br>");
            $updatedAt = strtotime($value["updated_at"]);
            $statusChanged = 1;
        }
    }

    if ($statusChanged) {
        $dt = date("Y-m-d H:i:s", $updatedAt);
        $stmt = $conn->prepare("INSERT INTO Voslog (datumtijd, vos, status) VALUES (?, ?, ?)");
        
        foreach ($data["data"] as $value) {
            $foxName = ucfirst(strtolower($value['name']));
            $status = 0;
            if ($value['status'] == 'orange') $status = 1;
            elseif ($value['status'] == 'green') $status = 2;
            
            $stmt->bind_param("ssi", $dt, $foxName, $status);
            if (!$stmt->execute()) {
                log2DB("Error inserting for {$foxName}: " . $stmt->error . "<br>");
            } else {
                foreach ($data["data"] as $v) {
                    if (ucfirst(strtolower($v['name'])) == $foxName && strtotime($lastupdate) < strtotime($v["updated_at"])) {
                        $status_nl = ($v['status'] == 'red') ? 'Rood' : (($v['status'] == 'orange') ? 'Oranje' : 'Groen');
                        send_push_notification(
                            'ALL', 
                            "Vosstatus {$foxName}", 
                            "De status van {$foxName} is nu {$status_nl}.",
                            '/vossen',
                            'cron/areas',
                            null,
                            'vosstatus'
                        );
                        break;
                    }
                }
            }
        }
        $stmt->close();
    } else {
        log2DB("No changes</br>");
    }
    log2DB("-VOSSEN<br>");
} catch (Throwable $e) {
    $status_code = ($status_code !== 200) ? $status_code : 500;
    $output .= "\nException: " . $e->getMessage();
    error_log("cron/areas.php error: " . $e->getMessage());
} finally {
    recordCronLog($conn, NAME, START_TIME, $output, $status_code);
    $conn->close();
}
