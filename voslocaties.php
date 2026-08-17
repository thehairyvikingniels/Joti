<?php
define("PAGE_NAME", "voslocaties");

session_start();

if (!isset($_SESSION['id'])) {
    header("Location: index");
    exit();
}

require("dblogin.php");
require_once("functies.php");

$message = '';

/**
 * Converts RD (Rijksdriehoekstelsel) coordinates to WGS84 (Latitude/Longitude).
 * Provides an approximation based on the formulas from the Dutch Kadaster.
 *
 * @param float $rd_x The RD X-coordinate.
 * @param float $rd_y The RD Y-coordinate.
 * @return array An associative array containing 'lat' and 'lon'.
 */
function convertRDtoLatLon($rd_x, $rd_y) {
    $X0 = 155000;
    $Y0 = 463000;
    $phi0 = 52.15517440;
    $lambda0 = 5.38720621;

    $dx = ($rd_x - $X0) * 1E-5;
    $dy = ($rd_y - $Y0) * 1E-5;

    $sum_lat = (3235.65389 * $dy) + (-32.58297 * pow($dx, 2)) + (-0.2475 * pow($dy, 2)) + (-0.84978 * pow($dx, 2) * $dy) + (-0.0655 * pow($dy, 3)) + (-0.01709 * pow($dx, 2) * pow($dy, 2)) + (-0.00738 * $dx) + (0.0053 * pow($dx, 4)) + (-0.00039 * pow($dx, 2) * pow($dy, 3)) + (0.00033 * pow($dx, 4) * $dy) + (-0.00012 * $dx * $dy);

    $sum_lon = (5260.52916 * $dx) + (105.94684 * $dx * $dy) + (2.45656 * $dx * pow($dy, 2)) + (-0.81885 * pow($dx, 3)) + (0.05594 * $dx * pow($dy, 3)) + (-0.05607 * pow($dx, 3) * $dy) + (0.01199 * $dy) + (-0.00256 * pow($dx, 3) * pow($dy, 2)) + (0.00128 * $dx * pow($dy, 4)) + (0.00022 * pow($dy, 2)) + (-0.00022 * pow($dx, 2)) + (0.00026 * pow($dx, 5));

    $lat = $phi0 + $sum_lat / 3600;
    $lon = $lambda0 + $sum_lon / 3600;

    return ['lat' => $lat, 'lon' => $lon];
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_voslocatie'])) {
    // Sanitize and retrieve form data
    $coord_type = $_POST['coord_type'] ?? '';
    $type = $_POST['type'] ?? '';
    $deelgebied = $_POST['deelgebied'] ?? '';
    $datumtijd_str = $_POST['datumtijd'] ?? '';
    $code = $_POST['code'] ?? null;
    $opmerking = $_POST['opmerking'] ?? null;
    $ingeleverd_door = $_SESSION['id'];

    $lat = 0.0;
    $lon = 0.0;

    if ($coord_type === 'latlon') {
        $lat = filter_input(INPUT_POST, 'lat', FILTER_VALIDATE_FLOAT);
        $lon = filter_input(INPUT_POST, 'lon', FILTER_VALIDATE_FLOAT);
    } elseif ($coord_type === 'rd') {
        $rd_x = filter_input(INPUT_POST, 'rd_x', FILTER_VALIDATE_FLOAT);
        $rd_y = filter_input(INPUT_POST, 'rd_y', FILTER_VALIDATE_FLOAT);
        if ($rd_x && $rd_y) {
            $converted_coords = convertRDtoLatLon($rd_x, $rd_y);
            $lat = $converted_coords['lat'];
            $lon = $converted_coords['lon'];
        }
    } elseif ($coord_type === 'group') {
        $group_id = filter_input(INPUT_POST, 'group_id', FILTER_VALIDATE_INT);
        if ($group_id) {
            $stmt_grp = $conn->prepare("SELECT lat, lon FROM Groepen WHERE id = ?");
            $stmt_grp->bind_param("i", $group_id);
            $stmt_grp->execute();
            $grp_res = $stmt_grp->get_result();
            if ($grp_row = $grp_res->fetch_assoc()) {
                $lat = $grp_row['lat'];
                $lon = $grp_row['lon'];
            }
            $stmt_grp->close();
        }
    }

    $ingestuurd_op = str_replace('T', ' ', $datumtijd_str) . ':00';

    if ($lat && $lon) {
        $stmt = $conn->prepare("INSERT INTO Voslocaties (type, deelgebied, ingestuurd_op, coordinaat_x, coordinaat_y, code, opmerking, ingeleverd_door, ingeleverd) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)");
        $stmt->bind_param("sssddssi", $type, $deelgebied, $ingestuurd_op, $lat, $lon, $code, $opmerking, $ingeleverd_door);
        
        if ($stmt->execute()) {
            $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6 shadow-sm">
                            <span onclick="this.parentElement.style.display=\'none\'" class="absolute top-0 bottom-0 right-0 px-4 py-3 cursor-pointer">
                                <i class="fas fa-times opacity-70 hover:opacity-100 transition"></i>
                            </span>
                            <strong class="font-bold">Success!</strong>
                            <span class="block sm:inline">Voslocatie succesvol toegevoegd.</span>
                        </div>';
        } else {
            $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6 shadow-sm">
                            <span onclick="this.parentElement.style.display=\'none\'" class="absolute top-0 bottom-0 right-0 px-4 py-3 cursor-pointer">
                                <i class="fas fa-times opacity-70 hover:opacity-100 transition"></i>
                            </span>
                            <strong class="font-bold">Error!</strong>
                            <span class="block sm:inline">Er is een fout opgetreden: ' . htmlspecialchars($stmt->error) . '</span>
                        </div>';
        }
        $stmt->close();
    } else {
        $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6 shadow-sm">
                        <span onclick="this.parentElement.style.display=\'none\'" class="absolute top-0 bottom-0 right-0 px-4 py-3 cursor-pointer">
                            <i class="fas fa-times circle text-red-500 mr-1"></i>
                        </span>
                        <strong class="font-bold">Error!</strong>
                        <span class="block sm:inline">Ongeldige coördinaten ingevoerd.</span>
                    </div>';
    }
}

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

