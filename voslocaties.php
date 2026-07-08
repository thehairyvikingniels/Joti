<?php
define("PAGE_NAME", "voslocaties");

session_start();

if (!isset($_SESSION['id'])) {
    header("Location: index");
    exit();
}

require("dblogin.php");
require_once("functies.php");

// --- START: NEW FEATURE - PROCESS FORM ---
$message = ''; // Variable to store success or error messages for the user.

/**
 * Converts RD (Rijksdriehoekstelsel) coordinates to WGS84 (Latitude/Longitude).
 * This is an approximation based on the formulas provided by the Dutch Kadaster.
 *
 * @param float $rd_x The RD X-coordinate.
 * @param float $rd_y The RD Y-coordinate.
 * @return array An associative array with 'lat' and 'lon'.
 */
function convertRDtoLatLon($rd_x, $rd_y) {
    // Amersfoort datum parameters
    $X0 = 155000;
    $Y0 = 463000;
    $phi0 = 52.15517440;
    $lambda0 = 5.38720621;

    // Calculate differences from the origin
    $dx = ($rd_x - $X0) * 1E-5;
    $dy = ($rd_y - $Y0) * 1E-5;

    // Polynomial expansion for latitude
    $sum_lat = (3235.65389 * $dy) + (-32.58297 * pow($dx, 2)) + (-0.2475 * pow($dy, 2)) + (-0.84978 * pow($dx, 2) * $dy) + (-0.0655 * pow($dy, 3)) + (-0.01709 * pow($dx, 2) * pow($dy, 2)) + (-0.00738 * $dx) + (0.0053 * pow($dx, 4)) + (-0.00039 * pow($dx, 2) * pow($dy, 3)) + (0.00033 * pow($dx, 4) * $dy) + (-0.00012 * $dx * $dy);

    // Polynomial expansion for longitude
    $sum_lon = (5260.52916 * $dx) + (105.94684 * $dx * $dy) + (2.45656 * $dx * pow($dy, 2)) + (-0.81885 * pow($dx, 3)) + (0.05594 * $dx * pow($dy, 3)) + (-0.05607 * pow($dx, 3) * $dy) + (0.01199 * $dy) + (-0.00256 * pow($dx, 3) * pow($dy, 2)) + (0.00128 * $dx * pow($dy, 4)) + (0.00022 * pow($dy, 2)) + (-0.00022 * pow($dx, 2)) + (0.00026 * pow($dx, 5));

    $lat = $phi0 + $sum_lat / 3600;
    $lon = $lambda0 + $sum_lon / 3600;

    return ['lat' => $lat, 'lon' => $lon];
}


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_voslocatie'])) {
    // Sanitize and retrieve form data
    $coord_type = $_POST['coord_type'];
    $type = $_POST['type'];
    $deelgebied = $_POST['deelgebied'];
    $datumtijd_str = $_POST['datumtijd'];
    $code = $_POST['code'];
    $opmerking = $_POST['opmerking'];
    $ingeleverd_door = $_SESSION['id'];

    $lat = 0.0;
    $lon = 0.0;

    // Check which coordinate type was submitted and process accordingly
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
    }

    // Format datetime from "YYYY-MM-DDTHH:mm" to "YYYY-MM-DD HH:mm:ss" for MySQL
    $ingestuurd_op = str_replace('T', ' ', $datumtijd_str) . ':00';

    if ($lat && $lon) {
        // Prepare and execute the insert statement to prevent SQL injection
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
                            <i class="fas fa-times opacity-70 hover:opacity-100 transition"></i>
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

// Get global site settings
$stmt = $conn->prepare("SELECT * FROM Site_Instellingen");
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
  while($row = $result->fetch_assoc()) {
    $siteSettings[$row['Instelling']] = $row['Waarde'];
  }
}

?>
<!DOCTYPE html>
<html lang="nl">
<head>
<title>Jotihunt - Voslocaties</title>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="shortcut icon" type="image/png" href="media/geusje.png"/>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://kit.fontawesome.com/870ab34ea3.js" crossorigin="anonymous"></script>
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<?php include_once('includes/theme.php'); ?>
</head>
<body class="flex h-screen overflow-hidden">

<!-- Sidebar -->
<?php include_once('includes/sidebar.php') ?>

