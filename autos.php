<?php
// Vehicle management interface for registering new hunt cars, deleting vehicles, joining or leaving as passengers, and Jotihunt portal synchronization.
define("PAGE_NAME", "autos");
require_once('includes/auth.php');
require_once('includes/helpers.php');

if (!isset($privilege) || ($privilege < 1 && !isset($_SESSION['kiosk_id']))) {
  header("Location: home");
  exit();
}

$rdw_enabled = (!empty($GLOBALS['site_settings']['RDW_LOOKUP_ENABLED']) && $GLOBALS['site_settings']['RDW_LOOKUP_ENABLED'] != '0');

// Controleer of de hunter cronjob actief is en bereken de interval in minuten
$cron_portal_enabled = false;
$cron_portal_minutes = 3;
$stmt_cj = $conn->prepare("SELECT enabled, `interval` FROM Cronjobs WHERE name IN ('SCRAPE_Portal', 'jotiPortal') ORDER BY (name = 'SCRAPE_Portal') DESC LIMIT 1");
if ($stmt_cj) {
  $stmt_cj->execute();
  if ($cj_row = $stmt_cj->get_result()->fetch_assoc()) {
    $cron_portal_enabled = ((int)$cj_row['enabled'] === 1);
    $cron_portal_minutes = max(1, (int)round($cj_row['interval'] / 60));
  }
  $stmt_cj->close();
}

// Voertuigkleur bijwerken (AJAX / POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_vehicle_color') {
  header('Content-Type: application/json');
  if (empty($_SESSION['id'])) {
    echo json_encode(['success' => false, 'error' => 'Niet ingelogd']);
    exit();
  }

  $kenteken = strtoupper(trim($_POST['kenteken'] ?? ''));
  $nieuwe_kleur = trim($_POST['rdw_kleur'] ?? '');

  if (empty($kenteken)) {
    echo json_encode(['success' => false, 'error' => 'Geen kenteken opgegeven']);
    exit();
  }

  // Check of de gebruiker eigenaar is of admin
  $stmt_check = $conn->prepare("SELECT eigenaar FROM Auto WHERE kenteken = ? LIMIT 1");
  $stmt_check->bind_param("s", $kenteken);
  $stmt_check->execute();
  $res_check = $stmt_check->get_result()->fetch_assoc();
  $stmt_check->close();

  if (!$res_check) {
    echo json_encode(['success' => false, 'error' => 'Voertuig niet gevonden']);
    exit();
  }

  if ($res_check['eigenaar'] != $_SESSION['id'] && ($privilege ?? 0) < 2) {
    echo json_encode(['success' => false, 'error' => 'Geen toestemming om de kleur van dit voertuig te wijzigen']);
    exit();
  }

  // Kleur bijwerken in database
  $stmt_upd = $conn->prepare("UPDATE Auto SET rdw_kleur = ? WHERE kenteken = ?");
  $stmt_upd->bind_param("ss", $nieuwe_kleur, $kenteken);
  $success = $stmt_upd->execute();
  $stmt_upd->close();

  $hex = getRdwColorHex($nieuwe_kleur);
  echo json_encode([
    'success' => $success,
    'kenteken' => $kenteken,
    'kleur' => $nieuwe_kleur,
    'hex' => $hex
  ]);
  exit();
}

