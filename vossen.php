<?php
define("PAGE_NAME", "vossen");

session_start();

if (!isset($_SESSION['id'])) {
    header("Location: index");
    exit();
}

require("dblogin.php");
require_once("functies.php");

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

$siteSettings = array();

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
      $siteSettings[$row['Instelling']] = $row['Waarde'];
    }
}
$stmt->close();

// Fetch game and fox exchange times from settings
$stmt = $conn->prepare("SELECT Instelling, Waarde FROM Site_Instellingen WHERE Instelling IN ('GAME_STARTDATE', 'GAME_ENDDATE', 'FOXEXCHANGE_STARTDATE', 'FOXEXCHANGE_ENDDATE')");
$stmt->execute();
$result = $stmt->get_result();

$settings = [];

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $settings[$row['Instelling']] = $row['Waarde'];
    }
}
$stmt->close();

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
$stmt = $conn->prepare("SELECT * FROM Voslog WHERE datumtijd >= ? AND datumtijd <= ? ORDER BY datumtijd ASC");

// Assign the method outputs to variables first
$start_fmt = $game_start_time->format('Y-m-d H-i-s');
$end_fmt = $game_end_time->format('Y-m-d H-i-s');

// Pass the variables to bind_param
$stmt->bind_param("ss", $start_fmt, $end_fmt);

$stmt->execute();
$result = $stmt->get_result();

$voslog_data_assoc = [];

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $dt = $row['datumtijd'];
        if (!isset($voslog_data_assoc[$dt])) {
            $voslog_data_assoc[$dt] = ['datumtijd' => $dt];
        }
        $voslog_data_assoc[$dt][$row['vos']] = $row['status'];
    }
}
$voslog_data = array_values($voslog_data_assoc);
$stmt->close();

// --- FETCH HUNTS DATA ---
$stmt_hunts = $conn->prepare("SELECT ingestuurd_op, deelgebied FROM Voslocaties WHERE type = 'Hunt' AND status IN ('Correct', 'HAPPY HOUR !') AND ingestuurd_op >= ? AND ingestuurd_op <= ?");
$stmt_hunts->bind_param("ss", $start_fmt, $end_fmt);
$stmt_hunts->execute();
$result_hunts = $stmt_hunts->get_result();

$hunts_data = [];
if ($result_hunts->num_rows > 0) {
    while($row = $result_hunts->fetch_assoc()) {
        $hunt_team = strtolower($row['deelgebied']);
        if (!isset($hunts_data[$hunt_team])) {
            $hunts_data[$hunt_team] = [];
        }
        $hunts_data[$hunt_team][] = $row['ingestuurd_op'];
    }
}
$stmt_hunts->close();
// ------------------------

$fox_teams = $vossen_names;
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
    // Array uitgebreid met 'hunts' teller
    $stats[$team] = [
        'spelhelft1' => [0 => 0, 1 => 0, 2 => 0, 'total' => 0, 'hunts' => 0],
        'spelhelft2' => [0 => 0, 1 => 0, 2 => 0, 'total' => 0, 'hunts' => 0],
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
                $stats[$team][$spelhelft]['total'] += $duration1;

                $spelhelft = 'spelhelft2'; // The rest is in the next half
                $duration2 = $segment_end_time->getTimestamp() - $spelhelft_end->getTimestamp();
                 $stats[$team][$spelhelft][$last_status] += $duration2;
                $stats[$team][$spelhelft]['total'] += $duration2;
            } else {
                $stats[$team][$spelhelft][$last_status] += $duration;
                $stats[$team][$spelhelft]['total'] += $duration;
            }
        }
        
        $last_time = $segment_end_time;
        $last_status = $log[$team] ?? $last_status; 
    }

    // --- NIEUW: Tellen van de hunts per spelhelft ---
    $team_lower = strtolower($team);
    if (isset($hunts_data[$team_lower])) {
        foreach ($hunts_data[$team_lower] as $hunt_time_str) {
            $hunt_time = new DateTime($hunt_time_str);
            // Alleen tellen als de hunt echt in de speeltijd valt
            if ($hunt_time >= $game_start_time && $hunt_time <= $game_end_time) {
                if ($hunt_time < $fox_exchange_end_time) {
                    $stats[$team]['spelhelft1']['hunts']++;
                } else {
                    $stats[$team]['spelhelft2']['hunts']++;
                }
            }
        }
    }
}


