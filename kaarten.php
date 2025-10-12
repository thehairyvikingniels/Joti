<?php
define("PAGE_NAME", "kaarten");
session_start();

if (!isset($_SESSION['id'])){
  header("Location: index");
  exit(); 
}

require("dblogin.php");

$sql = "SELECT * FROM Gebruikers WHERE id='".$_SESSION['id']."'";
$result = mysqli_query($conn, $sql);

if (mysqli_num_rows($result) > 0) {
    while($row = mysqli_fetch_assoc($result)) {
      $vn = $row['voornaam'];
      $priv = $row['priv'];
    }
} else {
    echo "0 results";
    exit(); 
}

// Get global site settings
$sql = "SELECT * FROM Site_Instellingen";
$result = mysqli_query($conn, $sql);
$siteSettings = array();
if (mysqli_num_rows($result) > 0) {
    while($row = mysqli_fetch_assoc($result)) {
      $siteSettings[$row['Instelling']] = $row['Waarde'];
    }
} else {
    echo "0 results";
    exit(); 
}

// Check if there are any fox locations to enable the radius checkbox
$sql = "SELECT id FROM Voslocaties LIMIT 1";
$result = mysqli_query($conn, $sql);
$hasVoslocaties = (mysqli_num_rows($result) > 0);

?>
<!DOCTYPE html>
<html>
<title>Jotihunt - Kaarten</title>
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

<!-- Topbar -->
<?php include_once('includes/topbar.php') ?>

<!-- Sidebar -->
<?php include_once('includes/sidebar.php') ?>

<!-- !PAGE CONTENT! -->
<div class="w3-main" style="margin-left:200px;margin-top:43px;">

  <!-- Header -->
  <header class="w3-container" style="padding-top:22px">
    <h5><b><i class="fas fa-map-marked-alt"></i> Kaarten</b></h5>
  </header>

  <div class="w3-container">
    <div class="w3-card w3-white w3-padding">
        <form>
          <input type="checkbox" onchange="kaartveranderen()" id="groepen" name="groepen" value="true"><label for="groepen" class="w3-margin-left">Groepen</label>
          <input type="checkbox" onchange="kaartveranderen()" id="personen" name="personen" value="true" class="w3-margin-left"><label for="personen" class="w3-margin-left">Personen</label>
          <input type="checkbox" onchange="kaartveranderen()" id="hints" name="hints" value="true" class="w3-margin-left"><label for="hints" class="w3-margin-left">Vossen (locaties)</label>
          <br class="w3-hide-large w3-hide-medium"> <!-- Break line on smaller screens -->
          <input type="checkbox" onchange="kaartveranderen()" id="vossenpad" name="vossenpad" value="true" class="w3-margin-left"><label for="vossenpad" class="w3-margin-left">Vossenpad</label>
          <input type="checkbox" onchange="kaartveranderen()" id="predicted_route" name="predicted_route" value="true" class="w3-margin-left"><label for="predicted_route" class="w3-margin-left">Voorspelde Route</label>
          <input type="checkbox" onchange="kaartveranderen()" id="zoekcirkel" name="zoekcirkel" value="true" <?php if (!$hasVoslocaties) echo 'disabled'; ?> class="w3-margin-left"><label for="zoekcirkel" class="w3-margin-left">Zoekcirkel</label>
        </form>
    </div>
    <div class="w3-card w3-white w3-margin-top" style="width:100%; height:73vh">
      <iframe id="iframe01" src="maps.php?groepen=false&personen=false&hints=false&vossenpad=false&predicted_route=false&zoekcirkel=false" style="width:100%; height:100%; border:0;"></iframe>
    </div>
  </div>

  <!-- Footer -->
  <?php require_once('includes/footer.php') ?>

  <!-- End page content -->
</div>

<script>
function kaartveranderen() {
  const checkboxes = ['groepen', 'personen', 'hints', 'vossenpad', 'predicted_route', 'zoekcirkel'];
  const params = checkboxes.map(id => {
    const el = document.getElementById(id);
    return `${id}=${el.checked ? 'true' : 'false'}`;
  }).join('&');
  
  document.getElementById('iframe01').src = `maps.php?${params}`;
}
  
// GPS Functions
if ("<?php echo $_SESSION['gps'] ?? 'false' ?>" == "true"){
  setInterval(GPSrefresh, 5555);
}

function GPSrefresh() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(showPosition);
    } else {
        console.log("Geolocation is not supported by this browser.");
    }
}

function showPosition(position) {
    console.log("Latitude: " + position.coords.latitude + ", Longitude: " + position.coords.longitude);
    const xmlhttp = new XMLHttpRequest();
    xmlhttp.open("GET", `functies.php?lat=${position.coords.latitude}&lon=${position.coords.longitude}`, true);
    xmlhttp.send();
}
</script>

</body>
</html>

