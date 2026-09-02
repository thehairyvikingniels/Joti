<?php
// Score breakdowns and ranking information for the user's team alongside the full Jotihunt leaderboard.
define("PAGE_NAME", "punten");
require_once('includes/auth.php');

// Get scout group count
$stmt = $conn->prepare("SELECT id, count(*) as NUM FROM Groepen");
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
  while($row = $result->fetch_assoc()) {
    $group_count = $row['NUM'];
  }
} else {
  $group_count = "E?";
}
$stmt->close();

// Get score from points table for own team
$my_group_id = (int)($site_settings['GROUP_ID'] ?? 0);
if ($my_group_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM Punten WHERE groep_id = ?");
    $stmt->bind_param("i", $my_group_id);
} else {
    $stmt = $conn->prepare("SELECT * FROM Punten ORDER BY (COALESCE(hunts,0) + COALESCE(tegenhunts,0) + COALESCE(opdrachten,0) + COALESCE(foto_opdrachten,0) + COALESCE(hints,0) + COALESCE(bonus,0) - COALESCE(strafpunten,0)) DESC LIMIT 1");
}

$rank = '?';
$hunts = 0;
$tegenhunts = 0;
$opdrachten = 0;
$fotoopdrachten = 0;
$hints = 0;
$bonus = 0;
$penalties = 0;
$total_points = 0;

if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
      while($row = $result->fetch_assoc()) {
          $rank = $row['plaats'] ?? '?';
          $hunts = $row['hunts'] ?? 0;
          $tegenhunts = $row['tegenhunts'] ?? 0;
          $opdrachten = $row['opdrachten'] ?? 0;
          $fotoopdrachten = $row['foto_opdrachten'] ?? 0;
          $hints = $row['hints'] ?? 0;
          $bonus = $row['bonus'] ?? 0;
          $penalties = $row['strafpunten'] ?? 0;
          $total_points = $hunts + $tegenhunts + $opdrachten + $fotoopdrachten + $hints + $bonus - $penalties;
      }
    }
    $stmt->close();
}

?>