// Voertuig toevoegen
if (isset($_POST['kenteken'])){
  $kenteken = strtoupper(trim($_POST['kenteken']));
  $hunter_type = strtolower(trim($_POST['hunter_type'] ?? 'car'));

  if (!in_array($hunter_type, ['car', 'motorcycle', 'scooter', 'bike', 'foot', 'other'], true)) {
    $hunter_type = 'car';
  }

  $rdw_kleur = !empty($_POST['rdw_kleur']) ? strtoupper(trim($_POST['rdw_kleur'])) : null;
  $aantal_zitplaatsen = !empty($_POST['aantal_zitplaatsen']) ? (int)$_POST['aantal_zitplaatsen'] : null;

  // Optionele fallback voor RDW kleur en zitplaatsen als deze niet via de frontend meegestuurd werden
  if ((empty($rdw_kleur) || $aantal_zitplaatsen === null) && in_array($hunter_type, ['car', 'motorcycle', 'scooter'], true) && $rdw_enabled) {
    $clean_plate = preg_replace('/[^A-Za-z0-9]/', '', $kenteken);
    if (strlen($clean_plate) >= 4) {
      $ctx = stream_context_create(['http' => ['timeout' => 2]]);
      $resp = @file_get_contents("https://opendata.rdw.nl/resource/m9d7-ebf2.json?kenteken=" . strtoupper($clean_plate), false, $ctx);
      if ($resp) {
        $json = json_decode($resp, true);
        if (!empty($json[0]['eerste_kleur']) && empty($rdw_kleur)) {
          $c = strtoupper(trim($json[0]['eerste_kleur']));
          if (!in_array($c, ['N.V.T.', 'NIET GEREGISTREERD', 'DIVERSEN'], true)) {
            $rdw_kleur = $c;
          }
        }
        if (!empty($json[0]['aantal_zitplaatsen']) && $aantal_zitplaatsen === null) {
          $aantal_zitplaatsen = (int)$json[0]['aantal_zitplaatsen'];
        }
      }
    }
  }

  // Telefoonnummer en naam automatisch ophalen van het gebruikersaccount
  $telefoon = '';
  $naam = '';
  $stmt_u = $conn->prepare("SELECT voornaam, achternaam, phone FROM Gebruikers WHERE id = ?");
  if ($stmt_u) {
    $stmt_u->bind_param("i", $_SESSION['id']);
    $stmt_u->execute();
    if ($u_row = $stmt_u->get_result()->fetch_assoc()) {
      $telefoon = $u_row['phone'] ?? '';
      $first_name = trim($u_row['voornaam'] ?? '');
      $last_name = trim($u_row['achternaam'] ?? '');
      $full_name = trim($first_name . ' ' . $last_name);
      $naam = !empty($full_name) ? $full_name : $first_name;
    }
    $stmt_u->close();
  }

  if (empty($naam)) {
    $naam = "Voertuig " . $kenteken;
  }

  if (!empty($kenteken) && strlen($kenteken) >= 4 && strlen($kenteken) <= 8) {
    $stmt_ins = $conn->prepare("
      INSERT INTO Auto (eigenaar, kenteken, hunter_type, rdw_kleur, aantal_zitplaatsen, naam, telefoon) 
      VALUES (?, ?, ?, ?, ?, ?, ?) 
      ON DUPLICATE KEY UPDATE 
        hunter_type = VALUES(hunter_type),
        rdw_kleur = COALESCE(VALUES(rdw_kleur), rdw_kleur),
        aantal_zitplaatsen = COALESCE(VALUES(aantal_zitplaatsen), aantal_zitplaatsen),
        naam = VALUES(naam),
        telefoon = VALUES(telefoon)
    ");
    $stmt_ins->bind_param("isssiss", $_SESSION['id'], $kenteken, $hunter_type, $rdw_kleur, $aantal_zitplaatsen, $naam, $telefoon);
    $stmt_ins->execute();
    $stmt_ins->close();
  }
  header("Location: autos");
  exit();
}

// Auto verwijderen
if (isset($_GET['delauto'])){
  $kenteken_to_delete = trim($_GET['delauto']);
  if (!empty($kenteken_to_delete)) {
    // Only owner or admin (privilege >= 2) can delete car
    $stmt_del = $conn->prepare("DELETE FROM Auto WHERE kenteken = ? AND (eigenaar = ? OR ? >= 2)");
    $stmt_del->bind_param("sii", $kenteken_to_delete, $_SESSION['id'], $privilege);
    if ($stmt_del->execute()) {
      // Clean up passengers and whiteboard assignments
      $stmt_clean_bijr = $conn->prepare("DELETE FROM Auto_Bijrijders WHERE auto = ?");
      $stmt_clean_bijr->bind_param("s", $kenteken_to_delete);
      $stmt_clean_bijr->execute();
      $stmt_clean_bijr->close();

      $stmt_clean_toew = $conn->prepare("DELETE FROM Auto_Toewijzingen WHERE auto = ?");
      $stmt_clean_toew->bind_param("s", $kenteken_to_delete);
      $stmt_clean_toew->execute();
      $stmt_clean_toew->close();
    }
    $stmt_del->close();
    header("Location: autos");
    exit();
  }
}

// In of uitstappen als bijrijder
if (isset($_POST['carid'])) {
  if ($_POST['carid'] === "geen") {
    $stmt_bijr = $conn->prepare("DELETE FROM Auto_Bijrijders WHERE gebruiker_id = ?");
    $stmt_bijr->bind_param("i", $_SESSION['id']);
    $stmt_bijr->execute();
    $stmt_bijr->close();
  } else {
    $stmt_bijr = $conn->prepare("INSERT INTO Auto_Bijrijders (auto, gebruiker_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE auto = ?");
    $stmt_bijr->bind_param("sis", $_POST['carid'], $_SESSION['id'], $_POST['carid']);
    $stmt_bijr->execute();
    $stmt_bijr->close();
  }
}

function getVehicleIcon($type) {
  switch ($type) {
    case 'motorcycle': return 'fa-motorcycle';
    case 'scooter': return 'fa-motorcycle';
    case 'bike': return 'fa-bicycle';
    case 'foot': return 'fa-walking';
    case 'other': return 'fa-helicopter';
    default: return 'fa-car';
  }
}

function getVehicleLabel($type) {
  switch ($type) {
    case 'motorcycle': return 'Motor';
    case 'scooter': return 'Scooter';
    case 'bike': return 'Fiets';
    case 'foot': return 'Lopend';
    case 'other': return 'Overig';
    default: return 'Auto';
  }
}

function getRdwColorHex(?string $colorName): string {
  if (empty($colorName)) return '#64748b';
  $c = strtoupper(trim($colorName));
  if (preg_match('/^#[0-9A-F]{6}$/i', $c)) {
    return $c;
  }
  switch ($c) {
    case 'ZWART': return '#18181b';
    case 'WIT': return '#cbd5e1';
    case 'GRIJS': return '#64748b';
    case 'ZILVER': return '#94a3b8';
    case 'BLAUW': return '#2563eb';
    case 'ROOD': return '#dc2626';
    case 'GROEN': return '#16a34a';
    case 'GEEL': return '#eab308';
    case 'ORANJE': return '#ea580c';
    case 'BRUIN': return '#78350f';
    case 'PAARS': return '#9333ea';
    case 'ROSE':
    case 'ROZE': return '#ec4899';
    case 'BEIGE': return '#d4b896';
    case 'CREME': return '#fde047';
    case 'GOUD': return '#d97706';
    case 'DIVERSEN':
    case 'MEERKLEURIG': return '#6366f1';
    default: return '#64748b';
  }
}
?>

<!DOCTYPE html>
<html lang="nl">
<head>
<title>Jotify - Auto's & Wagenpark</title>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="shortcut icon" type="image/png" href="media/geusje.png"/>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://kit.fontawesome.com/870ab34ea3.js" crossorigin="anonymous"></script>
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<link rel="stylesheet" href="includes/numberPlate.css">
<style>
  .valid-message::before {
    content: none !important;
  }
  .car-license-wrap {
    position: relative;
    display: inline-flex;
    align-items: center;
  }
  #plate-icon-container {
    position: absolute;
    right: -32px;
    top: 50%;
    transform: translateY(-50%);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
  }
