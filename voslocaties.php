<?php
// Form and interface for submitting new fox sightings using GPS coordinates, Dutch RD coordinates, or scout group presets.
define("PAGE_NAME", "voslocaties");

require_once('includes/auth.php');

$message = '';


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_fox_location'])) {
    // Sanitize and retrieve form data
    $coord_type = $_POST['coord_type'] ?? '';
    $type = $_POST['type'] ?? '';
    $fox_team = $_POST['fox_team'] ?? '';
    $datetime_str = $_POST['datetime'] ?? '';
    $code = $_POST['code'] ?? null;
    $remarks = $_POST['remarks'] ?? null;
    $submitted_by = $_SESSION['id'];

    $lat = 0.0;
    $lon = 0.0;

    if ($coord_type === 'latlon') {
        $lat = filter_input(INPUT_POST, 'lat', FILTER_VALIDATE_FLOAT);
        $lon = filter_input(INPUT_POST, 'lon', FILTER_VALIDATE_FLOAT);
    } elseif ($coord_type === 'rd') {
        $rd_x = filter_input(INPUT_POST, 'rd_x', FILTER_VALIDATE_FLOAT);
        $rd_y = filter_input(INPUT_POST, 'rd_y', FILTER_VALIDATE_FLOAT);
        if ($rd_x && $rd_y) {
            $converted_coords = convertRdToWgs($rd_x, $rd_y);
            $lat = $converted_coords['lat'];
            $lon = $converted_coords['lon'];
        }
    } elseif ($coord_type === 'group') {
        $group_id = filter_input(INPUT_POST, 'group_id', FILTER_VALIDATE_INT);
        if ($group_id) {
            $stmt_grp = $conn->prepare("SELECT lat, lon FROM Groepen WHERE id = ?");
            $stmt_grp->bind_param("i", $group_id);
            $stmt_grp->execute();
            $grp_res = $stmt_grp->get_result();
            if ($grp_row = $grp_res->fetch_assoc()) {
                $lat = $grp_row['lat'];
                $lon = $grp_row['lon'];
            }
            $stmt_grp->close();
        }
    }

    $submitted_at = str_replace('T', ' ', $datetime_str) . ':00';

    $photoUrl = null;
    if ($type === 'Hunt' && isset($_FILES['hunt_photo']) && $_FILES['hunt_photo']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/media/hunts/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }
        $ext = strtolower(pathinfo($_FILES['hunt_photo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            $filename = 'hunt_' . time() . '_' . substr(md5(uniqid()), 0, 8) . '.' . $ext;
            $dest = $uploadDir . $filename;
            if (move_uploaded_file($_FILES['hunt_photo']['tmp_name'], $dest)) {
                $photoUrl = 'media/hunts/' . $filename;
            }
        }
    }

    if ($lat && $lon) {
        $stmt = $conn->prepare("INSERT INTO Voslocaties (type, deelgebied, ingestuurd_op, coordinaat_x, coordinaat_y, code, opmerking, foto, ingeleverd_door, ingeleverd) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)");
        $stmt->bind_param("sssddsssi", $type, $fox_team, $submitted_at, $lat, $lon, $code, $remarks, $photoUrl, $submitted_by);
        
        if ($stmt->execute()) {
            send_push_notification('ALL', 'Nieuwe Voslocatie', "Nieuwe {$type} toegevoegd voor {$fox_team}.", '/voslocaties', 'voslocaties', null, 'locatiestatus');
            $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6 shadow-sm">
                            <span onclick="this.parentElement.style.display=\'none\'" class="absolute top-0 bottom-0 right-0 px-4 py-3 cursor-pointer">
                                <i class="fas fa-times opacity-70 hover:opacity-100 transition"></i>
                            </span>
                            <strong class="font-bold">Success!</strong>
                            <span class="block sm:inline">Voslocatie succesvol toegevoegd.</span>
                        </div>';
        } else {
            $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6 shadow-sm">
                            <span onclick="this.parentElement.style.display=\'none\'" class="absolute top-0 bottom-0 right-0 px-4 py-3 cursor-pointer">
                                <i class="fas fa-times opacity-70 hover:opacity-100 transition"></i>
                            </span>
                            <strong class="font-bold">Error!</strong>
                            <span class="block sm:inline">Er is een fout opgetreden: ' . htmlspecialchars($stmt->error) . '</span>
                        </div>';
        }
        $stmt->close();
    } else {
        $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6 shadow-sm">
                        <span onclick="this.parentElement.style.display=\'none\'" class="absolute top-0 bottom-0 right-0 px-4 py-3 cursor-pointer">
                            <i class="fas fa-times circle text-red-500 mr-1"></i>
                        </span>
                        <strong class="font-bold">Error!</strong>
                        <span class="block sm:inline">Ongeldige coördinaten ingevoerd.</span>
                    </div>';
    }
}

