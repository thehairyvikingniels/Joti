<?php
// Primary dashboard displaying game score, leaderboard standing, hunt/hint metrics, vehicle statuses, and recent event feeds.
define("PAGE_NAME", "home");
require_once('includes/auth.php');

// Get hints
$stmt = $conn->prepare("SELECT id, count(*) as NUM FROM Voslocaties WHERE type='Hint' GROUP BY ingestuurd_op");
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
  while($row = $result->fetch_assoc()) {
    $hintaantal = $row['NUM'];
  }
} else {
  $hintaantal = "0";
}
$stmt->close();

// Get hunts
$stmt = $conn->prepare("SELECT id, count(*) as NUM FROM Voslocaties WHERE type='Hunt'");
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
  while($row = $result->fetch_assoc()) {
    $huntaantal = $row['NUM'];
  }
} else {
  $huntaantal = "0";
}
$stmt->close();

// Get points for Geuzen
$stmt = $conn->prepare("SELECT * FROM Punten WHERE groep_id = (SELECT id FROM Groepen WHERE naam LIKE '%geuzen%')");
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
  while($row = $result->fetch_assoc()) {
    $puntentotaal = ($row['hunts'] ?? 0) + ($row['tegenhunts'] ?? 0) + ($row['opdrachten'] ?? 0) + ($row['foto_opdrachten'] ?? 0) + ($row['hints'] ?? 0) - ($row['strafpunten'] ?? 0);
    $plaats = $row['plaats'] ?? "?";
  }
}
$stmt->close();
?>

<!DOCTYPE html>

<html>

