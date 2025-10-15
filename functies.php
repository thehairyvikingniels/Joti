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

// Zet een tijd in een leesbare vorm
function time2str($ts) {
    if(!ctype_digit($ts)) {
        $ts = strtotime($ts);
    }
    $diff = time() - $ts;
    if($diff == 0) {
        return 'Nu';
    } elseif($diff > 0) {
        $day_diff = floor($diff / 86400);
        if($day_diff == 0) {
            if($diff < 60) return 'Zojuist';
            if($diff < 120) return '1 min geleden';
            if($diff < 3600) return floor($diff / 60) . ' min geleden';
            if($diff < 7200) return '1 uur geleden';
            if($diff < 86400) return floor($diff / 3600) . ' uur geleden';
        }
        if($day_diff == 1) { return 'Gisteren'; }
        if($day_diff < 7) { return $day_diff . ' dagen geleden'; }
        if($day_diff < 8) { return ceil($day_diff / 7) . ' week geleden'; }
        if($day_diff < 31) { return ceil($day_diff / 7) . ' weken geleden'; }
        if($day_diff < 60) { return 'Vorige maand'; }
        return date('F Y', $ts);
    } else {
        $diff = abs($diff);
        $day_diff = floor($diff / 86400);
        if($day_diff == 0) {
            if($diff < 120) { return 'Over een min'; }
            if($diff < 3600) { return 'Over ' . floor($diff / 60) . ' min'; }
            if($diff < 7200) { return 'Over een uur'; }
            if($diff < 86400) { return 'Over ' . floor($diff / 3600) . ' uur'; }
        }
        if($day_diff == 1) { return 'Morgen'; }
        if($day_diff < 4) { return date('l', $ts); }
        if($day_diff < 7 + (7 - date('w'))) { return 'Volgende week'; }
        if(ceil($day_diff / 7) < 4) { return 'Over ' . ceil($day_diff / 7) . ' weken'; }
        if(date('n', $ts) == date('n') + 1) { return 'Volgende maand'; }
        return date('F Y', $ts);
    }
}

/**
 * Calculates the great-circle distance between two points, with
 * the Haversine formula.
 * @param float $latitudeFrom Latitude of start point in [deg decimal]
 * @param float $longitudeFrom Longitude of start point in [deg decimal]
 * @param float $latitudeTo Latitude of target point in [deg decimal]
 * @param float $longitudeTo Longitude of target point in [deg decimal]
 * @param float $earthRadius Mean earth radius in [m]
 * @return float Distance between points in [m] (same as earthRadius)
 */
function latlon_dist($latitudeFrom, $longitudeFrom, $latitudeTo, $longitudeTo, $earthRadius = 6371000) {
    // convert from degrees to radians
    $latFrom = deg2rad($latitudeFrom);
    $lonFrom = deg2rad($longitudeFrom);
    $latTo = deg2rad($latitudeTo);
    $lonTo = deg2rad($longitudeTo);

    $latDelta = $latTo - $latFrom;
    $lonDelta = $lonTo - $lonFrom;

    $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) + cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
    return round($angle * $earthRadius);
}

// RD coordinaten omzetten naar WGS84 coordinaten
// https://forum.geocaching.nl/topic/7886-co%C3%B6rdinaat-transformaties-rd-wgs/#elComment_117766
function rdtowgs($rdx, $rdy){
  $dx = ($rdx - 155000) * pow(10,-5);
  $dy = ($rdy - 463000) * pow(10,-5);
  
  $somN = (3235.65389 * $dy) + (-32.58297 * pow($dx,2)) + (-0.2475 * pow($dy,2)) + (-0.84978 * pow($dx,2) * $dy) + (-0.0655 * pow($dy,3)) + (-0.01709 * pow($dx,2) * pow($dy,2)) + (-0.00738 * $dx) + (0.0053 * pow($dx,4)) + (-0.00039 * pow($dx,2) * pow($dy,3)) + (0.00033 * pow($dx,4) * $dy) + (-0.00012 * $dx * $dy);
  $somE = (5260.52916 * $dx) + (105.94684 * $dx * $dy) + (2.45656 * $dx * pow($dy,2)) + (-0.81885 * pow($dx,3)) + (0.05594 * $dx * pow($dy,3)) + (-0.05607 * pow($dx,3) * $dy) + (0.01199 * $dy) + (-0.00256 * pow($dx,3) * pow($dy,2)) + (0.00128 * $dx * pow($dy,4)) + (0.00022 * pow($dy,2)) + (-0.00022 * pow($dx,2)) + (0.00026 * pow($dx,5));
    
  $a["lat"] = 52.15517 + ($somN / 3600);
  $a["lon"] = 5.387206 + ($somE / 3600);
  return($a);
}

