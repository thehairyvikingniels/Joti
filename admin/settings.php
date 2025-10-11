<?php
session_start();

if (!isset($_SESSION['id'])) {
    header("Location: ../index");
    exit();
}

require("../dblogin.php");

// Check user privileges
$sql = "SELECT voornaam, priv FROM Gebruikers WHERE id='".$_SESSION['id']."'";
$result = mysqli_query($conn, $sql);

if (mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);
    $vn = $row['voornaam'];
    $priv = $row['priv'];
} else {
    // Failsafe if user not found
    header("Location: ../index");
    exit();
}

if ($priv < 3) {
    header("Location: ../home");
    exit();
}

$succes_message = '';
$error_message = '';

// Handle form submission to UPDATE settings
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update_settings') {
    $all_updates_successful = true;
    
    // Using prepared statements to prevent SQL injection
    $stmt = $conn->prepare("UPDATE Site_Instellingen SET Waarde = ? WHERE Instelling = ?");

    if ($stmt) {
        foreach ($_POST as $instelling => $waarde) {
            // Only process fields that are actual settings (not action or other form data)
            if ($instelling != 'action' && $instelling != 'add_setting_name' && $instelling != 'add_setting_value' && $instelling != 'add_setting_description') {
                $instelling = trim($instelling);
                $waarde = trim($waarde);
                
                $stmt->bind_param("ss", $waarde, $instelling);
                if (!$stmt->execute()) {
                    $all_updates_successful = false;
                    $error_message = "Fout bij het bijwerken van de instelling: " . htmlspecialchars($instelling);
                    break; // Exit the loop on first error
                }
            }
        }
        $stmt->close();
        
        if ($all_updates_successful) {
            $succes_message = "De instellingen zijn succesvol opgeslagen!";
        }
    } else {
        $error_message = "Er is een fout opgetreden bij het voorbereiden van de database-update.";
    }
}

// Handle form submission to ADD new setting
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add_setting') {
    $newName = trim($_POST['add_setting_name'] ?? '');
    $newValue = trim($_POST['add_setting_value'] ?? '');
    $newDescription = trim($_POST['add_setting_description'] ?? '');

    if (!empty($newName)) {
        // Check if setting already exists
        $check_stmt = $conn->prepare("SELECT COUNT(*) FROM Site_Instellingen WHERE Instelling = ?");
        $check_stmt->bind_param("s", $newName);
        $check_stmt->execute();
        $check_stmt->bind_result($count);
        $check_stmt->fetch();
        $check_stmt->close();

        if ($count > 0) {
            $error_message = "Instelling met de naam '" . htmlspecialchars($newName) . "' bestaat al.";
        } else {
            $insert_stmt = $conn->prepare("INSERT INTO Site_Instellingen (Instelling, Waarde, Omschrijving) VALUES (?, ?, ?)");
            if ($insert_stmt) {
                $insert_stmt->bind_param("sss", $newName, $newValue, $newDescription);
                if ($insert_stmt->execute()) {
                    $succes_message = "Nieuwe instelling '" . htmlspecialchars($newName) . "' is succesvol toegevoegd!";
                } else {
                    $error_message = "Fout bij het toevoegen van de instelling: " . $insert_stmt->error;
                }
                $insert_stmt->close();
            } else {
                $error_message = "Er is een fout opgetreden bij het voorbereiden van de database-invoeging.";
            }
        }
    } else {
        $error_message = "Naam van de instelling mag niet leeg zijn.";
    }
}

// Handle deletion of a setting
if (isset($_GET['delete_setting'])) {
    $setting_to_delete = trim($_GET['delete_setting']);
    if (!empty($setting_to_delete)) {
        $delete_stmt = $conn->prepare("DELETE FROM Site_Instellingen WHERE Instelling = ?");
        if ($delete_stmt) {
            $delete_stmt->bind_param("s", $setting_to_delete);
            if ($delete_stmt->execute()) {
                $succes_message = "Instelling '" . htmlspecialchars($setting_to_delete) . "' is succesvol verwijderd!";
            } else {
                $error_message = "Fout bij het verwijderen van de instelling: " . $delete_stmt->error;
            }
            $delete_stmt->close();
        } else {
            $error_message = "Er is een fout opgetreden bij het voorbereiden van de database-verwijdering.";
        }
    }
}

