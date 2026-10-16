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
    $hint_count = $row['NUM'];
  }
} else {
  $hint_count = "0";
}
$stmt->close();

// Get hunts
$stmt = $conn->prepare("SELECT id, count(*) as NUM FROM Voslocaties WHERE type='Hunt'");
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
  while($row = $result->fetch_assoc()) {
    $hunt_count = $row['NUM'];
  }
} else {
  $hunt_count = "0";
}
$stmt->close();

// Get points for group
$my_group_id = (int)($site_settings['GROUP_ID'] ?? 0);
$total_points = 0;
$rank = "?";

if ($my_group_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM Punten WHERE groep_id = ?");
    $stmt->bind_param("i", $my_group_id);
} else {
    $stmt = $conn->prepare("SELECT * FROM Punten ORDER BY (COALESCE(hunts,0) + COALESCE(tegenhunts,0) + COALESCE(opdrachten,0) + COALESCE(foto_opdrachten,0) + COALESCE(hints,0) + COALESCE(bonus,0) - COALESCE(strafpunten,0)) DESC LIMIT 1");
}
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
      while($row = $result->fetch_assoc()) {
        $total_points = ($row['hunts'] ?? 0) + ($row['tegenhunts'] ?? 0) + ($row['opdrachten'] ?? 0) + ($row['foto_opdrachten'] ?? 0) + ($row['hints'] ?? 0) + ($row['bonus'] ?? 0) - ($row['strafpunten'] ?? 0);
        $rank = $row['plaats'] ?? "?";
      }
    }
    $stmt->close();
}
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
  <?php if (!$is_kiosk && isset($fox_names) && !empty($vos)): ?>
  <div class="md:hidden p-4 grid grid-cols-3 sm:grid-cols-4 gap-2">
    <?php
    	foreach ($fox_names as $n) {
            echo renderFoxBadge($n, $vos[$n], true);
        }
    ?>
  </div>