$stmt = $conn->prepare("SELECT * FROM Site_Instellingen");
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
  while($row = $result->fetch_assoc()) {
    $siteSettings[$row['Instelling']] = $row['Waarde'];
  }
}
$stmt->close();

$groups = [];
$stmt_groups = $conn->prepare("SELECT id, naam, deelgebied FROM Groepen ORDER BY deelgebied, naam");
$stmt_groups->execute();
$res_groups = $stmt_groups->get_result();
while ($row_group = $res_groups->fetch_assoc()) {
    $groups[] = $row_group;
}
$stmt_groups->close();

$group_lat = 52.15517440;
$group_lon = 5.38720621;

if (isset($siteSettings['GROUP_ID'])) {
    $stmt = $conn->prepare("SELECT lat, lon FROM Groepen WHERE id = ?");
    $stmt->bind_param("i", $siteSettings['GROUP_ID']);
    $stmt->execute();
    $groupResult = $stmt->get_result();
    if ($groupRow = $groupResult->fetch_assoc()) {
        if (!empty($groupRow['lat']) && !empty($groupRow['lon'])) {
            $group_lat = floatval($groupRow['lat']);
            $group_lon = floatval($groupRow['lon']);
        }
    }
    $stmt->close();
}

?>
<!DOCTYPE html>
<html lang="nl">
<head>
<title>Jotify - Voslocaties</title>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="shortcut icon" type="image/png" href="media/geusje.png"/>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://kit.fontawesome.com/870ab34ea3.js" crossorigin="anonymous"></script>
<script src='https://api.mapbox.com/mapbox-gl-js/v3.1.2/mapbox-gl.js'></script>
<link href='https://api.mapbox.com/mapbox-gl-js/v3.1.2/mapbox-gl.css' rel='stylesheet' />
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<?php include_once('includes/theme.php'); ?>
</head>
<body class="flex h-screen overflow-hidden">

