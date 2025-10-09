<?php
define("NAME", "areas");
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


// Vos-status ophalen
log2DB("-VOSSEN<br>");
$sql = "Select datumtijd FROM Voslog ORDER BY datumtijd desc LIMIT 1";
$result = mysqli_query($conn, $sql);

if (mysqli_num_rows($result) > 0) {
    // output data of each row
    $a = 0;
    while($row = mysqli_fetch_assoc($result)) {
      $lastupdate = $row['datumtijd'];
    }
}



$data = json_decode(file_get_contents(JOTI_URL."/api/2.0/areas"),true);
$statusChanged = 0;
foreach($data["data"] as $value){
  if (strtotime($lastupdate) < strtotime($value["updated_at"])) {
    log2DB("Status ".$value['name']." has changed to ".$value['status']."</br>");
    $updatedAt = strtotime($value["updated_at"]);
    $statusChanged = 1;
  }
  switch ((string)$value['status']) {
    case 'red':
      ${strtolower($value['name'])."_status"} = 0;
      break;
    case 'orange':
      ${strtolower($value['name'])."_status"} = 1;
      break;
    case 'green':
      ${strtolower($value['name'])."_status"} = 2;
      break;
  }
}
if ($statusChanged) {

  $sql = "INSERT INTO Voslog (datumtijd, alpha, bravo, charlie, delta, echo, foxtrot, golf, hotel)
  VALUES ('".date("Y-m-d H:i:s", $updatedAt)."','".$alpha_status."','".$bravo_status."','".$charlie_status."','".$delta_status."','".$echo_status."','".$foxtrot_status."','".$golf_status."','".$hotel_status."')";
  
  if (!mysqli_query($conn, $sql)) {
    log2DB("Error: " . $sql . "<br>" . mysqli_error($conn));
  }
} else {
    log2DB("No changes</br>");
}

log2DB("-VOSSEN<br>");


define("END_TIME", microtime(true));
$duration = intval((END_TIME - START_TIME)*1000);
$output = addslashes($output);


// prepare and bind
$stmt = $conn->prepare("INSERT INTO Cronlogs (name, exec_time, exec_length, exec_stat, exec_output)
VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("ssiis", $p1, $p2, $p3, $p4, $p5);
$p1 = NAME;
$p2 = $datumtijd;
$p3 = $duration;
$p4 = 200;
$p5 = $output;
$stmt->execute();

if ($conn->query($sql) === TRUE) {
  
}
$stmt->close();
$conn->close();

function log2DB(string $entry) {
    global $output;
    echo $entry;
    $output .= $entry."\n";
}