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

if (!isset($priv) || $priv < 1) {
    header("Location: home");
    exit();
}

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
<html lang="nl">
<head>
<title>Jotify - Kaarten</title>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="shortcut icon" type="image/png" href="media/geusje.png"/>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://kit.fontawesome.com/870ab34ea3.js" crossorigin="anonymous"></script>
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<?php include_once('includes/theme.php'); ?>
<style>
.map-view-wrapper {
    position: relative;
    height: calc(100vh - 64px); /* Full viewport height minus topbar */
    width: 100%;
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
    gap: 8px;
}

.floating-panel {
    position: absolute;
    top: 10px;
    left: 70px;
    z-index: 9;
    width: 320px;
    max-height: calc(100% - 20px);
    overflow-y: auto;
    display: none;
}

/* Modern Toggle Switch Styles */
.switch { position: relative; display: inline-block; width: 44px; height: 24px; }
.switch input { opacity: 0; width: 0; height: 0; }
.slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .4s; border-radius: 24px; }
.slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; box-shadow: 0 1px 3px rgba(0,0,0,0.3); }
input:checked + .slider { background-color: #3b82f6; }
input:focus + .slider { box-shadow: 0 0 1px #3b82f6; }
input:checked + .slider:before { transform: translateX(20px); }
.control-label { cursor: pointer; flex-grow: 1; font-size: 0.9rem; font-weight: 500; }
input:disabled + .slider { background-color: #e2e8f0; cursor: not-allowed; }

/* Mobile adjustments */
@media (max-width: 992px) {
    .menu-trigger-bar {
        flex-direction: row;
        left: 50%;
        transform: translateX(-50%);
    }
    .floating-panel {
        top: 60px;
        left: 50%;
        transform: translateX(-50%);
        width: calc(100% - 20px);
    }
}
</style>
</head>
<body class="flex h-screen overflow-hidden">

<!-- Sidebar -->
<?php include_once('includes/sidebar.php') ?>

<!-- Main Content -->
<div class="flex-1 flex flex-col h-screen overflow-hidden w-full relative">
  <!-- Topbar -->
  <?php include_once('includes/topbar.php') ?>

  <div class="map-view-wrapper flex-1">
    <iframe id="iframe01" src=""></iframe>
    
    <div class="menu-trigger-bar">
        <!-- Layers Menu Trigger -->
        <button class="bg-white/90 backdrop-blur text-gray-800 hover:text-blue-600 p-3 rounded shadow-md transition" onclick="togglePanel('layers-panel')"><i class="fa fa-layer-group text-xl"></i></button>
        <!-- Filters Menu Trigger -->
        <button class="bg-white/90 backdrop-blur text-gray-800 hover:text-blue-600 p-3 rounded shadow-md transition" onclick="togglePanel('filters-panel')"><i class="fa fa-filter text-xl"></i></button>
    </div>

    <!-- Layers Panel -->
    <div id="layers-panel" class="theme-card rounded border shadow-lg floating-panel overflow-hidden">
        <header class="px-4 py-3 flex justify-between items-center text-white" style="background-color: var(--theme-sidebar-active);">
            <h4 class="font-bold">Kaart Lagen</h4>
            <button onclick="togglePanel('layers-panel')" class="text-white hover:opacity-80 transition text-xl leading-none">&times;</button>
        </header>
        <div class="p-4">
            <h5 class="text-sm font-bold uppercase tracking-wider opacity-60 mb-3">Algemeen</h5>
            <ul class="space-y-3 mb-6">
                <li class="flex justify-between items-center cursor-pointer hover:bg-black/5 p-2 rounded -mx-2 transition" onclick="toggleCheckbox('groepen');">
                  <span class="control-label">Groepen</span>
                  <label class="switch" onclick="event.stopPropagation()"><input type="checkbox" id="groepen" onchange="kaartveranderen()"><span class="slider"></span></label>
                </li>
                <li class="flex justify-between items-center cursor-pointer hover:bg-black/5 p-2 rounded -mx-2 transition" onclick="toggleCheckbox('personen');">
                  <span class="control-label">Personen</span>
                  <label class="switch" onclick="event.stopPropagation()"><input type="checkbox" id="personen" onchange="kaartveranderen()"><span class="slider"></span></label>
                </li>
                <li class="flex justify-between items-center cursor-pointer hover:bg-black/5 p-2 rounded -mx-2 transition" onclick="toggleCheckbox('autos');">
                  <span class="control-label">Auto's</span>
                  <label class="switch" onclick="event.stopPropagation()"><input type="checkbox" id="autos" onchange="kaartveranderen()"><span class="slider"></span></label>
                </li>
            </ul>
            
            <h5 class="text-sm font-bold uppercase tracking-wider opacity-60 mb-3">Vossen</h5>
            <ul class="space-y-3">
                <li class="flex justify-between items-center cursor-pointer hover:bg-black/5 p-2 rounded -mx-2 transition" onclick="toggleCheckbox('hints');">
                  <span class="control-label">Vossen (locaties)</span>
                  <label class="switch" onclick="event.stopPropagation()"><input type="checkbox" id="hints" onchange="kaartveranderen()" checked><span class="slider"></span></label>
                </li>
                <li class="flex justify-between items-center cursor-pointer hover:bg-black/5 p-2 rounded -mx-2 transition" onclick="toggleCheckbox('vossenpad');">
                  <span class="control-label">Vossenpad</span>
                  <label class="switch" onclick="event.stopPropagation()"><input type="checkbox" id="vossenpad" onchange="kaartveranderen()"><span class="slider"></span></label>
                </li>
                <li class="flex justify-between items-center cursor-pointer hover:bg-black/5 p-2 rounded -mx-2 transition" onclick="toggleCheckbox('predicted_route');">
                  <span class="control-label">Voorspelde Route</span>
                  <label class="switch" onclick="event.stopPropagation()"><input type="checkbox" id="predicted_route" onchange="kaartveranderen()"><span class="slider"></span></label>
                </li>
                <li class="flex justify-between items-center cursor-pointer hover:bg-black/5 p-2 rounded -mx-2 transition" <?php if (!$hasVoslocaties) echo 'style="cursor:not-allowed;opacity:0.5;"'; ?> onclick="toggleCheckbox('zoekcirkel');">
                  <span class="control-label">Zoekcirkel</span>
                  <label class="switch" onclick="event.stopPropagation()"><input type="checkbox" id="zoekcirkel" onchange="kaartveranderen()" <?php if (!$hasVoslocaties) echo 'disabled'; ?>><span class="slider"></span></label>
                </li>
            </ul>
        </div>
    </div>
    
    <!-- Filters Panel -->
    <div id="filters-panel" class="theme-card rounded border shadow-lg floating-panel overflow-hidden">
        <header class="px-4 py-3 flex justify-between items-center bg-gray-600 text-white">
            <h4 class="font-bold">Filters</h4>
            <button onclick="togglePanel('filters-panel')" class="text-white hover:opacity-80 transition text-xl leading-none">&times;</button>
        </header>
        <div class="p-4">
            <h5 class="text-sm font-bold uppercase tracking-wider opacity-60 mb-3">Spelhelft</h5>
            <div class="space-y-2 mb-6">
              <label class="flex items-center space-x-3 cursor-pointer">
                <input type="checkbox" id="helft1" onchange="kaartveranderen()" checked class="w-4 h-4 text-blue-600 rounded focus:ring-blue-500">
                <span class="font-medium text-sm">Eerste helft</span>
              </label>
              <label class="flex items-center space-x-3 cursor-pointer">
                <input type="checkbox" id="helft2" onchange="kaartveranderen()" checked class="w-4 h-4 text-blue-600 rounded focus:ring-blue-500">
                <span class="font-medium text-sm">Tweede helft</span>
              </label>
            </div>

            <h5 class="text-sm font-bold uppercase tracking-wider opacity-60 mb-3">Deelgebieden</h5>
            <div class="space-y-2">
            <?php
              $teams = array("Alpha","Bravo","Charlie","Delta","Echo","Foxtrot", "Golf", "Hotel");
              foreach($teams as $team) {
                  echo '<label class="flex items-center space-x-3 cursor-pointer">';
                  echo '<input type="checkbox" id="'.strtolower($team).'" onchange="kaartveranderen()" checked class="w-4 h-4 team-filter rounded text-blue-600 focus:ring-blue-500">';
                  echo '<span class="font-medium text-sm">'.ucfirst($team).'</span>';
                  echo '</label>';
              }
            ?>
            </div>
        </div>
    </div>

    <button id="fullscreen-button" class="bg-white/90 backdrop-blur text-gray-800 hover:text-blue-600 w-10 h-10 flex items-center justify-center rounded shadow-md transition" onclick="toggleFullScreen()"><i id="fullscreen-icon" class="fa fa-expand"></i></button>
  </div>

</div>

<script>
const savedMapSettings = <?php echo isset($_SESSION['map_settings']) ? json_encode($_SESSION['map_settings']) : 'null'; ?>;
let lastKnownMapState = savedMapSettings && savedMapSettings.mapState ? savedMapSettings.mapState : null;
let mapSaveTimeout;

window.addEventListener("message", (event) => {
    if (event.data && event.data.type === 'mapUpdate') {
        lastKnownMapState = event.data.state;
        clearTimeout(mapSaveTimeout);
        mapSaveTimeout = setTimeout(() => {
            saveMapSettings();
        }, 1000);
    }
}, false);

function saveMapSettings() {
  const layers = ['groepen', 'personen', 'autos', 'hints', 'vossenpad', 'predicted_route', 'zoekcirkel'];
  const teams = Array.from(document.querySelectorAll('.team-filter:checked')).map(el => el.id);
  const savePayload = {
      checkboxes: {
          'helft1': document.getElementById('helft1').checked,
          'helft2': document.getElementById('helft2').checked
      },
      teams: teams,
      mapState: lastKnownMapState
  };
  layers.forEach(id => {
      const el = document.getElementById(id);
      if (el) savePayload.checkboxes[id] = el.checked;
  });
  
  fetch('functies.php?save_map_settings=1', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(savePayload)
  }).catch(e => console.error(e));
}

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
  saveMapSettings();
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
  if (savedMapSettings) {
      if (savedMapSettings.checkboxes) {
          Object.keys(savedMapSettings.checkboxes).forEach(id => {
              const el = document.getElementById(id);
              if (el) el.checked = savedMapSettings.checkboxes[id];
          });
      }
      if (savedMapSettings.teams) {
          document.querySelectorAll('.team-filter').forEach(el => {
              el.checked = savedMapSettings.teams.includes(el.id);
          });
      }
  }
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

