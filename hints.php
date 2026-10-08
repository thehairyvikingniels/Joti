<?php
// Displays published puzzle hints and provides a form for submitting solved RD coordinates as new hint locations.
define("PAGE_NAME", "hints");
require_once('includes/auth.php');

// Load existing saved Hint coordinates from Voslocaties for each hint and subarea
$saved_hint_coords = [];
$stmt_saved = $conn->prepare("SELECT deelgebied, code, opmerking FROM Voslocaties WHERE type = 'Hint' AND opmerking LIKE 'Hint #%'");
$stmt_saved->execute();
$res_saved = $stmt_saved->get_result();
while ($s_row = $res_saved->fetch_assoc()) {
    if (preg_match('/Hint #(\d+)/', (string)$s_row['opmerking'], $m)) {
        $h_id = (int)$m[1];
        $parts = preg_split('/\s+/', trim((string)$s_row['code']));
        if (count($parts) >= 3) {
            $saved_hint_coords[$h_id][$s_row['deelgebied']] = [
                'rd_x' => (strlen($parts[1]) > 4) ? substr($parts[1], 0, 4) : $parts[1],
                'rd_y' => (strlen($parts[2]) > 4) ? substr($parts[2], 0, 4) : $parts[2]
            ];
        }
    }
}
$stmt_saved->close();

?>
<!DOCTYPE html>
<html lang="nl">