<?php endif; ?>

  <main class="p-4 md:p-6 max-w-[1400px] mx-auto w-full flex-1">

    <?php if ($privilege == 0 && !$is_kiosk): ?>
      <!-- Gast Activatie Banner -->
      <div class="mb-6 p-4 sm:p-5 rounded-xl border border-amber-300 dark:border-amber-700/60 bg-amber-50 dark:bg-amber-950/40 border-l-4 border-l-amber-500 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 shadow-sm">
        <div class="flex items-center gap-3.5">
          <div class="w-11 h-11 rounded-full bg-amber-500 text-white flex items-center justify-center text-lg flex-shrink-0 shadow-xs">
            <i class="fas fa-user-clock"></i>
          </div>
          <div>
            <p class="font-bold text-sm sm:text-base text-amber-950 dark:text-amber-100">Je account moet nog worden geactiveerd als Vossenjager</p>
            <p class="text-xs sm:text-sm text-amber-800 dark:text-amber-300 mt-0.5">Je hebt momenteel Gast-toegang. Een beheerder kan je account activeren.</p>
          </div>
        </div>
        <button type="button" onclick="openWelcomeModal(false)" class="text-xs sm:text-sm px-4 py-2.5 rounded-lg bg-amber-600 hover:bg-amber-700 text-white font-bold transition flex-shrink-0 shadow-xs flex items-center gap-2 cursor-pointer">
          <i class="fas fa-users-cog"></i>
          <span>Bekijk Beheerders</span>
        </button>
      </div>
    <?php endif; ?>

      <!-- Stats -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="theme-card rounded p-5 border shadow-sm flex flex-col justify-between">
          <div class="flex justify-between items-center mb-2">
            <p class="text-xs font-semibold uppercase tracking-wider opacity-60">Punten Totaal</p>
            <i class="fas fa-trophy opacity-40"></i>
          </div>
          <h3 class="text-3xl font-bold"><?php echo isset($total_points) ? $total_points : 0; ?></h3>
        </div>
        <div class="theme-card rounded p-5 border shadow-sm flex flex-col justify-between">
          <div class="flex justify-between items-center mb-2">
            <p class="text-xs font-semibold uppercase tracking-wider opacity-60">Huidige Plaats</p>
            <i class="fas fa-star opacity-40"></i>
          </div>
          <h3 class="text-3xl font-bold"><?php echo isset($rank) ? $rank : 0; ?><span class="text-xl opacity-60">e</span></h3>
        </div>
        <div class="theme-card rounded p-5 border shadow-sm flex flex-col justify-between">
          <div class="flex justify-between items-center mb-2">
            <p class="text-xs font-semibold uppercase tracking-wider opacity-60">Aantal Hunts</p>
            <i class="fas fa-bullseye opacity-40"></i>
          </div>
          <h3 class="text-3xl font-bold"><?php echo $hunt_count; ?></h3>
        </div>
        <div class="theme-card rounded p-5 border shadow-sm flex flex-col justify-between">
          <div class="flex justify-between items-center mb-2">
            <p class="text-xs font-semibold uppercase tracking-wider opacity-60">Hints Opgestuurd</p>
            <i class="fas fa-question-circle opacity-40"></i>
          </div>
          <h3 class="text-3xl font-bold"><?php echo $hint_count; ?></h3>
        </div>
      </div>

      <!-- Panels -->
      <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">
        <div class="xl:col-span-1 space-y-6">
          <div class="theme-card rounded-xl border shadow-sm overflow-hidden">
            <div class="theme-card-header px-5 py-3.5 border-b flex justify-between items-center text-white" style="background-color: var(--theme-sidebar-active); border-color: var(--theme-card-border);">
              <h4 class="font-bold text-sm text-white">Invulgegevens</h4>
              <button id="invulgegevens_icon" class="opacity-50 hover:opacity-100 transition" onclick="invulgegevens()"><i class="fas fa-sync-alt text-xs"></i></button>
            </div>
            <div id="invulgegevens" class="p-0 overflow-x-auto">
            </div>
          </div>
          <div class="theme-card rounded-xl border shadow-sm overflow-hidden">
            <div class="theme-card-header px-5 py-3.5 border-b flex justify-between items-center text-white" style="background-color: var(--theme-sidebar-active); border-color: var(--theme-card-border);">
              <h4 class="font-bold text-sm text-white">Auto's Onderweg</h4>
              <button id="autosonderweg_icon" class="opacity-50 hover:opacity-100 transition" onclick="autosonderweg()"><i class="fas fa-sync-alt text-xs"></i></button>
            </div>
            <div id="autosonderweg" class="p-4">
            </div>
          </div>
        </div>
        <div class="xl:col-span-2">
          <div class="theme-card rounded-xl border shadow-sm overflow-hidden h-full flex flex-col">
            <div class="theme-card-header px-5 py-3.5 border-b flex justify-between items-center text-white" style="background-color: var(--theme-sidebar-active); border-color: var(--theme-card-border);">
              <h4 class="font-bold text-sm text-white">Recente Gebeurtenissen</h4>
              <button id="gebeurtenissen_icon" class="opacity-50 hover:opacity-100 transition" onclick="gebeurtenissen()"><i class="fas fa-sync-alt text-xs"></i></button>
            </div>
            <div id="gebeurtenissen" class="p-0 flex-1 overflow-x-auto">
            </div>
          </div>
        </div>
      </div>
  </main>
  
  <?php require_once('includes/footer.php') ?>
</div> <!-- End Main Content / flex-1 -->

<div id="modal01" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
  <div class="theme-card border max-w-md w-full rounded-2xl shadow-2xl overflow-hidden relative">
    <header class="theme-card-header px-6 py-4 border-b text-white flex justify-between items-center" style="background-color: var(--theme-sidebar-active); border-color: var(--theme-card-border);">
      <h3 class="text-lg font-bold flex items-center gap-2"><i class="fas fa-check-double"></i> <span>Markeren als ingeleverd?</span></h3>
      <button onclick="document.getElementById('modal01').style.display='none'" class="text-white opacity-70 hover:opacity-100 transition"><i class="fas fa-times"></i></button>
    </header>
    <div class="p-6">
      <p class="text-sm opacity-80 mb-6 leading-relaxed">Door dit te markeren als klaar bevestig je dat dit item succesvol is ingediend op de offici??le Jotihunt-website.</p>
      <div class="flex justify-end gap-3">
        <button type="button" class="theme-card border hover:bg-black/5 dark:hover:bg-white/5 py-2.5 px-5 rounded-xl font-bold text-sm transition" onclick="document.getElementById('modal01').style.display='none'">Annuleren</button>
        <a href="#" id="opgestuurdurl" class="bg-green-600 hover:bg-green-700 text-white py-2.5 px-6 rounded-xl font-bold text-sm transition shadow-md">Ja, ingeleverd</a>
      </div>
    </div>
  </div>
</div>

<!-- Welcome / Account Activation modal for Guests (priv 0) -->
<?php
$auto_open_welcome = false;
if ($privilege == 0 && !$is_kiosk):
    if (isset($_SESSION['show_welcome_modal']) && $_SESSION['show_welcome_modal'] === true) {
        $auto_open_welcome = true;
        unset($_SESSION['show_welcome_modal']);
    }
