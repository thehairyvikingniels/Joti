<?php
define("PAGE_NAME", "hints");
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


// Insert voslocaties after using hints form
if (isset($_POST['subarea']) && isset($_POST['rdX']) && isset($_POST['rdY'])) {
  $latlon = rdtowgs($_POST['rdX'], $_POST['rdY']);
  $ingestuurd_op = date("Y-m-d H:i:s");
  $code = $_POST['subarea'] . " " . $_POST['rdX'] . " " . $_POST['rdY'];
  $stmt = $conn->prepare("INSERT INTO Voslocaties (ingestuurd_op, type, deelgebied, ingeleverd, coordinaat_x, coordinaat_y, code) VALUES (?, 'Hint', ?, '0', ?, ?, ?)");
  $stmt->bind_param("sssss", $ingestuurd_op, $_POST['subarea'], $latlon["lat"], $latlon["lon"], $code);

  if ($stmt->execute()) {
    echo "New record created successfully";
  } else {
    echo "Error: " . $stmt->error;
  }
  $stmt->close();
}


?>
<!DOCTYPE html>
<html lang="nl">
<head>
<title>Jotihunt - Hints</title>
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
    <!-- Header -->
    <header class="mb-6">
      <h2 class="text-2xl font-bold"><i class="fas fa-question-circle opacity-70 mr-2"></i>Hints</h2>
    </header>

    <div class="space-y-6 mb-12">
      <?php
        $stmt = $conn->prepare("SELECT * FROM Hints ORDER BY datum DESC");
        $stmt->execute();
        $result = $stmt->get_result();

        $vossen = $vossen_names;

        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
              $content = $row['inhoud'];
              $doc = new DOMDocument();
              @$doc->loadHTML($content);
              $imgNodes = $doc->getElementsByTagName('img');
              foreach($imgNodes as $node) {
                $node->setAttribute('width', '100%');
                $node->removeAttribute('height');
                // Ensure images are responsive
                $classes = $node->getAttribute('class');
                $node->setAttribute('class', $classes . ' rounded my-4 w-full object-cover max-h-[500px]');
              }
              
              echo '
              <div class="theme-card rounded border shadow-sm overflow-hidden">
                <div class="theme-card-header px-6 py-4 flex justify-between items-center border-b" style="border-color: var(--theme-card-border);">
                  <h3 class="text-xl font-bold">'.htmlspecialchars($row['titel']).'</h3>
                  <span class="text-sm opacity-60 font-medium">'.date("d/m H:i", strtotime($row['datum'])).'</span>
                </div>
                
                <div class="p-6 prose max-w-none text-current opacity-90 overflow-x-auto">
                  '.$doc->saveHTML().'
                </div>';
                
                $subareas = $vossen;
                
                echo '<div class="bg-black/5 p-4 border-t grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4" style="border-color: var(--theme-card-border);">';
                foreach($subareas as $key => $subarea) {
                  $unique_id = htmlspecialchars($row['id'] . '_' . $subarea);
                  
                  echo '
                  <form action="hints.php" method="POST" class="flex-1">
                    <div class="theme-card rounded border p-3 flex flex-wrap items-center gap-2 shadow-sm" style="border-color: var(--theme-card-border);">
                      <div class="w-16 flex-shrink-0 text-center font-bold text-xs py-1.5 rounded uppercase tracking-wide shadow-sm" style="background-color:'.htmlspecialchars(getFoxColor($subarea)).'; color: black;">
                        '.ucfirst(htmlspecialchars($subarea)).'
                      </div>
                      
                      <div class="flex-1 flex min-w-[120px] gap-2">
                        <input type="number" class="w-1/2 border rounded px-2 py-1 text-sm text-gray-800 outline-none focus:ring-1 focus:ring-blue-500 shadow-sm" id="rdX_'.$unique_id.'" name="rdX" placeholder="rdX">
                        <input type="number" class="w-1/2 border rounded px-2 py-1 text-sm text-gray-800 outline-none focus:ring-1 focus:ring-blue-500 shadow-sm" id="rdY_'.$unique_id.'" name="rdY" placeholder="rdY">
                      </div>
                      
                      <input type="hidden" id="subarea_'.$unique_id.'" name="subarea" value="'.htmlspecialchars($subarea).'"> 
                      
                      <div class="flex gap-2 w-full sm:w-auto mt-2 sm:mt-0">
                        <button type="button" class="flex-1 sm:flex-none text-xs bg-teal-600 hover:bg-teal-700 text-white font-bold py-1.5 px-3 rounded transition shadow-sm">Probeer</button>
                        <button type="submit" class="flex-1 sm:flex-none text-xs bg-green-600 hover:bg-green-700 text-white font-bold py-1.5 px-3 rounded transition shadow-sm">Opslaan</button>
                      </div>
                    </div>
                  </form>';
                }
              echo '
                </div>
              </div>';
            }
        } else {
            echo "<div class='theme-card rounded border p-8 text-center'><h4 class='text-lg opacity-70'>Nog geen hints beschikbaar...</h4></div>";
        } 
        $stmt->close();
      ?>
    </div>
  </main>

  <!-- Footer -->
  <?php require_once('includes/footer.php') ?>
</div>

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
