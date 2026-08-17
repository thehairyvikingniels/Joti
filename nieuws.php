<?php
define("PAGE_NAME", "nieuws");

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

$siteSettings = array();

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
<title>Jotify - Nieuws</title>
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


    <div class="space-y-6">
    <?php
    // Get news items
    $stmt = $conn->prepare("SELECT * FROM Nieuws ORDER BY datum DESC");
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
      echo '<div class="space-y-6">';

      while($row = $result->fetch_assoc()) {
        $content = $row['inhoud'];
        $doc=new DOMDocument();
        @$doc->loadHTML('<?xml encoding="utf-8" ?>' . $content); // Ensure UTF-8 is parsed correctly
        $imgNodes = $doc->getElementsByTagName('img');

        foreach($imgNodes as $node) {
          // Tailwind responsive image
          $existingClass = $node->getAttribute('class');
          $node->setAttribute('class', trim($existingClass . ' max-w-full h-auto rounded-lg shadow-sm'));
          $node->removeAttribute('width');
          $node->removeAttribute('height');
        }

        // Get inner HTML of the loaded doc, skipping html and body tags
        $bodyNodes = $doc->getElementsByTagName('body');
        $htmlContent = '';
        if ($bodyNodes->length > 0) {
            foreach ($bodyNodes->item(0)->childNodes as $child) {
                $htmlContent .= $doc->saveHTML($child);
            }
        } else {
            $htmlContent = $content;
        }

        echo '
        <article class="theme-card rounded border shadow-sm overflow-hidden" id="nieuws-'.$row['id'].'">
          <header class="theme-card-header px-6 py-4 border-b text-white flex justify-between items-center" style="background-color: var(--theme-sidebar-active); border-color: var(--theme-card-border);">
            <h3 class="text-xl font-bold">'.$row['titel'].'</h3>
            <span class="text-sm font-medium opacity-80">'.time2str($row['datum']).'</span>
          </header>
          <div class="p-6 prose max-w-none prose-img:rounded-xl prose-a:text-blue-600 hover:prose-a:text-blue-500">
            '.$htmlContent.'
          </div>
        </article>
        ';
      }
      echo "</div>";
    } else {
        echo '<div class="theme-card rounded border shadow-sm p-6 text-center opacity-70">
                <i class="far fa-folder-open text-4xl mb-3 block"></i>
                <p>Geen nieuwsberichten gevonden.</p>
              </div>';
    }
    $stmt->close();
    ?>
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