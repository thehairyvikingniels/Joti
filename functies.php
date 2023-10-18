<?php
session_start();
if (empty($_SESSION['id'])){
  header("Location: index.php");
}

require("dblogin.php");

if (isset($_GET['gpstoggle'])){
  if ($_SESSION['gps'] == "true"){
    $_SESSION['gps'] = "false";
  } else {
    $_SESSION['gps'] = "true";
  }
  header("Location: ".$_GET['return']."");
  
}
  
// elke x seconden gps locatie ophalen
if (isset($_GET['lat']) AND isset($_GET['lon'])){
  $time = date("Y-m-d H:i:s");
  $sql = "SELECT auto FROM Auto_Bijrijders WHERE gebruiker_id = '".$_SESSION['id']."'";
  $result = mysqli_query($conn, $sql);

  if (mysqli_num_rows($result) > 0) {
    // output data of each row
    while($row = mysqli_fetch_assoc($result)) {
      $sql = "INSERT INTO Auto_Positie (auto, gebruiker_id, datumtijd, lat, lon) VALUES ('".$row['auto']."', '".$_SESSION['id']."', '".$time."', '".$_GET['lat']."', '".$_GET['lon']."')";

      if (mysqli_query($conn, $sql)) {
          echo "Record updated successfully";
      } else {
          echo "Error updating record: " . mysqli_error($conn);
      }
    }
  }
  $sql = "UPDATE Gebruikers SET lat='".$_GET['lat']."', lon='".$_GET['lon']."', geotijd='".$time."' WHERE id='".$_SESSION['id']."'";
  if (mysqli_query($conn, $sql)) {
      echo "Record updated successfully";
  } else {
      echo "Error updating record: " . mysqli_error($conn);
  }
}

// Invulgegevens voor homebase
if (isset($_GET['hunthintgedaan'])){
  $sql = "UPDATE Voslocaties SET ingeleverd='1', ingeleverd_door='".$_SESSION['id']."' WHERE id=".$_GET['hunthintgedaan'];
  
  if (mysqli_query($conn, $sql)) {
    header("Location: home");
    die();
  } else {
    echo "Error updating record: " . mysqli_error($conn);
  }
}

// Invulgegevens voor homebase
if (isset($_GET['invulgegevens'])){
  $sql = "SELECT * FROM Voslocaties WHERE ingeleverd='0' AND type='Hunt' ORDER BY ingestuurd_op DESC";
  $result = mysqli_query($conn, $sql);

  if (mysqli_num_rows($result) > 0) {
      echo "<table style='width:100%'>";
      echo "<tr>";
      echo "  <th>Code</th>";
      echo "  <th>Type</th>";
      echo "  <th>Tijd</th>";
      echo "  <th></th>";
      echo "</tr>";
      while($row = mysqli_fetch_assoc($result)) {
        echo "<tr>";
        echo "  <td>".$row['code']."</td>";
        echo "  <td>".$row['type']."</td>";
        echo "  <td>".date("H:i",strtotime($row['ingestuurd_op']))."</td>";
        echo "  <td><i class=\"fas fa-trash-alt\" onclick=\"document.getElementById('modal01').style.display='block';document.getElementById('opgestuurdurl').href='functies?hunthintgedaan=".$row['id']."';\"></i></td>";
        echo "</tr>"; 
      }
  } else {
    echo "<p>Hier verschijnen hunts die ingeleverd moeten worden bij de officiële jotihunt website</p>";
  }
}