?>
  <div id="welcomeModal" class="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4 hidden opacity-0 transition-opacity duration-300">
    <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full overflow-hidden transform -translate-y-4 transition-transform duration-300 theme-card theme-text" id="welcomeModalContent">
      <header class="theme-bg-primary text-white p-5 border-b border-black/10 flex items-center justify-between"> 
        <h2 class="text-xl font-bold flex items-center gap-2">
          <i class="fas fa-user-clock"></i>
          <span>Account Activatie Vereist</span>
        </h2>
        <button type="button" onclick="closeWelcomeModal()" id="welcomeTopClose" class="text-white/70 hover:text-white transition p-1 text-lg hidden cursor-pointer">
          <i class="fas fa-times"></i>
        </button>
      </header>
      <div class="p-6 space-y-4 text-sm">
        <p>
          Hoi <strong><?= htmlspecialchars(ucfirst($first_name)); ?></strong>, welkom bij Jotify!
        </p>
        <div class="p-4 rounded-xl border border-amber-300 dark:border-amber-700/60 bg-amber-50 dark:bg-amber-950/40 border-l-4 border-l-amber-500">
          <p class="font-bold flex items-center gap-2 text-sm text-amber-950 dark:text-amber-100">
            <i class="fas fa-exclamation-triangle text-amber-600 dark:text-amber-400"></i>
            <span>Je account staat momenteel op 'Gast' en is nog niet geactiveerd.</span>
          </p>
          <p class="text-xs sm:text-sm mt-1.5 text-amber-900 dark:text-amber-200 leading-relaxed font-medium">
            Zolang je account niet geactiveerd is door een beheerder heb je beperkte toegang en kun je geen locaties of opdrachten insturen.
          </p>
        </div>
        <p>
          Dit platform is speciaal ontwikkeld voor de Jotihunt en biedt verschillende functies om je ervaring te verbeteren:
        </p>
        <ul class="list-disc pl-5 space-y-1.5 opacity-90 text-xs sm:text-sm">
          <li>Je kunt eenvoudig hints en nieuws bekijken via het menu.</li>
          <li>Je locatie kan worden gedeeld met de homebase via de GPS-knop rechtsboven of deel je locatie met onze Jotify Telegram bot.</li>
          <li>Voor vragen of problemen kun je altijd contact opnemen met de organisatie.</li>
        </ul>
        <p class="font-semibold pt-1">
          Vraag een van de volgende beheerders om je account te activeren naar Vossenjager:
        </p>
        <ul class="list-disc pl-5 space-y-1 opacity-90 font-medium text-xs sm:text-sm">
          <?php
          // Get admins for the introduction modal
          $stmt = $conn->prepare("SELECT voornaam, achternaam FROM Gebruikers WHERE priv > 1 ORDER BY voornaam ASC");
          $stmt->execute();
          $result = $stmt->get_result();

          if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
              echo '<li>' . htmlspecialchars(ucfirst($row['voornaam']) . ' ' . ucfirst($row['achternaam'])) . '</li>';
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
          <button id="welcomeClose" onclick="closeWelcomeModal()" class="w-full py-2.5 px-4 bg-green-600 text-white font-bold rounded shadow-sm opacity-50 cursor-not-allowed flex items-center justify-center space-x-2 transition cursor-pointer" disabled>
            <span id="closeLabel">Sluiten</span>
            <span id="closeCountdown" class="opacity-90 font-mono">(5s)</span>
          </button>
          <!-- progress overlay -->
          <div id="welcomeProgress" class="absolute left-0 top-0 h-full bg-white/20 rounded pointer-events-none" style="width:0%;"></div>
        </div>
      </footer>
    </div>
  </div>
<?php endif; ?>

<input type="hidden" id="hgtyhgty">

<!-- The core Firebase JS SDK is always required and must be listed first -->
<script src="https://www.gstatic.com/firebasejs/7.0.0/firebase-app.js"></script>
<script src="https://www.gstatic.com/firebasejs/7.0.0/firebase-analytics.js"></script>

<script>
  // Your web app's Firebase configuration
  const firebaseConfig = {
    apiKey: "<?php echo addslashes($site_settings['API_KEY_FIREBASE'] ?? ''); ?>",
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

<script src="js/home.js"></script>
<script>
initHome(<?= ($privilege > 0) ? "true" : "false" ?>);
<?php if (!empty($auto_open_welcome)): ?>
initWelcomeModal(true);
<?php endif; ?>
</script>

<script src="js/gps.js"></script>
<script>initGpsTracking('<?php echo $_SESSION['gps'] ?? 'false'; ?>');</script>
</body>
</html>