</style>
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

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
      
      <!-- Auto Aanmaken & Tabel -->
      <div class="theme-card rounded-xl border shadow-sm overflow-hidden mb-6">
        <div class="theme-card-header px-6 py-4 border-b text-white flex justify-between items-center" style="background-color: var(--theme-sidebar-active); border-color: var(--theme-card-border);">
          <h3 class="text-xl font-bold"><i class="fas fa-car mr-2"></i> Auto / Voertuig Aanmaken</h3>
        </div>
        <form method="POST" class="p-6">    
          
          <!-- Vervoerstype Selectie -->
          <div class="mb-4 relative">
            <label class="block text-xs font-semibold opacity-70 uppercase tracking-wider mb-1.5" style="color: var(--theme-text);">Soort Vervoer</label>
            <input type="hidden" name="hunter_type" id="select-hunter-type" value="car">
            
            <button type="button" id="hunter-type-trigger" onclick="toggleHunterTypeDropdown()" class="w-full flex items-center justify-between border rounded-xl px-3.5 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-500 shadow-sm cursor-pointer text-slate-800 dark:text-slate-100" style="background-color: var(--theme-card-bg); color: var(--theme-text); border-color: var(--theme-card-border);">
              <span class="flex items-center gap-2.5">
                <i id="hunter-type-icon" class="fas fa-car text-blue-500 fa-fw"></i>
                <span id="hunter-type-label" class="font-medium text-slate-800 dark:text-slate-100" style="color: var(--theme-text);">Auto</span>
              </span>
              <i class="fas fa-chevron-down text-xs opacity-60 text-slate-800 dark:text-slate-100" style="color: var(--theme-text);"></i>
            </button>

            <!-- Dropdown Menu met FontAwesome iconen -->
            <div id="hunter-type-menu" class="hidden absolute z-30 mt-1 w-full border rounded-xl shadow-xl py-1.5 overflow-hidden text-slate-800 dark:text-slate-100" style="background-color: var(--theme-card-bg); color: var(--theme-text); border-color: var(--theme-card-border);">
              <div onclick="selectHunterType('car', 'fa-car text-blue-500', 'Auto')" class="px-4 py-2.5 hover:bg-black/5 dark:hover:bg-white/10 cursor-pointer flex items-center gap-3 text-sm transition font-medium text-slate-800 dark:text-slate-100" style="color: var(--theme-text);">
                <i class="fas fa-car text-blue-500 fa-fw"></i> <span style="color: var(--theme-text);">Auto</span>
              </div>
              <div onclick="selectHunterType('motorcycle', 'fa-motorcycle text-indigo-500', 'Motor')" class="px-4 py-2.5 hover:bg-black/5 dark:hover:bg-white/10 cursor-pointer flex items-center gap-3 text-sm transition font-medium text-slate-800 dark:text-slate-100" style="color: var(--theme-text);">
                <i class="fas fa-motorcycle text-indigo-500 fa-fw"></i> <span style="color: var(--theme-text);">Motor</span>
              </div>
              <div onclick="selectHunterType('scooter', 'fa-motorcycle text-emerald-500', 'Scooter')" class="px-4 py-2.5 hover:bg-black/5 dark:hover:bg-white/10 cursor-pointer flex items-center gap-3 text-sm transition font-medium text-slate-800 dark:text-slate-100" style="color: var(--theme-text);">
                <i class="fas fa-motorcycle text-emerald-500 fa-fw"></i> <span style="color: var(--theme-text);">Scooter</span>
              </div>
              <div onclick="selectHunterType('bike', 'fa-bicycle text-green-500', 'Fiets')" class="px-4 py-2.5 hover:bg-black/5 dark:hover:bg-white/10 cursor-pointer flex items-center gap-3 text-sm transition font-medium text-slate-800 dark:text-slate-100" style="color: var(--theme-text);">
                <i class="fas fa-bicycle text-green-500 fa-fw"></i> <span style="color: var(--theme-text);">Fiets</span>
              </div>
              <div onclick="selectHunterType('foot', 'fa-walking text-amber-500', 'Lopend')" class="px-4 py-2.5 hover:bg-black/5 dark:hover:bg-white/10 cursor-pointer flex items-center gap-3 text-sm transition font-medium text-slate-800 dark:text-slate-100" style="color: var(--theme-text);">
                <i class="fas fa-walking text-amber-500 fa-fw"></i> <span style="color: var(--theme-text);">Lopend</span>
              </div>
              <div onclick="selectHunterType('other', 'fa-helicopter text-purple-500', 'Overig')" class="px-4 py-2.5 hover:bg-black/5 dark:hover:bg-white/10 cursor-pointer flex items-center gap-3 text-sm transition font-medium text-slate-800 dark:text-slate-100" style="color: var(--theme-text);">
                <i class="fas fa-helicopter text-purple-500 fa-fw"></i> <span style="color: var(--theme-text);">Overig</span>
              </div>
            </div>
          </div>

          <div class="flex flex-col items-center justify-center">
            
            <input type="hidden" name="rdw_kleur" id="input-rdw-kleur" value="">
            <input type="hidden" name="aantal_zitplaatsen" id="input-zitplaatsen" value="">

            <!-- RDW Nummerbord Veld -->
            <div id="license-plate-container" class="form-control mb-2">
              <div class="car-license-wrap">
                <div class="car-license shadow-sm">
                  <abbr title="Netherlands" class="car-license__country-code">
                    <svg class="svg" viewBox="0 0 300 300" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="20" height="20" aria-labelledby="euSymbol" role="img">
                      <title id="euSymbol">EU symbol</title>
                      <g id="s" transform="translate(150,30)" fill="#fc0">
                        <g id="c">
                          <path id="t" d="M 0,-20 V 0 H 10" transform="rotate(18 0,-20)"/>
                          <use xlink:href="#t" transform="scale(-1,1)"/>
                        </g>
                        <use xlink:href="#c" transform="rotate(72)"/>
                        <use xlink:href="#c" transform="rotate(144)"/>
                        <use xlink:href="#c" transform="rotate(216)"/>
                        <use xlink:href="#c" transform="rotate(288)"/>
                      </g>
                      <use xlink:href="#s" transform="rotate(30 150,150) rotate(330 150,30)"/>
                      <use xlink:href="#s" transform="rotate(60 150,150) rotate(300 150,30)"/>
                      <use xlink:href="#s" transform="rotate(90 150,150) rotate(270 150,30)"/>
                      <use xlink:href="#s" transform="rotate(120 150,150) rotate(240 150,30)"/>
                      <use xlink:href="#s" transform="rotate(150 150,150) rotate(210 150,30)"/>
                      <use xlink:href="#s" transform="rotate(180 150,150) rotate(180 150,30)"/>
                      <use xlink:href="#s" transform="rotate(210 150,150) rotate(150 150,30)"/>
                      <use xlink:href="#s" transform="rotate(240 150,150) rotate(120 150,30)"/>
                      <use xlink:href="#s" transform="rotate(270 150,150) rotate(90 150,30)"/>
                      <use xlink:href="#s" transform="rotate(300 150,150) rotate(60 150,30)"/>
                      <use xlink:href="#s" transform="rotate(330 150,150) rotate(30 150,30)"/>
                    </svg>
                    <span>NL</span>
                  </abbr>
                  <div class="car-license__form-control">
                    <input type="text" class="car-license__input" id="input-kenteken" maxlength="8" autocomplete="off" name="kenteken" placeholder="007-JB-1" oninput="onPlateInput(this.value)">  
                  </div>
                </div>
                <span class="valid-message" id="plate-icon-container">
                  <i id="plate-status-icon" class="fas"></i>
                </span>
              </div>
              <!-- Merk en model onder het kenteken (wanneer RDW API actief is) -->
              <div id="rdw-vehicle-info" class="text-xs text-center font-bold tracking-wide mt-2 min-h-[1.75rem] flex items-center justify-center"></div>
            </div>

            <!-- Invoerveld voor fiets/voet/anders -->
            <div id="custom-plate-container" class="w-full hidden mb-4">
              <label class="block text-xs font-semibold opacity-70 uppercase tracking-wider mb-1.5">Unieke Identifier (max 8 tekens)</label>
              <input type="text" id="input-custom-plate" maxlength="8" placeholder="FIETS-1" class="w-full font-mono uppercase text-center font-bold tracking-widest theme-override-bg theme-override-text border rounded-xl px-3.5 py-2.5 text-base outline-none focus:ring-2 focus:ring-blue-500 shadow-sm" oninput="handleCustomPlateInput(this.value)">
            </div>

            <button type="submit" id="kentekenKnop" class="theme-bg-primary text-white font-bold py-2.5 px-8 rounded-xl shadow-md hover:opacity-90 transition border border-transparent disabled:bg-slate-200 disabled:text-slate-500 disabled:opacity-100 dark:disabled:bg-slate-800 dark:disabled:text-slate-400 disabled:cursor-not-allowed disabled:shadow-none" disabled>
              Aanmaken
            </button>
          </div>
        </form>

        <hr class="my-6 border-gray-200" style="border-color: var(--theme-card-border);">

        <!-- Wagenpark Tabel -->
        <div class="overflow-x-auto">
          <table class="w-full text-sm text-left">
            <thead class="text-xs uppercase theme-card-header opacity-80">
              <tr>
                <th class="px-4 py-3">Kenteken</th>
                <th class="px-4 py-3">Hunter Code</th>
                <th class="px-4 py-3 hidden md:table-cell">Inzittenden</th>
                <th class="px-4 py-3">Eigenaar</th>
                <th class="px-4 py-3 text-right">Acties</th>
              </tr>
            </thead>
            <tbody class="divide-y" style="border-color: var(--theme-card-border);">
              <?php
              $sql = "
              SELECT 
                CONCAT(UPPER(SUBSTRING(ge.voornaam,1,1)),LOWER(SUBSTRING(ge.voornaam,2))) as eigenaar,
                ge.id as id,
                a.kenteken as kenteken,
                a.hunter_type,
                a.rdw_kleur,
                a.hunter_code,
                a.pdf_path,
                GROUP_CONCAT(CONCAT(UPPER(SUBSTRING(gb.voornaam,1,1)),LOWER(SUBSTRING(gb.voornaam,2))) SEPARATOR ', ') as inzittenden
              FROM Auto a
              LEFT JOIN Auto_Bijrijders ab
                on a.kenteken = ab.auto
              LEFT JOIN Gebruikers gb
                on gb.id = ab.gebruiker_id
              LEFT JOIN Gebruikers ge
                on ge.id = a.eigenaar
              GROUP BY a.kenteken
              ORDER BY a.kenteken ASC
              ";
              
              $stmt_table = $conn->prepare($sql);
              $stmt_table->execute();
              $result_table = $stmt_table->get_result();

              if ($result_table->num_rows > 0) {
                while($row = $result_table->fetch_assoc()) {
                  $typeIcon = getVehicleIcon($row['hunter_type'] ?? 'car');
                  $typeLabel = getVehicleLabel($row['hunter_type'] ?? 'car');
                  $iconColor = getRdwColorHex($row['rdw_kleur'] ?? null);
                  $colorTitle = !empty($row['rdw_kleur']) ? "RDW Kleur: " . ucfirst(strtolower($row['rdw_kleur'])) : $typeLabel;
                  $whiteStyle = (strtoupper($row['rdw_kleur'] ?? '') === 'WIT') 
                    ? 'style="color: #cbd5e1; filter: drop-shadow(0 0 1px #475569);"' 
                    : 'style="color: ' . $iconColor . ';"';

                  $isOwner = ($_SESSION['id'] == $row['id'] || ($privilege ?? 0) >= 2);
                  $safeKenteken = htmlspecialchars($row['kenteken'], ENT_QUOTES);
                  $currentKleur = htmlspecialchars($row['rdw_kleur'] ?? '', ENT_QUOTES);
                  $safeHunterType = htmlspecialchars($row['hunter_type'] ?? 'car', ENT_QUOTES);

                  echo "<tr class='hover:opacity-80 transition-opacity' id='vehicle-row-{$safeKenteken}'>";
                  echo "  <td class='px-4 py-3 font-semibold'>";
                  echo "    <div class='flex items-center gap-2.5'>";
                  if ($isOwner) {
                    echo "      <button type='button' id='vehicle-color-btn-{$safeKenteken}' onclick='openVehicleColorModal(\"{$safeKenteken}\", \"".htmlspecialchars($iconColor, ENT_QUOTES)."\", \"{$currentKleur}\", \"{$safeHunterType}\")' class='group relative inline-flex items-center justify-center w-7 h-7 rounded-full bg-white shadow-sm border border-slate-200 flex-shrink-0 cursor-pointer hover:ring-2 hover:ring-blue-400 hover:scale-110 transition-all' title='Kleur aanpassen (huidig: ".htmlspecialchars($colorTitle).")'>";
                    echo "        <i id='vehicle-icon-{$safeKenteken}' class='fas {$typeIcon}' {$whiteStyle}></i>";
                    echo "        <span class='absolute -bottom-1 -right-1 w-3.5 h-3.5 bg-blue-600 rounded-full flex items-center justify-center text-[7px] text-white opacity-0 group-hover:opacity-100 transition shadow pointer-events-none'><i class='fas fa-palette text-[7px]'></i></span>";
                    echo "      </button>";
                  } else {
                    echo "      <span class='inline-flex items-center justify-center w-7 h-7 rounded-full bg-white shadow-sm border border-slate-200 flex-shrink-0' title='".htmlspecialchars($colorTitle)."'>";
                    echo "        <i class='fas {$typeIcon}' {$whiteStyle}></i>";
                    echo "      </span>";
                  }
                  echo "      <span>".strtoupper($safeKenteken)."</span>";
                  echo "    </div>";
                  echo "  </td>";

                  // Hunter code kolom
                  echo "  <td class='px-4 py-3 whitespace-nowrap'>";
                  if (!empty($row['hunter_code'])) {
                    echo "    <span class='px-2 py-0.5 font-mono font-bold text-xs bg-indigo-100 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-200 rounded border border-indigo-200 dark:border-indigo-800'>".htmlspecialchars($row['hunter_code'])."</span>";
                  } else {
                    $cronTooltip = $cron_portal_enabled
                      ? "Nog niet gesynchroniseerd. Kom over ca. {$cron_portal_minutes} minuten terug."
                      : "Nog niet gesynchroniseerd. Kom later terug.";
                    echo "    <span title='".htmlspecialchars($cronTooltip, ENT_QUOTES)."' class='cursor-help inline-flex items-center text-amber-500 hover:text-amber-600 transition' data-tooltip='".htmlspecialchars($cronTooltip, ENT_QUOTES)."'>";
                    echo "      <i class='fas fa-exclamation-triangle text-base'></i>";
                    echo "    </span>";
                  }
                  echo "  </td>";

                  echo "  <td class='px-4 py-3 hidden md:table-cell text-sm'>".htmlspecialchars($row['inzittenden'] ?: 'Geen')."</td>";
                  echo "  <td class='px-4 py-3'>".htmlspecialchars($row['eigenaar'])."</td>";
                  
                  // Acties: download icoon naast prullenbak
                  echo "  <td class='px-4 py-3 text-right whitespace-nowrap'>";
                  echo "    <div class='inline-flex items-center gap-2.5'>";
                  if (!empty($row['pdf_path'])) {
                    echo "      <a href='".htmlspecialchars($row['pdf_path'])."' target='_blank' download class='text-blue-500 hover:text-blue-700 transition p-1' title='Download raampas (PDF)'><i class=\"fas fa-download\"></i></a>";
                  }
                  if ($_SESSION['id'] == $row['id'] || $privilege >= 2) {
                    echo "      <button type='button' onclick='openDeleteVehicleModal(\"".htmlspecialchars($row['kenteken'], ENT_QUOTES)."\")' class='text-red-500 hover:text-red-700 transition p-1' title='Verwijderen'><i class=\"fas fa-trash\"></i></button>";
                  }
                  echo "    </div>";
                  echo "  </td>";
                  echo "</tr>";
                  echo "<tr class='md:hidden bg-black/5'>";
                  echo "  <td colspan='5' class='px-4 py-2 text-xs italic opacity-80'>Inzittenden: ".htmlspecialchars($row['inzittenden'] ?: 'Geen')."</td>";
                  echo "</tr>";
                }
              } else {
                  echo "<tr><td colspan='5' class='px-4 py-4 text-center opacity-70'>Geen voertuigen gevonden.</td></tr>";
              }
              $stmt_table->close();
              ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Stap in / Uit -->
      <div class="theme-card rounded-xl border shadow-sm overflow-hidden mb-6">
        <div class="theme-card-header px-6 py-4 border-b text-white flex justify-between items-center" style="background-color: var(--theme-sidebar-active); border-color: var(--theme-card-border);">
          <h3 class="text-xl font-bold"><i class="fas fa-user-plus mr-2"></i> Stap in / uit</h3>
        </div>
        <form method="POST" class="p-6">
          <select class="w-full theme-override-bg theme-override-text border rounded-xl px-3.5 py-2.5 outline-none focus:ring-2 focus:ring-blue-500 shadow-sm cursor-pointer mb-4" name="carid">
            <option value="geen" selected>Geen (Uitstappen)</option>
            <?php
            $sql_drop = "SELECT a.kenteken, a.eigenaar, a.hunter_type, a.hunter_code, b.voornaam FROM Auto as a INNER JOIN Gebruikers as b ON a.eigenaar = b.id ORDER BY b.voornaam ASC";
            $stmt_drop = $conn->prepare($sql_drop);
            $stmt_drop->execute();
            $result_drop = $stmt_drop->get_result();
            
            if ($result_drop->num_rows > 0) {
              while($row = $result_drop->fetch_assoc()) {
                $codeSuffix = !empty($row['hunter_code']) ? " [Code: " . $row['hunter_code'] . "]" : "";
                echo "<option value=\"".htmlspecialchars($row['kenteken'])."\">".getVehicleLabel($row['hunter_type'])." ".htmlspecialchars($row['kenteken'])." (".ucfirst(htmlspecialchars($row['voornaam']))."){$codeSuffix}</option>";
              }
            }
            $stmt_drop->close();
          ?>
          </select>  
          <div class="text-center">
            <button type="submit" class="bg-blue-600 text-white font-bold py-2 px-6 rounded hover:bg-blue-700 transition shadow-sm"><i class="fas fa-car-side mr-2"></i>Vroem!</button>
          </div>
        </form>
      </div>

    </div>
  </main>

  <!-- Verwijder Voertuig In-DOM Modal -->
  <div id="deleteVehicleModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-black/60 backdrop-blur-sm flex items-center justify-center p-4" role="dialog" aria-modal="true">
    <div class="theme-card rounded-2xl max-w-md w-full overflow-hidden shadow-2xl border transform transition-all" style="border-color: var(--theme-card-border);">
      <div class="px-6 py-4 bg-red-600 text-white flex justify-between items-center">
        <h3 class="text-lg font-bold flex items-center gap-2">
          <i class="fas fa-trash-alt"></i> <span>Voertuig Verwijderen</span>
        </h3>
        <button type="button" onclick="closeModal('deleteVehicleModal')" class="text-white/80 hover:text-white transition">
          <i class="fas fa-times text-lg"></i>
        </button>
      </div>
      <div class="p-6">
        <p class="text-sm opacity-90 leading-relaxed mb-4">
          Weet je zeker dat je het voertuig <strong id="delete-vehicle-plate" class="font-mono text-base font-bold text-red-500"></strong> wilt verwijderen?
        </p>
        <p class="text-xs opacity-70">
          Eventuele gekoppelde inzittenden en whiteboard-toewijzingen worden ook losgekoppeld.
        </p>
      </div>
      <div class="px-6 py-4 border-t flex justify-end gap-3" style="border-color: var(--theme-card-border);">
        <button type="button" onclick="closeModal('deleteVehicleModal')" class="px-4 py-2 text-sm font-semibold rounded-xl border opacity-80 hover:opacity-100 transition" style="border-color: var(--theme-card-border);">
          Annuleren
        </button>
        <a id="delete-vehicle-confirm-btn" href="#" class="px-5 py-2 text-sm font-bold rounded-xl bg-red-600 hover:bg-red-700 text-white transition shadow-sm">
          Verwijderen
        </a>
      </div>
    </div>
  </div>

  <!-- Voertuigkleur Wijzigen In-DOM Modal -->
  <div id="vehicleColorModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-black/60 backdrop-blur-sm flex items-center justify-center p-4" role="dialog" aria-modal="true">
    <div class="theme-card rounded-2xl max-w-md w-full overflow-hidden shadow-2xl border transform transition-all" style="border-color: var(--theme-card-border); background-color: var(--theme-card-bg);">
      <div class="px-6 py-4 theme-card-header flex justify-between items-center" style="background-color: var(--theme-sidebar-active);">
        <h3 class="text-lg font-bold flex items-center gap-2 text-white">
          <i class="fas fa-palette"></i> <span>Voertuigkleur Aanpassen</span>
        </h3>
        <button type="button" onclick="closeModal('vehicleColorModal')" class="text-white/80 hover:text-white transition cursor-pointer">
          <i class="fas fa-times text-lg"></i>
        </button>
      </div>
      <div class="p-6">
        <input type="hidden" id="modal-color-plate" value="">
        <div id="modal-color-error" class="hidden mb-3 p-2.5 rounded-lg bg-red-500/10 border border-red-500/30 text-red-500 text-xs font-semibold"></div>

        <!-- Live Preview -->
        <div class="flex items-center gap-4 mb-5 p-3.5 rounded-xl border" style="border-color: var(--theme-card-border); background-color: rgba(0,0,0,0.03);">
          <div class="w-12 h-12 rounded-full bg-white shadow-sm border border-slate-200 flex items-center justify-center flex-shrink-0">
            <i id="modal-color-preview-icon" class="fas fa-car text-xl transition-all"></i>
          </div>
          <div class="min-w-0 flex-1">
            <div id="modal-color-plate-label" class="text-base font-mono font-bold tracking-wider" style="color: var(--theme-text);"></div>
            <div id="modal-color-name-label" class="text-xs opacity-70 font-medium" style="color: var(--theme-text);">Selecteer een kleur</div>
          </div>
        </div>

        <!-- Voorgedefinieerde RDW Kleuren -->
        <div class="mb-5">
          <label class="block text-xs font-semibold opacity-70 uppercase tracking-wider mb-2" style="color: var(--theme-text);">Officiële Voertuigkleuren</label>
          <div class="grid grid-cols-7 gap-2">
            <button type="button" onclick="selectPresetColor('ZWART', '#18181b')" class="color-swatch-btn w-9 h-9 rounded-full shadow-sm border-2 border-transparent hover:scale-110 transition flex items-center justify-center cursor-pointer" style="background-color: #18181b;" title="Zwart" data-color="ZWART"></button>
            <button type="button" onclick="selectPresetColor('WIT', '#cbd5e1')" class="color-swatch-btn w-9 h-9 rounded-full shadow-sm border-2 border-slate-300 hover:scale-110 transition flex items-center justify-center cursor-pointer" style="background-color: #f8fafc;" title="Wit" data-color="WIT"></button>
            <button type="button" onclick="selectPresetColor('GRIJS', '#64748b')" class="color-swatch-btn w-9 h-9 rounded-full shadow-sm border-2 border-transparent hover:scale-110 transition flex items-center justify-center cursor-pointer" style="background-color: #64748b;" title="Grijs" data-color="GRIJS"></button>
            <button type="button" onclick="selectPresetColor('ZILVER', '#94a3b8')" class="color-swatch-btn w-9 h-9 rounded-full shadow-sm border-2 border-transparent hover:scale-110 transition flex items-center justify-center cursor-pointer" style="background-color: #94a3b8;" title="Zilver" data-color="ZILVER"></button>
            <button type="button" onclick="selectPresetColor('BLAUW', '#2563eb')" class="color-swatch-btn w-9 h-9 rounded-full shadow-sm border-2 border-transparent hover:scale-110 transition flex items-center justify-center cursor-pointer" style="background-color: #2563eb;" title="Blauw" data-color="BLAUW"></button>
            <button type="button" onclick="selectPresetColor('ROOD', '#dc2626')" class="color-swatch-btn w-9 h-9 rounded-full shadow-sm border-2 border-transparent hover:scale-110 transition flex items-center justify-center cursor-pointer" style="background-color: #dc2626;" title="Rood" data-color="ROOD"></button>
            <button type="button" onclick="selectPresetColor('GROEN', '#16a34a')" class="color-swatch-btn w-9 h-9 rounded-full shadow-sm border-2 border-transparent hover:scale-110 transition flex items-center justify-center cursor-pointer" style="background-color: #16a34a;" title="Groen" data-color="GROEN"></button>
            <button type="button" onclick="selectPresetColor('GEEL', '#eab308')" class="color-swatch-btn w-9 h-9 rounded-full shadow-sm border-2 border-transparent hover:scale-110 transition flex items-center justify-center cursor-pointer" style="background-color: #eab308;" title="Geel" data-color="GEEL"></button>
            <button type="button" onclick="selectPresetColor('ORANJE', '#ea580c')" class="color-swatch-btn w-9 h-9 rounded-full shadow-sm border-2 border-transparent hover:scale-110 transition flex items-center justify-center cursor-pointer" style="background-color: #ea580c;" title="Oranje" data-color="ORANJE"></button>
            <button type="button" onclick="selectPresetColor('BRUIN', '#78350f')" class="color-swatch-btn w-9 h-9 rounded-full shadow-sm border-2 border-transparent hover:scale-110 transition flex items-center justify-center cursor-pointer" style="background-color: #78350f;" title="Bruin" data-color="BRUIN"></button>
            <button type="button" onclick="selectPresetColor('PAARS', '#9333ea')" class="color-swatch-btn w-9 h-9 rounded-full shadow-sm border-2 border-transparent hover:scale-110 transition flex items-center justify-center cursor-pointer" style="background-color: #9333ea;" title="Paars" data-color="PAARS"></button>
            <button type="button" onclick="selectPresetColor('ROSE', '#ec4899')" class="color-swatch-btn w-9 h-9 rounded-full shadow-sm border-2 border-transparent hover:scale-110 transition flex items-center justify-center cursor-pointer" style="background-color: #ec4899;" title="Roze" data-color="ROSE"></button>
            <button type="button" onclick="selectPresetColor('BEIGE', '#d4b896')" class="color-swatch-btn w-9 h-9 rounded-full shadow-sm border-2 border-transparent hover:scale-110 transition flex items-center justify-center cursor-pointer" style="background-color: #d4b896;" title="Beige" data-color="BEIGE"></button>
            <button type="button" onclick="selectPresetColor('GOUD', '#d97706')" class="color-swatch-btn w-9 h-9 rounded-full shadow-sm border-2 border-transparent hover:scale-110 transition flex items-center justify-center cursor-pointer" style="background-color: #d97706;" title="Goud" data-color="GOUD"></button>
          </div>
        </div>

        <!-- Eigen Kleurkiezer (HTML5 Color Picker) -->
        <div class="mb-5">
          <label class="block text-xs font-semibold opacity-70 uppercase tracking-wider mb-2" style="color: var(--theme-text);">Eigen Kleur Kiezen</label>
          <div class="flex items-center gap-3">
            <input type="color" id="modal-custom-color-input" class="w-10 h-10 rounded-xl border cursor-pointer p-0.5 bg-transparent" onchange="onCustomColorChange(this.value)">
            <input type="text" id="modal-custom-color-hex" maxlength="7" placeholder="#2563EB" class="w-28 font-mono text-sm px-3 py-2 rounded-xl border uppercase outline-none focus:ring-2 focus:ring-blue-500 shadow-sm" style="background-color: var(--theme-card-bg); color: var(--theme-text); border-color: var(--theme-card-border);" oninput="onCustomColorHexInput(this.value)">
            <span class="text-xs opacity-60" style="color: var(--theme-text);">HEX kleurcode</span>
          </div>
        </div>

        <div class="pt-4 border-t flex justify-end gap-3" style="border-color: var(--theme-card-border);">
          <button type="button" onclick="closeModal('vehicleColorModal')" class="px-4 py-2 text-sm font-semibold rounded-xl border opacity-80 hover:opacity-100 transition cursor-pointer" style="border-color: var(--theme-card-border); color: var(--theme-text);">
            Annuleren
          </button>
          <button type="button" id="modal-color-save-btn" onclick="saveVehicleColor()" class="px-5 py-2 text-sm font-bold rounded-xl theme-bg-primary text-white hover:opacity-90 transition shadow flex items-center gap-2 cursor-pointer">
            <i class="fas fa-check"></i> <span>Opslaan</span>
          </button>
        </div>
      </div>
    </div>
  </div>

  <?php require_once('includes/footer.php') ?>