if ($privilege < 1) {
    header("Location: home");
    exit();
}

$groups = [];
$stmt_groups = $conn->prepare("SELECT id, naam, deelgebied FROM Groepen ORDER BY deelgebied, naam");
$stmt_groups->execute();
$res_groups = $stmt_groups->get_result();
while ($row_group = $res_groups->fetch_assoc()) {
    $groups[] = $row_group;
}
$stmt_groups->close();

$group_lat = 52.15517440;
$group_lon = 5.38720621;

if (isset($site_settings['GROUP_ID'])) {
    $stmt = $conn->prepare("SELECT lat, lon FROM Groepen WHERE id = ?");
    $stmt->bind_param("i", $site_settings['GROUP_ID']);
    $stmt->execute();
    $groupResult = $stmt->get_result();
    if ($groupRow = $groupResult->fetch_assoc()) {
        if (!empty($groupRow['lat']) && !empty($groupRow['lon'])) {
            $group_lat = floatval($groupRow['lat']);
            $group_lon = floatval($groupRow['lon']);
        }
    }
    $stmt->close();
}

?>
<!DOCTYPE html>
<html lang="nl">
<head>
<title>Jotify - Voslocaties</title>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="shortcut icon" type="image/png" href="media/geusje.png"/>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://kit.fontawesome.com/870ab34ea3.js" crossorigin="anonymous"></script>
<script src='https://api.mapbox.com/mapbox-gl-js/v3.1.2/mapbox-gl.js'></script>
<link href='https://api.mapbox.com/mapbox-gl-js/v3.1.2/mapbox-gl.css' rel='stylesheet' />
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<?php include_once('includes/theme.php'); ?>
</head>
<body class="flex h-screen overflow-hidden">

<?php include_once('includes/sidebar.php') ?>