// Verwijder een Voslocatie
if (isset($_GET['verwijder_voslocatie'])) {
    // Check user privilege
    $sql_priv = "SELECT priv FROM Gebruikers WHERE id='" . $_SESSION['id'] . "'";
    $result_priv = mysqli_query($conn, $sql_priv);
    $user = mysqli_fetch_assoc($result_priv);

    if ($user && $user['priv'] > 1) {
        $id_to_delete = intval($_GET['verwijder_voslocatie']);
        $sql_delete = "DELETE FROM Voslocaties WHERE id = " . $id_to_delete;
        
        if (mysqli_query($conn, $sql_delete)) {
            // Success
        } else {
            // Error, you could add error logging here
            error_log("Error deleting record: " . mysqli_error($conn));
        }
    }
    header("Location: admin/database"); // Redirect back to the admin page
    exit();
}

// Update een Voslocatie
if (isset($_POST['update_voslocatie'])) {
    // Check user privilege
    $sql_priv = "SELECT priv FROM Gebruikers WHERE id='" . $_SESSION['id'] . "'";
    $result_priv = mysqli_query($conn, $sql_priv);
    $user = mysqli_fetch_assoc($result_priv);

    if ($user && $user['priv'] > 1) {
        $id = intval($_POST['voslocatie_id']);
        $type = mysqli_real_escape_string($conn, $_POST['type']);
        $deelgebied = mysqli_real_escape_string($conn, $_POST['deelgebied']);
        $ingestuurd_op = mysqli_real_escape_string($conn, date('Y-m-d H:i:s', strtotime($_POST['ingestuurd_op'])));
        $coord_x = mysqli_real_escape_string($conn, $_POST['coordinaat_x']);
        $coord_y = mysqli_real_escape_string($conn, $_POST['coordinaat_y']);
        $code = mysqli_real_escape_string($conn, $_POST['code']);
        $opmerking = mysqli_real_escape_string($conn, $_POST['opmerking']);

        // Validation for 'code' when type is 'Hunt'
        if ($type === 'Hunt' && empty($code)) {
            die("Error: Code is verplicht bij het type Hunt.");
        }

        $sql_update = "UPDATE Voslocaties SET 
                        type = '$type',
                        deelgebied = '$deelgebied',
                        ingestuurd_op = '$ingestuurd_op',
                        coordinaat_x = '$coord_x',
                        coordinaat_y = '$coord_y',
                        code = '$code',
                        opmerking = '$opmerking'
                      WHERE id = $id";
        
        if (mysqli_query($conn, $sql_update)) {
            // Success
        } else {
            // Error
            error_log("Error updating record: " . mysqli_error($conn));
        }
    }
    header("Location: admin/database"); // Redirect back to the admin page
    exit();
}

  
// elke x seconden gps locatie ophalen
if (isset($_GET['lat']) AND isset($_GET['lon'])){
  $time = date("Y-m-d H:i:s");
  
  // Check if the user is in a car
  $sql_car = "SELECT auto FROM Auto_Bijrijders WHERE gebruiker_id = '".$_SESSION['id']."'";
  $result_car = mysqli_query($conn, $sql_car);

  if (mysqli_num_rows($result_car) > 0) {
    // If they are, update the car's position in Auto_Positie
    $row_car = mysqli_fetch_assoc($result_car);
    $kenteken = $row_car['auto'];
    $sql_update_car = "INSERT INTO Auto_Positie (auto, gebruiker_id, datumtijd, lat, lon) VALUES ('".mysqli_real_escape_string($conn, $kenteken)."', '".$_SESSION['id']."', '".$time."', '".mysqli_real_escape_string($conn, $_GET['lat'])."', '".mysqli_real_escape_string($conn, $_GET['lon'])."')";

    if (!mysqli_query($conn, $sql_update_car)) {
        // Optional: log error, but don't stop the script
        error_log("Error updating Auto_Positie: " . mysqli_error($conn));
    }
  }

  // Always update the user's personal location
  $sql_update_user = "UPDATE Gebruikers SET lat='".mysqli_real_escape_string($conn, $_GET['lat'])."', lon='".mysqli_real_escape_string($conn, $_GET['lon'])."', geotijd='".$time."' WHERE id='".$_SESSION['id']."'";
  if (mysqli_query($conn, $sql_update_user)) {
      echo "Success: Location updated.";
  } else {
      echo "Error: " . mysqli_error($conn);
  }
}

