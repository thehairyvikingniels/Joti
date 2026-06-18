<?php
define("PAGE_NAME", "voslocaties");

session_start();

if (!isset($_SESSION['id'])) {
    header("Location: index");
    exit();
}

require("dblogin.php");

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
            $message = '<div class="w3-panel w3-green w3-display-container">
                            <span onclick="this.parentElement.style.display=\'none\'" class="w3-button w3-large w3-display-topright">&times;</span>
                            <h3>Success!</h3>
                            <p>Voslocatie succesvol toegevoegd.</p>
                        </div>';
        } else {
            $message = '<div class="w3-panel w3-red w3-display-container">
                            <span onclick="this.parentElement.style.display=\'none\'" class="w3-button w3-large w3-display-topright">&times;</span>
                            <h3>Error!</h3>
                            <p>Er is een fout opgetreden: ' . htmlspecialchars($stmt->error) . '</p>
                        </div>';
        }
        $stmt->close();
    } else {
        $message = '<div class="w3-panel w3-red w3-display-container">
                        <span onclick="this.parentElement.style.display=\'none\'" class="w3-button w3-large w3-display-topright">&times;</span>
                        <h3>Error!</h3>
                        <p>Ongeldige coördinaten ingevoerd.</p>
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

?>
<!DOCTYPE html>
<html>
<title>Jotihunt - Vossen</title>
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
    <h5><b><i class="fas fa-circle-nodes"></i> Voslocaties</b></h5>
  </header>

  <!-- --- START: NEW FEATURE - LOCATION FORM --- -->
  <div class="w3-container w3-padding-16">
    <div class="w3-card-4">
      <div class="w3-container w3-blue-gray">
        <h2>Nieuwe voslocatie toevoegen</h2>
      </div>
      <form class="w3-container w3-padding" method="post" action="voslocaties.php">
        
        <!-- This is where success or error messages will be displayed -->
        <?php echo $message; ?>

        <div class="w3-row-padding">
          <div class="w3-half">
            <p>
              <label class="w3-text-grey"><b>Coördinaat Systeem</b></label><br>
              <input class="w3-radio" id="coord_latlon" type="radio" name="coord_type" value="latlon" onclick="showCoords('latlon');" checked>
              <label for="coord_latlon" style="cursor: pointer;">Latitude / Longitude</label><br>
              <input class="w3-radio" id="coord_rd" type="radio" name="coord_type" value="rd" onclick="showCoords('rd');">
              <label for="coord_rd" style="cursor: pointer;">RD Coördinaten</label>
            </p>
            
            <div id="latlon_coords">
              <div class="w3-padding-small">
                  <button type="button" class="w3-button w3-blue-gray w3-round" onclick="getGPSLocation()" id="gps-button"><i class="fas fa-location-arrow"></i> Haal locatie op</button>
                  <span id="gps-status" class="w3-text-grey w3-margin-left"></span>
              </div>
              <p>
                <label class="w3-text-grey">Latitude</label>
                <input class="w3-input w3-border" type="number" step="any" name="lat" placeholder="52.000000" required>
              </p>
              <p>
                <label class="w3-text-grey">Longitude</label>
                <input class="w3-input w3-border" type="number" step="any" name="lon" placeholder="5.900000" required>
              </p>
            </div>
            
            <div id="rd_coords" style="display:none;">
              <p>
                <label class="w3-text-grey">RD X</label>
                <input class="w3-input w3-border" type="number" step="any" name="rd_x" placeholder="190000">
              </p>
              <p>
                <label class="w3-text-grey">RD Y</label>
                <input class="w3-input w3-border" type="number" step="any" name="rd_y" placeholder="450000">
              </p>
            </div>
            
            <p>
              <label class="w3-text-grey"><b>Datum & Tijd</b></label>
              <input class="w3-input w3-border" type="datetime-local" name="datumtijd" value="<?php echo date('Y-m-d\TH:i'); ?>" required>
            </p>
          </div>

          <div class="w3-half">
            <p>
              <label class="w3-text-grey"><b>Vossenteam (Deelgebied)</b></label>
              <select class="w3-select w3-border" name="deelgebied" required>
                <option value="" disabled selected>Kies een team</option>
                <option value="Alpha">Alpha</option>
                <option value="Bravo">Bravo</option>
                <option value="Charlie">Charlie</option>
                <option value="Delta">Delta</option>
                <option value="Echo">Echo</option>
                <option value="Foxtrot">Foxtrot</option>
                <option value="Golf">Golf</option>
              </select>
            </p>
            
            <p>
              <label class="w3-text-grey"><b>Type Locatie</b></label>
              <select class="w3-select w3-border" name="type" id="type_select" onchange="toggleCodeInput()" required>
                <option value="Hint">Hint</option>
                <option value="Hunt">Hunt</option>
                <option value="Spot" selected>Spot</option>
                <option value="Voorspelling">Voorspelling</option>
              </select>
            </p>
            
            <p>
              <label class="w3-text-grey"><b>Code</b></label>
              <input class="w3-input w3-border" type="text" name="code" id="code_input" maxlength="32" disabled>
            </p>
            
            <p>
              <label class="w3-text-grey"><b>Opmerking (optioneel)</b></label>
              <textarea class="w3-input w3-border" name="opmerking" style="resize:vertical" maxlength="128"></textarea>
            </p>
          </div>
        </div>
        
        <p class="w3-padding-16">
          <button type="submit" name="submit_voslocatie" class="w3-button w3-blue-gray w3-padding-large w3-hover-dark-grey"><i class="fas fa-plus"></i> Locatie Toevoegen</button>
        </p>
      </form>
    </div>
  </div>
  <!-- --- END: NEW FEATURE - LOCATION FORM --- -->


  <!-- Footer -->
  <?php require_once('includes/footer.php') ?>

  <!-- End page content -->
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
     console.log("Latitude: " + position.coords.latitude + 
      "<br>Longitude: " . position.coords.longitude);
      
      var xmlhttp;
      if (window.XMLHttpRequest) {
            // code for IE7+, Firefox, Chrome, Opera, Safari
            xmlhttp = new XMLHttpRequest();
        } else {
            // code for IE6, IE5
            xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
        }
        xmlhttp.onreadystatechange = function() {
            if (this.readyState == 4 && this.status == 200) {
                // optional: handle response
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
        gpsStatus.textContent = 'Locatie ophalen...';
        navigator.geolocation.getCurrentPosition(
            function(position) {
                // Populate the fields with retrieved coordinates, rounded to 6 decimal places.
                latInput.value = position.coords.latitude.toFixed(6);
                lonInput.value = position.coords.longitude.toFixed(6);
                gpsStatus.innerHTML = '<i class="fas fa-check-circle" style="color: green;"></i> Locatie succesvol opgehaald.';
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
                gpsStatus.innerHTML = '<i class="fas fa-times-circle" style="color: red;"></i> ' + errorMessage;
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
    } else {
        codeInput.disabled = true;
        codeInput.required = false;
        codeInput.value = ''; // Clear the value if not a Hunt
    }
}

// Initialize the code input state on page load.
toggleCodeInput();
// --- END: NEW FEATURE - JAVASCRIPT ---
</script>
</body>
</html>

