<?php
// Master cron scheduler that queries due scheduled tasks and executes them asynchronously via CLI or HTTP.
$sleep = intval(@$_GET['sleep'] ?? 0);
if ($sleep > 0) {
    sleep($sleep);
}

define("NAME", "main");
define("START_TIME", microtime(true));
date_default_timezone_set('Europe/Amsterdam');

require_once(__DIR__ . '/../dblogin.php');
require_once(__DIR__ . '/../includes/helpers.php');

$sql = "SELECT 
  cj.*,
  (SELECT MAX(cl.exec_time) FROM Cronlogs cl WHERE cl.name = cj.name) as lastcron,
  UNIX_TIMESTAMP((SELECT MAX(cl.exec_time) FROM Cronlogs cl WHERE cl.name = cj.name)) + cj.interval as nextcron
FROM 
  `Cronjobs` cj
WHERE
  cj.enabled = 1
GROUP BY
  cj.name
HAVING
  nextcron IS NULL OR (UNIX_TIMESTAMP(now()) + 7200) >= nextcron - 12"; 

$stmt_cron = $conn->prepare($sql);
if ($stmt_cron) {
    $stmt_cron->execute();
    $result = $stmt_cron->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $target = $row['URL'];
            
            // Execute as an isolated HTTP request if it's a web URL
            if (strpos($target, 'http') === 0) {
                $ch = curl_init($target);
                curl_setopt($ch, CURLOPT_TIMEOUT, 1);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_exec($ch);
                curl_close($ch);
            } 
            // Execute as an isolated CLI process if it's a local file path (absolute or relative)
            else {
                $filePath = (strpos($target, '/') === 0) ? $target : realpath(__DIR__ . '/../' . $target);
                if ($filePath && file_exists($filePath)) {
                    exec("php " . escapeshellarg($filePath) . " > /dev/null 2>&1 &");
                }
            }
        }
    }
    $stmt_cron->close();
}
$conn->close();
