<?php
define("PAGE_NAME", "instellingen");
session_start();

if (!isset($_SESSION['id'])){

  header("Location: index");

}

require("dblogin.php");
require_once("functies.php");


// get userdata
$stmt = $conn->prepare("SELECT * FROM Gebruikers WHERE id=?");
$stmt->bind_param("i", $_SESSION['id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
      $vn = $row['voornaam'];
      $an = $row['achternaam'];
      $email = $row['email'];
      $api = $row['api'];
      $priv = $row['priv'];
      $username = $row['gebruikersnaam'];
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

?>

<!DOCTYPE html>
<html lang="nl">
<head>
<title>Jotihunt - Instellingen</title>
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


    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      
      <!-- Gegevens Wijzigen -->
      <div class="theme-card rounded border shadow-sm p-5">
        <?php if (isset($_GET['t']) && $_GET['t'] == "gegevens"): ?>
          <div class="bg-blue-100 text-blue-800 p-3 rounded mb-4 flex justify-between items-start">
            <p class="text-sm font-medium"><?= htmlspecialchars($_GET['e']) ?></p>
            <button onclick="this.parentElement.style.display='none'" class="text-blue-500 hover:text-blue-800"><i class="fas fa-times"></i></button>
          </div>
        <?php endif; ?>
        <form method="POST" action="instellingen_helper.php">
          <h3 class="text-lg font-bold mb-4">Gegevens wijzigen</h3>
          
          <div class="space-y-3">
            <div>
              <label class="block text-sm font-semibold mb-1 opacity-80">Gebruikersnaam</label>
              <input name="username" type="text" value="<?= htmlspecialchars($username) ?>" required minlength="5" maxlength="32" class="w-full border rounded px-3 py-2 text-gray-800 outline-none focus:ring-1 focus:ring-blue-500">
            </div>
            <div>
              <label class="block text-sm font-semibold mb-1 opacity-80">Voornaam</label>
              <input name="firstname" type="text" value="<?= htmlspecialchars(ucfirst($vn)) ?>" required class="w-full border rounded px-3 py-2 text-gray-800 outline-none focus:ring-1 focus:ring-blue-500">
            </div>
            <div>
              <label class="block text-sm font-semibold mb-1 opacity-80">Achternaam</label>
              <input name="lastname" type="text" value="<?= htmlspecialchars(ucfirst($an)) ?>" required class="w-full border rounded px-3 py-2 text-gray-800 outline-none focus:ring-1 focus:ring-blue-500">
            </div>
            <div>
              <label class="block text-sm font-semibold mb-1 opacity-80">Email</label>
              <input name="email" type="email" value="<?= htmlspecialchars($email) ?>" required class="w-full border rounded px-3 py-2 text-gray-800 outline-none focus:ring-1 focus:ring-blue-500">
            </div>
          </div>
          
          <div class="mt-5 text-center">
            <button type="submit" class="theme-bg-primary text-white font-bold py-2 px-6 rounded hover:opacity-90 transition shadow-sm">Verander</button>
          </div>
        </form>
      </div>

      <!-- Wachtwoord Wijzigen -->
      <div class="theme-card rounded border shadow-sm p-5">
        <?php if (isset($_GET['t']) && $_GET['t'] == "wachtwoord"): ?>
          <div class="bg-blue-100 text-blue-800 p-3 rounded mb-4 flex justify-between items-start">
            <p class="text-sm font-medium"><?= htmlspecialchars($_GET['e']) ?></p>
            <button onclick="this.parentElement.style.display='none'" class="text-blue-500 hover:text-blue-800"><i class="fas fa-times"></i></button>
          </div>
        <?php endif; ?>
        <form method="POST" action="instellingen_helper.php">
          <h3 class="text-lg font-bold mb-4">Wachtwoord wijzigen</h3>
          
          <div class="space-y-3">
            <div>
              <label class="block text-sm font-semibold mb-1 opacity-80">Wachtwoord</label>
              <input name="pswd0" type="password" placeholder="Nieuw Wachtwoord" required minlength="8" class="w-full border rounded px-3 py-2 text-gray-800 outline-none focus:ring-1 focus:ring-blue-500">
            </div>
            <div>
              <label class="block text-sm font-semibold mb-1 opacity-80">Herhaal Wachtwoord</label>
              <input name="pswd1" type="password" placeholder="Herhaal Wachtwoord" required minlength="8" class="w-full border rounded px-3 py-2 text-gray-800 outline-none focus:ring-1 focus:ring-blue-500">
            </div>
          </div>
          
          <div class="mt-5 text-center">
            <button type="submit" class="theme-bg-primary text-white font-bold py-2 px-6 rounded hover:opacity-90 transition shadow-sm">Verander</button>
          </div>
        </form>
      </div>

      <!-- Theme Switcher -->
      <div class="theme-card rounded border shadow-sm p-5">
        <h3 class="text-lg font-bold mb-4">Thema Voorkeur</h3>
        <p class="text-sm opacity-80 mb-4">Kies je favoriete kleurenschema. Deze wordt opgeslagen in je profiel en geladen op alle apparaten.</p>
        
        <div class="space-y-3">
          <select onchange="changeTheme(this.value)" class="w-full border rounded px-3 py-2 text-gray-800 outline-none focus:ring-1 focus:ring-blue-500 shadow-sm cursor-pointer">
            <option value="light" <?= $theme == 'light' ? 'selected' : '' ?>>Light</option>
            <option value="dark" <?= $theme == 'dark' ? 'selected' : '' ?>>Dark</option>
            <option value="rose-gold" <?= $theme == 'rose-gold' ? 'selected' : '' ?>>Rose Gold</option>
            <option value="cyber" <?= $theme == 'cyber' ? 'selected' : '' ?>>Cyber</option>
            <option value="nature" <?= $theme == 'nature' ? 'selected' : '' ?>>Nature</option>
            <option value="coral" <?= $theme == 'coral' ? 'selected' : '' ?>>Coral</option>
          </select>
        </div>
        
        <script>
        function changeTheme(newTheme) {
            var xhttp = new XMLHttpRequest();
            xhttp.onreadystatechange = function() {
                if (this.readyState == 4 && this.status == 200) {
                    window.location.href = window.location.pathname + window.location.search;
                }
            };
            xhttp.open("GET", "<?= $notInAdminfolder ?>functies.php?set_theme=" + newTheme, true);
            xhttp.send();
        }
        </script>
      </div>

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

     console.log("Latitude: " + position.coords.latitude + 

      "<br>Longitude: " + position.coords.longitude);

      

      

      if (window.XMLHttpRequest) {

            // code for IE7+, Firefox, Chrome, Opera, Safari

            xmlhttp = new XMLHttpRequest();

        } else {

            // code for IE6, IE5

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