<?php include_once('includes/sidebar.php') ?>

<div class="flex-1 flex flex-col h-screen overflow-y-auto w-full relative">
  <?php include_once('includes/topbar.php') ?>

  <main class="p-4 md:p-6 max-w-[1400px] mx-auto w-full flex-1">

    <div class="theme-card rounded border shadow-sm overflow-hidden mb-12 max-w-4xl">
      <div class="theme-card-header px-6 py-4 border-b text-white" style="background-color: var(--theme-sidebar-active); border-color: var(--theme-card-border);">
        <h3 class="text-xl font-bold">Nieuwe voslocatie toevoegen</h3>
      </div>
      <form class="p-6" method="post" action="voslocaties.php">
        
        <?php echo $message; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
          
          <div class="space-y-6">
            <div>
              <label class="block text-sm font-bold opacity-70 mb-2 uppercase tracking-wide">Locatie Invoermethode</label>
              <div class="flex items-center space-x-4">
                <label class="flex items-center space-x-2 cursor-pointer group">
                  <input id="coord_latlon" type="radio" name="coord_type" value="latlon" onclick="showCoords('latlon');" checked class="w-4 h-4 text-blue-600 focus:ring-blue-500">
                  <span class="group-hover:opacity-80 transition">Lat / Lon</span>
                </label>
                <label class="flex items-center space-x-2 cursor-pointer group">
                  <input id="coord_rd" type="radio" name="coord_type" value="rd" onclick="showCoords('rd');" class="w-4 h-4 text-blue-600 focus:ring-blue-500">
                  <span class="group-hover:opacity-80 transition">RD</span>
                </label>
                <label class="flex items-center space-x-2 cursor-pointer group">
                  <input id="coord_group" type="radio" name="coord_type" value="group" onclick="showCoords('group');" class="w-4 h-4 text-blue-600 focus:ring-blue-500">
                  <span class="group-hover:opacity-80 transition">Groep</span>
                </label>
              </div>
            </div>
            
            <div id="latlon_coords" class="space-y-4">
              <div>
                  <div class="flex items-center space-x-2 flex-wrap gap-y-2">
                      <button type="button" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded transition shadow-sm text-sm" onclick="getGPSLocation()" id="gps-button"><i class="fas fa-location-arrow mr-2"></i>Haal locatie op</button>
                      <button type="button" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded transition shadow-sm text-sm" onclick="openMapModal()" id="map-button"><i class="fas fa-map-marked-alt mr-2"></i>Kies op kaart</button>
                  </div>
                  <div class="mt-1">
                      <span id="gps-status" class="text-sm opacity-70 italic"></span>
                  </div>
              </div>
              <div>
                <label class="block text-sm font-bold opacity-70 mb-1">Latitude</label>
                <input class="w-full border rounded px-3 py-2 text-gray-800 outline-none focus:ring-1 focus:ring-blue-500 shadow-sm" type="number" step="any" name="lat" placeholder="52.000000" required>
              </div>
              <div>
                <label class="block text-sm font-bold opacity-70 mb-1">Longitude</label>
                <input class="w-full border rounded px-3 py-2 text-gray-800 outline-none focus:ring-1 focus:ring-blue-500 shadow-sm" type="number" step="any" name="lon" placeholder="5.900000" required>
              </div>
            </div>
            
            <div id="rd_coords" class="space-y-4" style="display:none;">
              <div>
                <label class="block text-sm font-bold opacity-70 mb-1">RD X</label>
                <input class="w-full border rounded px-3 py-2 text-gray-800 outline-none focus:ring-1 focus:ring-blue-500 shadow-sm" type="number" step="any" name="rd_x" placeholder="190000">
              </div>
              <div>
                <label class="block text-sm font-bold opacity-70 mb-1">RD Y</label>
                <input class="w-full border rounded px-3 py-2 text-gray-800 outline-none focus:ring-1 focus:ring-blue-500 shadow-sm" type="number" step="any" name="rd_y" placeholder="450000">
              </div>
            </div>

            <div id="group_coords" class="space-y-4" style="display:none;">
              <div>
                <label class="block text-sm font-bold opacity-70 mb-1">Zoek Scoutinggroep</label>
                <input type="text" id="group_search" onkeyup="filterGroups()" class="w-full border rounded px-3 py-2 text-gray-800 outline-none focus:ring-1 focus:ring-blue-500 shadow-sm mb-2" placeholder="Typ om te zoeken...">
                <div class="border rounded max-h-48 overflow-y-auto bg-white shadow-inner">
                    <table class="w-full text-sm text-left">
                        <tbody id="group_list">
                            <?php foreach($groups as $g): ?>
                            <tr class="group-row cursor-pointer hover:bg-blue-50 transition border-b last:border-b-0" onclick="selectGroup(<?= $g['id'] ?>, this)">
                                <td class="px-3 py-2 font-bold text-gray-600 w-10 border-r border-gray-100 text-center"><?= htmlspecialchars(substr($g['deelgebied'], 0, 1)) ?></td>
                                <td class="px-3 py-2"><?= htmlspecialchars($g['naam']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <input type="hidden" name="group_id" id="selected_group_id">
              </div>
            </div>
            
            <div>
              <label class="block text-sm font-bold opacity-70 mb-1 uppercase tracking-wide">Datum & Tijd</label>
              <input class="w-full border rounded px-3 py-2 text-gray-800 outline-none focus:ring-1 focus:ring-blue-500 shadow-sm" type="datetime-local" name="datumtijd" value="<?php echo date('Y-m-d\TH:i'); ?>" required>
            </div>
          </div>

          <div class="space-y-6">
            <div>
              <label class="block text-sm font-bold opacity-70 mb-1 uppercase tracking-wide">Vossenteam (Deelgebied)</label>
              <select class="w-full border rounded px-3 py-2 text-gray-800 outline-none focus:ring-1 focus:ring-blue-500 shadow-sm bg-white" name="deelgebied" required>
                <option value="" disabled selected>Kies een team</option>
                <?php
                foreach ($vossen_names as $fox) {
                    echo "<option value=\"" . htmlspecialchars($fox) . "\">" . htmlspecialchars($fox) . "</option>\n";
                }
                ?>
              </select>
            </div>
            
            <div>
              <label class="block text-sm font-bold opacity-70 mb-1 uppercase tracking-wide">Type Locatie</label>
              <select class="w-full border rounded px-3 py-2 text-gray-800 outline-none focus:ring-1 focus:ring-blue-500 shadow-sm bg-white" name="type" id="type_select" onchange="toggleCodeInput()" required>
                <option value="Hint">Hint</option>
                <option value="Hunt">Hunt</option>
                <option value="Spot" selected>Spot</option>
                <option value="Voorspelling">Voorspelling</option>
              </select>
            </div>
            
            <div>
              <label class="block text-sm font-bold opacity-70 mb-1 uppercase tracking-wide">Code</label>
              <input class="w-full border rounded px-3 py-2 text-gray-800 outline-none focus:ring-1 focus:ring-blue-500 shadow-sm bg-gray-100 disabled:opacity-60 disabled:cursor-not-allowed" type="text" name="code" id="code_input" maxlength="32" disabled>
            </div>
            
            <div>
              <label class="block text-sm font-bold opacity-70 mb-1 uppercase tracking-wide">Opmerking (optioneel)</label>
              <textarea class="w-full border rounded px-3 py-2 text-gray-800 outline-none focus:ring-1 focus:ring-blue-500 shadow-sm resize-y" name="opmerking" rows="3" maxlength="128"></textarea>
            </div>
          </div>
        </div>
        
        <div class="mt-8 border-t pt-6" style="border-color: var(--theme-card-border);">
          <button type="submit" name="submit_voslocatie" class="theme-bg-primary text-white font-bold py-3 px-8 rounded shadow-sm hover:opacity-90 transition"><i class="fas fa-plus mr-2"></i>Locatie Toevoegen</button>
        </div>
      </form>
    </div>
  </main>

  <div id="map-modal" class="fixed inset-0 z-50 hidden bg-black bg-opacity-60 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl overflow-hidden flex flex-col h-[80vh] md:h-[600px]">
        <div class="px-6 py-4 border-b flex justify-between items-center" style="background-color: var(--theme-sidebar-active); border-color: var(--theme-card-border);">
            <h3 class="text-xl font-bold text-white"><i class="fas fa-map-marked-alt mr-2"></i>Kies een locatie op de kaart</h3>
            <button type="button" onclick="closeMapModal()" class="text-white hover:text-gray-300 focus:outline-none transition">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        <div class="flex-1 w-full relative">
            <div id="modal-map" class="absolute inset-0 w-full h-full"></div>
        </div>
        <div class="px-6 py-4 border-t bg-gray-50 flex justify-end space-x-4">
            <button type="button" onclick="closeMapModal()" class="px-5 py-2.5 bg-gray-300 hover:bg-gray-400 text-gray-800 rounded font-bold transition shadow-sm">Annuleren</button>
            <button type="button" onclick="confirmMapLocation()" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded font-bold transition shadow-sm"><i class="fas fa-check mr-2"></i>Bevestig Locatie</button>
        </div>
    </div>
  </div>

  <?php require_once('includes/footer.php') ?>
</div>
  
<script>
if ("<?php echo $_SESSION['gps'] ?? 'false' ?>" == "true"){
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
      console.log("Latitude: " + position.coords.latitude + "<br>Longitude: " + position.coords.longitude);
      
      var xmlhttp;
      if (window.XMLHttpRequest) {
            xmlhttp = new XMLHttpRequest();
      } else {
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

/**
 * Toggles the visibility of coordinate input fields based on user selection.
 * Also handles the 'required' attribute to ensure HTML validation works properly.
 */
function showCoords(type) {
    const latInput = document.querySelector('input[name="lat"]');
    const lonInput = document.querySelector('input[name="lon"]');
    const rdXInput = document.querySelector('input[name="rd_x"]');
    const rdYInput = document.querySelector('input[name="rd_y"]');
    const gpsStatus = document.getElementById('gps-status');

    gpsStatus.textContent = '';
    rdXInput.value = '';
    rdYInput.value = '';

    document.getElementById('latlon_coords').style.display = 'none';
    document.getElementById('rd_coords').style.display = 'none';
    document.getElementById('group_coords').style.display = 'none';

    latInput.required = false;
    lonInput.required = false;
    rdXInput.required = false;
    rdYInput.required = false;

    if (type === 'latlon') {
        document.getElementById('latlon_coords').style.display = 'block';
        latInput.required = true;
        lonInput.required = true;
    } else if (type === 'rd') {
        document.getElementById('rd_coords').style.display = 'block';
        rdXInput.required = true;
        rdYInput.required = true;
    } else if (type === 'group') {
        document.getElementById('group_coords').style.display = 'block';
    }
}

/**
 * Retrieves the current GPS location and populates the lat/lon input fields.
 */
function getGPSLocation() {
    const gpsStatus = document.getElementById('gps-status');
    const latInput = document.querySelector('input[name="lat"]');
    const lonInput = document.querySelector('input[name="lon"]');

    if (navigator.geolocation) {
        gpsStatus.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Locatie ophalen...';
        navigator.geolocation.getCurrentPosition(
            function(position) {
                latInput.value = position.coords.latitude.toFixed(6);
                lonInput.value = position.coords.longitude.toFixed(6);
                gpsStatus.innerHTML = '<i class="fas fa-check-circle text-green-500 mr-1"></i>Locatie succesvol opgehaald.';
            },
            function(error) {
                let errorMessage;
                switch(error.code) {
                    case error.PERMISSION_DENIED:
                        errorMessage = "Toegang tot locatie geweigerd.";
                        break;
                    case error.POSITION_UNAVAILABLE:
                        errorMessage = "Locatie informatie niet beschikbaar.";
                        break;
                    case error.TIMEOUT:
                        errorMessage = "Timeout bij het ophalen van locatie.";
                        break;
                    case error.UNKNOWN_ERROR:
                        errorMessage = "Een onbekende fout is opgetreden.";
                        break;
                }
                gpsStatus.innerHTML = '<i class="fas fa-times-circle text-red-500 mr-1"></i>' + errorMessage;
            }
        );
    } else {
        gpsStatus.textContent = "Geolocation wordt niet ondersteund door deze browser.";
    }
}

/**
 * Sets the code input field state depending on the selected location type.
 */
function toggleCodeInput() {
    const typeSelect = document.getElementById('type_select');
    const codeInput = document.getElementById('code_input');

    if (typeSelect.value === 'Hunt') {
        codeInput.disabled = false;
        codeInput.required = true;
        codeInput.classList.remove('bg-gray-100');
        codeInput.classList.add('bg-white');
    } else {
        codeInput.disabled = true;
        codeInput.required = false;
        codeInput.value = '';
        codeInput.classList.add('bg-gray-100');
        codeInput.classList.remove('bg-white');
    }
}

/**
 * Marks a scout group as selected and populates the hidden input field.
 */
function selectGroup(id, rowElement) {
    document.getElementById('selected_group_id').value = id;
    const rows = document.querySelectorAll('#group_list .group-row');
    rows.forEach(row => row.classList.remove('bg-blue-100'));
    rowElement.classList.add('bg-blue-100');
}

/**
 * Filters the list of scout groups based on user search input.
 */
function filterGroups() {
    const filter = document.getElementById('group_search').value.toLowerCase();
    const rows = document.querySelectorAll('#group_list .group-row');
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
}

let mapModal;
let modalMarker;
let mapInitialized = false;

mapboxgl.accessToken = '<?php echo $siteSettings["API_KEY_MAPBOX"] ?? ""; ?>';

/**
 * Initializes and displays the Mapbox modal.
 */
function openMapModal() {
    document.getElementById('map-modal').classList.remove('hidden');
    
    if (!mapInitialized) {
        mapModal = new mapboxgl.Map({
            container: 'modal-map',
            style: 'mapbox://styles/mapbox/streets-v12',
            center: [<?php echo $group_lon; ?>, <?php echo $group_lat; ?>],
            zoom: 11
        });
        
        mapModal.on('click', function(e) {
            if (modalMarker) {
                modalMarker.remove();
            }
            modalMarker = new mapboxgl.Marker()
                .setLngLat(e.lngLat)
                .addTo(mapModal);
        });
        
        mapInitialized = true;
    }
    
    setTimeout(() => {
        mapModal.resize();
        
        const currentLat = parseFloat(document.querySelector('input[name="lat"]').value);
        const currentLon = parseFloat(document.querySelector('input[name="lon"]').value);
        
        if (!isNaN(currentLat) && !isNaN(currentLon)) {
            if (modalMarker) modalMarker.remove();
            modalMarker = new mapboxgl.Marker()
                .setLngLat([currentLon, currentLat])
                .addTo(mapModal);
                
            mapModal.setCenter([currentLon, currentLat]);
            mapModal.setZoom(14);
        }
    }, 200);
}

/**
 * Hides the Mapbox modal without saving.
 */
function closeMapModal() {
    document.getElementById('map-modal').classList.add('hidden');
}

/**
 * Extracts coordinates from the placed Mapbox marker and inserts them into the form.
 */
function confirmMapLocation() {
    if (modalMarker) {
        const lngLat = modalMarker.getLngLat();
        document.querySelector('input[name="lat"]').value = lngLat.lat.toFixed(6);
        document.querySelector('input[name="lon"]').value = lngLat.lng.toFixed(6);
        
        const gpsStatus = document.getElementById('gps-status');
        gpsStatus.innerHTML = '<i class="fas fa-check-circle text-green-500 mr-1"></i>Locatie succesvol gekozen via kaart.';
        
        closeMapModal();
    } else {
        alert("Klik eerst ergens op de kaart om een locatie te selecteren.");
    }
}

toggleCodeInput();
</script>
</body>
</html>