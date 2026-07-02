<?php
define("PAGE_NAME", "autos");
session_start();

if (!isset($_SESSION['id'])){
  header("Location: index");
  exit();
}

require("dblogin.php");

// Auto verwijderen
if (isset($_GET['delauto'])){
  if ($_SESSION['priv'] > 1) {
    $stmt_del = $conn->prepare("DELETE FROM Auto WHERE kenteken=?");
    $stmt_del->bind_param("s", $_GET['delauto']);
  } else {
    $stmt_del = $conn->prepare("DELETE FROM Auto WHERE kenteken=? AND eigenaar=?");
    $stmt_del->bind_param("si", $_GET['delauto'], $_SESSION['id']);
  }
  
  if ($stmt_del->execute()) {
    $stmt_del->close();
    header("Location: autos");
    exit();
  } else {
    echo "Error updating record: " . $stmt_del->error;
    $stmt_del->close();
  }
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


// Auto toevoegen
if (isset($_POST['kenteken'])){
  $stmt_ins = $conn->prepare("INSERT INTO Auto (eigenaar, kenteken) VALUES (?, ?) ON DUPLICATE KEY UPDATE eigenaar = eigenaar");
  $stmt_ins->bind_param("is", $_SESSION['id'], $_POST['kenteken']);
  
  if (!$stmt_ins->execute()) {
    echo "Error: " . $stmt_ins->error;
  }
  $stmt_ins->close();
}


// Gebruikersgegevens ophalen
$stmt = $conn->prepare("SELECT * FROM Gebruikers WHERE id=?");
$stmt->bind_param("i", $_SESSION['id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
      $vn = $row['voornaam'];
      $priv = $row['priv'];
    }
}
$stmt->close();


// In of uitstappen als bijrijder
if (isset($_POST['carid'])) {
  if ($_POST['carid'] === "geen") {
    $stmt_bijr = $conn->prepare("DELETE FROM Auto_Bijrijders WHERE gebruiker_id = ?");
    $stmt_bijr->bind_param("i", $_SESSION['id']);
    $stmt_bijr->execute();
    $stmt_bijr->close();
  } else {
    // We geven carid twee keer mee: één keer voor VALUES en één keer voor ON DUPLICATE KEY UPDATE
    $stmt_bijr = $conn->prepare("INSERT INTO Auto_Bijrijders (auto, gebruiker_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE auto = ?");
    $stmt_bijr->bind_param("sis", $_POST['carid'], $_SESSION['id'], $_POST['carid']);
    $stmt_bijr->execute();
    $stmt_bijr->close();
  }
}

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

<link rel="stylesheet" href="includes/numberPlate.css">
<style>
html,body,h1,h2,h3,h4,h5 {font-family: "Raleway", sans-serif}
</style>
<body class="w3-light-grey">

<?php include_once('includes/topbar.php') ?>

<?php include_once('includes/sidebar.php') ?>

<div class="w3-main" style="margin-left:200px;margin-top:43px;">

  <header class="w3-container" style="padding-top:22px">
    <h5><b><i class="fas fa-car fa-fw"></i>Auto's</b></h5>
    <div class="w3-row">
      <div class="w3-col l6 m12 s12 w3-padding">
        <div class="w3-card-4 w3-white w3-padding">
          <h5>Auto aanmaken</h5>
          <form method="POST">    
            <center>
              <div class="form-control">
                <div class="car-license">
                  <abbr title="Netherlands" class="car-license__country-code">
                    <svg class="svg" viewBox="0 0 300 300" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="20" height="20"
                          aria-labelledby="euSymbol" role="img">
                            <title id="euSymbol">EU symbol</title>
                      <g id="s" transform="translate(150,30)" fill="#fc0">
                        <g id="c">
                          <path id="t" d="M 0,-20 V 0 H 10" transform="rotate(18 0,-20)"/>
                          <use xlink:href="#t" transform="scale(-1,1)"/>
                        </g>
                        <use xlink:href="#c" transform="rotate(72)"/>
                        <use xlink:href="#c" transform="rotate(144)"/>
                        <use xlink:href="#c" transform="rotate(216)"/>
                        <use xlink:href="#c" transform="rotate(288)"/>
                      </g>
                      <use xlink:href="#s" transform="rotate(30 150,150) rotate(330 150,30)"/>
                      <use xlink:href="#s" transform="rotate(60 150,150) rotate(300 150,30)"/>
                      <use xlink:href="#s" transform="rotate(90 150,150) rotate(270 150,30)"/>
                      <use xlink:href="#s" transform="rotate(120 150,150) rotate(240 150,30)"/>
                      <use xlink:href="#s" transform="rotate(150 150,150) rotate(210 150,30)"/>
                      <use xlink:href="#s" transform="rotate(180 150,150) rotate(180 150,30)"/>
                      <use xlink:href="#s" transform="rotate(210 150,150) rotate(150 150,30)"/>
                      <use xlink:href="#s" transform="rotate(240 150,150) rotate(120 150,30)"/>
                      <use xlink:href="#s" transform="rotate(270 150,150) rotate(90 150,30)"/>
                      <use xlink:href="#s" transform="rotate(300 150,150) rotate(60 150,30)"/>
                      <use xlink:href="#s" transform="rotate(330 150,150) rotate(30 150,30)"/>
                    </svg>
                    <span>NL</span>
                  </abbr>
                    <div class="car-license__form-control">
                      <input type="text" class="car-license__input" id="input-kenteken" maxlength="8" autocomplete="off" name="kenteken" default="GE-LU-KT">  
                      <span class="valid-message"></span>
                    </div>
                </div>
              </div>
            </center>
            <center><button id="kentekenKnop" class="w3-button w3-disabled w3-ripple w3-margin w3-green" disabled>Aanmaken</button></center>
          </form>
          <hr>
          <table class="w3-table-all" style="width:100%">
            <tr class="w3-hide-small w3-hide-medium">
              <th>Kenteken</th>
              <th>Inzittenden</th>
              <th>Eigenaar</th>
              <th></th>
            </tr>
            <?php
            $sql = "
            SELECT 
              CONCAT(UPPER(SUBSTRING(ge.voornaam,1,1)),LOWER(SUBSTRING(ge.voornaam,2))) as eigenaar,
              ge.id as id,
              a.kenteken as kenteken,
              GROUP_CONCAT(CONCAT(UPPER(SUBSTRING(gb.voornaam,1,1)),LOWER(SUBSTRING(gb.voornaam,2))) SEPARATOR ', ') as inzittenden
            FROM Auto a
            LEFT JOIN Auto_Bijrijders ab
              on a.kenteken = ab.auto
            LEFT JOIN Gebruikers gb
              on gb.id = ab.gebruiker_id
            LEFT JOIN Gebruikers ge
              on ge.id = a.eigenaar
            GROUP BY a.kenteken
            ";
            
            $stmt_table = $conn->prepare($sql);
            $stmt_table->execute();
            $result_table = $stmt_table->get_result();

            if ($result_table->num_rows > 0) {
              // output data of each row
              while($row = $result_table->fetch_assoc()) {
                echo "<tr>";
                echo "  <td>".strtoupper(htmlspecialchars($row['kenteken']))."</td>";
                echo "  <td class='w3-hide-small w3-hide-medium'>".htmlspecialchars($row['inzittenden'])."</td>";
                echo "  <td>".htmlspecialchars($row['eigenaar'])."</td>";
                if ($_SESSION['id'] == $row['id']){
                  echo " <td class='w3-right'><a href='autos?delauto=".urlencode($row['kenteken'])."'><i class=\"fas fa-trash\"></i></a></td>";
                } else {
                  echo "<td></td>";
                }
                echo "</tr>";
                echo "<tr class='w3-hide-large'>";
                echo "  <td colspan='4'>".htmlspecialchars($row['inzittenden'])."</td>";
                echo "</tr>";
              }
            }
            $stmt_table->close();
            ?>
          </table>
        </div>
      </div>
      <div class="w3-col l6 m6 s12 w3-padding">
        <div class="w3-card-4 w3-white w3-padding">
          <h5>Stap in / uit</h5>
          <form method="POST">
            <select class="w3-select" name="carid">
              <option value="geen" selected>Geen</option>
              <?php
              $sql_drop = "SELECT a.kenteken, a.eigenaar, b.voornaam FROM Auto as a INNER JOIN Gebruikers as b ON a.eigenaar = b.id ORDER BY b.voornaam ASC";
              $stmt_drop = $conn->prepare($sql_drop);
              $stmt_drop->execute();
              $result_drop = $stmt_drop->get_result();
              
              if ($result_drop->num_rows > 0) {
                while($row = $result_drop->fetch_assoc()) {
                  echo "<option value=\"".htmlspecialchars($row['kenteken'])."\">Auto ".htmlspecialchars($row['kenteken'])." (".ucfirst(htmlspecialchars($row['voornaam'])).")</option>";
                }
              }
              $stmt_drop->close();
            ?>
            </select>  
            <center><button type="submit" class="w3-button w3-green w3-margin">Vroem!</button></center>
          </form>
        </div>
      </div>
    </div>
  </header>

  <?php require_once('includes/footer.php') ?>

  </div>

<script type="text/javascript" src="includes/numberPlate.js"></script>
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
               // Succes logica
            }
        };
        xmlhttp.open("GET","functies.php?lat="+position.coords.latitude+"&lon="+position.coords.longitude,true);
        xmlhttp.send();
    }
 } 
</script>

</body>
</html>