?>
<!DOCTYPE html>
<html lang="nl">
<head>
<title>Jotihunt - Vossen</title>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="shortcut icon" type="image/png" href="media/geusje.png"/>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://kit.fontawesome.com/870ab34ea3.js" crossorigin="anonymous"></script>
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<?php include_once('includes/theme.php'); ?>
<style>
.timeline-container {
    display: flex;
    width: 100%;
    height: 30px;
    background-color: var(--theme-card-bg);
    border-radius: 4px;
    overflow: hidden;
    position: relative;
    border: 1px solid var(--theme-card-border);
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
    background-color: #334155;
    color: #fff;
    text-align: center;
    border-radius: 6px;
    padding: 5px 0;
    position: absolute;
    z-index: 10;
    bottom: 125%;
    left: 50%;
    margin-left: -80px;
    opacity: 0;
    transition: opacity 0.3s;
    font-size: 0.75rem;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}
.timeline-segment .tooltiptext::after {
    content: "";
    position: absolute;
    top: 100%;
    left: 50%;
    margin-left: -5px;
    border-width: 5px;
    border-style: solid;
    border-color: #334155 transparent transparent transparent;
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
    background-color: #3b82f6; /* Tailwind blue-500 */
    z-index: 1;
    /* Responsive position calculated using a CSS variable set in PHP */
    /* Mobile-first: for 1/6 columns (16.667%) */
    left: calc(16.66667% + (83.33333% / 100 * var(--now-percentage)));
}
/* For large screens, override with 1/12 column width (8.333%) */
@media (min-width: 1024px) {
    .now-indicator {
        left: calc(8.33333% + (91.66667% / 100 * var(--now-percentage)));
    }
}
.now-indicator .tooltiptext {
    visibility: hidden;
    width: 100px;
    background-color: #334155;
    color: #fff;
    text-align: center;
    border-radius: 6px;
    padding: 5px;
    position: absolute;
    z-index: 10;
    bottom: 100%;
    left: 50%;
    margin-left: -50px;
    margin-bottom: 5px;
    opacity: 0;
    transition: opacity 0.3s;
    font-size: 0.75rem;
}
.now-indicator:hover .tooltiptext {
    visibility: visible;
    opacity: 1;
}

.hunt-immune-block {
    position: absolute;
    top: 0;
    bottom: 0;
    background: repeating-linear-gradient(
      45deg,
      rgba(100, 116, 139, 0.4),
      rgba(100, 116, 139, 0.4) 8px,
      rgba(100, 116, 139, 0.1) 8px,
      rgba(100, 116, 139, 0.1) 16px
    );
    z-index: 5;
    pointer-events: none;
}

.hunt-line {
    position: absolute;
    top: 0;
    bottom: 0;
    width: 2px;
    background-color: #1e293b;
    z-index: 6;
    cursor: help;
}

.hunt-line .tooltiptext {
    visibility: hidden;
    width: 90px;
    background-color: #1e293b;
    color: #fff;
    text-align: center;
    border-radius: 6px;
    padding: 5px;
    position: absolute;
    z-index: 10;
    bottom: 100%;
    left: 50%;
    margin-left: -45px;
    margin-bottom: 5px;
    opacity: 0;
    transition: opacity 0.3s;
    font-size: 0.75rem;
    pointer-events: none;
}

.hunt-line:hover .tooltiptext {
    visibility: visible;
    opacity: 1;
}

