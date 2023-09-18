<title>Is X op de RB?</title>
<?php
require("dblogin.php");
session_start();

$sql = "SELECT lat,lon,naam FROM Groepen";
$result = mysqli_query($conn, $sql);

if (mysqli_num_rows($result) > 0) {
  // output data of each row
  while($row = mysqli_fetch_assoc($result)) {
    $locaties[$row['naam']]["lat"] = $row['lat'];
    $locaties[$row['naam']]["lon"] = $row['lon'];
  }
}
$sql = "SELECT lat,lon FROM Gebruikers WHERE id = '".$_SESSION['id']."'";
$result = mysqli_query($conn, $sql);

if (mysqli_num_rows($result) > 0) {
  // output data of each row
  while($row = mysqli_fetch_assoc($result)) {
    foreach($locaties as $naam => $locatie) {
      if(sqrt(pow(($locatie["lat"] - $row["lat"]),2) + pow(($locatie["lon"] - $row["lon"]),2)) <= 0.00){
        echo sqrt(pow(($locatie["lat"] - $row["lat"]),2) + pow(($locatie["lon"] - $row["lon"]),2))." true ".$naam."<br>";
        break 2;
      } else {
        echo sqrt(pow(($locatie["lat"] - $row["lat"]),2) + pow(($locatie["lon"] - $row["lon"]),2))." false ".$naam."<br>";
      }
    }
  }
} else {
  echo "0 results";
}