// Invulgegevens voor homebase
if (isset($_GET['hunthintgedaan'])){
  $sql = "UPDATE Voslocaties SET ingeleverd='1', ingeleverd_door='".$_SESSION['id']."' WHERE id=".intval($_GET['hunthintgedaan']);
  
  if (mysqli_query($conn, $sql)) {
    header("Location: home");
    die();
  } else {
    echo "Error updating record: " . mysqli_error($conn);
  }
}

// Invulgegevens voor homebase
if (isset($_GET['invulgegevens'])){
  $sql = "SELECT * FROM Voslocaties WHERE ingeleverd='0' ORDER BY ingestuurd_op DESC";
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
        echo "  <td>".htmlspecialchars($row['code'])."</td>";
        echo "  <td>".htmlspecialchars($row['type'])."</td>";
        echo "  <td>".date("H:i",strtotime($row['ingestuurd_op']))."</td>";
        echo "  <td><i class=\"fas fa-trash-alt\" onclick=\"document.getElementById('modal01').style.display='block';document.getElementById('opgestuurdurl').href='functies?hunthintgedaan=".$row['id']."';\"></i></td>";
        echo "</tr>"; 
      }
      echo "</table>";
  } else {
    echo "<p>Hier verschijnen hunts die ingeleverd moeten worden bij de officiële jotihunt website</p>";
  }
}

// autosonderweg ophalen
if (isset($_GET['autos'])){
  // rb
  $locatie["lat"] = 51.98761;
  $locatie["lon"] = 5.87620;
  
  $sql = "SELECT 
            g.voornaam as voornaam,
            a.kenteken,
            ap.lat,
            ap.lon,
            ap.datumtijd as geotijd,
            (SELECT GROUP_CONCAT(b.voornaam SEPARATOR ', ') 
             FROM Auto_Bijrijders ab 
             JOIN Gebruikers b ON ab.gebruiker_id = b.id 
             WHERE ab.auto = a.kenteken) as bijrijders
          FROM Auto a
          JOIN Gebruikers g ON a.eigenaar = g.id
          LEFT JOIN (
              SELECT auto, lat, lon, datumtijd
              FROM Auto_Positie ap_inner
              WHERE ap_inner.datumtijd = (
                  SELECT MAX(datumtijd) 
                  FROM Auto_Positie 
                  WHERE auto = ap_inner.auto
              )
          ) ap ON a.kenteken = ap.auto
          WHERE ap.lat IS NOT NULL AND ap.lon IS NOT NULL";
  $result = mysqli_query($conn, $sql);
  
  $cars_found = false;
  if ($result && mysqli_num_rows($result) > 0) {
    echo "<table style='width:100%' class='w3-table-all'>";
    while($row = mysqli_fetch_assoc($result)) {
        $date1 = new DateTime($row['geotijd']);
        $now = new DateTime();
        $interval = $now->diff($date1);
        $minutes_since = ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;
      
        if($minutes_since < 15){ // Show cars active in last 15 minutes
          $cars_found = true;
          echo '<tr>';
          echo '  <td><i class="fas fa-car-side"></i> '.htmlspecialchars($row['kenteken']).'</td>';
          echo '  <td><i class="fas fa-users"></i></i> '.htmlspecialchars($row['bijrijders']).'</td>';
          echo '  <td><i class="fas fa-map-marker-alt"></i> <i>'.time2str($row['geotijd']).'</i></td>';
          echo '</tr>';
        }
    }
    echo "</table>";
  }
  if (!$cars_found){
    echo "<p>Er zijn nu geen autos onderweg...</p>";
  }
}