<title>Jotify - De Geuzen</title>

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

  <!-- Mobile Fox Status -->
  <div class="md:hidden p-4 grid grid-cols-3 sm:grid-cols-4 gap-2">
    <?php
      if (isset($vossen_names)) {
          foreach ($vossen_names as $n) {
            $tw_color = 'bg-gray-200 text-gray-700';
            if ($vos[$n]['Kleur'] == 'red') $tw_color = 'bg-red-500 text-white';
            elseif ($vos[$n]['Kleur'] == 'orange') $tw_color = 'bg-orange-500 text-white';
            elseif ($vos[$n]['Kleur'] == 'green') $tw_color = 'bg-green-500 text-white';
            
            echo '<div class="rounded py-2 px-3 flex items-center justify-center font-bold text-sm shadow-sm '.$tw_color.' whitespace-nowrap"';
            if (isset($vos[$n]["immune_until"])) {
                echo ' style="background-image: repeating-linear-gradient(45deg, rgba(100, 116, 139, 0.4), rgba(100, 116, 139, 0.4) 8px, rgba(100, 116, 139, 0.1) 8px, rgba(100, 116, 139, 0.1) 16px);"';
            }
            echo '>';
            echo '<span class="mr-2">'.htmlspecialchars(substr($n,0,1)).'</span>';
            if (isset($vos[$n]["immune_until"])) {
                $diff = $vos[$n]["immune_until"] - time();
                $initial_text = ($diff > 0) ? floor($diff / 60) . 'm ' . ($diff % 60) . 's' : '0m 0s';
                echo '<span class="immune-countdown" data-until="'.$vos[$n]["immune_until"].'" data-duratie="'.htmlspecialchars($vos[$n]["duratie"]).'">'.$initial_text.'</span>';
            } else {
                echo '<span>'.htmlspecialchars($vos[$n]["duratie"]).'</span>';
            }
            echo '</div>';
          }
      }
    ?>
  </div>

  <main class="p-4 md:p-6 max-w-[1400px] mx-auto w-full flex-1">

      <!-- Stats -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="theme-card rounded p-5 border shadow-sm flex flex-col justify-between">
          <div class="flex justify-between items-center mb-2">
            <p class="text-xs font-semibold uppercase tracking-wider opacity-60">Punten Totaal</p>
            <i class="fas fa-trophy opacity-40"></i>
          </div>
          <h3 class="text-3xl font-bold"><?php echo isset($puntentotaal) ? $puntentotaal : 0; ?></h3>
        </div>
        <div class="theme-card rounded p-5 border shadow-sm flex flex-col justify-between">
          <div class="flex justify-between items-center mb-2">
            <p class="text-xs font-semibold uppercase tracking-wider opacity-60">Huidige Plaats</p>
            <i class="fas fa-star opacity-40"></i>
          </div>
          <h3 class="text-3xl font-bold"><?php echo isset($plaats) ? $plaats : 0; ?><span class="text-xl opacity-60">e</span></h3>
        </div>
        <div class="theme-card rounded p-5 border shadow-sm flex flex-col justify-between">
          <div class="flex justify-between items-center mb-2">
            <p class="text-xs font-semibold uppercase tracking-wider opacity-60">Aantal Hunts</p>
            <i class="fas fa-bullseye opacity-40"></i>
          </div>
          <h3 class="text-3xl font-bold"><?php echo $huntaantal; ?></h3>
        </div>
        <div class="theme-card rounded p-5 border shadow-sm flex flex-col justify-between">
          <div class="flex justify-between items-center mb-2">
            <p class="text-xs font-semibold uppercase tracking-wider opacity-60">Hints Opgestuurd</p>
            <i class="fas fa-question-circle opacity-40"></i>
          </div>
          <h3 class="text-3xl font-bold"><?php echo $hintaantal; ?></h3>
        </div>
      </div>

      <!-- Panels -->
      <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">
        <div class="xl:col-span-1 space-y-6">
          <div class="theme-card rounded border shadow-sm">
            <div class="px-5 py-3 border-b flex justify-between items-center rounded-t" style="border-color: var(--theme-card-border); background: rgba(0,0,0,0.02);">
              <h4 class="font-semibold text-sm">Invulgegevens</h4>
              <button id="invulgegevens_icon" class="opacity-50 hover:opacity-100 transition" onclick="invulgegevens()"><i class="fas fa-sync-alt text-xs"></i></button>
            </div>
            <div id="invulgegevens" class="p-0 overflow-x-auto">
            </div>
          </div>
          <div class="theme-card rounded border shadow-sm">
            <div class="px-5 py-3 border-b flex justify-between items-center rounded-t" style="border-color: var(--theme-card-border); background: rgba(0,0,0,0.02);">
              <h4 class="font-semibold text-sm">Auto's Onderweg</h4>
              <button id="autosonderweg_icon" class="opacity-50 hover:opacity-100 transition" onclick="autosonderweg()"><i class="fas fa-sync-alt text-xs"></i></button>
            </div>
            <div id="autosonderweg" class="p-4">
            </div>
          </div>
        </div>
        <div class="xl:col-span-2">
          <div class="theme-card rounded border shadow-sm h-full flex flex-col">
            <div class="px-5 py-3 border-b flex justify-between items-center rounded-t" style="border-color: var(--theme-card-border); background: rgba(0,0,0,0.02);">
              <h4 class="font-semibold text-sm">Recente Gebeurtenissen</h4>
              <button id="gebeurtenissen_icon" class="opacity-50 hover:opacity-100 transition" onclick="gebeurtenissen()"><i class="fas fa-sync-alt text-xs"></i></button>
            </div>
            <div id="gebeurtenissen" class="p-0 flex-1 overflow-x-auto">
            </div>
          </div>
        </div>
      </div>
  </main>
  
  <!-- Footer -->
  <?php require_once('includes/footer.php') ?>
</div> <!-- End Main Content / flex-1 -->

<div id="modal01" class="fixed inset-0 bg-black/60 z-50 hidden flex items-center justify-center p-4">
  <div class="bg-white rounded-lg shadow-xl max-w-md w-full overflow-hidden theme-card theme-text">
    <header class="bg-red-500 text-white p-4 relative"> 
      <span onclick="document.getElementById('modal01').style.display='none'" class="absolute top-4 right-4 cursor-pointer hover:opacity-80"><i class="fas fa-times"></i></span>
      <h2 class="text-xl font-bold">Zeker weten?</h2>
    </header>
    <div class="p-6">
      <p class="mb-6 font-medium">Door dit te markeren als klaar betekend het dat iemand dit in de officiele jotihuntwebsite heeft opgestuurd.</p>
      <div class="flex space-x-3">
        <a href="#" id="opgestuurdurl" class="px-4 py-2 bg-red-500 text-white rounded font-bold hover:bg-red-600 transition" onclick="">Ja</a>
        <button class="px-4 py-2 bg-gray-200 text-gray-800 rounded font-bold hover:bg-gray-300 transition" onclick="document.getElementById('modal01').style.display='none'">Nee</button>
      </div>
    </div>
  </div>
