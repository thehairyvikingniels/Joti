<?php
define("NAME", "areas");
define("JOTI_URL", "https://jotihunt.nl");
define("START_TIME", microtime(true));
date_default_timezone_set('Europe/Amsterdam');
$output = "";

require_once("../dblogin.php");
require_once("../functies.php");

$datumtijd = date('Y-m-d H:i:s');


// Vos-status ophalen
log2DB("-VOSSEN<br>");
$sql = "Select datumtijd FROM Voslog ORDER BY datumtijd desc LIMIT 1";
$result = mysqli_query($conn, $sql);

$lastupdate = '2000-01-01 00:00:00';
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
}

if ($statusChanged) {
  $dt = date("Y-m-d H:i:s", $updatedAt);
  $stmt = $conn->prepare("INSERT INTO Voslog (datumtijd, vos, status) VALUES (?, ?, ?)");
  
  foreach($data["data"] as $value) {
      $foxName = ucfirst(strtolower($value['name']));
      $status = 0;
      if ($value['status'] == 'orange') $status = 1;
      elseif ($value['status'] == 'green') $status = 2;
      
      $stmt->bind_param("ssi", $dt, $foxName, $status);
      if (!$stmt->execute()) {
          log2DB("Error inserting for {$foxName}: " . $stmt->error . "<br>");
      } else {
          // Check if this specific fox changed in this iteration
          foreach($data["data"] as $v) {
              if (ucfirst(strtolower($v['name'])) == $foxName && strtotime($lastupdate) < strtotime($v["updated_at"])) {
                  $status_nl = $v['status'] == 'red' ? 'Rood' : ($v['status'] == 'orange' ? 'Oranje' : 'Groen');
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