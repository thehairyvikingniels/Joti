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

    if ($new_priv === 3) {
        // Verwijder de gebruiker als optie 3 is geselecteerd
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
              <th>Priv</th>
              <th></th>
            </tr>
            <?php
            // Genereer de desktop tabel vanuit de vooraf geladen array
            foreach($users_data as $row) {
                $priv0 = ($row['priv'] == 0) ? "selected" : "";
                $priv1 = ($row['priv'] == 1) ? "selected" : "";
                $priv2 = ($row['priv'] == 2) ? "selected" : "";
                
                echo "<tr>";
                echo "  <td>".htmlspecialchars($row["id"])."</td>";
                echo "  <td>".htmlspecialchars($row["voornaam"])."<br>".htmlspecialchars($row["achternaam"])."</td>";
                echo "  <td>".htmlspecialchars($row["email"])."</td>";
                echo '  <form method="POST">';
                echo '    <td>
                            <input type="hidden" value="'.htmlspecialchars($row['id']).'" name="user">
                            <select class="w3-select" name="priv">
                              <option value="0" '.$priv0.'>0</option>
                              <option value="1" '.$priv1.'>1</option>
                              <option value="2" '.$priv2.'>2</option>
                              <option value="3" class="w3-red">Verwijder</option>
                            </select>
                          </td>';
                echo "  <td><button class='w3-button w3-blue-gray'><i class=\"fas fa-check\"></i></button></td>";
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
                
                echo "<tr>";
                echo "  <td>".htmlspecialchars($row["voornaam"])." ".htmlspecialchars($row["achternaam"])."<span class=\"w3-right\"><b>Id:</b> ".htmlspecialchars($row["id"])."</span><br><span class=\"w3-tiny\">".htmlspecialchars($row["email"])."</span></td>";
                echo '  <form method="POST">';
                echo '  <td style="width:15%">
                          <input type="hidden" value="'.htmlspecialchars($row['id']).'" name="user">
                          <select class="w3-select" name="priv">
                            <option value="0" '.$priv0.'>0</option>
                            <option value="1" '.$priv1.'>1</option>
                            <option value="2" '.$priv2.'>2</option>
                            <option value="3" class="w3-red">Verwijder</option>
                          </select>
                        </td>';
                echo "  <td><button class='w3-button w3-blue-gray' style=\"padding:2px;padding-top:5px;padding-bottom:5px;\"><i class=\"fas fa-check\"></i></button></td>";
                echo '  </form>';
                echo "</tr>";
            }
            ?>
          </table>
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