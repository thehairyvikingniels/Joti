<?php
define("PAGE_NAME", "a_users");
session_start();

if (!isset($_SESSION['id'])){
  header("Location: ../index");
  exit();
}
if (!isset($_SESSION['priv']) || $_SESSION['priv'] < 2) {
  header("Location: ../home");
  exit();
}

require("../dblogin.php");

// Huidige gebruiker rechten ophalen
$stmt = $conn->prepare("SELECT voornaam, priv FROM Gebruikers WHERE id=?");
$stmt->bind_param("i", $_SESSION['id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $vn = $row['voornaam'];
    $priv = $row['priv'];
} else {
    // Fallback als de account niet meer bestaat
    session_destroy();
    header("Location: ../index");
    exit();
}
$stmt->close();

// Controleer admin rechten
if ($priv < 2){
  header("Location: ../home");
  exit();
}

// Get global site settings
$stmt_settings = $conn->prepare("SELECT * FROM Site_Instellingen");
$stmt_settings->execute();
$result_settings = $stmt_settings->get_result();
$siteSettings = array();

if ($result_settings->num_rows > 0) {
    while($row = $result_settings->fetch_assoc()) {
      $siteSettings[$row['Instelling']] = $row['Waarde'];
    }
} else {
    echo "0 results";
    $stmt_settings->close();
    exit();
}
$stmt_settings->close();


// Gebruiker updaten of verwijderen
if (isset($_POST["user"]) && isset($_POST['priv'])){
    $target_user_id = intval($_POST['user']);
    $new_priv = intval($_POST['priv']);

    // Fetch current priv of target
    $stmt_current = $conn->prepare("SELECT priv FROM Gebruikers WHERE id=?");
    $stmt_current->bind_param("i", $target_user_id);
    $stmt_current->execute();
    $result_current = $stmt_current->get_result();
    $current_priv = 0;
    if ($result_current->num_rows > 0) {
        $row_current = $result_current->fetch_assoc();
        $current_priv = $row_current['priv'];
    }
    $stmt_current->close();

    $allowed = false;
    if ($_SESSION['priv'] >= 3) {
        $allowed = true;
    } else if ($_SESSION['priv'] == 2) {
        if ($current_priv <= 2 && ($new_priv <= 2 || $new_priv == 4)) {
            $allowed = true;
        }
    }

    if ($allowed) {
        if ($new_priv === 4) {
            // Verwijder de gebruiker als optie 4 is geselecteerd
            $stmt_update = $conn->prepare("DELETE FROM Gebruikers WHERE id=?");
            $stmt_update->bind_param("i", $target_user_id);
        } else {
            // Update de privileges
            $stmt_update = $conn->prepare("UPDATE Gebruikers SET priv=? WHERE id=?");
            $stmt_update->bind_param("ii", $new_priv, $target_user_id);
        }

        if ($stmt_update->execute()) {
            $succes = true;
        } else {
            $error_msg = "Error updating record: " . $stmt_update->error;
        }
        $stmt_update->close();
    } else {
        $error_msg = "Je hebt niet de juiste rechten om deze actie uit te voeren.";
    }
}

// Impersonate
if (isset($_POST['impersonate_user_id'])) {
    $target_user_id = intval($_POST['impersonate_user_id']);
    
    $stmt_target = $conn->prepare("SELECT id, priv FROM Gebruikers WHERE id=?");
    $stmt_target->bind_param("i", $target_user_id);
    $stmt_target->execute();
    $result_target = $stmt_target->get_result();
    
    if ($result_target->num_rows > 0) {
        $row_target = $result_target->fetch_assoc();
        $target_priv = $row_target['priv'];
        
        $allowed = false;
        if ($_SESSION['priv'] >= 3 && $target_priv <= 2) {
            $allowed = true;
        } else if ($_SESSION['priv'] == 2 && $target_priv <= 1) {
            $allowed = true;
        }
        
        if ($allowed) {
            $_SESSION['original_id'] = $_SESSION['id'];
            $_SESSION['id'] = $row_target['id'];
            $_SESSION['priv'] = $row_target['priv'];
            header("Location: ../home");
            exit();
        } else {
            $error_msg = "Je hebt niet de juiste rechten om deze gebruiker te imiteren.";
        }
    }
    $stmt_target->close();
}

// Haal alle gebruikers op en sla ze op in een array (voorkomt 2x dezelfde query uitvoeren)
$users_data = [];
$stmt_users = $conn->prepare("SELECT id, voornaam, achternaam, email, priv FROM Gebruikers ORDER BY id ASC");
$stmt_users->execute();
$result_users = $stmt_users->get_result();

if ($result_users->num_rows > 0) {
    while($row = $result_users->fetch_assoc()) {
        $users_data[] = $row;
    }
}
$stmt_users->close();

?>

<!DOCTYPE html>
<html>
<title>Jotihunt - De Geuzen</title>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="shortcut icon" type="image/png" href="media/geusje.png"/>
<link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">
<link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Raleway">
<script src="https://kit.fontawesome.com/870ab34ea3.js" crossorigin="anonymous"></script>
<style>
html,body,h1,h2,h3,h4,h5 {font-family: "Raleway", sans-serif}
</style>
<body class="w3-light-grey">

<?php include_once('../includes/topbar.php') ?>

<?php include_once('../includes/sidebar.php') ?>

<div class="w3-main" style="margin-left:200px;margin-top:43px;">

  <header class="w3-container" style="padding-top:22px">
    <h5><b><i class="fas fa-cogs"></i> Admin</b></h5>
  </header>
  
  <div class="w3-row" style="margin-bottom:100px;">
    <div class="w3-col l7 m12 s12 w3-padding">
      <div class="w3-card-4 w3-white">
        <div class="w3-blue-gray w3-padding" style="width:100%">
          <h5>Gebruikers</h5>
        </div>
        <div class="">
          <?php if (isset($succes)){
            echo "
            <div class='w3-green w3-card w3-padding w3-margin w3-display-container'>
              <span onclick=\"this.parentElement.style.display='none'\" class=\"w3-button w3-display-topright\">X</span>
              Succes!
            </div>
            ";
          } elseif (isset($error_msg)) {
            echo "
            <div class='w3-red w3-card w3-padding w3-margin w3-display-container'>
              <span onclick=\"this.parentElement.style.display='none'\" class=\"w3-button w3-display-topright\">X</span>
              ".htmlspecialchars($error_msg)."
            </div>
            ";
          }
          ?>
          
          <table class="w3-table-all w3-hide-small" style="width:100%">
            <tr>
              <th>ID</th>
              <th>Naam</th>
              <th>Email</th>
              <th>Rol</th>
              <th></th>
            </tr>
            <?php
            // Genereer de desktop tabel vanuit de vooraf geladen array
            foreach($users_data as $row) {
                $priv0 = ($row['priv'] == 0) ? "selected" : "";
                $priv1 = ($row['priv'] == 1) ? "selected" : "";
                $priv2 = ($row['priv'] == 2) ? "selected" : "";
                $priv3 = ($row['priv'] == 3) ? "selected" : "";
                
                echo "<tr>";
                echo "  <td>".htmlspecialchars($row["id"])."</td>";
                echo "  <td>".htmlspecialchars($row["voornaam"])."<br>".htmlspecialchars($row["achternaam"])."</td>";
                echo "  <td>".htmlspecialchars($row["email"])."</td>";
                echo '  <form method="POST">';
                echo '    <td>
                            <input type="hidden" value="'.htmlspecialchars($row['id']).'" name="user">
                            <select class="w3-select" name="priv">
                              <option value="0" '.$priv0.'>Gast</option>
                              <option value="1" '.$priv1.'>Vossenjager</option>
                              <option value="2" '.$priv2.'>Admin</option>
                              <option value="3" '.$priv3.'>Superadmin</option>
                               <option value="4" class="w3-red">Verwijder</option>
                            </select>
                          </td>';
                echo "  <td>";
                echo "      <button class='w3-button w3-blue-gray' type='button' onclick=\"document.getElementById('priv_modal_desk_".$row['id']."').style.display='block'\"><i class=\"fas fa-check\"></i></button>";
                
                echo "
                <div id='priv_modal_desk_".$row['id']."' class='w3-modal'>
                  <div class='w3-modal-content w3-card-4' style='max-width:500px'>
                    <header class='w3-container w3-blue-gray'> 
                      <span onclick=\"document.getElementById('priv_modal_desk_".$row['id']."').style.display='none'\" class='w3-button w3-display-topright'>&times;</span>
                      <h2>Bevestiging</h2>
                    </header>
                    <div class='w3-container w3-padding-16'>
                      <p>Weet je zeker dat je de rol/rechten van ".htmlspecialchars($row['voornaam'])." wilt wijzigen?</p>
                      <button type='submit' class='w3-button w3-green'>Ja, wijzig</button>
                      <button type='button' onclick=\"document.getElementById('priv_modal_desk_".$row['id']."').style.display='none'\" class='w3-button w3-red w3-right'>Annuleer</button>
                    </div>
                  </div>
                </div>
                ";
                
                $can_impersonate = false;
                if ($_SESSION['priv'] >= 3 && $row['priv'] <= 2) $can_impersonate = true;
                if ($_SESSION['priv'] == 2 && $row['priv'] <= 1) $can_impersonate = true;
                if ($can_impersonate) {
                    echo "      <button type='button' onclick=\"document.getElementById('imp_modal_".$row['id']."').style.display='block'\" class='w3-button w3-dark-gray w3-margin-left'><i class=\"fas fa-user-secret\"></i></button>";
                }
                echo "  </td>";
                echo "  </form>";
                echo "</tr>";
            }
            ?>
          </table>
          
          <table class="w3-table-all w3-hide-large w3-hide-medium" style="width:100%">
            <?php
            // Genereer de mobiele tabel vanuit exact dezelfde array
            foreach($users_data as $row) {
                $priv0 = ($row['priv'] == 0) ? "selected" : "";
                $priv1 = ($row['priv'] == 1) ? "selected" : "";
                $priv2 = ($row['priv'] == 2) ? "selected" : "";
                $priv3 = ($row['priv'] == 3) ? "selected" : "";
                
                echo "<tr>";
                echo "  <td>".htmlspecialchars($row["voornaam"])." ".htmlspecialchars($row["achternaam"])."<span class=\"w3-right\"><b>Id:</b> ".htmlspecialchars($row["id"])."</span><br><span class=\"w3-tiny\">".htmlspecialchars($row["email"])."</span></td>";
                echo '  <form method="POST">';
                echo '  <td style="width:15%">
                          <input type="hidden" value="'.htmlspecialchars($row['id']).'" name="user">
                          <select class="w3-select" name="priv">
                            <option value="0" '.$priv0.'>Gast</option>
                            <option value="1" '.$priv1.'>Vossenjager</option>
                            <option value="2" '.$priv2.'>Admin</option>
                            <option value="3" '.$priv3.'>Superadmin</option>
                            <option value="4" class="w3-red">Verwijder</option>
                          </select>
                        </td>';
                echo "  <td>";
                echo "      <button class='w3-button w3-blue-gray' style=\"padding:2px;padding-top:5px;padding-bottom:5px;\" type='button' onclick=\"document.getElementById('priv_modal_mob_".$row['id']."').style.display='block'\"><i class=\"fas fa-check\"></i></button><br>";
                
                echo "
                <div id='priv_modal_mob_".$row['id']."' class='w3-modal'>
                  <div class='w3-modal-content w3-card-4' style='max-width:500px'>
                    <header class='w3-container w3-blue-gray'> 
                      <span onclick=\"document.getElementById('priv_modal_mob_".$row['id']."').style.display='none'\" class='w3-button w3-display-topright'>&times;</span>
                      <h2>Bevestiging</h2>
                    </header>
                    <div class='w3-container w3-padding-16'>
                      <p>Weet je zeker dat je de rol/rechten van ".htmlspecialchars($row['voornaam'])." wilt wijzigen?</p>
                      <button type='submit' class='w3-button w3-green'>Ja, wijzig</button>
                      <button type='button' onclick=\"document.getElementById('priv_modal_mob_".$row['id']."').style.display='none'\" class='w3-button w3-red w3-right'>Annuleer</button>
                    </div>
                  </div>
                </div>
                ";
                
                $can_impersonate = false;
                if ($_SESSION['priv'] >= 3 && $row['priv'] <= 2) $can_impersonate = true;
                if ($_SESSION['priv'] == 2 && $row['priv'] <= 1) $can_impersonate = true;
                if ($can_impersonate) {
                    echo "      <button type='button' onclick=\"document.getElementById('imp_modal_".$row['id']."').style.display='block'\" class='w3-button w3-dark-gray' style=\"padding:2px;padding-top:5px;padding-bottom:5px;margin-top:5px;\"><i class=\"fas fa-user-secret\"></i></button>";
                }
                echo "  </td>";
                echo '  </form>';
                echo "</tr>";
            }
            ?>
          </table>

          <?php
          // Generate Modals once for all users
          foreach($users_data as $row) {
              $can_impersonate = false;
              if ($_SESSION['priv'] >= 3 && $row['priv'] <= 2) $can_impersonate = true;
              if ($_SESSION['priv'] == 2 && $row['priv'] <= 1) $can_impersonate = true;
              
              if ($can_impersonate) {
                  echo "
                  <div id='imp_modal_".$row['id']."' class='w3-modal'>
                    <div class='w3-modal-content w3-card-4' style='max-width:500px'>
                      <header class='w3-container w3-blue-gray'> 
                        <span onclick=\"document.getElementById('imp_modal_".$row['id']."').style.display='none'\" class='w3-button w3-display-topright'>&times;</span>
                        <h2>Bevestiging</h2>
                      </header>
                      <div class='w3-container w3-padding-16'>
                        <p>Weet je zeker dat je wilt inloggen als ".htmlspecialchars($row['voornaam'])." ".htmlspecialchars($row['achternaam'])."?</p>
                        <form method='POST'>
                          <input type='hidden' name='impersonate_user_id' value='".htmlspecialchars($row['id'])."'>
                          <button type='submit' class='w3-button w3-green'>Ja, log in</button>
                          <button type='button' onclick=\"document.getElementById('imp_modal_".$row['id']."').style.display='none'\" class='w3-button w3-red w3-right'>Annuleer</button>
                        </form>
                      </div>
                    </div>
                  </div>
                  ";
              }
          }
          ?>
        </div>
      </div>
    </div>
  </div>

  <?php require_once('../includes/footer.php') ?>

  </div>

<script>
if ("<?php echo $_SESSION['gps']?>" == "true"){
  setInterval(function() {
    GPSrefresh();
  }, 5555);
}
 
function GPSrefresh() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(showPosition);
    } else {
        console.log("Geolocation is not supported by this browser.");
    }
    
    function showPosition(position) {
      console.log("Latitude: " + position.coords.latitude + 
      "\nLongitude: " + position.coords.longitude);
      
      if (window.XMLHttpRequest) {
            // code for IE7+, Firefox, Chrome, Opera, Safari
            xmlhttp = new XMLHttpRequest();
        } else {
            // code for IE6, IE5
            xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
        }
        xmlhttp.onreadystatechange = function() {
            if (this.readyState == 4 && this.status == 200) {
                // Success logic
            }
        };
        xmlhttp.open("GET","../functies.php?lat="+position.coords.latitude+"&lon="+position.coords.longitude,true);
        xmlhttp.send();
    }
} 
</script>

</body>
</html>