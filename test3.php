<?php
require('dblogin.php');

$pk = "pk.eyJ1IjoidGhlaGFpcnl2aWtpbmduaWVscyIsImEiOiJjam40YzI2eGEwMjh6M3hscGEweHpxYzg1In0.3obc3XmgMCZ-rY5LLzhW2A";
//$pk = "sk.eyJ1IjoidGhlaGFpcnl2aWtpbmduaWVscyIsImEiOiJjbDk3MTBya2Yyb29tM3BwMmtpc2VlODQwIn0.3KsZ5Q0eyYegMg3Ytr6Otw";


$link = "https://api.mapbox.com/matching/v5/mapbox/cycling/";


$sql = "SELECT * FROM Voslocaties WHERE deelgebied = 'Alpha'";
$result = mysqli_query($conn, $sql);

if (mysqli_num_rows($result) > 0) {
  // output data of each row
  $i = 1;
  $radius = null;
  while($row = mysqli_fetch_assoc($result)) {
    $link .= substr($row['coordinaat_y'],0,-5).",".substr($row['coordinaat_x'],0,-5);
    $radius .= "25";

    if (mysqli_num_rows($result) != $i) {
        $link .= ";";
        $radius .= ";";
    }

    $i++;
  }
}

$link .= "?radiuses=".$radius;
$link .= "&steps=true";
$link .= "&access_token=".$pk;
echo $link."</br></br>";
print_r(json_decode(file_get_contents($link),true));