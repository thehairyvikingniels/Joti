<?php
// Tegenhunt tactical command page featuring live 90-degree search quadrant radar, GPS breadcrumbs, and rapid sticker submission.
define("PAGE_NAME", "tegenhunt");
require_once('includes/auth.php');

$groupId = intval($site_settings['GROUP_ID'] ?? 0);
$groupCoords = getMyGroupCoordinates($conn, $groupId);
$activeTegenhunt = getActiveTegenhunt($conn);
$userPriv = intval($privilege ?? $_SESSION['priv'] ?? 0);

// Access control: non-admins are redirected when no session is active
if (!$activeTegenhunt && $userPriv < 2) {
    header("Location: home?e=" . urlencode("Er is momenteel geen actieve tegenhunt.") . "&t=info");
    exit();
}

// Fetch past sessions for admin history
$pastSessions = [];
if ($userPriv >= 2) {
    $stmt_hist = $conn->prepare("
        SELECT t.*, u.voornaam AS finder_first_name, u.achternaam AS finder_last_name 
        FROM Tegenhunt_Sessions t 
        LEFT JOIN Gebruikers u ON t.found_by_user_id = u.id 
        ORDER BY t.start_time DESC LIMIT 10
    ");
    if ($stmt_hist) {
        $stmt_hist->execute();
        $pastSessions = $stmt_hist->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_hist->close();
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<title>Jotify - Tegenhunt Radar</title>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.js"></script>
<link href="https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.css" rel="stylesheet" />
<?php include('includes/theme.php'); ?>
</head>
<body class="theme-bg theme-text flex h-screen overflow-hidden">

<?php include('includes/sidebar.php'); ?>

<div class="flex-1 flex flex-col min-h-screen overflow-y-auto w-full relative">
  <?php include('includes/topbar.php'); ?>

  <main class="flex-1 p-3 sm:p-5 flex flex-col">
    
    <?php if ($activeTegenhunt): ?>
    <!-- MAP CONTAINER & LIVE SEARCHERS (Directly under Breaking News Banner) -->
    <div class="flex-1 min-h-[500px] flex flex-col lg:flex-row gap-4 relative rounded-xl overflow-hidden shadow-lg border border-black/10">
      
      <!-- MAPBOX GL RADAR -->
      <div id="tegenhunt-map" class="flex-1 w-full min-h-[400px] lg:min-h-[500px] rounded-xl relative">
        <!-- Floating Sticker Found Button (Static) -->
        <button onclick="openStickerFoundModal()" class="absolute bottom-6 right-6 z-20 bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white font-black text-base sm:text-lg py-3.5 px-6 rounded-full shadow-2xl flex items-center gap-3 transform hover:scale-105 transition-all border-2 border-white/40">
          <i class="fas fa-camera text-xl"></i>
          <span>STICKER GEVONDEN!</span>
        </button>

        <!-- Compass Rose Overlay with Direction & Distance -->
        <div class="absolute top-4 right-4 z-10 bg-black/70 backdrop-blur text-white px-3.5 py-2 rounded-xl text-xs sm:text-sm font-bold flex items-center gap-2 border border-white/20 shadow-md">
          <i class="fas fa-compass text-yellow-400 text-base"></i>
          <span>Zoekrichting: <b class="text-yellow-300 uppercase"><?= htmlspecialchars($activeTegenhunt['wind_direction']) ?></b> (<?= htmlspecialchars($activeTegenhunt['compass_degrees']) ?>&deg; | 90&deg; Boog | Max 450m)</span>
        </div>
      </div>

      <!-- LIVE SEARCHERS SIDEBAR -->
      <div class="w-full lg:w-72 rounded-xl flex flex-col shadow-md theme-card border overflow-hidden" style="border-color: var(--theme-card-border); background-color: var(--theme-card-bg);">
        <div class="theme-card-header px-5 py-3.5 border-b text-white flex justify-between items-center" style="background-color: var(--theme-sidebar-active); border-color: var(--theme-card-border);">
          <span class="font-bold text-sm flex items-center gap-2"><i class="fas fa-walking"></i> Zoekers Buiten</span>
          <span id="searcher-count" class="bg-white/20 text-white text-xs px-2.5 py-0.5 rounded-full font-bold shadow-sm">0</span>
        </div>
        <div class="p-4 flex-1 flex flex-col">
          <div id="searcher-list" class="flex-1 overflow-y-auto space-y-2 text-xs max-h-48 lg:max-h-none">
            <div class="text-center opacity-50 py-4">Zoekers laden...</div>
          </div>
        </div>
      </div>

    </div>

    <?php else: ?>
    <!-- ADMIN LAUNCHPAD (WHEN INACTIVE) -->
    <div class="max-w-3xl mx-auto w-full my-auto theme-card rounded-xl shadow-xl border overflow-hidden" style="border-color: var(--theme-card-border);">
      <div class="theme-card-header px-6 py-4 border-b text-white flex justify-between items-center" style="background-color: var(--theme-sidebar-active); border-color: var(--theme-card-border);">
        <h3 class="text-xl font-bold flex items-center gap-2"><i class="fas fa-bullseye"></i> <span>Tegenhunt Starten</span></h3>
      </div>
      <div class="p-6">
        <div class="text-center mb-6">
          <p class="text-sm opacity-70">Selecteer de gemelde windrichting uit het Telegram-bericht om direct de 90&deg; zoekradar en push-notificaties te activeren.</p>
        </div>

      <form id="form-start-tegenhunt" onsubmit="event.preventDefault(); startTegenhunt();" class="space-y-6">
        
        <!-- COMPASS WIND DIRECTION GRID -->
        <div>
          <label class="block text-xs font-bold uppercase tracking-wider mb-2 opacity-80 text-center">Selecteer Zoekrichting</label>
          <div class="grid grid-cols-4 sm:grid-cols-8 gap-2">
            <?php
            $directions = [
                'N' => 0, 'NNO' => 22, 'NO' => 45, 'ONO' => 67,
                'O' => 90, 'OZO' => 112, 'ZO' => 135, 'ZZO' => 157,
                'Z' => 180, 'ZZW' => 202, 'ZW' => 225, 'WZW' => 247,
                'W' => 270, 'WNW' => 292, 'NW' => 315, 'NNW' => 337
            ];
            $idx = 0;
            foreach ($directions as $dir => $deg):
              $activeClass = ($idx === 2) ? 'ring-2 ring-red-500 bg-red-500 text-white font-black' : 'bg-black/5 hover:bg-red-500/20 font-bold';
            ?>
            <button type="button" onclick="selectDirection('<?= $dir ?>', <?= $deg ?>, this)" class="btn-dir py-3 px-2 rounded-xl text-center transition flex flex-col items-center justify-center <?= $activeClass ?>">
              <span class="text-sm"><?= $dir ?></span>
              <span class="text-[10px] opacity-70"><?= $deg ?>&deg;</span>
            </button>
            <?php $idx++; endforeach; ?>
          </div>
          <input type="hidden" id="input-direction" value="NO">
          <input type="hidden" id="input-degrees" value="45">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-bold uppercase tracking-wider mb-1 opacity-80">Duur (Minuten)</label>
            <input type="number" id="input-duration" value="30" min="5" max="120" class="w-full bg-black/5 border rounded-xl p-3 text-sm font-bold focus:outline-none focus:ring-2 focus:ring-red-500">
          </div>
          <div>
            <label class="block text-xs font-bold uppercase tracking-wider mb-1 opacity-80">Optionele Notitie / Aanwijzing</label>
            <input type="text" id="input-message" placeholder="bijv. Aan de overkant van het spoor" class="w-full bg-black/5 border rounded-xl p-3 text-sm focus:outline-none focus:ring-2 focus:ring-red-500">
          </div>
        </div>

        <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white font-extrabold py-4 px-6 rounded-xl text-lg shadow-lg hover:shadow-xl transition transform hover:-translate-y-0.5 flex items-center justify-center gap-3">
          <i class="fas fa-bullhorn text-xl"></i>
          <span>ACTIVEER TEGENHUNT MODE (30:00)</span>
        </button>
      </form>

      <!-- PAST SESSIONS -->
      <?php if (!empty($pastSessions)): ?>
      <div class="mt-8 border-t pt-5">
        <h3 class="text-xs font-bold uppercase tracking-wider opacity-60 mb-3">Recente Tegenhunt Historie</h3>
        <div class="space-y-2 max-h-48 overflow-y-auto text-xs">
          <?php foreach ($pastSessions as $s): ?>
          <div class="flex items-center justify-between p-2.5 bg-black/5 rounded-lg">
            <div>
              <span class="font-bold uppercase text-red-500 mr-2"><?= htmlspecialchars($s['wind_direction']) ?></span>
              <span class="opacity-70"><?= date('d/m H:i', strtotime($s['start_time'])) ?></span>
              <?php if (!empty($s['found_code'])): ?>
              <span class="ml-2 font-mono bg-green-500/20 text-green-700 dark:text-green-300 px-1.5 py-0.5 rounded font-bold">Code: <?= htmlspecialchars($s['found_code']) ?></span>
              <?php endif; ?>
            </div>
            <div class="font-semibold">
              <?php if ($s['status'] === 'found'): ?>
              <span class="text-green-500"><i class="fas fa-check-circle mr-1"></i> Gevonden door <?= htmlspecialchars($s['finder_first_name'] ?? 'Onbekend') ?></span>
              <?php elseif ($s['status'] === 'expired'): ?>
              <span class="text-gray-400">Verlopen</span>
              <?php else: ?>
              <span class="text-gray-400"><?= htmlspecialchars($s['status']) ?></span>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

  </main>

  <?php require_once('includes/footer.php'); ?>
</div>

<!-- MODAL STOP CONFIRMATION -->
<div id="modal-stop-tegenhunt" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
  <div class="bg-white dark:bg-gray-900 theme-card rounded-2xl max-w-sm w-full p-6 shadow-2xl border border-black/10 relative">
    <button onclick="closeStopModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 text-xl font-bold">&times;</button>
    
    <div class="text-center mb-5">
      <div class="w-14 h-14 bg-red-500/10 text-red-500 rounded-full flex items-center justify-center text-3xl mx-auto mb-2">
        <i class="fas fa-stop-circle"></i>
      </div>
      <h2 class="text-xl font-black">Tegenhunt Be&euml;indigen?</h2>
      <p class="text-xs opacity-70 mt-1">Weet je zeker dat je de actieve tegenhunt wilt stoppen? De radar en countdown worden voor iedereen gesloten.</p>
    </div>

    <div class="flex gap-3">
      <button type="button" onclick="closeStopModal()" class="flex-1 bg-black/5 hover:bg-black/10 font-bold py-3 rounded-xl transition text-sm">
        Annuleren
      </button>
      <button type="button" onclick="confirmStopTegenhunt(<?= (int)($activeTegenhunt['id'] ?? 0) ?>)" class="flex-1 bg-red-600 hover:bg-red-700 text-white font-bold py-3 rounded-xl transition shadow text-sm">
        Be&euml;indigen
      </button>
    </div>
  </div>
</div>

<!-- STICKER FOUND MODAL -->
<div id="modal-sticker-found" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
  <div class="bg-white dark:bg-gray-900 theme-card rounded-2xl max-w-md w-full p-6 shadow-2xl border border-black/10 relative">
    <button onclick="closeStickerFoundModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 text-xl font-bold">&times;</button>
    
    <div class="text-center mb-5">
      <div class="w-14 h-14 bg-green-500/10 text-green-500 rounded-full flex items-center justify-center text-3xl mx-auto mb-2">
        <i class="fas fa-check-circle"></i>
      </div>
      <h2 class="text-xl font-black">Sticker Gevonden!</h2>
      <p class="text-xs opacity-70 mt-0.5">Voer de stickercode in en maak direct een foto voor verificatie.</p>
    </div>

    <form id="form-submit-sticker" onsubmit="event.preventDefault(); submitStickerFound();" class="space-y-4">
      <div>
        <label class="block text-xs font-bold uppercase tracking-wider mb-1 opacity-80">Stickercode *</label>
        <input type="text" id="sticker-code" required placeholder="bijv. TH-1234" class="w-full bg-black/5 border rounded-xl p-3 text-lg font-mono font-black uppercase text-center focus:outline-none focus:ring-2 focus:ring-green-500">
      </div>

      <div>
        <label class="block text-xs font-bold uppercase tracking-wider mb-1 opacity-80">Foto van Sticker (met scout/hoogte)</label>
        <input type="file" id="sticker-photo" accept="image/*" capture="environment" class="w-full text-xs file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-green-500 file:text-white hover:file:bg-green-600 cursor-pointer">
      </div>

      <input type="hidden" id="sticker-lat" value="">
      <input type="hidden" id="sticker-lon" value="">

      <button type="submit" id="btn-submit-found" class="w-full bg-green-500 hover:bg-green-600 text-white font-extrabold py-3.5 px-6 rounded-xl text-base shadow-lg transition flex items-center justify-center gap-2">
        <i class="fas fa-paper-plane"></i>
        <span>INLEVEREN & TEGENHUNT SLUITEN</span>
      </button>
    </form>
  </div>
</div>

<!-- STICKER FOUND SUCCESS MODAL -->
<div id="modal-sticker-success" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
  <div class="bg-white dark:bg-gray-900 theme-card rounded-2xl max-w-sm w-full p-6 shadow-2xl border border-black/10 text-center relative">
    <div class="w-16 h-16 bg-green-500 text-white rounded-full flex items-center justify-center text-3xl mx-auto mb-3 shadow-lg">
      <i class="fas fa-check"></i>
    </div>
    <h2 class="text-2xl font-black text-green-500">GEWELDIG!</h2>
    <p id="sticker-success-message" class="text-sm font-semibold mt-2">Sticker succesvol ingeleverd!</p>
    <p class="text-xs opacity-70 mt-1">De tegenhunt is voltooid en geregistreerd.</p>
    
    <button onclick="window.location.reload()" class="mt-6 w-full bg-green-500 hover:bg-green-600 text-white font-bold py-3 rounded-xl transition shadow">
      Sluiten
    </button>
  </div>
</div>

<script>
const MAPBOX_ACCESS_TOKEN = <?= json_encode($site_settings['API_KEY_MAPBOX'] ?? '') ?>;
const HOME_COORDS = <?= json_encode($groupCoords ?? ['lat' => 51.98778, 'lon' => 5.87625, 'naam' => 'Clubhuis']) ?>;
const ACTIVE_SESSION = <?= json_encode($activeTegenhunt) ?>;
</script>
<script src="js/app.js"></script>
<script src="js/gps.js"></script>
<script src="js/tegenhunt.js"></script>
</body>
</html>
