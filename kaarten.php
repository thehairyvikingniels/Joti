<?php
define("PAGE_NAME", "kaarten");
session_start();

if (!isset($_SESSION['id'])){
  header("Location: index");
  exit(); 
}

require("dblogin.php");

// Get userdata
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

// Get global site settings
$stmt = $conn->prepare("SELECT * FROM Site_Instellingen");
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $siteSettings[$row['Instelling']] = $row['Waarde'];
    }
}
$stmt->close();


// Check if there are any fox locations within the last 24 hours to enable the radius checkbox
$stmt = $conn->prepare("SELECT id FROM Voslocaties WHERE ingestuurd_op >= NOW() - INTERVAL 24 HOUR LIMIT 1");
$stmt->execute();
$result = $stmt->get_result();

$hasVoslocaties = ($result->num_rows > 0);
$stmt->close();

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

.map-view-wrapper {
    position: relative;
    height: calc(100vh - 43px); /* Full viewport height minus topbar */
}
#iframe01 {
    width: 100%;
    height: 100%;
    border: 0;
}

#fullscreen-button {
    position: absolute;
    top: 10px;
    right: 10px;
    z-index: 10;
}

.menu-trigger-bar {
    position: absolute;
    top: 10px;
    left: 10px;
    z-index: 10;
    display: flex;
    flex-direction: column;
}

