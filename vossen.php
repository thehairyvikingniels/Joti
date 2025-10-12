<?php
define("PAGE_NAME", "vossen");

session_start();

if (!isset($_SESSION['id'])) {
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

// Fetch game and fox exchange times from settings
$settings_sql = "SELECT Instelling, Waarde FROM Site_Instellingen WHERE Instelling IN ('GAME_STARTDATE', 'GAME_ENDDATE', 'FOXEXCHANGE_STARTDATE', 'FOXEXCHANGE_ENDDATE')";
$settings_result = mysqli_query($conn, $settings_sql);
$settings = [];
while($row = mysqli_fetch_assoc($settings_result)) {
    $settings[$row['Instelling']] = $row['Waarde'];
}

$game_start_str = $settings['GAME_STARTDATE'] ?? '2025-10-11 10:00:00';
$game_end_str = $settings['GAME_ENDDATE'] ?? '2025-10-12 12:00:00';
$fox_exchange_start_str = $settings['FOXEXCHANGE_STARTDATE'] ?? '2025-10-11 22:45:00';
$fox_exchange_end_str = $settings['FOXEXCHANGE_ENDDATE'] ?? '2025-10-11 23:15:00';


$game_start_time = new DateTime($game_start_str);
$game_end_time = new DateTime($game_end_str);
$fox_exchange_start_time = new DateTime($fox_exchange_start_str);
$fox_exchange_end_time = new DateTime($fox_exchange_end_str);
$now = new DateTime();

// Calculate total game duration in seconds for timeline calculation
$total_duration_seconds = $game_end_time->getTimestamp() - $game_start_time->getTimestamp();
if ($total_duration_seconds <= 0) {
    $total_duration_seconds = 1; // Prevent division by zero
}


// Fetch all voslog data
$voslog_sql = "SELECT * FROM Voslog WHERE datumtijd >= '".$game_start_time->format('Y-m-d H-i-s')."' AND datumtijd <= '".$game_end_time->format('Y-m-d H-i-s')."' ORDER BY datumtijd ASC";
$voslog_result = mysqli_query($conn, $voslog_sql);

$voslog_data = [];
while ($row = mysqli_fetch_assoc($voslog_result)) {
    $voslog_data[] = $row;
}

$fox_teams = ['alpha', 'bravo', 'charlie', 'delta', 'echo', 'foxtrot', 'golf', 'hotel'];
$status_colors = [
    0 => 'w3-red',    // Red
    1 => 'w3-orange', // Orange
    2 => 'w3-green'   // Green
];
$future_color = 'w3-light-grey'; // Grey for future

// --- STATS CALCULATION ---
$stats = [];

// Helper function to format seconds into HH:MM:SS
function format_seconds($seconds) {
    $h = floor($seconds / 3600);
    $m = floor(($seconds % 3600) / 60);
    $s = $seconds % 60;
    return sprintf('%02d:%02d:%02d', $h, $m, $s);
}

foreach ($fox_teams as $team) {
    $stats[$team] = [
        'spelhelft1' => [0 => 0, 1 => 0, 2 => 0, 'total' => 0],
        'spelhelft2' => [0 => 0, 1 => 0, 2 => 0, 'total' => 0],
    ];

    $last_time = clone $game_start_time;
    $last_status = 2; // Assume start status is green

    $all_events = $voslog_data;
    $end_marker_time = ($now < $game_end_time) ? clone $now : clone $game_end_time;
    $all_events[] = ['datumtijd' => $end_marker_time->format('Y-m-d H:i:s')];
    
    foreach ($all_events as $log) {
        $event_time = new DateTime($log['datumtijd']);
        if ($event_time <= $last_time || $last_time >= $now) continue;

        $segment_end_time = ($event_time < $now) ? clone $event_time : clone $now;
        $duration = $segment_end_time->getTimestamp() - $last_time->getTimestamp();

        if ($duration > 0) {
            // Determine which Spelhelft this segment belongs to
            if ($last_time < $fox_exchange_end_time) {
                $spelhelft = 'spelhelft1';
                $spelhelft_end = $fox_exchange_end_time;
            } else {
                $spelhelft = 'spelhelft2';
                $spelhelft_end = $game_end_time;
            }

            // If the segment crosses the exchange boundary, split it
            if ($segment_end_time > $spelhelft_end && $last_time < $spelhelft_end) {
                $duration1 = $spelhelft_end->getTimestamp() - $last_time->getTimestamp();
                $stats[$team][$spelhelft][$last_status] += $duration1;

                $spelhelft = 'spelhelft2'; // The rest is in the next half
                $duration2 = $segment_end_time->getTimestamp() - $spelhelft_end->getTimestamp();
                 $stats[$team][$spelhelft][$last_status] += $duration2;
            } else {
                $stats[$team][$spelhelft][$last_status] += $duration;
            }
        }
        
        $last_time = $event_time;
        if (isset($log[$team])) {
            $last_status = $log[$team];
        }
    }
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
.timeline-container {
    display: flex;
    width: 100%;
    height: 30px;
    background-color: #f1f1f1;
    border-radius: 4px;
    overflow: hidden;
    position: relative;
    border: 1px solid #ccc;
}
.timeline-segment {
    height: 100%;
    float: left;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 12px;
    position: relative; /* For tooltip */
}
.timeline-segment .tooltiptext {
    visibility: hidden;
    width: 160px;
    background-color: #555;
    color: #fff;
    text-align: center;
    border-radius: 6px;
    padding: 5px 0;
    position: absolute;
    z-index: 1;
    bottom: 125%;
    left: 50%;
    margin-left: -80px;
    opacity: 0;
    transition: opacity 0.3s;
}
.timeline-segment .tooltiptext::after {
    content: "";
    position: absolute;
    top: 100%;
    left: 50%;
    margin-left: -5px;
    border-width: 5px;
    border-style: solid;
    border-color: #555 transparent transparent transparent;
}
.timeline-segment:hover .tooltiptext {
    visibility: visible;
    opacity: 1;
}
.now-indicator {
    position: absolute;
    top: 0;
    bottom: 0;
    width: 3px;
    background-color: #0000ff;
    z-index: 1;
    /* Responsive position calculated using a CSS variable set in PHP */
    /* Mobile-first: for s2/m2 columns (16.667%) */
    left: calc(16.66667% + (83.33333% / 100 * var(--now-percentage)));
}
/* For large screens, override with l1 column width (8.333%) */
@media (min-width: 993px) {
    .now-indicator {
        left: calc(8.33333% + (91.66667% / 100 * var(--now-percentage)));
    }
}
.now-indicator .tooltiptext {
    visibility: hidden;
    width: 100px;
    background-color: #555;
    color: #fff;
    text-align: center;
    border-radius: 6px;
    padding: 5px;
    position: absolute;
    z-index: 1;
    bottom: 100%;
    left: 50%;
    margin-left: -50px;
    margin-bottom: 5px;
    opacity: 0;
    transition: opacity 0.3s;
}
.now-indicator:hover .tooltiptext {
    visibility: visible;
    opacity: 1;
}
</style>
<body class="w3-light-grey">

<!-- Top container --><div class="w3-bar w3-top w3-black w3-large" style="z-index:4">
  <button class="w3-bar-item w3-button w3-hide-large w3-hover-none w3-hover-text-light-grey" onclick="w3_open();"><i class="fa fa-bars"></i>  Menu</button>
  <span class="w3-bar-item w3-right">De Geuzen Arnhem</span>
</div>

<!-- Sidebar -->
<?php include_once('includes/sidebar.php') ?>

<!-- !PAGE CONTENT! -->
<div class="w3-main" style="margin-left:200px;margin-top:43px;">

  <!-- Header --><header class="w3-container" style="padding-top:22px">
    <h5><b><i class="fas fa-bullseye"></i> Vossen</b></h5>
  </header>

  <div class="w3-container w3-padding">
    <div class="w3-card-4 w3-white">
        <div class="w3-container w3-blue-gray">
            <h5>Vossen Status Tijdlijn</h5>
        </div>
        <div class="w3-container w3-padding">
            <div style="position: relative;"> <!-- Parent container for rows and the absolute indicator -->
                <?php foreach ($fox_teams as $team): ?>
                    <div class="w3-row" style="height: 46px; display: flex; align-items: center;">
                        <!-- Column for Team Name -->
                        <div class="w3-col s2 m2 l1 w3-right-align" style="padding-right: 8px;">
                            <b><?php echo ucfirst($team); ?></b>
                        </div>
                        <!-- Column for Timeline -->
                        <div class="w3-col s10 m10 l11">
                            <div class="timeline-container">
                                <?php
                                $last_time = clone $game_start_time;
                                $last_status = 2;

                                $all_events = $voslog_data;
                                $all_events[] = ['datumtijd' => $game_end_time->format('Y-m-d H:i:s')];

                                foreach ($all_events as $log) {
                                    if ($last_time >= $now) break;

                                    $event_time = new DateTime($log['datumtijd']);
                                    if ($event_time <= $last_time) continue;
                                    
                                    $segment_end_time = ($event_time < $now) ? $event_time : clone $now;

                                    $duration_seconds = $segment_end_time->getTimestamp() - $last_time->getTimestamp();
                                    if ($duration_seconds > 0) {
                                        $width_percentage = ($duration_seconds / $total_duration_seconds) * 100;
                                        $color = $status_colors[$last_status] ?? 'w3-grey';
                                        $tooltip = 'Status: ' . $last_status . ' | Van: ' . $last_time->format('H:i') . ' tot ' . $segment_end_time->format('H:i');
                                        echo "<div class='timeline-segment $color' style='width: $width_percentage%;'><span class='tooltiptext'>$tooltip</span></div>";
                                    }

                                    $last_time = $event_time;
                                    if (isset($log[$team])) {
                                        $last_status = $log[$team];
                                    }
                                }

                                if ($now < $game_end_time) {
                                    $future_start_time = ($now > $last_time) ? $now : $last_time;
                                    if($future_start_time < $game_end_time) {
                                        $future_duration_seconds = $game_end_time->getTimestamp() - $future_start_time->getTimestamp();
                                        $width_percentage = ($future_duration_seconds / $total_duration_seconds) * 100;
                                        echo "<div class='timeline-segment $future_color' style='width: $width_percentage%;'></div>";
                                    }
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Now Indicator -->
                <?php
                if ($now > $game_start_time && $now < $game_end_time) {
                    $now_offset_seconds = $now->getTimestamp() - $game_start_time->getTimestamp();
                    $left_percentage = ($now_offset_seconds / $total_duration_seconds) * 100;
                    // Set a CSS variable for the percentage. The actual position is calculated in the <style> block using media queries.
                    echo "<div class='now-indicator' style='--now-percentage: {$left_percentage};'><span class='tooltiptext'>Nu: ".$now->format('H:i')."</span></div>";
                }
                ?>
            </div>
             <div class="w3-container w3-padding w3-margin-top">
                <span class="w3-tag w3-green w3-margin-left">Lopend</span>
                <span class="w3-tag w3-orange  w3-margin-left">Kleine verplaatsing</span>
                <span class="w3-tag w3-red w3-margin-left">Grote verplaatsing</span>
                <span style="display: inline-block; width: 3px; height: 22.5px; background-color: blue; vertical-align: middle;" class="w3-margin-left"></span> Nu
            </div>
        </div>
    </div>
  </div>

  <div class="w3-container w3-padding">
    <div class="w3-card-4 w3-white">
        <div class="w3-container w3-blue-gray">
            <h5>Vossen Statistieken</h5>
        </div>
        <div class="w3-container w3-padding w3-responsive">
            <table class="w3-table-all">
                <thead>
                    <tr class="w3-light-grey">
                        <th>Vos</th>
                        <th class="w3-center">Spelhelft</th>
                        <th class="w3-center w3-green">Lopend</th>
                        <th class="w3-center w3-orange">Kleine Verpl.</th>
                        <th class="w3-center w3-red">Grote Verpl.</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fox_teams as $team): ?>
                        <tr>
                            <td rowspan="2" style="vertical-align: middle;"><b><?php echo ucfirst($team); ?></b></td>
                            <td class="w3-center">Spelhelft 1</td>
                            <td class="w3-center">
                                <?php echo format_seconds($stats[$team]['spelhelft1'][2]); ?><br>
                                <small>(<?php echo $stats[$team]['spelhelft1']['total'] > 0 ? round($stats[$team]['spelhelft1'][2] / $stats[$team]['spelhelft1']['total'] * 100, 1) : 0; ?>%)</small>
                            </td>
                            <td class="w3-center">
                                <?php echo format_seconds($stats[$team]['spelhelft1'][1]); ?><br>
                                <small>(<?php echo $stats[$team]['spelhelft1']['total'] > 0 ? round($stats[$team]['spelhelft1'][1] / $stats[$team]['spelhelft1']['total'] * 100, 1) : 0; ?>%)</small>
                            </td>
                            <td class="w3-center">
                                <?php echo format_seconds($stats[$team]['spelhelft1'][0]); ?><br>
                                <small>(<?php echo $stats[$team]['spelhelft1']['total'] > 0 ? round($stats[$team]['spelhelft1'][0] / $stats[$team]['spelhelft1']['total'] * 100, 1) : 0; ?>%)</small>
                            </td>
                        </tr>
                        <tr>
                            <td class="w3-center">Spelhelft 2</td>
                            <td class="w3-center">
                                <?php echo format_seconds($stats[$team]['spelhelft2'][2]); ?><br>
                                <small>(<?php echo $stats[$team]['spelhelft2']['total'] > 0 ? round($stats[$team]['spelhelft2'][2] / $stats[$team]['spelhelft2']['total'] * 100, 1) : 0; ?>%)</small>
                            </td>
                            <td class="w3-center">
                                <?php echo format_seconds($stats[$team]['spelhelft2'][1]); ?><br>
                                <small>(<?php echo $stats[$team]['spelhelft2']['total'] > 0 ? round($stats[$team]['spelhelft2'][1] / $stats[$team]['spelhelft2']['total'] * 100, 1) : 0; ?>%)</small>
                            </td>
                            <td class="w3-center">
                                <?php echo format_seconds($stats[$team]['spelhelft2'][0]); ?><br>
                                <small>(<?php echo $stats[$team]['spelhelft2']['total'] > 0 ? round($stats[$team]['spelhelft2'][0] / $stats[$team]['spelhelft2']['total'] * 100, 1) : 0; ?>%)</small>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
  </div>

  <!-- Footer -->
  <?php require_once('includes/footer.php') ?>

  <!-- End page content --></div>

<script>
// Get the Sidebar
var mySidebar = document.getElementById("mySidebar");

// Get the DIV with overlay effect
var overlayBg = document.getElementById("myOverlay");

// Toggle between showing and hiding the sidebar, and add overlay effect
function w3_open() {
    if (mySidebar.style.display === 'block') {
        mySidebar.style.display = 'none';
        overlayBg.style.display = "none";
    } else {
        mySidebar.style.display = 'block';
        overlayBg.style.display = "block";
    }
}

// Close the sidebar with the close button
function w3_close() {
    mySidebar.style.display = "none";
    overlayBg.style.display = "none";
}
  </script>
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
</script>
</body>
</html>