// Fetch all current settings to display in the form
$sql_settings = "SELECT Instelling, Waarde, Omschrijving FROM Site_Instellingen";
$result_settings = mysqli_query($conn, $sql_settings);
$settings = [];
if (mysqli_num_rows($result_settings) > 0) {
    while ($row = mysqli_fetch_assoc($result_settings)) {
        $settings[] = $row;
    }
}
?>
<!DOCTYPE html>
<html>
<title>Jotihunt - Instellingen</title>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="shortcut icon" type="image/png" href="media/geusje.png"/>
<link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">
<link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Raleway">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.3.1/css/all.css" xintegrity="sha384-mzrmE5qonljUremFsqc01SB46JvROS7bZs3IO2EmfFsd15uHvIt+Y8vEf7N7fWAU" crossorigin="anonymous">
<style>
html,body,h1,h2,h3,h4,h5 {font-family: "Raleway", sans-serif}
</style>
<body class="w3-light-grey">

<!-- Top container --><div class="w3-bar w3-top w3-black w3-large" style="z-index:4">
  <button class="w3-bar-item w3-button w3-hide-large w3-hover-none w3-hover-text-light-grey" onclick="w3_open();"><i class="fa fa-bars"></i>  Menu</button>
  <span class="w3-bar-item w3-right">De Geuzen Arnhem</span>
</div>

<!-- Sidebar/menu --><nav class="w3-sidebar w3-collapse w3-white w3-animate-left" style="z-index:3;width:200px;" id="mySidebar"><br>
  <div class="w3-container w3-row">
    <div class="w3-col s4">
      <img src="../media/geusje.png" class="w3-margin-right" style="width:46px">
    </div>
    <div class="w3-col s8 w3-bar">
      <span>Welkom, <strong><?php echo ucfirst(htmlspecialchars($vn)); ?></strong></span><br>
      <a href="index" class="w3-bar-item w3-button"><i class="fas fa-sign-out-alt"></i></a>
      <a href="functies?gpstoggle=true&return=instellingen" class="w3-bar-item w3-button <?php echo ($_SESSION['gps'] ?? 'false') == "true" ? "w3-green" : "w3-red"; ?>"><i class="fas fa-location-arrow"></i></a>
    </div>
  </div>
  <hr>
  <div class="w3-container">
    <h5>Dashboard</h5>
  </div>
  <div class="w3-bar-block">
    <a href="#" class="w3-bar-item w3-button w3-padding-16 w3-hide-large w3-dark-grey w3-hover-black" onclick="w3_close()" title="close menu"><i class="fa fa-remove fa-fw"></i>  Sluit Menu</a>
    <a href="../home" class="w3-bar-item w3-button w3-padding"><i class="fa fa-users fa-fw"></i>  Overzicht</a>
    <a href="../kaarten" class="w3-bar-item w3-button w3-padding"><i class="fas fa-map-marked-alt fa-fw"></i>  Kaarten</a>
    <a href="../hunts" class="w3-bar-item w3-button w3-padding"><i class="fas fa-map-marker-alt fa-fw"></i>  Hunt!</a>
    <a href="../vossen" class="w3-bar-item w3-button w3-padding"><i class="fas fa-bullseye fa-fw"></i>  Vossen</a>
    <a href="../nieuws" class="w3-bar-item w3-button w3-padding"><i class="far fa-newspaper fa-fw"></i>  Nieuws</a>
    <a href="../opdrachten" class="w3-bar-item w3-button w3-padding"><i class="far fa-bell fa-fw"></i>  Opdrachten</a>
    <a href="../hints" class="w3-bar-item w3-button w3-padding"><i class="fas fa-question-circle fa-fw"></i>  Hints</a>
    <a href="../punten" class="w3-bar-item w3-button w3-padding"><i class="fas fa-trophy fa-fw"></i>  Punten</a>
    <a href="../groepen" class="w3-bar-item w3-button w3-padding"><i class="fas fa-home fa-fw"></i>  Groepen</a>
    <a href="../instellingen" class="w3-bar-item w3-button w3-padding"><i class="fas fa-cog fa-fw"></i>  Instellingen</a>
    <?php if ($priv > 0){echo '<a href="../autos" class="w3-bar-item w3-button w3-padding"><i class="fas fa-car fa-fw"></i>  Auto\'s</a>';}?>
    <?php if ($priv > 1){echo '<a href="users" class="w3-bar-item w3-button w3-padding"><i class="fas fa-user-cog fa-fw"></i>  [Admin] Users</a>';} ?>
    <?php if ($priv > 1){echo '<a href="cronjobs" class="w3-bar-item w3-button w3-padding"><i class="fas fa-stopwatch fa-fw"></i>  [Admin] Cronjobs</a>';} ?>
    <?php if ($priv > 1){echo '<a href="database" class="w3-bar-item w3-button w3-padding"><i class="fas fa-database fa-fw"></i>  [Admin] Database</a>';} ?>
    <?php if ($priv > 2){echo '<a href="settings" class="w3-bar-item w3-button w3-padding w3-blue"><i class="fas fa-toolbox fa-fw"></i>  [Admin] Settings</a>';} ?>
    <br><br>
  </div>