</div>

<script type="text/javascript" src="includes/numberPlate.js"></script>
<script src="js/gps.js"></script>
<script>
initGpsTracking('<?php echo $_SESSION['gps'] ?? 'false'; ?>');

window.rdwEnabled = <?= $rdw_enabled ? 'true' : 'false' ?>;
let rdwTimeout = null;

const hunterTypeMeta = {
  car: { iconClass: 'fa-car text-blue-500', label: 'Auto' },
  motorcycle: { iconClass: 'fa-motorcycle text-indigo-500', label: 'Motor' },
  scooter: { iconClass: 'fa-motorcycle text-emerald-500', label: 'Scooter' },
  bike: { iconClass: 'fa-bicycle text-green-500', label: 'Fiets' },
  foot: { iconClass: 'fa-walking text-amber-500', label: 'Lopend' },
  other: { iconClass: 'fa-helicopter text-purple-500', label: 'Overig' }
};

function toggleHunterTypeDropdown() {
  const menu = document.getElementById('hunter-type-menu');
  if (menu) menu.classList.toggle('hidden');
}

function selectHunterType(type, iconClass, label) {
  const input = document.getElementById('select-hunter-type');
  const iconEl = document.getElementById('hunter-type-icon');
  const labelEl = document.getElementById('hunter-type-label');
  const menu = document.getElementById('hunter-type-menu');

  if (input) input.value = type;
  if (iconEl && iconClass) iconEl.className = 'fas fa-fw ' + iconClass;
  if (labelEl && label) labelEl.textContent = label;
  if (menu) menu.classList.add('hidden');

  handleTypeChange(type);
}

