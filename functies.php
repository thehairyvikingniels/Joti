<?php
// Legacy AJAX router providing utility functions, coordinate transformations, time formatting, and push notification dispatch.
// Online check for service worker
if (isset($_GET['onlinecheck'])) {
    http_response_code(200);
    echo "OK";
    exit();
}

require_once(__DIR__ . '/includes/auth.php');

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

// Theme Switcher
if (isset($_GET['set_theme'])) {
    $newTheme = $_GET['set_theme'];
    $valid_themes = ['light', 'dark', 'rose-gold', 'cyber', 'nature', 'coral'];
    if (in_array($newTheme, $valid_themes)) {
        $_SESSION['theme'] = $newTheme;
        if (isset($_SESSION['id'])) {
            $stmt = $conn->prepare("UPDATE Gebruikers SET theme=? WHERE id=?");
            $stmt->bind_param("si", $newTheme, $_SESSION['id']);
            $stmt->execute();
            $stmt->close();
        }
    }
    exit();
}

// Save Map Settings to Session
if (isset($_GET['save_map_settings'])) {
    $settings = json_decode(file_get_contents('php://input'), true);
    if (is_array($settings)) {
        $_SESSION['map_settings'] = $settings;
    }
    exit();
}


// Delete a fox location
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

        // Code is optional for Hunt locations

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