</div>

<!-- Welcome modal -->
<?php
// show welcome modal once after login for priv 0
if (isset($_SESSION['show_welcome_modal']) && $_SESSION['show_welcome_modal'] === true && isset($_SESSION['priv']) && $_SESSION['priv'] == 0) {
  // unset immediately so it won't show on refresh
  unset($_SESSION['show_welcome_modal']);
  ?>
  <div id="welcomeModal" class="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4 opacity-0 transition-opacity duration-300">
    <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full overflow-hidden transform -translate-y-4 transition-transform duration-300 theme-card theme-text" id="welcomeModalContent">
      <header class="theme-bg-primary text-white p-5 border-b border-black/10"> 
        <h2 class="text-xl font-bold">Welkom bij Jotify!</h2>
      </header>
      <div class="p-6 space-y-4 text-sm">
        <p>
          Hoi <strong><?php echo ucfirst($vn); ?></strong>, als nieuwe gebruiker van dit platform willen we je graag welkom heten!
        </p>
        <p>
          Dit platform is speciaal ontwikkeld voor de Jotihunt en biedt verschillende functies om je ervaring te verbeteren. Hier zijn enkele belangrijke punten:
        </p>
        <ul class="list-disc pl-5 space-y-1 opacity-90">
          <li>Je kunt eenvoudig hints opvragen en opdrachten bekijken via het menu.</li>
          <li>Je locatie kan worden gedeeld met de homebase. Dit kun je aan- of uitzetten met de GPS-knop linksbovenin.</li>
          <li>Voor vragen of problemen kun je altijd contact opnemen met de organisatie.</li>
        </ul>
        <p class="font-semibold pt-2">
          Jij hebt op dit moment nog geen extra rechten. Deze kan je aanvragen bij:
        </p>
        <ul class="list-disc pl-5 space-y-1 opacity-90">
          <?php
          // Get admins for the introduction modal
          $stmt = $conn->prepare("SELECT voornaam, achternaam FROM Gebruikers WHERE priv > 1 ORDER BY voornaam ASC");
          $stmt->execute();
          $result = $stmt->get_result();

          if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
              echo '<li>'.ucfirst($row['voornaam']).' '.ucfirst($row['achternaam']).'</li>';
            }
          } else {
            echo '<li>Geen beheerders gevonden...</li>';
          }
          $stmt->close();
          ?>
        </ul>
      </div>
      <footer class="p-4 bg-black/5 border-t border-black/10 flex justify-end">
        <div class="relative min-w-[160px]">
          <!-- disabled button with countdown -->
          <button id="welcomeClose" class="w-full py-2.5 px-4 bg-green-500 text-white font-bold rounded shadow-sm opacity-50 cursor-not-allowed flex items-center justify-center space-x-2 transition-opacity" disabled>
            <span id="closeLabel">Sluiten</span>
            <span id="closeCountdown" class="opacity-90 font-mono">(7s)</span>
          </button>
          <!-- progress overlay -->
          <div id="welcomeProgress" class="absolute left-0 top-0 h-full bg-white/20 rounded pointer-events-none" style="width:0%;"></div>
        </div>
      </footer>
    </div>
  </div>

  <script>
    // auto-open welcome modal on page load and force 7s read-delay with countdown
    (function() {
      window.addEventListener('load', function() {
        var modal = document.getElementById('welcomeModal');
        var modalContent = document.getElementById('welcomeModalContent');
        if (!modal) return;
        
        modal.classList.remove('hidden');
        // trigger animation
        setTimeout(() => {
          modal.classList.remove('opacity-0');
          modalContent.classList.remove('-translate-y-4');
        }, 10);

        var closeBtn = document.getElementById('welcomeClose');
        var progress = document.getElementById('welcomeProgress');
        var countdownEl = document.getElementById('closeCountdown');
        var duration = 7000; // milliseconds
        var start = Date.now();

        var raf;
        function tick() {
          var elapsed = Date.now() - start;
          var pct = Math.min(100, (elapsed / duration) * 100);
          progress.style.width = pct + '%';

          var remaining = Math.max(0, Math.ceil((duration - elapsed) / 1000));
          if (remaining > 0) {
            countdownEl.textContent = '(' + remaining + 's)';
          } else {
            countdownEl.style.display = 'none';
          }

          if (elapsed < duration) {
            raf = requestAnimationFrame(tick);
          } else {
            // enable button after duration
            closeBtn.disabled = false;
            closeBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            closeBtn.classList.add('hover:bg-green-600');
            progress.style.display = 'none';
            cancelAnimationFrame(raf);
          }
        }
        tick();

        closeBtn.addEventListener('click', function() {
          if (!closeBtn.disabled) {
            modal.classList.add('opacity-0');
            modalContent.classList.add('-translate-y-4');
            setTimeout(() => { modal.style.display = 'none'; }, 300);
          }
        });
      });
    })();
  </script>
  <?php
}
?>