document.addEventListener('click', function(e) {
  const trigger = document.getElementById('hunter-type-trigger');
  const menu = document.getElementById('hunter-type-menu');
  if (menu && !menu.classList.contains('hidden') && trigger && !trigger.contains(e.target) && !menu.contains(e.target)) {
    menu.classList.add('hidden');
  }
});

function handleTypeChange(type) {
  const plateContainer = document.getElementById('license-plate-container');
  const customContainer = document.getElementById('custom-plate-container');
  const mainInput = document.getElementById('input-kenteken');
  const customInput = document.getElementById('input-custom-plate');
  const knop = document.getElementById('kentekenKnop');
  const infoEl = document.getElementById('rdw-vehicle-info');
  const iconEl = document.getElementById('plate-status-icon');
  const kleurInput = document.getElementById('input-rdw-kleur');
  const zitplaatsenInput = document.getElementById('input-zitplaatsen');

  if (type === 'car' || type === 'motorcycle' || type === 'scooter') {
    plateContainer.classList.remove('hidden');
    customContainer.classList.add('hidden');
    mainInput.value = '';
    if (kleurInput) kleurInput.value = '';
    if (zitplaatsenInput) zitplaatsenInput.value = '';
    infoEl.innerHTML = '';
    iconEl.className = 'fas';
    knop.disabled = true;
  } else {
    plateContainer.classList.add('hidden');
    customContainer.classList.remove('hidden');
    if (kleurInput) kleurInput.value = '';
    if (zitplaatsenInput) zitplaatsenInput.value = '';
    infoEl.innerHTML = '';
    iconEl.className = 'fas';
    
    const prefix = type === 'bike' ? 'FIETS' : (type === 'foot' ? 'VOET' : 'HELI');
    const randomNr = Math.floor(Math.random() * 90) + 10;
    const suggested = `${prefix}-${randomNr}`.substring(0, 8);
    customInput.value = suggested;
    mainInput.value = suggested;
    knop.disabled = false;
  }
}

