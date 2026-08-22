<?php
// Fetches participating scouting group locations and details from the Jotihunt API and updates them in the database.
define("NAME", "subscriptions");
define("JOTI_URL", "https://jotihunt.nl");
define("START_TIME", microtime(true));
date_default_timezone_set('Europe/Amsterdam');
$output = "";

require_once('../dblogin.php');

$datumtijd = date('Y-m-d H:i:s');


log2DB("-GROEPEN<br>");
$data = json_decode(file_get_contents(JOTI_URL."/api/2.0/subscriptions"),true);
$a = 1;
foreach($data["data"] as $key => $value){
  $sql = "INSERT INTO Groepen (id, naam, gebruikersnaam, straat, huisnummer, postcode, plaats, lat, lon, url, deelgebied) 
          VALUES ('".($key + 1)."', '".addslashes($value['name'])."', 'null', '".addslashes($value['street'])."', '".strtoupper($value['housenumber'].$value['housenumber_addition'])."', '".strtoupper(str_replace(" ","",$value['postcode']))."', '".addslashes($value['city'])."', '".$value['lat']."', '".$value['long']."', 'null', '".@$value['area']."')ON DUPLICATE KEY UPDATE deelgebied = '".@$value['area']."'";
  if (mysqli_query($conn, $sql)) {
    log2DB($a." - ".$value['name']."<br>");
  } else {
    log2DB("Error: " . $sql . "<br>" . mysqli_error($conn));
  }
  $a ++;
}
$data = file_get_contents(JOTI_URL."/subscriptions");
$data = substr($data, strpos($data, '<tbody class="divide-y divide-gray-200 bg-white">')+50);
$data = explode("</tr>",$data);
$groups = array();
foreach($data as $key => $row) {
  $row = str_replace("<tr>","",$row);
  $column = explode("td>", $row);
  // skip rows that don't have enough columns (header, empty, malformed rows)
  if (count($column) < 6) continue;
  // get icon URL
  $groups[$key]["url"] = substr($column[0], strpos($column[0], 'src="')+5);
  $groups[$key]["url"] = substr($groups[$key]["url"], 0, strpos($groups[$key]["url"], '"'));
  // get group name
  $groups[$key]["name"] = substr($column[3], strpos($column[3], '">')+2);
  $groups[$key]["name"] = substr($groups[$key]["name"], 0, strpos($groups[$key]["name"], '<'));
  // get group coordinates
  $groups[$key]["coord"] = substr($column[5], strpos($column[5], '>')+1);
  $groups[$key]["coord"] = substr($groups[$key]["coord"], 0, strpos($groups[$key]["coord"], '<'));
  
  // insert new data into database
  $sql = "UPDATE Groepen SET url = '".$groups[$key]["url"]."' WHERE CONCAT(LEFT(lat, 8), ', ', LEFT(lon,7)) = '".$groups[$key]["coord"]."'";
  if (mysqli_query($conn, $sql)) {
    
  }
}
log2DB("-GROEPEN<br>");


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