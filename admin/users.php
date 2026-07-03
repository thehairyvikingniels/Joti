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
require_once(__DIR__ . '/../includes/helpers.php');

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

// Reset Wachtwoord
if (isset($_POST['reset_password_user_id']) && isset($_POST['new_password'])) {
    $target_user_id = intval($_POST['reset_password_user_id']);
    $new_password = $_POST['new_password'];
    
    $stmt_target = $conn->prepare("SELECT priv FROM Gebruikers WHERE id=?");
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
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt_reset = $conn->prepare("UPDATE Gebruikers SET wachtwoord=? WHERE id=?");
            $stmt_reset->bind_param("si", $hashed, $target_user_id);
            if ($stmt_reset->execute()) {
                $succes = true;
            } else {
                $error_msg = "Error updating wachtwoord: " . $stmt_reset->error;
            }
            $stmt_reset->close();
        } else {
            $error_msg = "Je hebt niet de juiste rechten om dit wachtwoord te resetten.";
        }
    }
    $stmt_target->close();
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
$stmt_users = $conn->prepare("SELECT id, voornaam, achternaam, email, priv, first_login, last_login FROM Gebruikers ORDER BY id ASC");
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
.admin-user-table-wrapper {
  overflow-x: auto;
  width: 100%;
}
.admin-user-table {
  width: 100%;
  table-layout: fixed;
  min-width: 900px;
}
.admin-user-table th,
.admin-user-table td {
  white-space: nowrap;
}
.admin-user-table td select {
  width: 100%;
  min-width: 140px;
}
.admin-user-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
  align-items: center;
  justify-content: flex-end;
}
.admin-user-actions form {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
  align-items: center;
  justify-content: flex-end;
  width: 100%;
}
.admin-user-actions select {
  flex: 1 1 220px;
  min-width: 180px;
  max-width: 320px;
}
.admin-user-actions button {
  min-width: 38px;
}
.admin-role-select-mobile {
  width: 100%;
  max-width: 100%;
}
.admin-action-cell {
  white-space: normal;
}
@media screen and (max-width: 992px) {
  .admin-user-table {
    min-width: 0;
    table-layout: auto;
  }
  .admin-user-table td,
  .admin-user-table th {
    white-space: normal;
  }
  .admin-user-actions {
    justify-content: flex-start;
    gap: 0.5rem;
  }
  .admin-user-actions select {
    min-width: 160px;
    flex: 1 1 100%;
  }
  .admin-user-actions button {
    min-width: 42px;
  }
}
</style>
<body class="w3-light-grey">

<?php include_once('../includes/topbar.php') ?>

<?php include_once('../includes/sidebar.php') ?>