// gebeurtenissen ophalen
if (isset($_GET['gebeurtenissen'])){
  $a = 0;
  $data = [];
  $sql = "SELECT * FROM Opdrachten";
  $result = mysqli_query($conn, $sql);
  
  if (mysqli_num_rows($result) > 0) {
      while($row = mysqli_fetch_assoc($result)) {
        $data[] = ["type" => "Opdracht", "titel" => $row['titel'], "datum" => strtotime($row['datum'])];
      }
  }
  $sql = "SELECT * FROM Hints";
  $result = mysqli_query($conn, $sql);
  
  if (mysqli_num_rows($result) > 0) {
      while($row = mysqli_fetch_assoc($result)) {
         $data[] = ["type" => "Hint", "titel" => $row['titel'], "datum" => strtotime($row['datum'])];
      }
  }
  
  $sql = "SELECT * FROM Nieuws";
  $result = mysqli_query($conn, $sql);
  if (mysqli_num_rows($result) > 0) {
      while($row = mysqli_fetch_assoc($result)) {
        $data[] = ["type" => "Nieuws", "titel" => $row['titel'], "datum" => strtotime($row['datum'])];
      }
  }
  
  $sql = "SELECT * FROM Voslocaties";
  $result = mysqli_query($conn, $sql);
  if (mysqli_num_rows($result) > 0) {
      while($row = mysqli_fetch_assoc($result)) {
        $data[] = ["type" => $row['type'], "titel" => ucfirst($row['deelgebied']), "datum" => strtotime($row['ingestuurd_op'])];
      }
  }
  
  // sorteren van array
  usort($data, function($a, $b) {
      return $b['datum'] <=> $a['datum'];
  });
  $num = intval($_GET['gebeurtenissen']);
  $data = array_slice($data, 0, $num);
  echo '<table class="w3-table w3-striped w3-white" id="gebeurtenissentabel">';
  // in tabel zetten
  foreach($data as $element){
    switch ($element["type"]) {
        case "Opdracht": $fa = "fa fa-bell w3-text-teal"; $url="opdrachten"; break;
        case "Hint": $fa = "fas fa-question-circle w3-text-blue"; $url="hints"; break;
        case "Nieuws": $fa = "far fa-newspaper w3-text-black"; $url="nieuws"; break;
        default: $fa = "fas fa-bullseye w3-text-red"; $url="kaarten"; break; // Hunt, Spot, etc.
    }
    echo '<tr onclick="location.href=\''.$url.'\'" style="cursor:pointer;">';
    echo '<td><i class="'.$fa.' w3-large"></i> '.htmlspecialchars($element['type']).'</td>';
    echo '<td>'.htmlspecialchars($element['titel']).'</td>';
    echo '<td><i>'.time2str($element['datum']).'</i></td>';
    echo '</tr>';
  }
  echo "</table>";
  echo "<center><span id='meerknop' class='w3-button w3-green w3-round-xlarge' onclick='gebeurtenissen(". ($num+5) .")'>Meer resultaten</span></center>";
}

?>
