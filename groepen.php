<?php
define("PAGE_NAME", "groepen");
session_start();

if (!isset($_SESSION['id'])){

  header("Location: index");

}

require("dblogin.php");
require_once("functies.php");


// Get userdata
$stmt = $conn->prepare("SELECT * FROM Gebruikers WHERE id=?");
$stmt->bind_param("i", $_SESSION['id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
      $vn = $row['voornaam'];
      $priv = $row['priv'];

      if ($row['lat']) {
        $usr_lat = $row['lat'];
        $usr_lon = $row['lon'];
      } else {
        // LAT LON van RB bij geen persoonlijke latlon
        $usr_lat = 51.98769228691746;
        $usr_lon = 5.876286397679744;
      }
    }
}
$stmt->close();


// Get global site settings
$siteSettings = array();
$stmt = $conn->prepare("SELECT * FROM Site_Instellingen");
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
  while($row = $result->fetch_assoc()) {
    $siteSettings[$row['Instelling']] = $row['Waarde'];
  }
}
$stmt->close();

?>

<!DOCTYPE html>
<html lang="nl">
<head>
<title>Jotify - Groepen</title>
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


    <div class="mb-6 sticky top-[4.5rem] z-20">
      <div class="theme-card rounded border shadow-sm p-4 flex justify-between gap-4 items-center">
        <input id="tableSearchInput" oninput="tableSearch()" class="flex-grow border rounded px-3 py-2 text-gray-800 outline-none focus:ring-1 focus:ring-blue-500 shadow-sm" type="text" placeholder="Zoek op naam...">
        <div class="flex space-x-2 sm:space-x-4">
          <button id="meta-distance-sorter" class="w-10 h-10 rounded theme-bg-primary text-white flex items-center justify-center hover:opacity-90 transition shadow-sm" onclick="sortTable('meta-distance')" title="Sorteer op afstand">
            <i class="fas fa-ruler"></i>
          </button>
          <button id="meta-name-sorter" class="w-10 h-10 rounded theme-bg-primary text-white flex items-center justify-center hover:opacity-90 transition shadow-sm" onclick="sortTable('meta-name')" title="Sorteer op naam">
            <i class="fas fa-font"></i>
          </button>
          <button id="meta-subarea-sorter" class="w-10 h-10 rounded theme-bg-primary text-white flex items-center justify-center hover:opacity-90 transition shadow-sm" onclick="sortTable('meta-subarea')" title="Sorteer op deelgebied">
            <i class="fas fa-draw-polygon"></i>
          </button>
        </div>
      </div>
    </div>

    <?php
    // Get all scout groups
    $stmt = $conn->prepare("SELECT * FROM Groepen ORDER BY naam DESC");
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
      echo '<div class="theme-card rounded border shadow-sm overflow-hidden">';
      echo '<ul id="tableSearchTable" class="divide-y" style="border-color: var(--theme-card-border);">';
      while($row = $result->fetch_assoc()) {
        $color = getFoxColor(ucfirst($row['deelgebied']));
        $distance = round(latlon_dist($row['lat'], $row['lon'], $usr_lat, $usr_lon)/1000, 1);
        
        echo '<li class="p-4 md:p-6 hover:bg-black/5 transition" meta-name="'.htmlspecialchars($row['naam']).'" meta-subarea="'.htmlspecialchars($row['deelgebied']).'" meta-distance="'.latlon_dist($row['lat'], $row['lon'], $usr_lat, $usr_lon).'">';
        
        echo '  <div class="theme-card-header text-white px-4 py-2 rounded mb-4 flex items-center shadow-sm" style="background-color: var(--theme-sidebar-active);">';
        echo '    <span class="text-xs font-bold px-2 py-1 rounded text-black uppercase tracking-wider shadow-sm" style="background-color:'.$color.'">'.htmlspecialchars($row['deelgebied']).'</span>';
        echo '    <span class="ml-4 font-bold text-lg hidden sm:block">'.htmlspecialchars($row['naam']).'</span>';
        echo '    <span class="ml-auto font-bold sm:hidden">'.htmlspecialchars($row['naam']).'</span>';
        echo '  </div>';

        echo '  <div class="flex flex-wrap items-center justify-between gap-4 md:gap-6">';
        
        echo '    <div class="w-16 h-16 flex-shrink-0 bg-white rounded border overflow-hidden flex items-center justify-center shadow-sm">';
        echo '      <img src="'.htmlspecialchars($row['url']).'" class="w-full h-auto object-contain" onerror="this.src=\'media/scoutingLogo.png\'">';
        echo '    </div>';

        echo '    <div class="flex-grow min-w-[200px] text-sm space-y-1 opacity-80">';
        echo '      <p><i class="fas fa-map-pin fa-fw opacity-70"></i> <span>'.htmlspecialchars($row['lat']).', '.htmlspecialchars($row['lon']).'</span></p>';
        echo '      <p><i class="fas fa-map-marked fa-fw opacity-70"></i> <span>'.htmlspecialchars(ucfirst($row['straat'])).' '.htmlspecialchars($row['huisnummer']).', '.htmlspecialchars(ucfirst($row['plaats'])).'</span></p>';
        echo '      <p><i class="fas fa-ruler fa-fw opacity-70"></i> <span>'.$distance.'km</span></p>';
        echo '    </div>';

        echo '    <div class="flex gap-2 w-full md:w-auto mt-2 md:mt-0">';
        echo '      <a href="#" class="flex-1 md:flex-none text-center bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded text-sm font-semibold transition shadow-sm">Tegenhunt</a>';
        echo '      <a href="http://www.google.com/maps/dir/?api=1&destination='.urlencode($row['straat'].' '.$row['huisnummer'].', '.$row['plaats']).'&travelmode=driving" target="_blank" class="flex-1 md:flex-none text-center theme-bg-primary hover:opacity-90 text-white px-4 py-2 rounded text-sm font-semibold transition shadow-sm">Navigeer</a>';
        echo '      <a href="http://www.google.com/maps/search/?api=1&query='.$row['lat'].','.$row['lon'].'" target="_blank" class="flex-1 md:flex-none text-center bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded text-sm font-semibold transition shadow-sm">Maps</a>';
        echo '    </div>';

        echo '  </div>';
        echo '</li>';
      }
      echo "</ul>";
      echo "</div>";
    } else {
        echo "<div class='theme-card rounded border p-6 text-center opacity-70'>Geen groepen gevonden.</div>";
    }
    $stmt->close();
    ?>

  </main>

  <!-- Footer -->
  <?php require_once('includes/footer.php') ?>
