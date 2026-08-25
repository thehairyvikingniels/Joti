<?php
// Sortable list of all submitted fox locations (hints, hunts, spots, predictions) with view, edit, and delete controls.
define("PAGE_NAME", "a_database");
require_once(__DIR__ . '/../includes/auth.php');
if ($privilege < 2) {
    header("Location: ../home");
    exit();
}

// Fetch Voslocaties
$stmt_vos = $conn->prepare("SELECT * FROM Voslocaties ORDER BY ingestuurd_op DESC");
$stmt_vos->execute();
$result_voslocaties = $stmt_vos->get_result();

?>
<!DOCTYPE html>
<html lang="nl">
<head>
<title>Jotify - Database</title>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="shortcut icon" type="image/png" href="../media/geusje.png"/>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://kit.fontawesome.com/870ab34ea3.js" crossorigin="anonymous"></script>
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<?php include_once('../includes/theme.php'); ?>
<style>
    th { cursor: pointer; user-select: none; }
    th .fas { margin-left: 5px; opacity: 0.3; transition: opacity 0.2s; }
    th:hover .fas { opacity: 0.6; }
    th .fas.active { opacity: 1; }
</style>
</head>
<body class="flex h-screen overflow-hidden">

<!-- Sidebar -->
<?php include_once('../includes/sidebar.php') ?>