<head>
  <title>Jotify - Hints</title>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="shortcut icon" type="image/png" href="media/geusje.png" />
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://kit.fontawesome.com/870ab34ea3.js" crossorigin="anonymous"></script>
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <script src='https://api.mapbox.com/mapbox-gl-js/v3.1.2/mapbox-gl.js'></script>
  <link href='https://api.mapbox.com/mapbox-gl-js/v3.1.2/mapbox-gl.css' rel='stylesheet' />
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

      <div class="space-y-6 mb-12">
        <?php
        $stmt = $conn->prepare("SELECT * FROM Hints ORDER BY datum DESC");
        $stmt->execute();
        $result = $stmt->get_result();

        $vossen = $fox_names;
        $prefill_configs = getDeelgebiedenCoordinatePrefills($conn);

        if ($result->num_rows > 0) {
          while ($row = $result->fetch_assoc()) {
            $content = $row['inhoud'];
            $rendered_content = $content;
            if (!empty($content)) {
              $doc = new DOMDocument();
              @$doc->loadHTML('<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>' . $content . '</body></html>');
              $imgNodes = $doc->getElementsByTagName('img');
              foreach ($imgNodes as $node) {
                $node->setAttribute('width', '100%');
                $node->removeAttribute('height');
                // Ensure images are responsive
                $classes = $node->getAttribute('class');
                $node->setAttribute('class', trim($classes . ' rounded my-4 w-full object-cover max-h-[500px]'));
              }
              $body = $doc->getElementsByTagName('body')->item(0);
              if ($body) {
                $rendered_content = '';
                foreach ($body->childNodes as $child) {
                  $rendered_content .= $doc->saveHTML($child);
                }
              }
            }

            echo '
              <div class="theme-card rounded-xl border shadow-sm overflow-hidden mb-6">
                <div class="theme-card-header px-6 py-4 flex justify-between items-center border-b" style="border-color: var(--theme-card-border);">
                  <h3 class="text-xl font-bold">' . htmlspecialchars($row['titel']) . '</h3>
                  <span class="text-sm opacity-60 font-medium">' . date("d/m H:i", strtotime($row['datum'])) . '</span>
                </div>
                
                <div class="p-6 prose max-w-none text-current opacity-90 overflow-x-auto">
                  ' . $rendered_content . '
                </div>';

            $stmt_toew = $conn->prepare("SELECT g.id, g.voornaam, g.achternaam, g.profile_picture FROM Toewijzingen t JOIN Gebruikers g ON t.gebruiker_id = g.id WHERE t.type = 'hint' AND t.referentie_id = ?");
            $stmt_toew->bind_param("i", $row['id']);
            $stmt_toew->execute();
            $res_toew = $stmt_toew->get_result();

            $is_assigned = false;
            $avatars_html = "";
            if ($res_toew->num_rows > 0) {
              while ($t_row = $res_toew->fetch_assoc()) {
                if ($t_row['id'] == $_SESSION['id'])
                  $is_assigned = true;

                $volledige_naam = htmlspecialchars(ucfirst($t_row['voornaam']) . ' ' . ucfirst($t_row['achternaam']));
                $avatar_content = '';
                if ($t_row['profile_picture']) {
                  $avatar_content = '<img class="inline-block h-10 w-10 rounded-full ring-2 ring-white object-cover bg-white pointer-events-none" src="profile_image.php?hash=' . urlencode($t_row['profile_picture']) . '&res=low" alt="' . $volledige_naam . '"/>';
                } else {
                  $initial = strtoupper(substr($t_row['voornaam'], 0, 1));
                  $avatar_content = '<div class="inline-flex items-center justify-center h-10 w-10 rounded-full ring-2 ring-white bg-blue-500 text-white font-bold text-xs pointer-events-none">' . $initial . '</div>';
                }
                $safe_naam = htmlspecialchars(addslashes($volledige_naam));
                $avatars_html .= '<div class="inline-block flex-shrink-0 cursor-pointer" onmouseenter="showAvatarTooltip(event, this, \'' . $safe_naam . '\')" onmouseleave="hideAvatarTooltip()" onclick="showAvatarTooltip(event, this, \'' . $safe_naam . '\')">' . $avatar_content . '</div>';
              }
            } else {
              $avatars_html = "<span class='text-xs opacity-50 italic mr-2'>Nog niemand toegewezen</span>";
            }
            $stmt_toew->close();

            $btn_class = $is_assigned ? "bg-red-100 text-red-700 hover:bg-red-200" : "bg-blue-100 text-blue-700 hover:bg-blue-200";
            $btn_text = $is_assigned ? "<i class='fas fa-times mr-1'></i> Stop hiermee" : "<i class='fas fa-hand-paper mr-1'></i> Ga hiermee aan de slag";

            echo '<div class="px-6 pb-4 flex items-center justify-between border-t pt-4" style="border-color: var(--theme-card-border);">
                    <div id="toewijzingen-avatars-hint-' . $row['id'] . '" class="flex -space-x-2 overflow-visible items-center p-1">
                        ' . $avatars_html . '
                    </div>';
            if ($privilege > 0) {
              echo '<button id="toewijzingen-btn-hint-' . $row['id'] . '" onclick="toggleToewijzing(\'hint\', ' . $row['id'] . ')" class="text-sm font-bold ' . $btn_class . ' px-3 py-1.5 rounded transition shadow-sm whitespace-nowrap ml-4">
                        ' . $btn_text . '
                    </button>';
            }
            echo '</div>';

            $subareas = $vossen;

            if (isset($privilege) && $privilege > 0) {
              echo '<div class="bg-black/5 p-4 border-t grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4" style="border-color: var(--theme-card-border);">';
              foreach ($subareas as $key => $subarea) {
                $unique_id = htmlspecialchars($row['id'] . '_' . $subarea);
                $p_cfg = $prefill_configs[$subarea] ?? ($prefill_configs[strtolower($subarea)] ?? [
                  'prefill_x' => '',
                  'placeholder_x' => '1/2xxxxx',
                  'prefill_y' => '4',
                  'placeholder_y' => '4xxxxx'
                ]);
                $init_x = $saved_hint_coords[$row['id']][$subarea]['rd_x'] ?? $p_cfg['prefill_x'];
                $init_y = $saved_hint_coords[$row['id']][$subarea]['rd_y'] ?? $p_cfg['prefill_y'];
                $placeholder_x = $p_cfg['placeholder_x'];
                $placeholder_y = $p_cfg['placeholder_y'];

                echo '
                    <div id="hint_row_' . $unique_id . '" class="theme-card rounded border p-3 flex flex-wrap items-center gap-2 shadow-sm transition-all flex-1" style="border-color: var(--theme-card-border);">
                      <div class="w-16 flex-shrink-0 text-center font-bold text-xs py-1.5 rounded uppercase tracking-wide shadow-sm" style="background-color:' . htmlspecialchars(getFoxColor($subarea)) . '; color: black;">
                        ' . ucfirst(htmlspecialchars($subarea)) . '
                      </div>
                      
                      <div class="flex-1 flex min-w-[120px] gap-2">
                        <input type="text" maxlength="4" class="w-1/2 border rounded px-2 py-1 text-sm text-gray-800 outline-none focus:ring-1 focus:ring-blue-500 shadow-sm font-mono transition-colors" id="rdX_' . $unique_id . '" name="rdX" placeholder="' . htmlspecialchars($placeholder_x) . '" value="' . htmlspecialchars($init_x) . '" data-subarea="' . htmlspecialchars($subarea) . '" data-prefill-x="' . htmlspecialchars($p_cfg['prefill_x']) . '">
                        <input type="text" maxlength="4" class="w-1/2 border rounded px-2 py-1 text-sm text-gray-800 outline-none focus:ring-1 focus:ring-blue-500 shadow-sm font-mono transition-colors" id="rdY_' . $unique_id . '" name="rdY" placeholder="' . htmlspecialchars($placeholder_y) . '" value="' . htmlspecialchars($init_y) . '" data-subarea="' . htmlspecialchars($subarea) . '" data-prefill-y="' . htmlspecialchars($p_cfg['prefill_y']) . '">
                      </div>
                      
                      <div class="flex gap-2 w-full sm:w-auto mt-2 sm:mt-0">
                        <button type="button" onclick="openProbeerModal(' . $row['id'] . ', \'' . htmlspecialchars($subarea, ENT_QUOTES) . '\')" class="flex-1 sm:flex-none text-xs bg-teal-600 hover:bg-teal-700 text-white font-bold py-1.5 px-3 rounded transition shadow-sm flex items-center justify-center gap-1 cursor-pointer">
                          <i class="fas fa-eye text-[10px]"></i> Probeer
                        </button>
                        <button type="button" id="save_btn_' . $unique_id . '" onclick="saveCoordinates(' . $row['id'] . ', \'' . htmlspecialchars($subarea, ENT_QUOTES) . '\')" class="flex-1 sm:flex-none text-xs bg-green-600 hover:bg-green-700 text-white font-bold py-1.5 px-3 rounded transition shadow-sm flex items-center justify-center gap-1 cursor-pointer">
                          <i class="fas fa-save text-[10px]"></i> Opslaan
                        </button>
                      </div>
                    </div>';
              }
              echo '
                  </div>';
            }
            echo '
              </div>';
          }
        } else {
          echo "<div class='theme-card rounded border p-8 text-center'><h4 class='text-lg opacity-70'>Nog geen hints beschikbaar...</h4></div>";
        }
        $stmt->close();
        ?>
      </div>
    </main>

    <?php require_once('includes/footer.php') ?>
  </div>

  <!-- Probeer Mapbox Modal -->
  <div id="probeer-map-modal" class="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-3 sm:p-6 hidden">
    <div class="theme-card rounded-2xl border shadow-2xl max-w-4xl w-full flex flex-col max-h-[90vh] overflow-hidden" style="background-color: var(--theme-card-bg); border-color: var(--theme-card-border);">
      <div class="theme-card-header px-5 py-3.5 flex justify-between items-center border-b" style="border-color: var(--theme-card-border);">
        <div class="flex items-center gap-2">
          <i class="fas fa-map-marked-alt text-blue-500 text-lg"></i>
          <h3 class="font-bold text-base sm:text-lg">Probeer Coördinaat & Vos Geschiedenis</h3>
        </div>
        <button type="button" onclick="closeProbeerModal()" class="transition text-lg p-1.5 rounded-lg hover:opacity-75 cursor-pointer opacity-80" style="color: var(--theme-text);">
          <i class="fas fa-times"></i>
        </button>
      </div>
      
      <div class="grid grid-cols-1 md:grid-cols-3 flex-1 overflow-hidden min-h-[380px] sm:min-h-[480px]">
        <!-- Mapbox map -->
        <div id="probe-modal-map" class="md:col-span-2 h-[320px] md:h-full w-full bg-slate-900 relative"></div>
        
        <!-- Stats / Info Panel -->
        <div class="p-4 overflow-y-auto border-t md:border-t-0 md:border-l flex flex-col justify-between" style="border-color: var(--theme-card-border);">
          <div id="probe-info-panel">
            <!-- Populated dynamically by hints.js -->
          </div>
          
          <div class="pt-4 border-t mt-3 flex flex-col gap-2" style="border-color: var(--theme-card-border);">
            <button type="button" id="modal-direct-save-btn" onclick="directSaveFromModal()" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-2.5 px-4 rounded-lg text-sm shadow transition flex items-center justify-center gap-1.5 hidden cursor-pointer">
              <i class="fas fa-check"></i> Direct Opslaan
            </button>
            <button type="button" onclick="closeProbeerModal()" class="w-full py-2.5 px-4 rounded-lg font-bold text-sm transition cursor-pointer border shadow-sm flex items-center justify-center gap-2 hover:opacity-85 active:scale-[0.99]" style="background-color: var(--theme-bg); border-color: var(--theme-card-border); color: var(--theme-text);">
              <i class="fas fa-times text-xs opacity-70"></i> Sluiten
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="js/gps.js"></script>
  <script src="js/assignments.js"></script>
  <script src="js/hints.js"></script>
  <script>
    initAssignments(<?= (int)$user_id ?>);
    initHints({
      mapboxKey: "<?= htmlspecialchars($site_settings['API_KEY_MAPBOX'] ?? '') ?>"
    });
  </script>
</body>
</html>