// Toggle toewijzing
if (isset($_POST['toggle_toewijzing'])) {
    if (!isset($_SESSION['id']) || !isset($_SESSION['priv']) || $_SESSION['priv'] < 1) {
        echo json_encode(["status" => "error", "message" => "Geen rechten"]);
        exit();
    }
    
    header('Content-Type: application/json');
    $type = $_POST['type'] ?? '';
    $ref_id = intval($_POST['referentie_id'] ?? 0);
    $user_id = $_SESSION['id'];
    $force = isset($_POST['force']) && $_POST['force'] == '1';
    
    if (!$type || !$ref_id) {
        echo json_encode(["status" => "error"]);
        exit();
    }
    
    // Helper function to get assigned users
    $get_users = function($conn, $t, $r) {
        $stmt_users = $conn->prepare("SELECT g.id, g.voornaam, g.achternaam, g.profile_picture FROM Toewijzingen t JOIN Gebruikers g ON t.gebruiker_id = g.id WHERE t.type = ? AND t.referentie_id = ?");
        $stmt_users->bind_param("si", $t, $r);
        $stmt_users->execute();
        $res_users = $stmt_users->get_result();
        $users = $res_users->fetch_all(MYSQLI_ASSOC);
        $stmt_users->close();
        return $users;
    };

    // Check if already assigned to THIS exact item
    $stmt = $conn->prepare("SELECT id FROM Toewijzingen WHERE gebruiker_id = ? AND type = ? AND referentie_id = ?");
    $stmt->bind_param("isi", $user_id, $type, $ref_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    
    if ($result->num_rows > 0) {
        // Unassign THIS item
        $stmt_del = $conn->prepare("DELETE FROM Toewijzingen WHERE gebruiker_id = ? AND type = ? AND referentie_id = ?");
        $stmt_del->bind_param("isi", $user_id, $type, $ref_id);
        $stmt_del->execute();
        $stmt_del->close();
        
        send_push_notification($user_id, "Taak gewijzigd", "Je bent van een taak verwijderd.", "/whiteboard", "functies/toewijzing", null, "assignment_changes");
        
        $users = $get_users($conn, $type, $ref_id);
        echo json_encode(["status" => "unassigned", "target_type" => $type, "target_id" => $ref_id, "users" => $users]);
        exit();
    }
    
    // Check if assigned to ANYTHING ELSE
    $stmt_any = $conn->prepare("SELECT type, referentie_id FROM Toewijzingen WHERE gebruiker_id = ?");
    $stmt_any->bind_param("i", $user_id);
    $stmt_any->execute();
    $res_any = $stmt_any->get_result();
    
    $stmt_car = $conn->prepare("SELECT auto FROM Auto_Bijrijders WHERE gebruiker_id = ?");
    $stmt_car->bind_param("i", $user_id);
    $stmt_car->execute();
    $res_car = $stmt_car->get_result();
    
    $unassigned_type = null;
    $unassigned_id = null;
    $unassigned_users = [];
    
    if ($res_any->num_rows > 0 || $res_car->num_rows > 0) {
        $c_type = "";
        $c_ref = "";
        $c_name = "";
        
        if ($res_any->num_rows > 0) {
            $conflict = $res_any->fetch_assoc();
            $c_type = $conflict['type'];
            $c_ref = $conflict['referentie_id'];
            $c_name = ucfirst($c_type) . " " . $c_ref; // Default
            if ($c_type == 'opdracht') {
                $s = $conn->prepare("SELECT titel FROM Opdrachten WHERE id = ?");
                $s->bind_param("i", $c_ref);
                $s->execute();
                $r = $s->get_result();
                if ($r->num_rows > 0) $c_name = "Opdracht: " . $r->fetch_assoc()['titel'];
                $s->close();
            } else if ($c_type == 'hint') {
                $s = $conn->prepare("SELECT titel FROM Hints WHERE id = ?");
                $s->bind_param("i", $c_ref);
                $s->execute();
                $r = $s->get_result();
                if ($r->num_rows > 0) $c_name = "Hint: " . $r->fetch_assoc()['titel'];
                $s->close();
            } else if ($c_type == 'custom') {
                $s = $conn->prepare("SELECT naam FROM Whiteboard_Categorieen WHERE id = ?");
                $s->bind_param("i", $c_ref);
                $s->execute();
                $r = $s->get_result();
                if ($r->num_rows > 0) $c_name = $r->fetch_assoc()['naam'];
                $s->close();
            } else if ($c_type == 'hunt') {
                $c_name = isset($fox_names[$c_ref]) ? "Hunt: " . $fox_names[$c_ref] : "Hunt " . $c_ref;
            }
        } else {
            $conflict = $res_car->fetch_assoc();
            $c_type = 'auto';
            $c_ref = $conflict['auto'];
            $c_name = "Auto: " . $c_ref;
        }
        
        if (!$force) {
            // We have a conflict
            // Get name of target
            $t_name = ucfirst($type) . " " . $ref_id;
            if ($type == 'opdracht') {
                $s = $conn->prepare("SELECT titel FROM Opdrachten WHERE id = ?");
                $s->bind_param("i", $ref_id);
                $s->execute();
                $r = $s->get_result();
                if ($r->num_rows > 0) $t_name = "Opdracht: " . $r->fetch_assoc()['titel'];
                $s->close();
            } else if ($type == 'hint') {
                $s = $conn->prepare("SELECT titel FROM Hints WHERE id = ?");
                $s->bind_param("i", $ref_id);
                $s->execute();
                $r = $s->get_result();
                if ($r->num_rows > 0) $t_name = "Hint: " . $r->fetch_assoc()['titel'];
                $s->close();
            } else if ($type == 'custom') {
                $s = $conn->prepare("SELECT naam FROM Whiteboard_Categorieen WHERE id = ?");
                $s->bind_param("i", $ref_id);
                $s->execute();
                $r = $s->get_result();
                if ($r->num_rows > 0) $t_name = $r->fetch_assoc()['naam'];
                $s->close();
            } else if ($type == 'hunt') {
                $t_name = isset($fox_names[$ref_id]) ? "Hunt: " . $fox_names[$ref_id] : "Hunt " . $ref_id;
            }

            echo json_encode(["status" => "conflict", "conflict_name" => $c_name, "target_name" => $t_name]);
            $stmt_any->close();
            $stmt_car->close();
            exit();
        } else {
            // Force override: track what we unassigned so frontend can sync
            $unassigned_type = $c_type;
            $unassigned_id = $c_ref;
        }
    }
    $stmt_any->close();
    $stmt_car->close();

    // If we reach here, we either have no conflict, or we have force=1
    if ($force) {
        $stmt_del_all = $conn->prepare("DELETE FROM Toewijzingen WHERE gebruiker_id = ?");
        $stmt_del_all->bind_param("i", $user_id);
        $stmt_del_all->execute();
        $stmt_del_all->close();
        
        $stmt_del_auto = $conn->prepare("DELETE FROM Auto_Bijrijders WHERE gebruiker_id = ?");
        $stmt_del_auto->bind_param("i", $user_id);
        $stmt_del_auto->execute();
        $stmt_del_auto->close();
        
        if ($unassigned_type && $unassigned_type != 'auto') {
            $unassigned_users = $get_users($conn, $unassigned_type, $unassigned_id);
        }
    }
    
    // Assign
    $stmt_ins = $conn->prepare("INSERT INTO Toewijzingen (gebruiker_id, type, referentie_id) VALUES (?, ?, ?)");
    $stmt_ins->bind_param("isi", $user_id, $type, $ref_id);
    $stmt_ins->execute();
    $stmt_ins->close();
    
    send_push_notification($user_id, "Taak gewijzigd", "Je bent aan een taak toegewezen.", "/whiteboard", "functies/toewijzing", null, "assignment_changes");
    
    $users = $get_users($conn, $type, $ref_id);
    
    echo json_encode([
        "status" => "assigned", 
        "target_type" => $type, 
        "target_id" => $ref_id, 
        "users" => $users,
        "unassigned_type" => $unassigned_type,
        "unassigned_id" => $unassigned_id,
        "unassigned_users" => $unassigned_users
    ]);
    exit();
}
  
// Periodic GPS location update
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

    // If active Tegenhunt session exists, record breadcrumb
    $activeTegenhunt = function_exists('getActiveTegenhunt') ? getActiveTegenhunt($conn) : null;
    if ($activeTegenhunt && function_exists('recordTegenhuntBreadcrumb')) {
        $accuracy = isset($_GET['accuracy']) ? floatval($_GET['accuracy']) : 10.0;
        recordTegenhuntBreadcrumb($conn, (int)$activeTegenhunt['id'], (int)$_SESSION['id'], floatval($_GET['lat']), floatval($_GET['lon']), $accuracy);
    }
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
        
        $stmt_info = $conn->prepare("SELECT type, code FROM Voslocaties WHERE id = ?");
        $stmt_info->bind_param("i", $hunt_id);
        $stmt_info->execute();
        $res_info = $stmt_info->get_result();
        if ($res_info->num_rows > 0) {
            $row_info = $res_info->fetch_assoc();
            send_push_notification('ALL', 'Voslocatie Ingeleverd', "{$row_info['type']} {$row_info['code']} is succesvol ingeleverd bij de Jotihunt.", '/voslocaties', 'functies/ingeleverd', null, 'locatiestatus');
        }
        $stmt_info->close();
        
        header("Location: home");
        die();
    } else {
        echo "Error updating record: " . $stmt->error;
        $stmt->close();
    }
}