<!-- Main Content -->
<div class="flex-1 flex flex-col h-screen overflow-y-auto w-full relative">
  <!-- Topbar -->
  <?php include_once('../includes/topbar.php') ?>

  <main class="p-4 md:p-6 max-w-[1400px] mx-auto w-full flex-1">

    <div class="space-y-6 mb-24">
      <div class="theme-card rounded-xl border shadow-sm overflow-hidden w-full mb-6">
        <div class="theme-card-header px-6 py-4 border-b text-white flex justify-between items-center" style="background-color: var(--theme-sidebar-active); border-color: var(--theme-card-border);">
          <h3 class="text-xl font-bold">Ingestuurde Locaties (Hints, Hunts, etc.)</h3>
        </div>
        <div class="p-0 overflow-x-auto">
          <table class="w-full text-sm text-left whitespace-nowrap" id="voslocatiesTable">
            <thead class="text-xs uppercase bg-black/5 border-b" style="border-color: var(--theme-card-border);">
              <tr>
                <th class="px-6 py-3 font-bold hover:bg-black/10 transition" onclick="sortTable(0)">Type <i class="fas fa-sort"></i></th>
                <th class="px-6 py-3 font-bold hover:bg-black/10 transition" onclick="sortTable(1)">Deelgebied <i class="fas fa-sort"></i></th>
                <th class="px-6 py-3 font-bold hover:bg-black/10 transition" onclick="sortTable(2)">Ingestuurd Op <i class="fas fa-sort"></i></th>
                <th class="px-6 py-3 font-bold">Coördinaten (Lat, Lon)</th>
                <th class="px-6 py-3 font-bold">Code</th>
                <th class="px-6 py-3 font-bold">Opmerking</th>
                <th class="px-6 py-3 font-bold text-right">Acties</th>
              </tr>
            </thead>
            <tbody class="divide-y" style="border-color: var(--theme-card-border);">
                <?php
                if ($result_voslocaties->num_rows > 0) {
                    while ($row = $result_voslocaties->fetch_assoc()) {
                        echo "<tr class='hover:bg-black/5 transition'>";
                        echo "<td class='px-6 py-4 font-medium'>" . htmlspecialchars($row['type']) . "</td>";
                        echo "<td class='px-6 py-4'>" . htmlspecialchars($row['deelgebied']) . "</td>";
                        echo "<td class='px-6 py-4 opacity-80'>" . date('Y-m-d H:i', strtotime($row['ingestuurd_op'])) . "</td>";
                        echo "<td class='px-6 py-4 opacity-80'>" . htmlspecialchars($row['coordinaat_x']) . ", " . htmlspecialchars($row['coordinaat_y']) . "</td>";
                        echo "<td class='px-6 py-4 opacity-80'>" . htmlspecialchars($row['code']) . "</td>";
                        echo "<td class='px-6 py-4 opacity-80 max-w-[200px] truncate' title='".htmlspecialchars($row['opmerking'])."'>" . htmlspecialchars($row['opmerking']) . "</td>";
                        
                        // Veilig encoderen van de JSON data voor gebruik in een HTML attribuut
                        $json_data = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                        
                        echo "<td class='px-6 py-4 text-right'>
                                <div class='flex items-center justify-end gap-2'>
                                  <button onclick='openEditModal(" . $json_data . ")' class='theme-bg-primary hover:opacity-80 text-white p-2 rounded shadow-sm transition' title='Bewerken'><i class='fas fa-pencil-alt'></i></button>
                                  <button onclick='openDeleteModal(" . (int)$row['id'] . ")' class='bg-red-500 hover:bg-red-600 text-white p-2 rounded shadow-sm transition' title='Verwijderen'><i class='fas fa-trash-alt'></i></button>
                                </div>
                              </td>";
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='7' class='px-6 py-8 text-center opacity-60'>Geen locaties gevonden.</td></tr>";
                }
                $stmt_vos->close();
                ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>

  <!-- Edit Modal -->
  <div id="editModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-black/60 backdrop-blur-sm modal-backdrop flex items-center justify-center p-4" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="relative inline-block theme-card border rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all max-w-lg w-full" onclick="event.stopPropagation()">
      <header class="theme-card-header px-6 py-4 border-b text-white flex justify-between items-center" style="background-color: var(--theme-sidebar-active); border-color: var(--theme-card-border);">
          <h4 class="text-lg font-bold flex items-center gap-2"><i class="fas fa-pencil-alt"></i> <span>Bewerk Locatie</span></h4>
          <button type="button" onclick="closeModal(this)" class="text-white opacity-70 hover:opacity-100 transition"><i class="fas fa-times text-xl"></i></button>
      </header>
      <form id="editForm" action="../functies.php" method="POST" class="p-6 space-y-4">
          <input type="hidden" id="edit_id" name="voslocatie_id">
          
          <div>
              <label class="block text-xs font-bold uppercase tracking-wider mb-1 opacity-80">Type</label>
              <select class="w-full theme-override-bg theme-override-text border rounded-xl px-3.5 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-500 shadow-sm" id="edit_type" name="type" required>
                  <option value="Hint">Hint</option>
                  <option value="Hunt">Hunt</option>
                  <option value="Spot">Spot</option>
                  <option value="Voorspelling">Voorspelling</option>
              </select>
          </div>
          <div>
              <label class="block text-xs font-bold uppercase tracking-wider mb-1 opacity-80">Deelgebied</label>
              <select class="w-full theme-override-bg theme-override-text border rounded-xl px-3.5 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-500 shadow-sm" id="edit_deelgebied" name="deelgebied" required>
                  <?php
                  foreach ($fox_names as $fox) {
                      echo "<option value=\"" . htmlspecialchars($fox) . "\">" . htmlspecialchars($fox) . "</option>\n";
                  }
                  ?>
              </select>
          </div>
          <div>
              <label class="block text-xs font-bold uppercase tracking-wider mb-1 opacity-80">Ingestuurd Op</label>
              <input class="w-full theme-override-bg theme-override-text border rounded-xl px-3.5 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-500 shadow-sm" type="datetime-local" id="edit_ingestuurd_op" name="ingestuurd_op" required>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider mb-1 opacity-80">Latitude (X)</label>
                <input class="w-full theme-override-bg theme-override-text border rounded-xl px-3.5 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-500 shadow-sm" type="text" id="edit_coord_x" name="coordinaat_x" required>
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider mb-1 opacity-80">Longitude (Y)</label>
                <input class="w-full theme-override-bg theme-override-text border rounded-xl px-3.5 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-500 shadow-sm" type="text" id="edit_coord_y" name="coordinaat_y" required>
            </div>
          </div>
          <div>
              <label class="block text-xs font-bold uppercase tracking-wider mb-1 opacity-80">Code (max. 8 tekens, verplicht bij Hunt)</label>
              <input class="w-full theme-override-bg theme-override-text border rounded-xl px-3.5 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-500 shadow-sm" type="text" id="edit_code" name="code" maxlength="8">
          </div>
          <div>
              <label class="block text-xs font-bold uppercase tracking-wider mb-1 opacity-80">Opmerking (max. 128 tekens)</label>
              <input class="w-full theme-override-bg theme-override-text border rounded-xl px-3.5 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-500 shadow-sm" type="text" id="edit_opmerking" name="opmerking" maxlength="128">
          </div>
          
          <div class="mt-6 pt-4 border-t flex justify-end gap-3" style="border-color: var(--theme-card-border);">
              <button type="button" onclick="closeModal(this)" class="theme-card border hover:bg-black/5 dark:hover:bg-white/5 py-2.5 px-5 rounded-xl font-bold text-sm transition">Annuleren</button>
              <button type="submit" name="update_voslocatie" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 px-6 rounded-xl transition shadow">Opslaan</button>
          </div>
      </form>
    </div>
  </div>

  <!-- Delete Modal -->
  <div id="deleteModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-black/60 backdrop-blur-sm modal-backdrop flex items-center justify-center p-4" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="relative inline-block theme-card border rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all max-w-md w-full" onclick="event.stopPropagation()">
        <header class="theme-card-header px-6 py-4 border-b text-white flex justify-between items-center" style="background-color: var(--theme-sidebar-active); border-color: var(--theme-card-border);">
            <h4 class="text-lg font-bold flex items-center gap-2"><i class="fas fa-trash-alt"></i> <span>Verwijder Locatie</span></h4>
            <button type="button" onclick="closeModal(this)" class="text-white opacity-70 hover:opacity-100 transition"><i class="fas fa-times text-xl"></i></button>
        </header>
        <div class="p-6">
            <p class="text-sm opacity-90 leading-relaxed">Weet je zeker dat je dit item wilt verwijderen? Deze actie kan niet ongedaan worden gemaakt.</p>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" onclick="closeModal(this)" class="theme-card border hover:bg-black/5 dark:hover:bg-white/5 py-2.5 px-5 rounded-xl font-bold text-sm transition">Annuleren</button>
                <a id="deleteLink" href="#" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2.5 px-6 rounded-xl transition shadow text-center">Verwijderen</a>
            </div>
        </div>
    </div>
  </div>

  <?php require_once('../includes/footer.php') ?>
</div>

<script src="../js/admin_database.js"></script>
<script src="../js/gps.js"></script>
<script>initGpsTracking('<?php echo $_SESSION['gps'] ?? 'false'; ?>');</script>

</body>
</html>