// autosonderweg ophalen
if (isset($_GET['autos'])){
  // rb
  $locatie["lat"] = 51.98761;
  $locatie["lon"] = 5.87620;
  
  $sql = "		  SELECT 
  Gebruikers.voornaam as voornaam,
  Auto.*,
  (SELECT
      lat
  FROM 
      Auto_Positie
  WHERE
      Auto = Auto.kenteken
  ORDER BY
      datumtijd DESC
  LIMIT 1) as lat,
  (SELECT
      lon
  FROM 
      Auto_Positie
  WHERE
      Auto = Auto.kenteken
  ORDER BY
      datumtijd DESC
  LIMIT 1) as lon,
(SELECT
      datumtijd
  FROM 
      Auto_Positie
  WHERE
      Auto = Auto.kenteken
  ORDER BY
      datumtijd DESC
  LIMIT 1) as geotijd,
  (SELECT GROUP_CONCAT(Gebruikers.voornaam)
FROM Auto_Bijrijders AB
INNER JOIN
	Gebruikers
    ON AB.gebruiker_id = Gebruikers.id
GROUP BY
	AB.auto) as bijrijders
FROM 
  Auto 
INNER JOIN 
  Gebruikers
    ON Auto.eigenaar = Gebruikers.id";
  $result = mysqli_query($conn, $sql);
  
  if (mysqli_num_rows($result) > 0) {
    echo "<table style='width:100%' class='w3-table-all'>";
    // output data of each row
    while($row = mysqli_fetch_assoc($result)) {
      if(sqrt(pow(($locatie["lat"] - $row["lat"]),2) + pow(($locatie["lon"] - $row["lon"]),2)) >= 0.001){
        $sunrise = date('Y-m-d H:i:s', strtotime('-900 seconds'));
        $sunset = date('Y-m-d H:i:s', strtotime('+10 seconds'));
        $date1 = DateTime::createFromFormat('Y-m-d H:i:s', $row['geotijd']);
        $date2 = DateTime::createFromFormat('Y-m-d H:i:s', $sunrise);
        $date3 = DateTime::createFromFormat('Y-m-d H:i:s', $sunset);
        if ($date1 > $date2 && $date1 < $date3) {
          $cars = "true";
          echo '<tr>';
          echo '  <td><i class="fas fa-car-side"></i> '.$row['kenteken'].'</td>';
          echo '  <td><i class="fas fa-users"></i></i> '.$row['bijrijders'].'</td>';
          echo '  <td><i class="fas fa-map-marker-alt"></i> <i>'.time2str($row['geotijd']).'</i></td>';
          echo '</tr>';
        }
      }
    }
    echo "</table>";
  }
  if (!isset($cars)){
    echo "<p>Er zijn nu geen autos onderweg...</p>";
  }
}


// gebeurtenissen ophalen
if (isset($_GET['gebeurtenissen'])){
  $a = 0;
  $sql = "SELECT * FROM Opdrachten";
  $result = mysqli_query($conn, $sql);
  
  if (mysqli_num_rows($result) > 0) {
      // output data of each row
      while($row = mysqli_fetch_assoc($result)) {
        $data["$a"]["type"] = "Opdracht";
        $data["$a"]["titel"] = $row['titel'];
        $data["$a"]["datum"] = strtotime($row['datum']);
        $a = count($data)+1;
      }
  }
  $sql = "SELECT * FROM Hints";
  $result = mysqli_query($conn, $sql);
  
  if (mysqli_num_rows($result) > 0) {
      // output data of each row
      while($row = mysqli_fetch_assoc($result)) {
        $data["$a"]["type"] = "Hint";
        $data["$a"]["titel"] = $row['titel'];
        $data["$a"]["datum"] = strtotime($row['datum']);
        $a = count($data)+1;
      }
  }
  
  $sql = "SELECT * FROM Nieuws";
  $result = mysqli_query($conn, $sql);
  if (mysqli_num_rows($result) > 0) {
      // output data of each row
      while($row = mysqli_fetch_assoc($result)) {
        $data["$a"]["type"] = "Nieuws";
        $data["$a"]["titel"] = $row['titel'];
        $data["$a"]["datum"] = strtotime($row['datum']);
        $a = count($data)+1;
      }
  }
  
  $sql = "SELECT * FROM Voslocaties";
  $result = mysqli_query($conn, $sql);
  if (mysqli_num_rows($result) > 0) {
      // output data of each row
      while($row = mysqli_fetch_assoc($result)) {
        $data["$a"]["type"] = $row['type'];
        $data["$a"]["titel"] = ucfirst($row['deelgebied']);
        $data["$a"]["datum"] = strtotime($row['ingestuurd_op']);
        $a = count($data)+1;
      }
  }
  
  // sorteren van array
  usort($data, function($a, $b) {
      return $b['datum'] <=> $a['datum'];
  });
  $num = $_GET['gebeurtenissen'];
  $data = array_slice($data, 0, $num);
  echo '<table class="w3-table w3-striped w3-white" id="gebeurtenissentabel">';
  // in tabel zetten
  $z = 0;
  foreach($data as $element){
    $z++;
    switch ($element["type"]) {
        case "Opdracht":
            $fa = "fa fa-bell w3-text-teal";
            $url="opdrachten";
            break;
        case "Hint":
            $fa = "fas fa-question-circle w3-text-blue";
            $url="hints";
            break;
        case "Nieuws":
            $fa = "far fa-newspaper w3-text-black";
            $url="nieuws";
            break;
        case "Hunt":
            $fa = "fas fa-bullseye w3-text-red";
            $url="kaarten";
            break;
    }
    echo '<tr id="tr_'.$z.'" class="" onclick="location.href=\''.$url.'\'">';
    echo '<td><i class="'.$fa.' w3-large"></i> '.$element['type'].'</td>';
    echo '<td>'.$element['titel'].'</td>';
    echo '<td><i>'.time2str($element['datum']).'</i></td>';
    echo '</tr>';
  }
  echo "</table>";
  echo "<center><span id='meerknop' class='w3-button w3-green w3-round-xlarge' onclick='gebeurtenissen(". ($num+5) .")'>Meer resultaten</span></center>";
}