</nav>

<!-- Overlay effect when opening sidebar on small screens --><div class="w3-overlay w3-hide-large w3-animate-opacity" onclick="w3_close()" style="cursor:pointer" title="close side menu" id="myOverlay"></div>

<!-- !PAGE CONTENT! --><div class="w3-main" style="margin-left:200px;margin-top:43px;">

  <!-- Header --><header class="w3-container" style="padding-top:22px">
    <h5><b><i class="fas fa-toolbox"></i> Site Instellingen</b></h5>
  </header>

  <div class="w3-container" style="margin-bottom:100px;">
    <?php if (!empty($succes_message)): ?>
      <div class="w3-panel w3-green w3-display-container w3-round-large">
        <span onclick="this.parentElement.style.display='none'" class="w3-button w3-large w3-display-topright">&times;</span>
        <p><?php echo $succes_message; ?></p>
      </div>
    <?php endif; ?>
    <?php if (!empty($error_message)): ?>
      <div class="w3-panel w3-red w3-display-container w3-round-large">
        <span onclick="this.parentElement.style.display='none'" class="w3-button w3-large w3-display-topright">&times;</span>
        <p><?php echo $error_message; ?></p>
      </div>
    <?php endif; ?>

    <!-- Card 1: Edit Existing Settings --><div class="w3-card-4 w3-white w3-margin-bottom">
      <div class="w3-container w3-blue-gray">
        <h5>Bewerk Bestaande Instellingen</h5>
      </div>
      <div class="w3-container w3-padding">
        <form method="POST" action="settings" class="w3-container">
          <input type="hidden" name="action" value="update_settings">
          <?php foreach ($settings as $setting): ?>
            <p>
              <label class="w3-text-grey">
                <b><?php echo htmlspecialchars($setting['Instelling']); ?></b>
                <br>
                <small><?php echo htmlspecialchars($setting['Omschrijving']); ?></small>
              </label>
              <div class="w3-row">
                  <div class="w3-col" style="width:calc(100% - 60px)">
                      <input class="w3-input w3-border w3-round-large" type="text" name="<?php echo htmlspecialchars($setting['Instelling']); ?>" value="<?php echo htmlspecialchars($setting['Waarde']); ?>" required>
                  </div>
                  <div class="w3-rest w3-right-align">
                      <button type="button" onclick="confirmDelete('<?php echo htmlspecialchars($setting['Instelling']); ?>')" class="w3-button w3-red w3-round-large w3-margin-left" title="Verwijder instelling">
                          <i class="fas fa-trash"></i>
                      </button>
                  </div>
              </div>
            </p>
          <?php endforeach; ?>
          <p>
            <button type="submit" class="w3-btn w3-blue w3-round-large w3-padding">Instellingen Opslaan</button>
          </p>
        </form>
      </div>
    </div>

    <!-- Card 2: Add New Setting --><div class="w3-card-4 w3-white">
      <div class="w3-container w3-blue-gray">
        <h5>Voeg Nieuwe Instelling Toe</h5>
      </div>
      <div class="w3-container w3-padding">
        <form method="POST" action="settings" class="w3-container">
          <input type="hidden" name="action" value="add_setting">
          <p>
            <label class="w3-text-grey"><b>Instelling Naam (Uniek)</b></label>
            <input class="w3-input w3-border w3-round-large" type="text" name="add_setting_name" placeholder="Bijv. joti_startdatum" required>
          </p>
          <p>
            <label class="w3-text-grey"><b>Waarde</b></label>
            <input class="w3-input w3-border w3-round-large" type="text" name="add_setting_value" placeholder="Bijv. 2023-10-13 18:00:00" required>
          </p>
          <p>
            <label class="w3-text-grey"><b>Omschrijving</b></label>
            <input class="w3-input w3-border w3-round-large" type="text" name="add_setting_description" placeholder="Korte omschrijving van deze instelling">
          </p>
          <p>
            <button type="submit" class="w3-btn w3-green w3-round-large w3-padding">Nieuwe Instelling Toevoegen</button>
          </p>
        </form>
      </div>
    </div>
  </div>

  <!-- Delete Confirmation Modal -->
  <div id="deleteModal" class="w3-modal">
    <div class="w3-modal-content w3-card-4 w3-animate-zoom" style="max-width:600px">
      <div class="w3-container w3-red">
        <span onclick="document.getElementById('deleteModal').style.display='none'" class="w3-button w3-display-topright w3-hover-red">&times;</span>
        <h4><i class="fas fa-exclamation-triangle"></i> Bevestig Verwijdering</h4>
      </div>
      <div class="w3-container w3-padding">
        <p id="deleteModalText"></p>
        <p><b>LET OP:</b> Het verwijderen van essentiële instellingen kan de werking van de website permanent verstoren!</p>
      </div>
      <div class="w3-container w3-padding w3-light-grey w3-right-align">
        <button type="button" class="w3-button w3-round-large w3-border" onclick="document.getElementById('deleteModal').style.display='none'">Annuleren</button>
        <a id="confirmDeleteButton" class="w3-button w3-red w3-round-large">Verwijderen</a>
      </div>
    </div>
  </div>

  <!-- Footer -->
  <?php require_once('../includes/footer.php') ?>

