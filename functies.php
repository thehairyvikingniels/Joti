<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['id'])){
  header("Location: index.php");
  exit();
}

require("dblogin.php");

// GPS Toggle
if (isset($_GET['gpstoggle'])){
  if ($_SESSION['gps'] == "true"){
    $_SESSION['gps'] = "false";
  } else {
    $_SESSION['gps'] = "true";
  }
  header("Location: ".$_GET['return']);
  exit();
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
 */
function latlon_dist($latitudeFrom, $longitudeFrom, $latitudeTo, $longitudeTo, $earthRadius = 6371000) {
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
    $stmt_priv = $conn->prepare("SELECT priv FROM Gebruikers WHERE id=?");
    $stmt_priv->bind_param("i", $_SESSION['id']);
    $stmt_priv->execute();
    $result_priv = $stmt_priv->get_result();
    $user = $result_priv->fetch_assoc();
    $stmt_priv->close();

    if ($user && $user['priv'] > 1) {
        $id_to_delete = intval($_GET['verwijder_voslocatie']);
        $stmt_del = $conn->prepare("DELETE FROM Voslocaties WHERE id = ?");
        $stmt_del->bind_param("i", $id_to_delete);
        
        if (!$stmt_del->execute()) {
            error_log("Error deleting record: " . $stmt_del->error);
        }
        $stmt_del->close();
    }
    header("Location: admin/database");
    exit();
}

// Update een Voslocatie
if (isset($_POST['update_voslocatie'])) {
    // Check user privilege
    $stmt_priv = $conn->prepare("SELECT priv FROM Gebruikers WHERE id=?");
    $stmt_priv->bind_param("i", $_SESSION['id']);
    $stmt_priv->execute();
    $result_priv = $stmt_priv->get_result();
    $user = $result_priv->fetch_assoc();
    $stmt_priv->close();

    if ($user && $user['priv'] > 1) {
        $id = intval($_POST['voslocatie_id']);
        $type = $_POST['type'];
        $deelgebied = $_POST['deelgebied'];
        $ingestuurd_op = date('Y-m-d H:i:s', strtotime($_POST['ingestuurd_op']));
        $coord_x = $_POST['coordinaat_x'];
        $coord_y = $_POST['coordinaat_y'];
        $code = $_POST['code'];
        $opmerking = $_POST['opmerking'];

        // Validation for 'code' when type is 'Hunt'
        if ($type === 'Hunt' && empty($code)) {
            die("Error: Code is verplicht bij het type Hunt.");
        }

        $stmt_upd = $conn->prepare("UPDATE Voslocaties SET type=?, deelgebied=?, ingestuurd_op=?, coordinaat_x=?, coordinaat_y=?, code=?, opmerking=? WHERE id=?");
        $stmt_upd->bind_param("sssssssi", $type, $deelgebied, $ingestuurd_op, $coord_x, $coord_y, $code, $opmerking, $id);
        
        if (!$stmt_upd->execute()) {
            error_log("Error updating record: " . $stmt_upd->error);
        }
        $stmt_upd->close();
    }
    header("Location: admin/database");
    exit();
}

  
// elke x seconden gps locatie ophalen
if (isset($_GET['lat']) && isset($_GET['lon'])) {
    $time = date("Y-m-d H:i:s");
  
    // Check if the user is in a car
    $stmt_car = $conn->prepare("SELECT auto FROM Auto_Bijrijders WHERE gebruiker_id = ?");
    $stmt_car->bind_param("i", $_SESSION['id']);
    $stmt_car->execute();
    $result_car = $stmt_car->get_result();

    if ($result_car->num_rows > 0) {
        $row_car = $result_car->fetch_assoc();
        $kenteken = $row_car['auto'];
        
        $stmt_ins_car = $conn->prepare("INSERT INTO Auto_Positie (auto, gebruiker_id, datumtijd, lat, lon) VALUES (?, ?, ?, ?, ?)");
        $stmt_ins_car->bind_param("sisss", $kenteken, $_SESSION['id'], $time, $_GET['lat'], $_GET['lon']);

        if (!$stmt_ins_car->execute()) {
            error_log("Error updating Auto_Positie: " . $stmt_ins_car->error);
        }
        $stmt_ins_car->close();
    }
    $stmt_car->close();

    // Always update the user's personal location
    $stmt_user = $conn->prepare("UPDATE Gebruikers SET lat=?, lon=?, geotijd=? WHERE id=?");
    $stmt_user->bind_param("sssi", $_GET['lat'], $_GET['lon'], $time, $_SESSION['id']);
    
    if ($stmt_user->execute()) {
        echo "Success: Location updated.";
    } else {
        echo "Error: " . $stmt_user->error;
    }
    $stmt_user->close();
}

// Invulgegevens voor homebase (afvinken)
if (isset($_GET['hunthintgedaan'])){
    if (!isset($_SESSION['priv']) || $_SESSION['priv'] < 2) {
        exit();
    }
    $stmt = $conn->prepare("UPDATE Voslocaties SET ingeleverd='1', ingeleverd_door=? WHERE id=?");
    $hunt_id = intval($_GET['hunthintgedaan']);
    $stmt->bind_param("ii", $_SESSION['id'], $hunt_id);
  
    if ($stmt->execute()) {
        $stmt->close();
        header("Location: home");
        die();
    } else {
        echo "Error updating record: " . $stmt->error;
        $stmt->close();
    }
}

// Invulgegevens voor homebase (tabel tonen)
if (isset($_GET['invulgegevens'])){
    if (!isset($_SESSION['priv']) || $_SESSION['priv'] < 2) {
        exit();
    }
    $stmt = $conn->prepare("SELECT * FROM Voslocaties WHERE ingeleverd='0' ORDER BY ingestuurd_op DESC");
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo "<table style='width:100%'>";
        echo "<tr>";
        echo "  <th>Code</th>";
        echo "  <th>Type</th>";
        echo "  <th>Tijd</th>";
        echo "  <th></th>";
        echo "</tr>";
        while($row = $result->fetch_assoc()) {
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
    $stmt->close();
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
            
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
  
    $cars_found = false;
    if ($result && $result->num_rows > 0) {
        echo "<table style='width:100%' class='w3-table-all'>";
        while($row = $result->fetch_assoc()) {
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
    $stmt->close();
}


// gebeurtenissen ophalen
if (isset($_GET['gebeurtenissen'])){
    $data = [];
    
    // Opdrachten ophalen
    $stmt_opdr = $conn->prepare("SELECT * FROM Opdrachten");
    $stmt_opdr->execute();
    $result = $stmt_opdr->get_result();
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $data[] = ["type" => "Opdracht", "titel" => $row['titel'], "datum" => strtotime($row['datum'])];
        }
    }
    $stmt_opdr->close();
  
    // Hints ophalen
    $stmt_hints = $conn->prepare("SELECT * FROM Hints");
    $stmt_hints->execute();
    $result = $stmt_hints->get_result();
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $data[] = ["type" => "Hint", "titel" => $row['titel'], "datum" => strtotime($row['datum'])];
        }
    }
    $stmt_hints->close();
  
    // Nieuws ophalen
    $stmt_nieuws = $conn->prepare("SELECT * FROM Nieuws");
    $stmt_nieuws->execute();
    $result = $stmt_nieuws->get_result();
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $data[] = ["type" => "Nieuws", "titel" => $row['titel'], "datum" => strtotime($row['datum'])];
        }
    }
    $stmt_nieuws->close();
  
    // Voslocaties ophalen
    $stmt_vos = $conn->prepare("SELECT * FROM Voslocaties");
    $stmt_vos->execute();
    $result = $stmt_vos->get_result();
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $data[] = ["type" => $row['type'], "titel" => ucfirst($row['deelgebied']), "datum" => strtotime($row['ingestuurd_op'])];
        }
    }
    $stmt_vos->close();
  
    // Sorteren van array
    usort($data, function($a, $b) {
        return $b['datum'] <=> $a['datum'];
    });
    
    $num = intval($_GET['gebeurtenissen']);
    $data = array_slice($data, 0, $num);
    
    echo '<table class="w3-table w3-striped w3-white" id="gebeurtenissentabel">';
    
    // In tabel zetten
    foreach($data as $element){
        switch ($element["type"]) {
            case "Opdracht": 
                $fa = "fa fa-bell w3-text-teal"; 
                $url = "opdrachten"; 
                break;
            case "Hint": 
                $fa = "fas fa-question-circle w3-text-blue"; 
                $url = "hints"; 
                break;
            case "Nieuws": 
                $fa = "far fa-newspaper w3-text-black"; 
                $url = "nieuws"; 
                break;
            default: 
                // Hunt, Spot, etc.
                $fa = "fas fa-bullseye w3-text-red"; 
                $url = "kaarten"; 
                break; 
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