if (isset($_GET['gebeurtenissenheadless'])){
  $a = 0;
  $sql = "SELECT * FROM Opdrachten";
  $result = mysqli_query($conn, $sql);
  
  if (mysqli_num_rows($result) > 0) {
      // output data of each row
      while($row = mysqli_fetch_assoc($result)) {
        $data["$a"]["type"] = "Opdracht";
        $data["$a"]["titel"] = $row['titel'];
        $data["$a"]["datum"] = strtotime($row['datum']);
        $a = count($data)+1;
      }
  }
  $sql = "SELECT * FROM Hints";
  $result = mysqli_query($conn, $sql);
  
  if (mysqli_num_rows($result) > 0) {
      // output data of each row
      while($row = mysqli_fetch_assoc($result)) {
        $data["$a"]["type"] = "Hint";
        $data["$a"]["titel"] = $row['titel'];
        $data["$a"]["datum"] = strtotime($row['datum']);
        $a = count($data)+1;
      }
  }
  
  $sql = "SELECT * FROM Nieuws";
  $result = mysqli_query($conn, $sql);
  if (mysqli_num_rows($result) > 0) {
      // output data of each row
      while($row = mysqli_fetch_assoc($result)) {
        $data["$a"]["type"] = "Nieuws";
        $data["$a"]["titel"] = $row['titel'];
        $data["$a"]["datum"] = strtotime($row['datum']);
        $a = count($data)+1;
      }
  }
  
  $sql = "SELECT * FROM Voslocaties";
  $result = mysqli_query($conn, $sql);
  if (mysqli_num_rows($result) > 0) {
      // output data of each row
      while($row = mysqli_fetch_assoc($result)) {
        $data["$a"]["type"] = $row['type'];
        $data["$a"]["titel"] = $row['deelgebied'];
        $data["$a"]["datum"] = strtotime($row['ingestuurd_op']);
        $a = count($data)+1;
      }
  }
  
  // sorteren van array
  usort($data, function($a, $b) {
      return $b['datum'] <=> $a['datum'];
  });
  $data = array_slice($data, 0, 1);
  foreach($data as $element){
    echo "type: ".$element['type']." titel:".$element['titel']." datumtijd:".$element['datum'];
  }
}





function gpslookup($lat, $lon){
  $sql = "SELECT lat,lon,naam FROM Groepen";
  $result = mysqli_query($conn, $sql);

  if (mysqli_num_rows($result) > 0) {
    // output data of each row
    while($row = mysqli_fetch_assoc($result)) {
      $locaties[$row['naam']]["lat"] = $row['lat'];
      $locaties[$row['naam']]["lon"] = $row['lon'];
    }
  }
  
  foreach($locaties as $naam => $locatie) {
    if(sqrt(pow(($locatie["lat"] - $lat),2) + pow(($locatie["lon"] - $lon),2)) <= 0.001){
      return $naam;
      break 1;
    }
  }
}