<div class="w3-main" style="margin-left:200px;margin-top:43px;">

  <header class="w3-container" style="padding-top:22px">
    <h5><b><i class="fas fa-cogs"></i> Admin</b></h5>
  </header>
  
  <div class="w3-row" style="margin-bottom:100px;">
    <div class="w3-col l12 m12 s12 w3-padding">
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
          
          <div style="overflow-x:auto; width:100%;">
            <table class="w3-table-all w3-hide-small w3-hide-medium" style="width:100%; table-layout: fixed; min-width:900px;">
              <tr>
                <th style="width:5%; white-space: nowrap;">ID</th>
                <th style="width:20%;">Naam</th>
                <th style="width:22%;">Email</th>
                <th style="width:16%; white-space: nowrap;">Laatste login</th>
                <th style="width:16%; white-space: nowrap;">Eerste login</th>
                <th style="width:21%; white-space: nowrap;">Acties</th>
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
                echo "  <td>".htmlspecialchars(time2str($row['last_login']))."</td>";
                echo "  <td>".htmlspecialchars(time2str($row['first_login']))."</td>";
                $can_impersonate = false;
                if ($_SESSION['priv'] >= 3 && $row['priv'] <= 2) $can_impersonate = true;
                if ($_SESSION['priv'] == 2 && $row['priv'] <= 1) $can_impersonate = true;
                echo '    <td class="admin-user-actions">';
                echo '      <form id="priv_form_desk_'.$row['id'].'" method="POST" style="display:flex; flex-wrap:nowrap; gap:0.25rem; align-items:center; justify-content:flex-end;">';
                echo '        <input type="hidden" value="'.htmlspecialchars($row['id']).'" name="user">';
                echo '        <select class="w3-select" name="priv" style="flex:0 1 auto; min-width:100px;">';
                echo '          <option value="0" '.$priv0.'>Gast</option>';
                echo '          <option value="1" '.$priv1.'>Vossenjager</option>';
                echo '          <option value="2" '.$priv2.'>Admin</option>';
                echo '          <option value="3" '.$priv3.'>Superadmin</option>';
                echo '          <option value="4" class="w3-red">Verwijder</option>';
                echo '        </select>';
                echo '        <button class="w3-button w3-blue-gray" type="button" onclick="document.getElementById(\'priv_modal_desk_'.$row['id'].'\').style.display=\'block\'" style="flex:0 0 auto; padding:4px 8px;"><i class="fas fa-check"></i></button>';
                if ($can_impersonate) {
                    echo '        <button type="button" onclick="document.getElementById(\'imp_modal_'.$row['id'].'\').style.display=\'block\'" class="w3-button w3-dark-gray" style="flex:0 0 auto; padding:4px 8px;"><i class="fas fa-user-secret"></i></button>';
                    echo '        <button type="button" onclick="document.getElementById(\'reset_modal_'.$row['id'].'\').style.display=\'block\'" class="w3-button w3-orange w3-text-white" style="flex:0 0 auto; padding:4px 8px;"><i class="fas fa-key"></i></button>';
                } else {
                    echo '        <button type="button" class="w3-button w3-grey w3-disabled" disabled style="flex:0 0 auto; padding:4px 8px;"><i class="fas fa-user-secret"></i></button>';
                    echo '        <button type="button" class="w3-button w3-grey w3-disabled" disabled style="flex:0 0 auto; padding:4px 8px;"><i class="fas fa-key"></i></button>';
                }
                echo '      </form>';
                echo '    </td>';
                echo "
                <div id='priv_modal_desk_".$row['id']."' class='w3-modal'>
                  <div class='w3-modal-content w3-card-4' style='max-width:500px'>
                    <header class='w3-container w3-blue-gray'> 
                      <span onclick=\"document.getElementById('priv_modal_desk_".$row['id']."').style.display='none'\" class='w3-button w3-display-topright'>&times;</span>
                      <h2>Bevestiging</h2>
                    </header>
                    <div class='w3-container w3-padding-16'>
                      <p>Weet je zeker dat je de rol/rechten van ".htmlspecialchars($row['voornaam'])." wilt wijzigen?</p>
                      <button type='submit' form='priv_form_desk_".$row['id']."' class='w3-button w3-green'>Ja, wijzig</button>
                      <button type='button' onclick=\"document.getElementById('priv_modal_desk_".$row['id']."').style.display='none'\" class='w3-button w3-red w3-right'>Annuleer</button>
                    </div>
                  </div>
                </div>
                ";
                echo "</tr>";
            }
            ?>
            </table>
          </div>
          
          <table class="w3-table-all w3-hide-large" style="width:100%;">
            <?php
            // Genereer de mobiele tabel vanuit exact dezelfde array
            foreach($users_data as $row) {
                $priv0 = ($row['priv'] == 0) ? "selected" : "";
                $priv1 = ($row['priv'] == 1) ? "selected" : "";
                $priv2 = ($row['priv'] == 2) ? "selected" : "";
                $priv3 = ($row['priv'] == 3) ? "selected" : "";
                
                echo "<tr>";
                echo "  <td>".htmlspecialchars($row["voornaam"])." ".htmlspecialchars($row["achternaam"])."<span class=\"w3-right\"><b>Id:</b> ".htmlspecialchars($row["id"])."</span><br><span class=\"w3-tiny\">".htmlspecialchars($row["email"])."</span><br><span class=\"w3-tiny\"><b>L:</b> ".htmlspecialchars(time2str($row['last_login']))."<br><b>E:</b> ".htmlspecialchars(time2str($row['first_login']))."</span></td>";
                echo "  <td class=\"admin-user-actions\">";
                echo "      <form id=\"priv_form_mob_".$row['id']."\" method=\"POST\" style=\"display:flex; flex-wrap:nowrap; gap:0.25rem; align-items:center; justify-content:flex-end; width:100%;\">";
                echo "        <input type=\"hidden\" value=\"".htmlspecialchars($row['id'])."\" name=\"user\">";
                echo "        <select class=\"w3-select\" name=\"priv\" style=\"flex:1 1 auto; min-width:100px;\">";
                echo "          <option value=\"0\" ".$priv0." >Gast</option>";
                echo "          <option value=\"1\" ".$priv1." >Vossenjager</option>";
                echo "          <option value=\"2\" ".$priv2." >Admin</option>";
                echo "          <option value=\"3\" ".$priv3." >Superadmin</option>";
                echo "          <option value=\"4\" class=\"w3-red\">Verwijder</option>";
                echo "        </select>";
                echo "        <button class='w3-button w3-blue-gray' style=\"flex:0 0 auto; padding:2px 4px;\" type='button' onclick=\"document.getElementById('priv_modal_mob_".$row['id']."').style.display='block'\"><i class=\"fas fa-check\"></i></button>";
                
                $can_impersonate = false;
                if ($_SESSION['priv'] >= 3 && $row['priv'] <= 2) $can_impersonate = true;
                if ($_SESSION['priv'] == 2 && $row['priv'] <= 1) $can_impersonate = true;
                if ($can_impersonate) {
                    echo "        <button type='button' onclick=\"document.getElementById('imp_modal_".$row['id']."').style.display='block'\" class='w3-button w3-dark-gray' style=\"flex:0 0 auto; padding:2px 4px;\"><i class=\"fas fa-user-secret\"></i></button>";
                    echo "        <button type='button' onclick=\"document.getElementById('reset_modal_".$row['id']."').style.display='block'\" class='w3-button w3-orange w3-text-white' style=\"flex:0 0 auto; padding:2px 4px;\"><i class=\"fas fa-key\"></i></button>";
                } else {
                    echo "        <button type='button' class='w3-button w3-grey w3-disabled' disabled style=\"flex:0 0 auto; padding:2px 4px;\"><i class=\"fas fa-user-secret\"></i></button>";
                    echo "        <button type='button' class='w3-button w3-grey w3-disabled' disabled style=\"flex:0 0 auto; padding:2px 4px;\"><i class=\"fas fa-key\"></i></button>";
                }
                echo "      </form>";
                echo "  </td>";
                echo "
                <div id='priv_modal_mob_".$row['id']."' class='w3-modal'>
                  <div class='w3-modal-content w3-card-4' style='max-width:500px'>
                    <header class='w3-container w3-blue-gray'> 
                      <span onclick=\"document.getElementById('priv_modal_mob_".$row['id']."').style.display='none'\" class='w3-button w3-display-topright'>&times;</span>
                      <h2>Bevestiging</h2>
                    </header>
                    <div class='w3-container w3-padding-16'>
                      <p>Weet je zeker dat je de rol/rechten van ".htmlspecialchars($row['voornaam'])." wilt wijzigen?</p>
                      <button type='submit' form='priv_form_mob_".$row['id']."' class='w3-button w3-green'>Ja, wijzig</button>
                      <button type='button' onclick=\"document.getElementById('priv_modal_mob_".$row['id']."').style.display='none'\" class='w3-button w3-red w3-right'>Annuleer</button>
                    </div>
                  </div>
                </div>
                ";
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
                  
                  echo "
                  <div id='reset_modal_".$row['id']."' class='w3-modal'>
                    <div class='w3-modal-content w3-card-4' style='max-width:500px'>
                      <header class='w3-container w3-orange w3-text-white'> 
                        <span onclick=\"document.getElementById('reset_modal_".$row['id']."').style.display='none'\" class='w3-button w3-display-topright'>&times;</span>
                        <h2>Nieuw Wachtwoord</h2>
                      </header>
                      <div class='w3-container w3-padding-16'>
                        <p>Vul een nieuw wachtwoord in voor ".htmlspecialchars($row['voornaam'])." ".htmlspecialchars($row['achternaam']).":</p>
                        <form method='POST'>
                          <input type='hidden' name='reset_password_user_id' value='".htmlspecialchars($row['id'])."'>
                          <input type='text' name='new_password' class='w3-input w3-border w3-margin-bottom' required placeholder='Nieuw wachtwoord'>
                          <button type='submit' class='w3-button w3-green'>Reset Wachtwoord</button>
                          <button type='button' onclick=\"document.getElementById('reset_modal_".$row['id']."').style.display='none'\" class='w3-button w3-red w3-right'>Annuleer</button>
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