function handleCustomPlateInput(val) {
  const cleanVal = val.toUpperCase().trim().substring(0, 8);
  document.getElementById('input-kenteken').value = cleanVal;
  const knop = document.getElementById('kentekenKnop');
  const rawChars = cleanVal.replace(/[^A-Z0-9]/gi, '');
  knop.disabled = (rawChars.length < 4 || cleanVal.length > 8);
}

function escapeHtml(str) {
  return String(str).replace(/[&<>'"]/g, 
    tag => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[tag] || tag)
  );
}

function onPlateInput(val) {
  const cleanVal = val.toUpperCase().trim();
  const rawChars = cleanVal.replace(/[^A-Z0-9]/gi, '');
  const knop = document.getElementById('kentekenKnop');
  const infoEl = document.getElementById('rdw-vehicle-info');
  const iconEl = document.getElementById('plate-status-icon');
  const kleurInput = document.getElementById('input-rdw-kleur');
  const zitplaatsenInput = document.getElementById('input-zitplaatsen');

  // Submit is actief zolang min 4 en max 8 karakters zijn ingevoerd
  const isValidLength = (rawChars.length >= 4 && cleanVal.length <= 8);
  knop.disabled = !isValidLength;

  if (!window.rdwEnabled) {
    return;
  }

  clearTimeout(rdwTimeout);
  const normalizedPlate = rawChars;

  if (normalizedPlate.length < 4) {
    iconEl.className = 'fas';
    infoEl.innerHTML = '';
    if (kleurInput) kleurInput.value = '';
    if (zitplaatsenInput) zitplaatsenInput.value = '';
    return;
  }

  // Toon spinner tijdens ophalen van RDW data
  iconEl.className = 'fas fa-spinner fa-spin text-blue-500';
  infoEl.innerHTML = '';

  rdwTimeout = setTimeout(async () => {
    try {
      const resp = await fetch(`https://opendata.rdw.nl/resource/m9d7-ebf2.json?kenteken=${normalizedPlate}`);
      const data = await resp.json();
      if (Array.isArray(data) && data.length > 0) {
        const car = data[0];
        const merk = car.merk || '';
        const model = car.handelsbenaming || '';
        const desc = `${merk} ${model}`.trim();

        // 1. Detecteer voertuigsoort en selecteer automatisch
        const rdwVoertuig = (car.voertuigsoort || '').toLowerCase();
        let detectedType = null;
        if (rdwVoertuig.includes('bromfiets') || rdwVoertuig.includes('snorfiets')) {
          detectedType = 'scooter';
        } else if (rdwVoertuig.includes('motor')) {
          detectedType = 'motorcycle';
        } else if (rdwVoertuig.includes('auto') || rdwVoertuig.includes('bedrijfswagen') || rdwVoertuig.includes('kampeerwagen') || rdwVoertuig.includes('bus')) {
          detectedType = 'car';
        }

        if (detectedType && hunterTypeMeta[detectedType]) {
          const meta = hunterTypeMeta[detectedType];
          const input = document.getElementById('select-hunter-type');
          if (input && input.value !== detectedType) {
            selectHunterType(detectedType, meta.iconClass, meta.label);
          }
        }

        // 2. Bewaar RDW kleur en zitplaatsen in verborgen velden
        if (car.eerste_kleur && kleurInput) {
          kleurInput.value = car.eerste_kleur;
        }
        if (car.aantal_zitplaatsen && zitplaatsenInput) {
          zitplaatsenInput.value = car.aantal_zitplaatsen;
        }

        // 3. Toon checkmark en hoog-contrast make+model badge
        iconEl.className = 'fas fa-check-circle text-green-600 text-lg';
        const typeBadge = detectedType && hunterTypeMeta[detectedType] ? ` <span class="opacity-75 font-normal">(${hunterTypeMeta[detectedType].label})</span>` : '';
        infoEl.innerHTML = `<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full font-bold text-xs bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900 shadow border border-slate-700 dark:border-slate-300 tracking-normal"><i class="fas fa-check text-green-400 dark:text-green-600"></i> ${escapeHtml(desc)}${typeBadge}</span>`;
      } else {
        // Niet gevonden in RDW API: waarschuwing, knop blijft enabled voor min 4 max 8
        iconEl.className = 'fas fa-exclamation-triangle text-amber-500 text-lg';
        infoEl.innerHTML = '';
        if (kleurInput) kleurInput.value = '';
        if (zitplaatsenInput) zitplaatsenInput.value = '';
      }
    } catch (err) {
      // Offline of fout: waarschuwing, knop blijft enabled
      iconEl.className = 'fas fa-exclamation-triangle text-amber-500 text-lg';
      infoEl.innerHTML = '';
      if (kleurInput) kleurInput.value = '';
      if (zitplaatsenInput) zitplaatsenInput.value = '';
    }
  }, 400);
}

