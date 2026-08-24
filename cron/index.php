<?php
// Master cron scheduler that queries due scheduled tasks and executes them asynchronously via CLI or HTTP.
$sleep = intval(@$_GET['sleep']);
sleep($sleep);

define("NAME", "main");
define("JOTI_URL", "https://jotihunt.nl");
define("START_TIME", microtime(true));
date_default_timezone_set('Europe/Amsterdam');
$output = "";

require_once('../dblogin.php');

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
$stmt_cron->execute();
$result = $stmt_cron->get_result();

if ($result->num_rows > 0) {
  while($row = $result->fetch_assoc()) {
    $target = $row['URL'];
    
    // Execute as an isolated CLI process if it's a local file path (e.g., /var/www/...)
    if (strpos($target, '/') === 0) {
        exec("php " . escapeshellarg($target) . " > /dev/null 2>&1 &");
    } 
    // Execute as an isolated HTTP request if it's a web URL (e.g., http://...)
    else if (strpos($target, 'http') === 0) {
        $ch = curl_init($target);
        // Set a 1-second timeout so the main index.php loop doesn't hang waiting for a response
        curl_setopt($ch, CURLOPT_TIMEOUT, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);
    }
  }
}
$conn->close();