<!-- Main Content -->
<div class="flex-1 flex flex-col h-screen overflow-y-auto w-full relative">
  <!-- Topbar -->
  <?php include_once('includes/topbar.php') ?>

  <main class="p-4 md:p-6 max-w-[1400px] mx-auto w-full flex-1">


    <!-- --- START: NEW FEATURE - LOCATION FORM --- -->
    <div class="theme-card rounded border shadow-sm overflow-hidden mb-12 max-w-4xl">
      <div class="theme-card-header px-6 py-4 border-b text-white" style="background-color: var(--theme-sidebar-active); border-color: var(--theme-card-border);">
        <h3 class="text-xl font-bold">Nieuwe voslocatie toevoegen</h3>
      </div>
      <form class="p-6" method="post" action="voslocaties.php">
        
        <!-- This is where success or error messages will be displayed -->
        <?php echo $message; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
          
          <!-- Left Column -->
          <div class="space-y-6">
            <div>
              <label class="block text-sm font-bold opacity-70 mb-2 uppercase tracking-wide">Coördinaat Systeem</label>
              <div class="flex items-center space-x-6">
                <label class="flex items-center space-x-2 cursor-pointer group">
                  <input id="coord_latlon" type="radio" name="coord_type" value="latlon" onclick="showCoords('latlon');" checked class="w-4 h-4 text-blue-600 focus:ring-blue-500">
                  <span class="group-hover:opacity-80 transition">Latitude / Longitude</span>
                </label>
                <label class="flex items-center space-x-2 cursor-pointer group">
                  <input id="coord_rd" type="radio" name="coord_type" value="rd" onclick="showCoords('rd');" class="w-4 h-4 text-blue-600 focus:ring-blue-500">
                  <span class="group-hover:opacity-80 transition">RD Coördinaten</span>
                </label>
              </div>
            </div>
            
            <div id="latlon_coords" class="space-y-4">
              <div>
                  <button type="button" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded transition shadow-sm text-sm" onclick="getGPSLocation()" id="gps-button"><i class="fas fa-location-arrow mr-2"></i>Haal locatie op</button>
                  <span id="gps-status" class="text-sm opacity-70 ml-3 italic"></span>
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
            
            <div>
              <label class="block text-sm font-bold opacity-70 mb-1 uppercase tracking-wide">Datum & Tijd</label>
              <input class="w-full border rounded px-3 py-2 text-gray-800 outline-none focus:ring-1 focus:ring-blue-500 shadow-sm" type="datetime-local" name="datumtijd" value="<?php echo date('Y-m-d\TH:i'); ?>" required>
            </div>
          </div>

          <!-- Right Column -->
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
    <!-- --- END: NEW FEATURE - LOCATION FORM --- -->
  </main>

  <!-- Footer -->
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

// --- START: NEW FEATURE - JAVASCRIPT ---
/**
 * Toggles the visibility of coordinate input fields based on user selection.
 * Also handles the 'required' attribute to ensure form validation works correctly.
 */
function showCoords(type) {
    const latInput = document.querySelector('input[name="lat"]');
    const lonInput = document.querySelector('input[name="lon"]');
    const rdXInput = document.querySelector('input[name="rd_x"]');
    const rdYInput = document.querySelector('input[name="rd_y"]');
    const gpsStatus = document.getElementById('gps-status');

    // Reset values and status first
    gpsStatus.textContent = '';
    rdXInput.value = '';
    rdYInput.value = '';

    if (type === 'latlon') {
        document.getElementById('latlon_coords').style.display = 'block';
        document.getElementById('rd_coords').style.display = 'none';
        
        latInput.required = true;
        lonInput.required = true;
        rdXInput.required = false;
        rdYInput.required = false;

    } else if (type === 'rd') {
        document.getElementById('latlon_coords').style.display = 'none';
        document.getElementById('rd_coords').style.display = 'block';
        
        latInput.required = false;
        lonInput.required = false;
        rdXInput.required = true;
        rdYInput.required = true;
        
        latInput.value = '';
        lonInput.value = '';
    }
}

/**
 * Gets the current GPS location and fills the lat/lon input fields.
 */
function getGPSLocation() {
    const gpsStatus = document.getElementById('gps-status');
    const latInput = document.querySelector('input[name="lat"]');
    const lonInput = document.querySelector('input[name="lon"]');

    if (navigator.geolocation) {
        gpsStatus.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Locatie ophalen...';
        navigator.geolocation.getCurrentPosition(
            function(position) {
                // Populate the fields with retrieved coordinates, rounded to 6 decimal places.
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
 * Toggles the code input field based on the selected location type.
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
        codeInput.value = ''; // Clear the value if not a Hunt
        codeInput.classList.add('bg-gray-100');
        codeInput.classList.remove('bg-white');
    }
}

// Initialize the code input state on page load.
toggleCodeInput();
// --- END: NEW FEATURE - JAVASCRIPT ---
</script>
</body>
</html>