// Submission data table for homebase
if (isset($_GET['invulgegevens'])){
    if (!isset($_SESSION['priv']) || $_SESSION['priv'] < 2) {
        exit();
    }
    $stmt = $conn->prepare("
        SELECT * FROM Voslocaties WHERE ingeleverd = '0' AND type NOT IN ('Voorspelling', 'Spot') ORDER BY ingestuurd_op DESC
    ");
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo "<table class='w-full text-sm text-left theme-text'>";
        echo "<thead class='text-xs uppercase theme-card-header opacity-80'>";
        echo "<tr>";
        echo "  <th class='px-3 py-2'>Code</th>";
        echo "  <th class='px-3 py-2'>Type</th>";
        echo "  <th class='px-3 py-2'>Tijd</th>";
        echo "  <th class='px-3 py-2 text-center'>Foto</th>";
        echo "  <th class='px-3 py-2 text-right'></th>";
        echo "</tr></thead><tbody>";
        while($row = $result->fetch_assoc()) {
            $photo = !empty($row['foto']) ? $row['foto'] : null;
            $typeClass = $row['type'] === 'Tegenhunt' ? 'bg-red-500/15 text-red-500 font-extrabold px-1.5 py-0.5 rounded text-xs uppercase tracking-wide' : 'font-medium';
            echo "<tr class='border-b hover:opacity-80 transition-opacity' style='border-color: var(--theme-card-border);'>";
            echo "  <td class='px-3 py-2 font-mono font-bold'>".htmlspecialchars($row['code'] ?? '')."</td>";
            echo "  <td class='px-3 py-2'><span class='{$typeClass}'>".htmlspecialchars($row['type'] ?? '')."</span></td>";
            echo "  <td class='px-3 py-2 font-mono text-xs opacity-75'>".date("H:i",strtotime($row['ingestuurd_op']))."</td>";
            echo "  <td class='px-3 py-2 text-center'>";
            if (!empty($photo)) {
                echo "<a href='".htmlspecialchars($photo)."' download class='inline-flex items-center gap-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold py-1 px-2.5 rounded-lg shadow-sm transition' title='Download foto van sticker'><i class='fas fa-download text-xs'></i> <span>Download</span></a>";
            } else {
                echo "<span class='opacity-40 text-xs italic'>-</span>";
            }
            echo "  </td>";
            echo "  <td class='px-3 py-2 text-right'><i class=\"fas fa-check-circle text-green-500 hover:text-green-600 text-lg cursor-pointer transition transform hover:scale-110\" title=\"Markeer als ingeleverd op Jotihunt.nl\" onclick=\"document.getElementById('modal01').style.display='flex';document.getElementById('opgestuurdurl').href='functies?hunthintgedaan=".$row['id']."';\"></i></td>";
            echo "</tr>"; 
        }
        echo "</tbody></table>";
    } else {
        echo "<p class=\"m-4 text-xs opacity-70 italic\">Hier verschijnen hunts en tegenhunts die ingeleverd moeten worden bij de offici&euml;le Jotihunt website.</p>";
    }
    $stmt->close();
}