function openDeleteVehicleModal(kenteken) {
  document.getElementById('delete-vehicle-plate').textContent = kenteken.toUpperCase();
  document.getElementById('delete-vehicle-confirm-btn').href = 'autos?delauto=' + encodeURIComponent(kenteken);
  openModal('deleteVehicleModal');
}

let activeColorPlate = '';
let activeColorVal = '';
let activeVehicleType = 'car';

function openVehicleColorModal(plate, currentHex, colorName, vehicleType) {
  activeColorPlate = plate;
  activeColorVal = colorName || currentHex || '#64748b';
  activeVehicleType = vehicleType || 'car';

  const errBox = document.getElementById('modal-color-error');
  if (errBox) { errBox.textContent = ''; errBox.classList.add('hidden'); }

  document.getElementById('modal-color-plate').value = plate;
  document.getElementById('modal-color-plate-label').textContent = plate.toUpperCase();

  const iconEl = document.getElementById('modal-color-preview-icon');
  iconEl.className = 'fas ' + getVehicleFaIcon(activeVehicleType) + ' text-2xl transition-all';

  updateModalColorPreview(currentHex || '#64748b', colorName || currentHex);

  const hexInput = document.getElementById('modal-custom-color-hex');
  const colorPicker = document.getElementById('modal-custom-color-input');
  if (currentHex && currentHex.startsWith('#')) {
    hexInput.value = currentHex.toUpperCase();
    colorPicker.value = currentHex;
  } else {
    hexInput.value = '';
    colorPicker.value = '#2563eb';
  }

  // Highlight actieve preset knop
  document.querySelectorAll('.color-swatch-btn').forEach(btn => {
    if (btn.getAttribute('data-color') === (colorName || '').toUpperCase()) {
      btn.classList.add('ring-2', 'ring-blue-500', 'ring-offset-2');
    } else {
      btn.classList.remove('ring-2', 'ring-blue-500', 'ring-offset-2');
    }
  });

  openModal('vehicleColorModal');
}

