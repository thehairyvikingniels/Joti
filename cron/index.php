<?php
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
echo "-VOSSEN<br>";
$sql = "Select datumtijd FROM Voslog ORDER BY datumtijd desc LIMIT 1";
$result = mysqli_query($conn, $sql);

if (mysqli_num_rows($result) > 0) {
    // output data of each row
    $a = 0;
    while($row = mysqli_fetch_assoc($result)) {
      $lastupdate = $row['datumtijd'];
    }
}



$data = json_decode(file_get_contents("https://jotihunt.nl/api/2.0/areas"),true);
$statusChanged = 0;
foreach($data["data"] as $value){
  if (strtotime($lastupdate) < strtotime($value["updated_at"])) {
    echo "Status ".$value['name']." has changed to ".$value['status']."</br>";
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

  $sql = "INSERT INTO Voslog (datumtijd, alpha, bravo, charlie, delta, echo, foxtrot)
  VALUES ('".date("Y-m-d H:i:s", $updatedAt)."','".$alpha_status."','".$bravo_status."','".$charlie_status."','".$delta_status."','".$echo_status."','".$foxtrot_status."')";
  
  if (!mysqli_query($conn, $sql)) {
      echo "Error: " . $sql . "<br>" . mysqli_error($conn);
  }
} else {
  echo "No changes</br>";
}

echo "-VOSSEN<br>";



echo "-ARTICLES</br>";

$data = json_decode(file_get_contents("https://jotihunt.nl/api/2.0/articles"),true);

foreach($data['data'] as $item) {
  echo $item['id']." - ".$item['title']."</br>";

  $news = $conn->prepare("INSERT INTO Nieuws (id, titel, inhoud, datum) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE id = id");
  $assignment = $conn->prepare("INSERT INTO Opdrachten (id, titel, inhoud, datum, eindtijd, maxpunten) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE id = id");
  $hint = $conn->prepare("INSERT INTO Hints (id, titel, inhoud, datum) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE id = id");

  if ($item['type'] == 'news') {
  
    $id = $item['id'];
    $title = $item['title'];
    $content = $item['message']['content'];
    $pubTime = date("Y-m-d H:i:s", strtotime($item['publish_at']));

    $news->bind_param("isss", $id, $title, $content, $pubTime);
    $news->execute();
    if (!mysqli_query($conn, $sql)) {
        echo "Error: " . $sql . "<br>" . mysqli_error($conn);
    }

  } else if ($item['type'] == 'assignment') {

    $id = $item['id'];
    $title = $item['title'];
    $content = $item['message']['content'];
    $pubTime = date("Y-m-d H:i:s", strtotime($item['publish_at']));
    $endTime = date("Y-m-d H:i:s", strtotime($item['message']['end_time']));
    $maxPoints = $item['message']['max_points'];

    $assignment->bind_param("issssi", $id, $title, $content, $pubTime, $endTime, $maxPoints);
    $assignment->execute();
    if (!mysqli_query($conn, $sql)) {
        echo "Error: " . $sql . "<br>" . mysqli_error($conn);
    }

  } else if ($item['type'] == 'hint') {

    $id = $item['id'];
    $title = $item['title'];
    $content = $item['message']['content'];
    $pubTime = date("Y-m-d H:i:s", strtotime($item['publish_at']));

    $hint->bind_param("isss", $id, $title, $content, $pubTime);
    $hint->execute();
    if (!mysqli_query($conn, $sql)) {
        echo "Error: " . $sql . "<br>" . mysqli_error($conn);
    }
  }
}


echo "-ARTICLES</br>";



echo "-GROEPEN<br>";
$data = json_decode(file_get_contents("https://jotihunt.nl/api/2.0/subscriptions"),true);
$a = 1;
foreach($data["data"] as $key => $value){
  $sql = "INSERT INTO Groepen (id, naam, gebruikersnaam, straat, huisnummer, postcode, plaats, lat, lon, url, deelgebied) 
          VALUES ('".($key + 1)."', '".addslashes($value['name'])."', 'null', '".addslashes($value['street'])."', '".strtoupper($value['housenumber'].$value['housenumber_addition'])."', '".strtoupper(str_replace(" ","",$value['postcode']))."', '".addslashes($value['city'])."', '".$value['lat']."', '".$value['long']."', 'null', '".$value['area']."')ON DUPLICATE KEY UPDATE deelgebied = '".$value['area']."'";
  if (mysqli_query($conn, $sql)) {
      echo $a." - ".$value['name']."<br>";
  } else {
      echo "Error: " . $sql . "<br>" . mysqli_error($conn);
  }
  $a ++;
}
echo "-GROEPEN<br>";