</div>

<script>
// Sidebar open/close logic
var mySidebar = document.getElementById("mySidebar");
var overlayBg = document.getElementById("myOverlay");

function w3_open() {
    if (mySidebar.style.display === 'block') {
        mySidebar.style.display = 'none';
        overlayBg.style.display = "none";
    } else {
        mySidebar.style.display = 'block';
        overlayBg.style.display = "block";
    }
}

function w3_close() {
    mySidebar.style.display = "none";
    overlayBg.style.display = "none";
}

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

      "<br>Longitude: " + position.coords.longitude);

      if (window.XMLHttpRequest) {

            // code for IE7+, Firefox, Chrome, Opera, Safari

            xmlhttp = new XMLHttpRequest();

        } else {

            // code for IE6, IE5

            xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");

        }

        xmlhttp.onreadystatechange = function() {

            if (this.readyState == 4 && this.status == 200) {

            }

        };

        xmlhttp.open("GET","functies.php?lat="+position.coords.latitude+"&lon="+position.coords.longitude,true);

        xmlhttp.send();
    }
 }

function showPosition(position) {
    var lat = position.coords.latitude;
    var lon = position.coords.longitude;
    
    var xhr = new XMLHttpRequest();
    xhr.open("GET", "functies.php?lat=" + lat + "&lon=" + lon, true);
    xhr.onload = function () {
        if (xhr.status === 200) {
            // Success
            console.log("GPS position sent.");
        } else {
            // Error
            console.error("Failed to send GPS position. Status: " + xhr.status);
        }
        // Redirect to settings.php to refresh messages or prevent resubmission
        window.location.href = "settings"; 
    };
    xhr.send();
}

// Show W3 modal for deleting a setting
function confirmDelete(settingName) {
    // Set the dynamic text in the modal
    document.getElementById('deleteModalText').innerHTML = "Weet je zeker dat je de instelling '<strong>" + settingName + "</strong>' wilt verwijderen?";
    
    // Set the href for the final delete button
    var deleteUrl = "settings?delete_setting=" + encodeURIComponent(settingName);
    document.getElementById('confirmDeleteButton').href = deleteUrl;

    // Show the modal
    document.getElementById('deleteModal').style.display = 'block';
}
</script>

</body>
</html>