<!DOCTYPE html>
<html lang="nl">
<head>
<title>Jotify - Punten</title>
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

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
      <!-- Your Team Score Card -->
      <div class="theme-card rounded-xl border shadow-sm overflow-hidden lg:col-span-1 h-fit mb-6">
        <div class="theme-card-header px-6 py-4 border-b text-white" style="background-color: var(--theme-sidebar-active); border-color: var(--theme-card-border);">
            <h5 class="text-lg font-bold flex items-center">
              <span class="text-2xl mr-2 font-black"><?php echo $rank;?>e</span> Plaats
            </h5>
        </div>
        <div class="p-0">
            <table class="w-full text-sm text-left">
              <tbody class="divide-y" style="border-color: var(--theme-card-border);">
                <tr class="hover:bg-black/5 transition">
                  <td class="px-6 py-3 font-medium">Hunts</td>
                  <td class="px-6 py-3 text-right font-bold"><?php echo $hunts;?></td>
                </tr>
                <tr class="hover:bg-black/5 transition">
                  <td class="px-6 py-3 font-medium">Tegenhunts</td>
                  <td class="px-6 py-3 text-right font-bold"><?php echo $tegenhunts;?></td>
                </tr>
                <tr class="hover:bg-black/5 transition">
                  <td class="px-6 py-3 font-medium">Opdrachten</td>
                  <td class="px-6 py-3 text-right font-bold"><?php echo $opdrachten;?></td>
                </tr>
                <tr class="hover:bg-black/5 transition">
                  <td class="px-6 py-3 font-medium">Fotoopdrachten</td>
                  <td class="px-6 py-3 text-right font-bold"><?php echo $fotoopdrachten;?></td>
                </tr>
                <tr class="hover:bg-black/5 transition">
                  <td class="px-6 py-3 font-medium">Hints</td>
                  <td class="px-6 py-3 text-right font-bold"><?php echo $hints;?></td>
                </tr>
                <tr class="hover:bg-black/5 transition">
                  <td class="px-6 py-3 font-medium text-green-600 dark:text-green-400">Bonus</td>
                  <td class="px-6 py-3 text-right font-bold text-green-600 dark:text-green-400"><?php echo $bonus;?></td>
                </tr>
                <tr class="hover:bg-black/5 transition">
                  <td class="px-6 py-3 font-medium text-red-600 dark:text-red-400">Penalties</td>
                  <td class="px-6 py-3 text-right font-bold text-red-600 dark:text-red-400"><?php echo $penalties;?></td>
                </tr>
                <tr class="hover:bg-black/5 transition" style="background-color: var(--theme-card-header);">
                  <td class="px-6 py-4 font-bold text-base uppercase">Totaal</td>
                  <td class="px-6 py-4 text-right font-bold text-base"><?php echo $total_points;?></td>
                </tr>
              </tbody>
            </table>
        </div>
      </div>

      <!-- Scoreboard Card -->
      <div class="theme-card rounded-xl border shadow-sm overflow-hidden lg:col-span-2 mb-6">
        <div class="theme-card-header px-6 py-4 border-b text-white" style="background-color: var(--theme-sidebar-active); border-color: var(--theme-card-border);">
            <h5 class="text-lg font-bold">Scorelijst</h5>
        </div>
        <div class="overflow-x-auto max-h-[600px]">
          <?php
          // Get the points for own group
          $stmt = $conn->prepare("SELECT * FROM Punten ORDER BY (hunts + tegenhunts + opdrachten + foto_opdrachten + hints + bonus - strafpunten) DESC");
          $stmt->execute();
          $result = $stmt->get_result();

          if ($result->num_rows > 0) {
            echo '
            <table class="w-full text-sm text-left whitespace-nowrap">
              <thead class="text-xs uppercase sticky top-0 z-10 shadow-sm" style="background-color: var(--theme-card-header);">
                <tr>
                  <th class="px-4 py-3 font-bold border-b" style="border-color: var(--theme-card-border);">Plts</th>
                  <th class="px-4 py-3 font-bold border-b" style="border-color: var(--theme-card-border);">Team</th>
                  <th class="px-4 py-3 font-bold border-b text-center" style="border-color: var(--theme-card-border);">Hunts</th>
                  <th class="px-4 py-3 font-bold border-b text-center" style="border-color: var(--theme-card-border);">T.Hunts</th>
                  <th class="px-4 py-3 font-bold border-b text-center" style="border-color: var(--theme-card-border);">Opdr</th>
                  <th class="px-4 py-3 font-bold border-b text-center" style="border-color: var(--theme-card-border);">F.Opdr</th>
                  <th class="px-4 py-3 font-bold border-b text-center" style="border-color: var(--theme-card-border);">Hints</th>
                  <th class="px-4 py-3 font-bold border-b text-center" style="border-color: var(--theme-card-border);">Bonus</th>
                  <th class="px-4 py-3 font-bold border-b text-center" style="border-color: var(--theme-card-border);">Pen</th>
                  <th class="px-4 py-3 font-bold border-b text-right text-base" style="border-color: var(--theme-card-border);">Totaal</th>
                </tr>
              </thead>
              <tbody class="divide-y" style="border-color: var(--theme-card-border);">';
            while($row = $result->fetch_assoc()) {
              // Fetch team name based on groep_id
              $teamName = "Groep " . $row['groep_id'];
              $stmt2 = $conn->prepare("SELECT naam FROM Groepen WHERE id=?");
              $stmt2->bind_param("i", $row['groep_id']);
              $stmt2->execute();
              $res2 = $stmt2->get_result();
              if($r2 = $res2->fetch_assoc()) {
                $teamName = $r2['naam'];
              }
              $stmt2->close();
              
              $isUs = ($row['groep_id'] == $my_group_id) || ($my_group_id == 0 && stripos($teamName, 'geuzen') !== false);
              $rowClass = $isUs ? 'bg-blue-500/10 font-bold' : 'hover:bg-black/5 transition';

              echo '
              <tr class="'.$rowClass.'">
                <td class="px-4 py-3 text-center">'.($row['plaats'] ?? "?").'</td>
                <td class="px-4 py-3 font-medium">'.$teamName.'</td>
                <td class="px-4 py-3 text-center">'.($row['hunts'] ?? 0).'</td>
                <td class="px-4 py-3 text-center">'.($row['tegenhunts'] ?? 0).'</td>
                <td class="px-4 py-3 text-center">'.($row['opdrachten'] ?? 0).'</td>
                <td class="px-4 py-3 text-center">'.($row['foto_opdrachten'] ?? 0).'</td>
                <td class="px-4 py-3 text-center">'.($row['hints'] ?? 0).'</td>
                <td class="px-4 py-3 text-center text-green-600 dark:text-green-400">'.($row['bonus'] ?? 0).'</td>
                <td class="px-4 py-3 text-center text-red-600 dark:text-red-400">'.($row['strafpunten'] ?? 0).'</td>
                <td class="px-4 py-3 text-right font-bold text-base">'.( ($row['hunts'] ?? 0) + ($row['tegenhunts'] ?? 0) + ($row['opdrachten'] ?? 0) + ($row['foto_opdrachten'] ?? 0) + ($row['hints'] ?? 0) + ($row['bonus'] ?? 0) - ($row['strafpunten'] ?? 0) ).'</td>
              </tr>
              ';
            }
            echo '</tbody></table>';
          } else {
            echo "<div class='p-8 text-center opacity-70'>
                    <i class='fas fa-trophy text-4xl mb-3 block'></i>
                    <h3 class='text-lg font-bold'>Nog geen punt-gegevens beschikbaar...</h3>
                  </div>";
          }
          $stmt->close();
        ?>
        </div>
      </div>
    </div>
  </main>

  <?php require_once('includes/footer.php') ?>
</div>

<script src="js/gps.js"></script>
<script>initGpsTracking('<?php echo $_SESSION['gps'] ?? 'false'; ?>');</script>

</body>
</html>
