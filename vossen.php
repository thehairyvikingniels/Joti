<?php
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

// Fetch game start and end times from settings
$settings_sql = "SELECT Waarde FROM Site_Instellingen WHERE Instelling IN ('GAME_STARTDATE', 'GAME_ENDDATE') ORDER BY Instelling";
$settings_result = mysqli_query($conn, $settings_sql);
$game_times = mysqli_fetch_all($settings_result);
$game_start_str = $game_times[1][0] ?? '2025-10-11 10:00:00';
$game_end_str = $game_times[0][0] ?? '2025-10-12 12:00:00';

$game_start_time = new DateTime($game_start_str);
$game_end_time = new DateTime($game_end_str);
$now = new DateTime();

// Calculate total game duration in seconds for timeline calculation
$total_duration_seconds = $game_end_time->getTimestamp() - $game_start_time->getTimestamp();
if ($total_duration_seconds <= 0) {
    $total_duration_seconds = 1; // Prevent division by zero
}


// Fetch all voslog data
$voslog_sql = "SELECT * FROM Voslog WHERE datumtijd >= '".$game_start_time->format('Y-m-d H:i:s')."' AND datumtijd <= '".$game_end_time->format('Y-m-d H:i:s')."' ORDER BY datumtijd ASC";
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
    z-index: 10;
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

<!-- Sidebar/menu --><nav class="w3-sidebar w3-collapse w3-white w3-animate-left" style="z-index:3;width:200px;" id="mySidebar"><br>
  <div class="w3-container w3-row">
    <div class="w3-col s4">
      <img src="media/geusje.png" class="w3-margin-right" style="width:46px">
    </div>
    <div class="w3-col s8 w3-bar">
      <span>Welkom, <strong><?php echo ucfirst($vn); ?></strong></span><br>
      <a href="index" class="w3-bar-item w3-button"><i class="fas fa-sign-out-alt"></i></a>
      <a href="functies?gpstoggle=true&return=vossen" class="w3-bar-item w3-button <?php if (($_SESSION['gps'] ?? 'false') == "true"){echo "w3-green";}else{echo "w3-red";} ?>"><i class="fas fa-location-arrow"></i></a>
    </div>
  </div>
  <hr>
  <div class="w3-container">
    <h5>Dashboard</h5>
  </div>
  <div class="w3-bar-block">
    <a href="#" class="w3-bar-item w3-button w3-padding-16 w3-hide-large w3-dark-grey w3-hover-black" onclick="w3_close()" title="close menu"><i class="fa fa-remove fa-fw"></i>  Sluit Menu</a>
    <a href="home" class="w3-bar-item w3-button w3-padding"><i class="fa fa-users fa-fw"></i>  Overzicht</a>
    <?php if ($priv > 0){echo '<a href="kaarten" class="w3-bar-item w3-button w3-padding"><i class="fas fa-map-marked-alt fa-fw"></i>  Kaarten</a>';}?>
    <?php if ($priv > 0){echo '<a href="hunts" class="w3-bar-item w3-button w3-padding"><i class="fas fa-map-marker-alt fa-fw"></i>  Hunt!</a>';}?>
    <?php if ($priv > 0){echo '<a href="vossen" class="w3-bar-item w3-button w3-padding w3-blue"><i class="fas fa-bullseye fa-fw"></i>  Vossen</a>';}?>
    <a href="nieuws" class="w3-bar-item w3-button w3-padding"><i class="far fa-newspaper fa-fw"></i>  Nieuws</a>
    <a href="opdrachten" class="w3-bar-item w3-button w3-padding"><i class="far fa-bell fa-fw"></i>  Opdrachten</a>
    <a href="hints" class="w3-bar-item w3-button w3-padding"><i class="fas fa-question-circle fa-fw"></i>  Hints</a>
    <?php if ($priv > 0){echo '<a href="punten" class="w3-bar-item w3-button w3-padding"><i class="fas fa-trophy fa-fw"></i>  Punten</a>';}?>
    <a href="groepen" class="w3-bar-item w3-button w3-padding"><i class="fas fa-home fa-fw"></i>  Groepen</a>
    <a href="instellingen" class="w3-bar-item w3-button w3-padding"><i class="fas fa-cog fa-fw"></i>  Instellingen</a>
    <?php if ($priv > 0){echo '<a href="autos" class="w3-bar-item w3-button w3-padding"><i class="fas fa-car fa-fw"></i>  Auto\'s</a>';}?>
    <?php if ($priv > 1){echo '<a href="admin/users" class="w3-bar-item w3-button w3-padding"><i class="fas fa-user-cog fa-fw"></i>  [Admin] Users</a>';} ?>
    <?php if ($priv > 1){echo '<a href="admin/cronjobs" class="w3-bar-item w3-button w3-padding"><i class="fas fa-stopwatch fa-fw"></i>  [Admin] Cronjobs</a>';} ?>
    <?php if ($priv > 1){echo '<a href="admin/database" class="w3-bar-item w3-button w3-padding"><i class="fas fa-database fa-fw"></i>  [Admin] Database</a>';} ?>
    <?php if ($priv > 2){echo '<a href="admin/settings" class="w3-bar-item w3-button w3-padding"><i class="fas fa-toolbox fa-fw"></i>  [Admin] Settings</a>';} ?><br><br>
  </div>
</nav>

<!-- Overlay effect when opening sidebar on small screens --><div class="w3-overlay w3-hide-large w3-animate-opacity" onclick="w3_close()" style="cursor:pointer" title="close side menu" id="myOverlay"></div>

<!-- !PAGE CONTENT! --><div class="w3-main" style="margin-left:200px;margin-top:43px;">

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

  <!-- Footer --><footer class="w3-container w3-padding-16 w3-dark-grey">
    <center><p><a href="#">Niels Maarleveld</a> - &copy; <?php echo date("Y");?></p>
  </footer>

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