<div class="flex-1 flex flex-col h-screen overflow-y-auto w-full relative">
  <?php include_once('includes/topbar.php') ?>

  <main class="p-4 md:p-6 max-w-[1400px] mx-auto w-full flex-1">

    <div class="theme-card rounded border shadow-sm overflow-hidden mb-12 max-w-4xl">
      <div class="theme-card-header px-6 py-4 border-b text-white" style="background-color: var(--theme-sidebar-active); border-color: var(--theme-card-border);">
        <h3 class="text-xl font-bold">Nieuwe voslocatie toevoegen</h3>
      </div>
      <form class="p-6" method="post" action="voslocaties.php" enctype="multipart/form-data">
        
        <?php echo $message; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
          
          <div class="space-y-6">
            <div>
              <label class="block text-sm font-bold opacity-70 mb-2 uppercase tracking-wide">Locatie Invoermethode</label>
              <div class="flex items-center space-x-4">
                <label class="flex items-center space-x-2 cursor-pointer group">
                  <input id="coord_latlon" type="radio" name="coord_type" value="latlon" onclick="showCoords('latlon');" checked class="w-4 h-4 text-blue-600 focus:ring-blue-500">
                  <span class="group-hover:opacity-80 transition">Lat / Lon</span>
                </label>
                <label class="flex items-center space-x-2 cursor-pointer group">
                  <input id="coord_rd" type="radio" name="coord_type" value="rd" onclick="showCoords('rd');" class="w-4 h-4 text-blue-600 focus:ring-blue-500">
                  <span class="group-hover:opacity-80 transition">RD</span>
                </label>
                <label class="flex items-center space-x-2 cursor-pointer group">
                  <input id="coord_group" type="radio" name="coord_type" value="group" onclick="showCoords('group');" class="w-4 h-4 text-blue-600 focus:ring-blue-500">
                  <span class="group-hover:opacity-80 transition">Groep</span>
                </label>
              </div>
            </div>
            
            <div id="latlon_coords" class="space-y-4">
              <div>
                  <div class="flex items-center space-x-2 flex-wrap gap-y-2">
                      <button type="button" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded transition shadow-sm text-sm" onclick="getGPSLocation()" id="gps-button"><i class="fas fa-location-arrow mr-2"></i>Haal locatie op</button>
                      <button type="button" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded transition shadow-sm text-sm" onclick="openMapModal()" id="map-button"><i class="fas fa-map-marked-alt mr-2"></i>Kies op kaart</button>
                  </div>
                  <div class="mt-1">
                      <span id="gps-status" class="text-sm opacity-70 italic"></span>
                  </div>
              </div>
              <div>
                <label class="block text-sm font-bold opacity-70 mb-1">Latitude</label>
                <input class="w-full border rounded px-3 py-2 text-gray-800 outline-none focus:ring-1 focus:ring-blue-500 shadow-sm" type="number" step="any" name="lat" placeholder="52.000000" required>
              </div>
              <div>
                <label class="block text-sm font-bold opacity-70 mb-1">Longitude</label>
                <input class="w-full border rounded px-3 py-2 text-gray-800 outline-none focus:ring-1 focus:ring-blue-500 shadow-sm" type="number" step="any" name="lon" placeholder="5.900000" required>
              </div>
            </div>
            
            <div id="rd_coords" class="space-y-4" style="display:none;">
              <div>
                <label class="block text-sm font-bold opacity-70 mb-1">RD X</label>
                <input class="w-full border rounded px-3 py-2 text-gray-800 outline-none focus:ring-1 focus:ring-blue-500 shadow-sm" type="number" step="any" name="rd_x" placeholder="190000">
              </div>
              <div>
                <label class="block text-sm font-bold opacity-70 mb-1">RD Y</label>
                <input class="w-full border rounded px-3 py-2 text-gray-800 outline-none focus:ring-1 focus:ring-blue-500 shadow-sm" type="number" step="any" name="rd_y" placeholder="450000">
              </div>
            </div>

            <div id="group_coords" class="space-y-4" style="display:none;">
              <div>
                <label class="block text-sm font-bold opacity-70 mb-1">Zoek Scoutinggroep</label>
                <input type="text" id="group_search" onkeyup="filterGroups()" class="w-full border rounded px-3 py-2 text-gray-800 outline-none focus:ring-1 focus:ring-blue-500 shadow-sm mb-2" placeholder="Typ om te zoeken...">
                <div class="border rounded max-h-48 overflow-y-auto bg-white shadow-inner">
                    <table class="w-full text-sm text-left">
                        <tbody id="group_list">
                            <?php foreach($groups as $g): ?>
                            <tr class="group-row cursor-pointer hover:bg-blue-50 transition border-b last:border-b-0" onclick="selectGroup(<?= $g['id'] ?>, this)">
                                <td class="px-3 py-2 font-bold text-gray-600 w-10 border-r border-gray-100 text-center"><?= htmlspecialchars(substr($g['deelgebied'], 0, 1)) ?></td>
                                <td class="px-3 py-2"><?= htmlspecialchars($g['naam']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <input type="hidden" name="group_id" id="selected_group_id">
              </div>
            </div>
            
            <div>
              <label class="block text-sm font-bold opacity-70 mb-1 uppercase tracking-wide">Datum & Tijd</label>
              <input class="w-full border rounded px-3 py-2 text-gray-800 outline-none focus:ring-1 focus:ring-blue-500 shadow-sm" type="datetime-local" name="datetime" value="<?php echo date('Y-m-d\TH:i'); ?>" required>
            </div>
          </div>

          <div class="space-y-6">
            <div>
              <label class="block text-sm font-bold opacity-70 mb-1 uppercase tracking-wide">Vossenteam (Deelgebied)</label>
              <select class="w-full border rounded px-3 py-2 text-gray-800 outline-none focus:ring-1 focus:ring-blue-500 shadow-sm bg-white" name="fox_team" required>
                <option value="" disabled selected>Kies een team</option>
                <?php
                foreach ($fox_names as $fox) {
                    echo "<option value=\"" . htmlspecialchars($fox) . "\">" . htmlspecialchars($fox) . "</option>\n";
                }
                ?>
              </select>
            </div>
            
            <div>
              <label class="block text-sm font-bold opacity-70 mb-1 uppercase tracking-wide">Type Locatie</label>
              <select class="w-full border rounded px-3 py-2 text-gray-800 outline-none focus:ring-1 focus:ring-blue-500 shadow-sm bg-white" name="type" id="type_select" onchange="toggleCodeInput()" required>
                <option value="Hint">Hint</option>
                <option value="Hunt">Hunt</option>
                <option value="Spot" selected>Spot</option>
                <option value="Voorspelling">Voorspelling</option>
              </select>
            </div>
            
            <div>
              <label class="block text-sm font-bold opacity-70 mb-1 uppercase tracking-wide">Code (optioneel)</label>
              <input class="w-full border rounded px-3 py-2 text-gray-800 outline-none focus:ring-1 focus:ring-blue-500 shadow-sm bg-gray-100 disabled:opacity-60 disabled:cursor-not-allowed" type="text" name="code" id="code_input" maxlength="32" disabled>
            </div>
            
            <div id="hunt_photo_container">
              <label class="block text-sm font-bold opacity-70 mb-1 uppercase tracking-wide">Foto van Huntcode (optioneel)</label>
              <input class="w-full text-xs file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-blue-600 file:text-white hover:file:bg-blue-700 cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed" type="file" name="hunt_photo" id="hunt_photo_input" accept="image/*" capture="environment" disabled>
              <p class="text-[11px] opacity-60 mt-1">Alleen van toepassing bij type Hunt.</p>
            </div>
            
            <div>
              <label class="block text-sm font-bold opacity-70 mb-1 uppercase tracking-wide">Opmerking (optioneel)</label>
              <textarea class="w-full border rounded px-3 py-2 text-gray-800 outline-none focus:ring-1 focus:ring-blue-500 shadow-sm resize-y" name="remarks" rows="3" maxlength="128"></textarea>
            </div>
          </div>
        </div>
        
        <div class="mt-8 border-t pt-6" style="border-color: var(--theme-card-border);">
          <button type="submit" name="submit_fox_location" class="theme-bg-primary text-white font-bold py-2.5 px-8 rounded-xl shadow-sm hover:opacity-90 transition"><i class="fas fa-plus mr-2"></i>Locatie Toevoegen</button>
        </div>
      </form>
    </div>
  </main>

  <div id="map-modal" class="fixed inset-0 z-50 hidden bg-black bg-opacity-60 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl overflow-hidden flex flex-col h-[80vh] md:h-[600px]">
        <div class="px-6 py-4 border-b flex justify-between items-center" style="background-color: var(--theme-sidebar-active); border-color: var(--theme-card-border);">
            <h3 class="text-xl font-bold text-white"><i class="fas fa-map-marked-alt mr-2"></i>Kies een locatie op de kaart</h3>
            <button type="button" onclick="closeMapModal()" class="text-white hover:text-gray-300 focus:outline-none transition">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        <div class="flex-1 w-full relative">
            <div id="modal-map" class="absolute inset-0 w-full h-full"></div>
        </div>
        <div class="px-6 py-4 border-t bg-gray-50 flex justify-end space-x-4">
            <button type="button" onclick="closeMapModal()" class="px-5 py-2.5 bg-gray-300 hover:bg-gray-400 text-gray-800 rounded font-bold transition shadow-sm">Annuleren</button>
            <button type="button" onclick="confirmMapLocation()" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded font-bold transition shadow-sm"><i class="fas fa-check mr-2"></i>Bevestig Locatie</button>
        </div>
    </div>
  </div>

  <?php require_once('includes/footer.php') ?>
</div>
  
<script src="js/gps.js"></script>
<script>initGpsTracking('<?= $_SESSION['gps'] ?? 'false' ?>');</script>
<script src="js/voslocaties.js"></script>
<script>
initVoslocaties({
    mapboxKey: '<?= htmlspecialchars($site_settings["API_KEY_MAPBOX"] ?? "") ?>',
    center: [<?= (float)$group_lon ?>, <?= (float)$group_lat ?>]
});
</script>
</body>
</html>