function getVehicleFaIcon(type) {
  switch (type) {
    case 'motorcycle': return 'fa-motorcycle';
    case 'scooter': return 'fa-motorcycle';
    case 'bike': return 'fa-bicycle';
    case 'foot': return 'fa-walking';
    case 'other': return 'fa-helicopter';
    default: return 'fa-car';
  }
}

function selectPresetColor(rdwName, hex) {
  activeColorVal = rdwName;
  updateModalColorPreview(hex, rdwName);
  document.getElementById('modal-custom-color-hex').value = hex.toUpperCase();
  document.getElementById('modal-custom-color-input').value = hex;

  document.querySelectorAll('.color-swatch-btn').forEach(btn => {
    if (btn.getAttribute('data-color') === rdwName) {
      btn.classList.add('ring-2', 'ring-blue-500', 'ring-offset-2');
    } else {
      btn.classList.remove('ring-2', 'ring-blue-500', 'ring-offset-2');
    }
  });
}

function onCustomColorChange(hex) {
  activeColorVal = hex.toUpperCase();
  updateModalColorPreview(hex, hex.toUpperCase());
  document.getElementById('modal-custom-color-hex').value = hex.toUpperCase();
  document.querySelectorAll('.color-swatch-btn').forEach(btn => btn.classList.remove('ring-2', 'ring-blue-500', 'ring-offset-2'));
}

function onCustomColorHexInput(hex) {
  let cleanHex = hex.trim();
  if (!cleanHex.startsWith('#') && cleanHex.length > 0) cleanHex = '#' + cleanHex;
  if (/^#[0-9A-Fa-f]{6}$/.test(cleanHex)) {
    activeColorVal = cleanHex.toUpperCase();
    updateModalColorPreview(cleanHex, cleanHex.toUpperCase());
    document.getElementById('modal-custom-color-input').value = cleanHex;
    document.querySelectorAll('.color-swatch-btn').forEach(btn => btn.classList.remove('ring-2', 'ring-blue-500', 'ring-offset-2'));
  }
}

function updateModalColorPreview(hex, displayName) {
  const iconEl = document.getElementById('modal-color-preview-icon');
  const labelEl = document.getElementById('modal-color-name-label');

  if (hex) {
    iconEl.style.color = hex;
    if (hex.toUpperCase() === '#CBD5E1' || hex.toUpperCase() === '#FFFFFF') {
      iconEl.style.filter = 'drop-shadow(0 0 1px #475569)';
    } else {
      iconEl.style.filter = 'none';
    }
  }

  const prettyName = displayName ? displayName.charAt(0).toUpperCase() + displayName.slice(1).toLowerCase() : hex;
  labelEl.textContent = 'Geselecteerd: ' + prettyName;
}

async function saveVehicleColor() {
  const saveBtn = document.getElementById('modal-color-save-btn');
  const errBox = document.getElementById('modal-color-error');
  const origHtml = saveBtn.innerHTML;
  saveBtn.disabled = true;
  saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Opslaan...</span>';
  if (errBox) { errBox.textContent = ''; errBox.classList.add('hidden'); }

  try {
    const formData = new FormData();
    formData.append('action', 'update_vehicle_color');
    formData.append('kenteken', activeColorPlate);
    formData.append('rdw_kleur', activeColorVal);

    const resp = await fetch('autos.php', {
      method: 'POST',
      body: formData
    });
    const result = await resp.json();

    if (result.success) {
      const iconEl = document.getElementById('vehicle-icon-' + activeColorPlate);
      if (iconEl) {
        iconEl.style.color = result.hex;
        if (result.hex.toUpperCase() === '#CBD5E1' || result.hex.toUpperCase() === '#FFFFFF') {
          iconEl.style.filter = 'drop-shadow(0 0 1px #475569)';
        } else {
          iconEl.style.filter = 'none';
        }
      }
      const btnEl = document.getElementById('vehicle-color-btn-' + activeColorPlate);
      if (btnEl) {
        btnEl.setAttribute('onclick', `openVehicleColorModal("${activeColorPlate}", "${result.hex}", "${result.kleur}", "${activeVehicleType}")`);
        btnEl.title = 'Kleur aanpassen (huidig: ' + result.kleur + ')';
      }
      closeModal('vehicleColorModal');
    } else {
      if (errBox) {
        errBox.textContent = result.error || 'Er is een fout opgetreden.';
        errBox.classList.remove('hidden');
      }
    }
  } catch (err) {
    console.error('Error saving vehicle color:', err);
    if (errBox) {
      errBox.textContent = 'Netwerkfout bij opslaan van de kleur.';
      errBox.classList.remove('hidden');
    }
  } finally {
    saveBtn.disabled = false;
    saveBtn.innerHTML = origHtml;
  }
}
</script>

</body>
</html>
