<?php
define("NAME", "main");
define("JOTI_URL", "https://jotihunt.nl");
define("START_TIME", microtime(true));
date_default_timezone_set('Europe/Amsterdam');
$output = "";

$token = "S5d78c5180a4457.86389445";
// $servername = "localhost";
// $username = "nielsmd365_joti";
// $password = "jotihunt2019";
// $dbname = "nielsmd365_joti";

$servername = "localhost";
$username = "maarleveld_one_joti";
$password = "jVfxEi8VxemB7mTF";
$dbname = "maarleveld_one_joti";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$datumtijd = date('Y-m-d H:i:s');

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
(UNIX_TIMESTAMP(now()) + 3600 + 3600) >= nextcron
  ";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
  // output data of each row
  while($row = $result->fetch_assoc()) {
    file_get_contents($row['URL']);
  }
}