<input type="hidden" id="hgtyhgty">

    <!-- The core Firebase JS SDK is always required and must be listed first -->

<script src="https://www.gstatic.com/firebasejs/7.0.0/firebase-app.js"></script>

<!-- TODO: Add SDKs for Firebase products that you want to use

     https://firebase.google.com/docs/web/setup#available-libraries -->

<script src="https://www.gstatic.com/firebasejs/7.0.0/firebase-analytics.js"></script>

<script>
  // Your web app's Firebase configuration
  var firebaseConfig = {
    apiKey: "<?php echo addslashes($siteSettings['API_KEY_FIREBASE'] ?? ''); ?>",
    authDomain: "jotihunt-1539122761269.firebaseapp.com",
    databaseURL: "https://jotihunt-1539122761269.firebaseio.com",
    projectId: "jotihunt-1539122761269",
    storageBucket: "",
    messagingSenderId: "376439098940",
    appId: "1:376439098940:web:3bf5ab91c34efafd3e3d39",
    measurementId: "G-1ZGJEPTP5T"
  };
  // Initialize Firebase
  firebase.initializeApp(firebaseConfig);
  firebase.analytics();
</script>

    </body>

</html>

<script>

setInterval(function() {

  invulgegevens();

  gebeurtenissen();

  autosonderweg();

}, 11111);

  

  

// Overzicht - Gebeurtenissen ophalen

window.onload = gebeurtenissen();

function gebeurtenissen(str = "6") {

  var icon = document.getElementById("gebeurtenissen_icon");

  icon.classList.add("w3-spin");

  var xhttp;

  xhttp = new XMLHttpRequest();

  xhttp.onreadystatechange = function() {

    if (this.readyState == 4 && this.status == 200) {

      document.getElementById("gebeurtenissen").innerHTML = this.responseText;

      setTimeout(function() {

        

      icon.classList.remove("w3-spin");

      }, 1000);

      

    }

  };

  xhttp.open("GET", "functies.php?gebeurtenissen="+str, true);

  xhttp.send();

}

 

  

// Overzicht - Auto's ophalen

window.onload = autosonderweg();

function autosonderweg(str = "6") {

  var icon = document.getElementById("autosonderweg_icon");

  icon.classList.add("w3-spin");

  var xhttp;

  xhttp = new XMLHttpRequest();

  xhttp.onreadystatechange = function() {

    if (this.readyState == 4 && this.status == 200) {

      document.getElementById("autosonderweg").innerHTML = this.responseText;

      setTimeout(function() {

        

      icon.classList.remove("w3-spin");

      }, 1000);

      

    }

  };

  xhttp.open("GET", "functies.php?autos="+str, true);

  xhttp.send();

}

  

<?php if ($priv > 0){ echo '

// Overzicht -Invulgegevens ophalen

window.onload = invulgegevens();

function invulgegevens(str = "6") {

  var icon = document.getElementById("invulgegevens_icon");

  icon.classList.add("w3-spin");

  var xhttp;

  xhttp = new XMLHttpRequest();

  xhttp.onreadystatechange = function() {

    if (this.readyState == 4 && this.status == 200) {

      document.getElementById("invulgegevens").innerHTML = this.responseText;

      setTimeout(function() {

        

      icon.classList.remove("w3-spin");

      }, 1000);

    }

  };

  xhttp.open("GET", "functies.php?invulgegevens="+str, true);

  xhttp.send();

}

';}?>  
  </script>

  <script src="js/gps.js"></script>
<script>initGpsTracking('<?php echo $_SESSION['gps'] ?? 'false'; ?>');</script>