</div>

<script>
// search function for groups
function tableSearch() {
  var input, ul, items, metaName;
  input = document.getElementById("tableSearchInput");
  ul = document.getElementById("tableSearchTable");
  items = ul.getElementsByTagName("li");

  // Loop through all LI's, and hide those who don't match the search query
  for (var i = 0; i < items.length; i++) {
    metaName = items[i].getAttribute("meta-name").toUpperCase();
    if (metaName.includes(input.value.toUpperCase())) {
      items[i].style.display = "";
    } else {
      items[i].style.display = "none";
    }
  }
}

function sortTable(metaType) {
  var table, rows, switching, i, x, y, shouldSwitch;
  table = document.getElementById("tableSearchTable");
  switching = true;

  while (switching) {
    switching = false;
    rows = table.getElementsByTagName("li");

    for (i = 0; i < (rows.length - 1); i++) {
      shouldSwitch = false;
      x = rows[i].getAttribute(metaType);
      y = rows[i + 1].getAttribute(metaType);

      if (metaType == "meta-distance") {
        if (parseFloat(x) > parseFloat(y)) {
          shouldSwitch = true;
          break;
        }
      } else {
        if (x.toLowerCase() > y.toLowerCase()) {
          shouldSwitch = true;
          break;
        }
      }
    }
    if (shouldSwitch) {
      rows[i].parentNode.insertBefore(rows[i + 1], rows[i]);
      switching = true;
    }
  }
}
</script>

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