// Fetch active hunt cars
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
        echo "<div class='space-y-2'>";
        while($row = $result->fetch_assoc()) {
            $date1 = new DateTime($row['geotijd']);
            $now = new DateTime();
            $interval = $now->diff($date1);
            $minutes_since = ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;
          
            if($minutes_since < 15){ // Show cars active in last 15 minutes
                $cars_found = true;
                echo '<div class="flex items-center justify-between p-3 border rounded theme-card" style="border-color: var(--theme-card-border);">';
                echo '  <span class="font-semibold text-sm theme-text"><i class="fas fa-car-side opacity-70 mr-1"></i> '.htmlspecialchars($row['kenteken']).' <span class="text-xs font-normal opacity-70 ml-2">('.htmlspecialchars($row['bijrijders']).')</span></span>';
                echo '  <span class="text-[10px] uppercase tracking-wider bg-green-500/10 text-green-600 border border-green-500/20 px-2 py-0.5 rounded-sm font-bold">Actief <span class="font-normal lowercase">('.time2str($row['geotijd']).')</span></span>';
                echo '</div>';
            }
        }
        echo "</div>";
    }
    if (!$cars_found){
        echo "<p>Er zijn nu geen autos onderweg...</p>";
    }
    $stmt->close();
}


// Fetch event log
if (isset($_GET['gebeurtenissen'])){
    $data = [];
    
    // Fetch assignments
    $stmt_opdr = $conn->prepare("SELECT * FROM Opdrachten");
    $stmt_opdr->execute();
    $result = $stmt_opdr->get_result();
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $data[] = ["type" => "Opdracht", "titel" => $row['titel'], "datum" => strtotime($row['datum'])];
        }
    }
    $stmt_opdr->close();
  
    // Fetch hints
    $stmt_hints = $conn->prepare("SELECT * FROM Hints");
    $stmt_hints->execute();
    $result = $stmt_hints->get_result();
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $data[] = ["type" => "Hint", "titel" => $row['titel'], "datum" => strtotime($row['datum'])];
        }
    }
    $stmt_hints->close();
  
    // Fetch news
    $stmt_nieuws = $conn->prepare("SELECT * FROM Nieuws");
    $stmt_nieuws->execute();
    $result = $stmt_nieuws->get_result();
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $data[] = ["type" => "Nieuws", "titel" => $row['titel'], "datum" => strtotime($row['datum'])];
        }
    }
    $stmt_nieuws->close();
  
    // Fetch fox locations
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
    
    echo '<table class="w-full text-sm text-left theme-text" id="gebeurtenissentabel"><tbody>';
    
    // Render table rows
    foreach($data as $element){
        switch ($element["type"]) {
            case "Opdracht": 
                $fa = "fa fa-bell text-teal-500 text-lg"; 
                $url = "opdrachten"; 
                break;
            case "Hint": 
                $fa = "fas fa-question-circle text-blue-500 text-lg"; 
                $url = "hints"; 
                break;
            case "Nieuws": 
                $fa = "far fa-newspaper text-gray-500 text-lg"; 
                $url = "nieuws"; 
                break;
            default: 
                // Hunt, Spot, etc.
                $fa = "fas fa-bullseye text-red-500 text-lg"; 
                $url = "kaarten"; 
                break; 
        }
        echo '<tr class="border-b hover:opacity-80 cursor-pointer transition-opacity" style="border-color: var(--theme-card-border);" onclick="location.href=\''.$url.'\'">';
        echo '<td class="px-5 py-4 w-12"><i class="'.$fa.'"></i></td>';
        echo '<td class="px-5 py-4 font-medium">'.htmlspecialchars($element['type']).': '.htmlspecialchars($element['titel']).'</td>';
        echo '<td class="px-5 py-4 text-right text-xs opacity-60 whitespace-nowrap">'.time2str($element['datum']).'</td>';
        echo '</tr>';
    }
    echo "</tbody></table>";
    echo "<div class='mt-4 flex justify-center'><button id='meerknop' class='px-4 py-2 theme-bg-primary text-white text-sm font-semibold rounded hover:opacity-90 transition' onclick='gebeurtenissen(". ($num+5) .")'>Meer resultaten</button></div>";
}

?>
