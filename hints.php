<?php
// Displays published puzzle hints and provides a form for submitting solved RD coordinates as new hint locations.
define("PAGE_NAME", "hints");
require_once('includes/auth.php');

// Insert voslocaties after using hints form
if (isset($privilege) && $privilege > 0 && isset($_POST['subarea']) && isset($_POST['rdX']) && isset($_POST['rdY'])) {
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
  <title>Jotify - Hints</title>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="shortcut icon" type="image/png" href="media/geusje.png" />
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

      <div class="space-y-6 mb-12">
        <?php
        $stmt = $conn->prepare("SELECT * FROM Hints ORDER BY datum DESC");
        $stmt->execute();
        $result = $stmt->get_result();

        $vossen = $fox_names;

        if ($result->num_rows > 0) {
          while ($row = $result->fetch_assoc()) {
            $content = $row['inhoud'];
            $doc = new DOMDocument();
            @$doc->loadHTML($content);
            $imgNodes = $doc->getElementsByTagName('img');
            foreach ($imgNodes as $node) {
              $node->setAttribute('width', '100%');
              $node->removeAttribute('height');
              // Ensure images are responsive
              $classes = $node->getAttribute('class');
              $node->setAttribute('class', $classes . ' rounded my-4 w-full object-cover max-h-[500px]');
            }

            echo '
              <div class="theme-card rounded-xl border shadow-sm overflow-hidden mb-6">
                <div class="theme-card-header px-6 py-4 flex justify-between items-center border-b" style="border-color: var(--theme-card-border);">
                  <h3 class="text-xl font-bold">' . htmlspecialchars($row['titel']) . '</h3>
                  <span class="text-sm opacity-60 font-medium">' . date("d/m H:i", strtotime($row['datum'])) . '</span>
                </div>
                
                <div class="p-6 prose max-w-none text-current opacity-90 overflow-x-auto">
                  ' . $doc->saveHTML() . '
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

                echo '
                    <form action="hints.php" method="POST" class="flex-1">
                      <div class="theme-card rounded border p-3 flex flex-wrap items-center gap-2 shadow-sm" style="border-color: var(--theme-card-border);">
                        <div class="w-16 flex-shrink-0 text-center font-bold text-xs py-1.5 rounded uppercase tracking-wide shadow-sm" style="background-color:' . htmlspecialchars(getFoxColor($subarea)) . '; color: black;">
                          ' . ucfirst(htmlspecialchars($subarea)) . '
                        </div>
                        
                        <div class="flex-1 flex min-w-[120px] gap-2">
                          <input type="number" class="w-1/2 border rounded px-2 py-1 text-sm text-gray-800 outline-none focus:ring-1 focus:ring-blue-500 shadow-sm" id="rdX_' . $unique_id . '" name="rdX" placeholder="rdX">
                          <input type="number" class="w-1/2 border rounded px-2 py-1 text-sm text-gray-800 outline-none focus:ring-1 focus:ring-blue-500 shadow-sm" id="rdY_' . $unique_id . '" name="rdY" placeholder="rdY">
                        </div>
                        
                        <input type="hidden" id="subarea_' . $unique_id . '" name="subarea" value="' . htmlspecialchars($subarea) . '"> 
                        
                        <div class="flex gap-2 w-full sm:w-auto mt-2 sm:mt-0">
                          <button type="button" class="flex-1 sm:flex-none text-xs bg-teal-600 hover:bg-teal-700 text-white font-bold py-1.5 px-3 rounded transition shadow-sm">Probeer</button>
                          <button type="submit" class="flex-1 sm:flex-none text-xs bg-green-600 hover:bg-green-700 text-white font-bold py-1.5 px-3 rounded transition shadow-sm">Opslaan</button>
                        </div>
                      </div>
                    </form>';
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

  <script src="js/gps.js"></script>
<script src="js/assignments.js"></script>
<script>
initAssignments(<?= (int)$user_id ?>);
</script>
</body>
</html>
