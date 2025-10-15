<?php
$sleep = intval(@$_GET['sleep']);
sleep($sleep);

define("NAME", "main");
define("JOTI_URL", "https://jotihunt.nl");
define("START_TIME", microtime(true));
date_default_timezone_set('Europe/Amsterdam');
$output = "";

require_once("../dblogin.php");

$sql = "SELECT 
*,
  (SELECT MAX(cl.exec_time) FROM Cronlogs cl WHERE cl.name = cj.name) as lastcron,
  UNIX_TIMESTAMP((SELECT MAX(cl.exec_time) FROM Cronlogs cl WHERE cl.name = cj.name)) + cj.interval as nextcron,
  FROM_UNIXTIME((UNIX_TIMESTAMP(now()) + 3600 + 3600)) as now
FROM 
`Cronjobs` cj
WHERE
  enabled = 1
GROUP BY
cj.name
HAVING
(UNIX_TIMESTAMP(now()) + 3600 + 3600) >= nextcron - 12 # added 2x 3600s for timezone correction. added 12 seconds overlap through execution
  ";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
  // output data of each row
  while($row = $result->fetch_assoc()) {
    file_get_contents($row['URL']);
  }
}