/* Custom Status Colors for Timeline */
.status-red { background-color: #ef4444; }
.status-orange { background-color: #f97316; }
.status-green { background-color: #22c55e; }
.status-future { background-color: #e2e8f0; opacity: 0.5; }
</style>
</head>
<body class="flex h-screen overflow-hidden">

<!-- Sidebar -->
<?php include_once('includes/sidebar.php') ?>

<!-- Main Content -->
<div class="flex-1 flex flex-col h-screen overflow-y-auto w-full relative">
  <!-- Topbar -->
  <?php include_once('includes/topbar.php') ?>

  <main class="p-4 md:p-6 max-w-[1400px] mx-auto w-full flex-1">


    <div class="space-y-6">
      
      <!-- Timeline Card -->
      <div class="theme-card rounded border shadow-sm overflow-hidden">
        <div class="theme-card-header px-6 py-4 border-b text-white flex justify-between items-center" style="background-color: var(--theme-sidebar-active); border-color: var(--theme-card-border);">
            <h5 class="text-lg font-bold">Vossen Status Tijdlijn</h5>
        </div>
        <div class="p-4 md:p-6">
            <div class="relative space-y-2">
                <?php 
                $status_tailwind_colors = [
                    0 => 'status-red',
                    1 => 'status-orange',
                    2 => 'status-green'
                ];
                $future_tailwind_color = 'status-future';

                foreach ($fox_teams as $team): ?>
                    <div class="flex items-center h-10 w-full">
                        <div class="w-1/6 lg:w-1/12 text-right pr-3 sm:pr-4">
                            <b class="text-sm md:text-base"><?php echo ucfirst($team); ?></b>
                        </div>
                        <div class="w-5/6 lg:w-11/12">
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
                                        $color = $status_tailwind_colors[$last_status] ?? 'bg-gray-400';
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
                                        echo "<div class='timeline-segment $future_tailwind_color' style='width: $width_percentage%;'></div>";
                                    }
                                }

                                $team_lower = strtolower($team);
                                if (isset($hunts_data[$team_lower])) {
                                    foreach ($hunts_data[$team_lower] as $hunt_time_str) {
                                        $hunt_time = new DateTime($hunt_time_str);
                                        $hunt_offset_seconds = $hunt_time->getTimestamp() - $game_start_time->getTimestamp();
                                        
                                        if ($hunt_offset_seconds >= 0 && $hunt_offset_seconds <= $total_duration_seconds) {
                                            $left_perc = ($hunt_offset_seconds / $total_duration_seconds) * 100;
                                            $immune_dur = min(3600, $total_duration_seconds - $hunt_offset_seconds);
                                            $width_perc = ($immune_dur / $total_duration_seconds) * 100;
                                            
                                            echo "<div class='hunt-immune-block' style='left: {$left_perc}%; width: {$width_perc}%;'></div>";
                                            echo "<div class='hunt-line' style='left: {$left_perc}%;'><span class='tooltiptext'>Hunt: ".$hunt_time->format('H:i')."</span></div>";
                                        }
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
                    echo "<div class='now-indicator' style='--now-percentage: {$left_percentage};'><span class='tooltiptext'>Nu: ".$now->format('H:i')."</span></div>";
                }
                ?>
            </div>
             <div class="mt-8 flex flex-wrap items-center gap-3 text-sm font-medium">
                <span class="bg-green-500 text-white px-2 py-1 rounded shadow-sm">Lopend</span>
                <span class="bg-orange-500 text-white px-2 py-1 rounded shadow-sm">Kleine verplaatsing</span>
                <span class="bg-red-500 text-white px-2 py-1 rounded shadow-sm">Grote verplaatsing</span>
                <div class="flex items-center gap-2 ml-4">
                  <span class="inline-block w-1 h-6 bg-blue-500 shadow-sm rounded-full"></span> <span>Nu</span>
                </div>
                <div class="flex items-center gap-2 ml-2">
                    <span class="inline-block w-6 h-6 rounded shadow-sm border-l-2 border-slate-800" style="background: repeating-linear-gradient(45deg, rgba(100, 116, 139, 0.4), rgba(100, 116, 139, 0.4) 4px, rgba(100, 116, 139, 0.1) 4px, rgba(100, 116, 139, 0.1) 8px);"></span>
                    <span>Hunt (1 uur immuniteit)</span>
                </div>
            </div>
        </div>
      </div>

      <!-- Statistics Card -->
      <div class="theme-card rounded border shadow-sm overflow-hidden">
        <div class="theme-card-header px-6 py-4 border-b text-white" style="background-color: var(--theme-sidebar-active); border-color: var(--theme-card-border);">
            <h5 class="text-lg font-bold">Vossen Statistieken</h5>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="text-xs uppercase opacity-80" style="background-color: var(--theme-card-header);">
                    <tr>
                        <th class="px-4 py-3">Vos</th>
                        <th class="px-4 py-3 text-center">Spelhelft</th>
                        <th class="px-4 py-3 text-center">Hunts</th>
                        <th class="px-4 py-3 text-center text-green-600 dark:text-green-400">Lopend</th>
                        <th class="px-4 py-3 text-center text-orange-600 dark:text-orange-400">Kleine Verpl.</th>
                        <th class="px-4 py-3 text-center text-red-600 dark:text-red-400">Grote Verpl.</th>
                    </tr>
                </thead>
                <tbody class="divide-y" style="border-color: var(--theme-card-border);">
                    <?php foreach ($fox_teams as $team): ?>
                        <tr class="hover:bg-black/5 transition">
                            <td rowspan="2" class="px-4 py-3 font-bold border-b align-middle border-r" style="border-color: var(--theme-card-border);"><?php echo ucfirst($team); ?></td>
                            <td class="px-4 py-3 text-center border-b font-medium" style="border-color: var(--theme-card-border);">Spelhelft 1</td>
                            <td class="px-4 py-3 text-center border-b font-bold text-lg" style="border-color: var(--theme-card-border);">
                                <?php echo $stats[$team]['spelhelft1']['hunts']; ?>
                            </td>
                            <td class="px-4 py-3 text-center border-b" style="border-color: var(--theme-card-border);">
                                <span class="font-semibold"><?php echo format_seconds($stats[$team]['spelhelft1'][2]); ?></span><br>
                                <span class="text-xs opacity-70">(<?php echo $stats[$team]['spelhelft1']['total'] > 0 ? round($stats[$team]['spelhelft1'][2] / $stats[$team]['spelhelft1']['total'] * 100, 1) : 0; ?>%)</span>
                            </td>
                            <td class="px-4 py-3 text-center border-b" style="border-color: var(--theme-card-border);">
                                <span class="font-semibold"><?php echo format_seconds($stats[$team]['spelhelft1'][1]); ?></span><br>
                                <span class="text-xs opacity-70">(<?php echo $stats[$team]['spelhelft1']['total'] > 0 ? round($stats[$team]['spelhelft1'][1] / $stats[$team]['spelhelft1']['total'] * 100, 1) : 0; ?>%)</span>
                            </td>
                            <td class="px-4 py-3 text-center border-b" style="border-color: var(--theme-card-border);">
                                <span class="font-semibold"><?php echo format_seconds($stats[$team]['spelhelft1'][0]); ?></span><br>
                                <span class="text-xs opacity-70">(<?php echo $stats[$team]['spelhelft1']['total'] > 0 ? round($stats[$team]['spelhelft1'][0] / $stats[$team]['spelhelft1']['total'] * 100, 1) : 0; ?>%)</span>
                            </td>
                        </tr>
                        <tr class="hover:bg-black/5 transition">
                            <td class="px-4 py-3 text-center font-medium">Spelhelft 2</td>
                            <td class="px-4 py-3 text-center font-bold text-lg">
                                <?php echo $stats[$team]['spelhelft2']['hunts']; ?>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="font-semibold"><?php echo format_seconds($stats[$team]['spelhelft2'][2]); ?></span><br>
                                <span class="text-xs opacity-70">(<?php echo $stats[$team]['spelhelft2']['total'] > 0 ? round($stats[$team]['spelhelft2'][2] / $stats[$team]['spelhelft2']['total'] * 100, 1) : 0; ?>%)</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="font-semibold"><?php echo format_seconds($stats[$team]['spelhelft2'][1]); ?></span><br>
                                <span class="text-xs opacity-70">(<?php echo $stats[$team]['spelhelft2']['total'] > 0 ? round($stats[$team]['spelhelft2'][1] / $stats[$team]['spelhelft2']['total'] * 100, 1) : 0; ?>%)</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="font-semibold"><?php echo format_seconds($stats[$team]['spelhelft2'][0]); ?></span><br>
                                <span class="text-xs opacity-70">(<?php echo $stats[$team]['spelhelft2']['total'] > 0 ? round($stats[$team]['spelhelft2'][0] / $stats[$team]['spelhelft2']['total'] * 100, 1) : 0; ?>%)</span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
      </div>
    </div>
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
</script>
</body>
</html>