.menu-trigger {
    background-color: rgba(255, 255, 255, 0.9);
    border-radius: 8px;
    margin-bottom: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

.floating-panel {
    position: absolute;
    top: 10px;
    left: 70px; /* Position next to the trigger icons */
    z-index: 9;
    width: 300px;
    max-height: calc(100% - 20px);
    overflow-y: auto;
    background-color: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(5px);
    display: none; /* Hidden by default */
    border-radius: 8px;
}

/* Modern Toggle Switch Styles */
.switch { position: relative; display: inline-block; width: 50px; height: 24px; }
.switch input { opacity: 0; width: 0; height: 0; }
.slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 24px; }
.slider:before { position: absolute; content: ""; height: 16px; width: 16px; left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%; }
input:checked + .slider { background-color: #2196F3; }
input:focus + .slider { box-shadow: 0 0 1px #2196F3; }
input:checked + .slider:before { transform: translateX(26px); }
.control-label { cursor: pointer; flex-grow: 1; }
input:disabled + .slider { background-color: #ddd; cursor: not-allowed; }
.w3-ul li { cursor: pointer; }
.w3-check-label { cursor: pointer; }

/* Mobile adjustments */
@media (max-width: 992px) {
    .w3-main {
        margin-left: 0 !important;
    }
    .menu-trigger-bar {
        flex-direction: row;
        left: 50%;
        transform: translateX(-50%);
    }
    .menu-trigger {
        margin-bottom: 0;
        margin-right: 8px;
    }
    .floating-panel {
        top: 60px;
        left: 50%;
        transform: translateX(-50%);
        width: calc(100% - 20px);
    }
}
</style>
<body class="w3-light-grey">

<!-- Topbar -->
<?php include_once('includes/topbar.php') ?>

<!-- Sidebar -->
<?php include_once('includes/sidebar.php') ?>

<!-- !PAGE CONTENT! -->
<div class="w3-main" style="margin-left:200px;margin-top:43px;">

  <div class="map-view-wrapper">
    <iframe id="iframe01" src=""></iframe>
    
    <div class="menu-trigger-bar">
        <!-- Layers Menu Trigger -->
        <button class="w3-button w3-round-large menu-trigger" onclick="togglePanel('layers-panel')"><i class="fa fa-layer-group"></i></button>
        <!-- Filters Menu Trigger -->
        <button class="w3-button w3-round-large menu-trigger" onclick="togglePanel('filters-panel')"><i class="fa fa-filter"></i></button>
    </div>

    <!-- Layers Panel -->
    <div id="layers-panel" class="w3-card-4 floating-panel">
        <header class="w3-container w3-blue">
            <h4 class="w3-display-container">Kaart Lagen <span onclick="togglePanel('layers-panel')" class="w3-button w3-display-right">&times;</span></h4>
        </header>
        <div class="w3-container w3-padding">
            <h5>Algemeen</h5>
            <ul class="w3-ul w3-border-0">
                <li class="w3-padding-small" onclick="toggleCheckbox('groepen');"><div class="w3-cell-row"><div class="w3-cell w3-cell-middle control-label">Groepen</div><div class="w3-cell w3-cell-middle w3-right-align"><label class="switch"><input type="checkbox" id="groepen" onchange="kaartveranderen()"><span class="slider"></span></label></div></div></li>
                <li class="w3-padding-small" onclick="toggleCheckbox('personen');"><div class="w3-cell-row"><div class="w3-cell w3-cell-middle control-label">Personen</div><div class="w3-cell w3-cell-middle w3-right-align"><label class="switch"><input type="checkbox" id="personen" onchange="kaartveranderen()"><span class="slider"></span></label></div></div></li>
                <li class="w3-padding-small" onclick="toggleCheckbox('autos');"><div class="w3-cell-row"><div class="w3-cell w3-cell-middle control-label">Auto's</div><div class="w3-cell w3-cell-middle w3-right-align"><label class="switch"><input type="checkbox" id="autos" onchange="kaartveranderen()"><span class="slider"></span></label></div></div></li>
            </ul>
            <h5 class="w3-margin-top">Vossen</h5>
            <ul class="w3-ul w3-border-0">
                <li class="w3-padding-small" onclick="toggleCheckbox('hints');"><div class="w3-cell-row"><div class="w3-cell w3-cell-middle control-label">Vossen (locaties)</div><div class="w3-cell w3-cell-middle w3-right-align"><label class="switch"><input type="checkbox" id="hints" onchange="kaartveranderen()" checked><span class="slider"></span></label></div></div></li>
                <li class="w3-padding-small" onclick="toggleCheckbox('vossenpad');"><div class="w3-cell-row"><div class="w3-cell w3-cell-middle control-label">Vossenpad</div><div class="w3-cell w3-cell-middle w3-right-align"><label class="switch"><input type="checkbox" id="vossenpad" onchange="kaartveranderen()"><span class="slider"></span></label></div></div></li>
                <li class="w3-padding-small" onclick="toggleCheckbox('predicted_route');"><div class="w3-cell-row"><div class="w3-cell w3-cell-middle control-label">Voorspelde Route</div><div class="w3-cell w3-cell-middle w3-right-align"><label class="switch"><input type="checkbox" id="predicted_route" onchange="kaartveranderen()"><span class="slider"></span></label></div></div></li>
                <li class="w3-padding-small" <?php if (!$hasVoslocaties) echo 'style="cursor:not-allowed;color:#aaa;"'; ?> onclick="toggleCheckbox('zoekcirkel');"><div class="w3-cell-row"><div class="w3-cell w3-cell-middle control-label">Zoekcirkel</div><div class="w3-cell w3-cell-middle w3-right-align"><label class="switch"><input type="checkbox" id="zoekcirkel" onchange="kaartveranderen()" <?php if (!$hasVoslocaties) echo 'disabled'; ?>><span class="slider"></span></label></div></div></li>
            </ul>
        </div>
    </div>
    
    <!-- Filters Panel -->
    <div id="filters-panel" class="w3-card-4 floating-panel">
        <header class="w3-container w3-blue-grey">
            <h4 class="w3-display-container">Filters <span onclick="togglePanel('filters-panel')" class="w3-button w3-display-right">&times;</span></h4>
        </header>
        <div class="w3-container w3-padding">
            <h5>Spelhelft</h5>
            <p><input class="w3-check" type="checkbox" id="helft1" onchange="kaartveranderen()" checked><label for="helft1" class="w3-check-label"> Eerste helft</label></p>
            <p><input class="w3-check" type="checkbox" id="helft2" onchange="kaartveranderen()" checked><label for="helft2" class="w3-check-label"> Tweede helft</label></p>
            <h5 class="w3-margin-top">Deelgebieden</h5>
            <?php
              $teams = $vossen_names;
              foreach($teams as $team) {
                  echo "<p><input class='w3-check team-filter' type='checkbox' id='".strtolower($team)."' onchange='kaartveranderen()' checked><label for='".strtolower($team)."' class='w3-check-label'> ".ucfirst($team)."</label></p>";
              }
            ?>
        </div>
    </div>

    <button id="fullscreen-button" class="w3-button w3-white w3-card-4 w3-round-large" onclick="toggleFullScreen()"><i id="fullscreen-icon" class="fa fa-expand"></i></button>
  </div>

</div>

<script>
let lastKnownMapState = null;

window.addEventListener("message", (event) => {
    // A security check for the origin of the message would be a good practice
    if (event.data && event.data.type === 'mapUpdate') {
        lastKnownMapState = event.data.state;
    }
}, false);

function kaartveranderen() {
  const layers = ['groepen', 'personen', 'autos', 'hints', 'vossenpad', 'predicted_route', 'zoekcirkel'];
  const params = layers.map(id => `${id}=${document.getElementById(id).checked}`);
  
  params.push(`helft1=${document.getElementById('helft1').checked}`);
  params.push(`helft2=${document.getElementById('helft2').checked}`);
  
  const teams = Array.from(document.querySelectorAll('.team-filter:checked')).map(el => el.id);
  if(teams.length > 0) {
      params.push(`teams=${teams.join(',')}`);
  }
  
  if(lastKnownMapState) {
      params.push(`lon=${lastKnownMapState.lon}`);
      params.push(`lat=${lastKnownMapState.lat}`);
      params.push(`zoom=${lastKnownMapState.zoom}`);
  }

  document.getElementById('iframe01').src = `maps.php?${params.join('&')}`;
}

function toggleCheckbox(id) {
    const checkbox = document.getElementById(id);
    if (!checkbox.disabled) {
        checkbox.checked = !checkbox.checked;
        kaartveranderen();
    }
}

function togglePanel(panelId) {
    const panel = document.getElementById(panelId);
    if (panel.style.display === 'block') {
        panel.style.display = 'none';
    } else {
        if(panelId === 'layers-panel') document.getElementById('filters-panel').style.display = 'none';
        if(panelId === 'filters-panel') document.getElementById('layers-panel').style.display = 'none';
        panel.style.display = 'block';
    }
}

function toggleFullScreen() {
    const elem = document.querySelector(".map-view-wrapper");
    if (!document.fullscreenElement) {
        elem.requestFullscreen().catch(err => alert(`Error: ${err.message}`));
    } else {
        document.exitFullscreen();
    }
}

document.addEventListener('fullscreenchange', () => {
    const icon = document.getElementById('fullscreen-icon');
    if (!document.fullscreenElement) {
        icon.classList.remove('fa-compress');
        icon.classList.add('fa-expand');
    } else {
        icon.classList.remove('fa-expand');
        icon.classList.add('fa-compress');
    }
});

window.onload = function() {
  kaartveranderen();
};

if ("<?php echo $_SESSION['gps'] ?? 'false' ?>" == "true"){
  setInterval(GPSrefresh, 5555);
}

function GPSrefresh() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(showPosition);
    }
}

function showPosition(position) {
    const xmlhttp = new XMLHttpRequest();
    xmlhttp.open("GET", `functies.php?lat=${position.coords.latitude}&lon=${position.coords.longitude}`, true);
    xmlhttp.send();
}
</script>

</body>
</html>

