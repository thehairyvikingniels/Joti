<?php
// System dashboard: version info, update management, backups, and live system metrics.
define("PAGE_NAME", "sa_system");
require_once(__DIR__ . '/../includes/auth.php');

if ($privilege < 3) {
    header("Location: ../home");
    exit();
}

$webroot = realpath(__DIR__ . '/..');

function get_server_git_val(string $cmd, string $webroot): string {
    $cleanCmd = preg_replace('/^git\s+/', 'git -c safe.directory=* ', trim($cmd));
    $output = shell_exec('cd ' . escapeshellarg($webroot) . ' && ' . $cleanCmd . ' 2>/dev/null');
    return trim($output ?? '');
}

$branch = get_server_git_val('git rev-parse --abbrev-ref HEAD', $webroot) ?: 'main';
$commit = get_server_git_val('git rev-parse --short HEAD', $webroot) ?: 'onbekend';
$commitDate = get_server_git_val('git log -1 --format="%ad" --date=format:"%d-%m-%Y %H:%M"', $webroot);
$commitMsg = get_server_git_val('git log -1 --format="%s"', $webroot);
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<title>Jotify - Systeem</title>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="shortcut icon" type="image/png" href="../media/geusje.png"/>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://kit.fontawesome.com/870ab34ea3.js" crossorigin="anonymous"></script>
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<?php include_once('../includes/theme.php'); ?>
</head>
<body class="flex h-screen overflow-hidden">

<!-- Sidebar -->
<?php include_once('../includes/sidebar.php') ?>

<!-- Main Content -->
<div class="flex-1 flex flex-col h-screen overflow-y-auto w-full relative">
  <!-- Topbar -->
  <?php include_once('../includes/topbar.php') ?>

  <main class="p-4 md:p-6 max-w-[1400px] mx-auto w-full flex-1">

    <div class="space-y-6 mb-24 max-w-5xl">

      <!-- Dynamic Feedback Alerts -->
      <div id="status-alert" class="hidden px-4 py-3 rounded-lg border relative shadow-sm flex items-center justify-between">
        <div class="flex items-center gap-3">
          <i id="status-alert-icon" class="fas fa-check-circle text-lg"></i>
          <span id="status-alert-text" class="text-sm font-medium"></span>
        </div>
        <button type="button" onclick="document.getElementById('status-alert').classList.add('hidden')" class="opacity-70 hover:opacity-100 transition">
          <i class="fas fa-times"></i>
        </button>
      </div>

      <!-- Card 0: Live System Metrics -->
      <div class="theme-card rounded-xl border shadow-sm overflow-hidden mb-6">
        <div class="theme-card-header px-6 py-4 border-b text-white flex justify-between items-center" style="background-color: var(--theme-sidebar-active); border-color: var(--theme-card-border);">
          <h3 class="text-xl font-bold flex items-center gap-2">
            <i class="fas fa-gauge-high"></i> <span>Systeembelasting</span>
          </h3>
          <div class="flex items-center gap-2">
            <span id="metrics-last-update" class="text-xs opacity-60">Laden...</span>
            <button onclick="refreshMetrics()" class="bg-white/20 hover:bg-white/30 text-white text-xs font-semibold px-3 py-1.5 rounded-lg transition flex items-center gap-1.5">
              <i id="icon-metrics-spin" class="fas fa-rotate"></i>
              <span>Ververs</span>
            </button>
          </div>
        </div>
        <div class="p-6">
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

            <!-- CPU -->
            <div class="p-4 rounded-xl border bg-black/5 flex flex-col justify-between gap-3 min-w-0" style="border-color: var(--theme-card-border);">
              <div class="flex items-center justify-between">
                <span class="text-xs uppercase tracking-wider opacity-60 font-semibold flex items-center gap-1.5">
                  <i class="fas fa-microchip opacity-70"></i> CPU
                </span>
                <span id="cpu-current" class="font-mono text-lg font-bold theme-primary">—%</span>
              </div>
              <div class="w-full bg-black/10 rounded-full h-2.5 overflow-hidden">
                <div id="cpu-bar" class="h-full rounded-full bg-blue-500 transition-all duration-700" style="width: 0%"></div>
              </div>
              <div class="flex justify-between text-xs opacity-60">
                <span>Actueel</span>
                <span>5 min gem: <strong id="cpu-avg" class="font-mono">—%</strong></span>
              </div>
            </div>

            <!-- RAM -->
            <div class="p-4 rounded-xl border bg-black/5 flex flex-col justify-between gap-3 min-w-0" style="border-color: var(--theme-card-border);">
              <div class="flex items-center justify-between">
                <span class="text-xs uppercase tracking-wider opacity-60 font-semibold flex items-center gap-1.5">
                  <i class="fas fa-memory opacity-70"></i> RAM
                </span>
                <span id="ram-current" class="font-mono text-lg font-bold theme-primary">—%</span>
              </div>
              <div class="w-full bg-black/10 rounded-full h-2.5 overflow-hidden">
                <div id="ram-bar" class="h-full rounded-full bg-emerald-500 transition-all duration-700" style="width: 0%"></div>
              </div>
              <div class="flex justify-between text-xs opacity-60">
                <span id="ram-used">— / — GB</span>
                <span>5 min gem: <strong id="ram-avg" class="font-mono">—%</strong></span>
              </div>
            </div>

            <!-- Network -->
            <div class="p-4 rounded-xl border bg-black/5 flex flex-col justify-between gap-3 min-w-0 overflow-hidden" style="border-color: var(--theme-card-border);">
              <div class="flex items-center justify-between min-w-0">
                <span class="text-xs uppercase tracking-wider opacity-60 font-semibold flex items-center gap-1.5 truncate">
                  <i class="fas fa-network-wired opacity-70"></i> Netwerk
                </span>
                <span id="net-interface" class="font-mono text-xs opacity-60 shrink-0">eth0</span>
              </div>
              <div class="grid grid-cols-2 gap-2 min-w-0">
                <div class="min-w-0 text-center p-2 rounded-lg bg-black/5 flex flex-col justify-between overflow-hidden">
                  <div class="min-w-0">
                    <div class="text-[11px] opacity-70 mb-0.5 truncate flex items-center justify-center gap-1">
                      <i class="fas fa-arrow-down text-emerald-500 text-[10px]"></i> <span class="font-medium">In (RX)</span>
                    </div>
                    <div id="net-rx" class="font-mono text-xs sm:text-sm font-bold truncate leading-tight py-0.5 tracking-tight">—</div>
                  </div>
                  <div class="text-[10px] opacity-60 mt-1 border-t border-black/5 pt-1 truncate">
                    <span class="text-[9px] opacity-75">5m:</span> <strong id="net-avg-rx" class="font-mono text-emerald-600 dark:text-emerald-400">—</strong>
                  </div>
                </div>
                <div class="min-w-0 text-center p-2 rounded-lg bg-black/5 flex flex-col justify-between overflow-hidden">
                  <div class="min-w-0">
                    <div class="text-[11px] opacity-70 mb-0.5 truncate flex items-center justify-center gap-1">
                      <i class="fas fa-arrow-up text-blue-500 text-[10px]"></i> <span class="font-medium">Uit (TX)</span>
                    </div>
                    <div id="net-tx" class="font-mono text-xs sm:text-sm font-bold truncate leading-tight py-0.5 tracking-tight">—</div>
                  </div>
                  <div class="text-[10px] opacity-60 mt-1 border-t border-black/5 pt-1 truncate">
                    <span class="text-[9px] opacity-75">5m:</span> <strong id="net-avg-tx" class="font-mono text-blue-600 dark:text-blue-400">—</strong>
                  </div>
                </div>
              </div>
              <div class="flex justify-between items-center text-xs opacity-60 min-w-0">
                <span class="truncate">Totaal Verkeer</span>
                <span id="net-total-rate" class="font-mono font-medium shrink-0 ml-1">—</span>
              </div>
            </div>

            <!-- Storage -->
            <div class="p-4 rounded-xl border bg-black/5 flex flex-col justify-between gap-3 min-w-0" style="border-color: var(--theme-card-border);">
              <div class="flex items-center justify-between">
                <span class="text-xs uppercase tracking-wider opacity-60 font-semibold flex items-center gap-1.5">
                  <i class="fas fa-hard-drive opacity-70"></i> Opslag
                </span>
                <span id="storage-pct" class="font-mono text-lg font-bold theme-primary">—%</span>
              </div>
              <div class="w-full bg-black/10 rounded-full h-2.5 overflow-hidden">
                <div id="storage-bar" class="h-full rounded-full bg-amber-500 transition-all duration-700" style="width: 0%"></div>
              </div>
              <div class="flex justify-between text-xs opacity-60">
                <span id="storage-used">— / — GB</span>
                <span id="storage-free">— GB vrij</span>
              </div>
            </div>

          </div>
        </div>
      </div>

      <!-- Card 1: Huidige Systeemstatus -->
      <div class="theme-card rounded-xl border shadow-sm overflow-hidden mb-6">
        <div class="theme-card-header px-6 py-4 border-b text-white flex justify-between items-center" style="background-color: var(--theme-sidebar-active); border-color: var(--theme-card-border);">
          <h3 class="text-xl font-bold flex items-center gap-2">
            <i class="fas fa-microchip"></i> <span>Systeemstatus & Huidige Versie</span>
          </h3>
          <div class="flex items-center gap-2">
            <button id="btn-check-updates" onclick="checkUpdates()" class="bg-white/20 hover:bg-white/30 text-white text-xs font-semibold px-3 py-1.5 rounded-lg transition flex items-center gap-1.5">
              <i id="icon-check-spin" class="fas fa-rotate"></i>
              <span>Controleer op Updates</span>
            </button>
          </div>
        </div>
        <div class="p-6">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            
            <!-- Versie / Commit -->
            <div class="p-4 rounded-xl border bg-black/5 flex flex-col justify-between" style="border-color: var(--theme-card-border);">
              <span class="text-xs uppercase tracking-wider opacity-60 font-semibold mb-1">Geïnstalleerde Versie</span>
              <div class="flex items-baseline gap-2 mb-1">
                <a id="display-commit-link" href="https://github.com/thehairyvikingniels/Joti/commit/<?= htmlspecialchars($commit) ?>" target="_blank" class="font-mono text-xl font-bold hover:underline theme-primary">
                  <?= htmlspecialchars($commit) ?>
                </a>
                <span class="text-xs opacity-60 font-mono">(HEAD)</span>
              </div>
              <p id="display-commit-date" class="text-xs opacity-70 truncate"><?= htmlspecialchars($commitDate) ?></p>
            </div>

            <!-- Actieve Branch -->
            <div class="p-4 rounded-xl border bg-black/5 flex flex-col justify-between" style="border-color: var(--theme-card-border);">
              <span class="text-xs uppercase tracking-wider opacity-60 font-semibold mb-1">Actieve Git Branch</span>
              <div class="flex items-center gap-2 mb-1">
                <i class="fas fa-code-branch opacity-60 text-sm"></i>
                <select id="select-branch" onchange="promptSwitchBranch(this.value)" class="bg-transparent font-bold text-base focus:outline-none cursor-pointer border-b border-dashed border-current">
                  <option value="<?= htmlspecialchars($branch) ?>" selected><?= htmlspecialchars($branch) ?></option>
                </select>
              </div>
              <p class="text-xs opacity-60">Wissel tussen hoofdbranches</p>
            </div>

            <!-- Up-to-date Status -->
            <div class="p-4 rounded-xl border bg-black/5 flex flex-col justify-between" style="border-color: var(--theme-card-border);">
              <span class="text-xs uppercase tracking-wider opacity-60 font-semibold mb-1">Updatestatus</span>
              <div id="status-pill-container" class="flex items-center gap-2 mb-1">
                <span id="status-badge" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-500/20 text-emerald-600 border border-emerald-500/30">
                  <i class="fas fa-check-circle"></i>
                  <span>Up-to-date</span>
                </span>
              </div>
              <p id="status-subtext" class="text-xs opacity-70">Laatste controle: <span id="last-check-time">Zojuist</span></p>
            </div>

          </div>

          <div class="mt-4 pt-4 border-t text-xs opacity-70 flex items-center justify-between" style="border-color: var(--theme-card-border);">
            <div class="flex items-center gap-2 truncate">
              <i class="fas fa-comment-alt opacity-50"></i>
              <span id="display-commit-msg" class="truncate"><?= htmlspecialchars($commitMsg) ?></span>
            </div>
          </div>
        </div>
      </div>

      <!-- Card 2: Beschikbare Updates (Verborgen als up-to-date) -->
      <div id="card-updates-available" class="hidden theme-card rounded-xl border shadow-sm overflow-hidden mb-6">
        <div class="theme-card-header px-6 py-4 border-b text-white flex justify-between items-center bg-amber-600" style="border-color: var(--theme-card-border);">
          <h3 class="text-xl font-bold flex items-center gap-2">
            <i class="fas fa-cloud-arrow-down"></i>
            <span>Nieuwe Updates Beschikbaar!</span>
          </h3>
          <span id="badge-commits-count" class="bg-black/20 text-white font-mono text-xs font-bold px-2.5 py-1 rounded-full">
            0 commits achter
          </span>
        </div>
        <div class="p-6 space-y-6">

          <!-- Impact Tags -->
          <div>
            <h4 class="text-sm font-semibold uppercase tracking-wider opacity-60 mb-2">Gedetecteerde Wijzigingen:</h4>
            <div id="impact-tags-container" class="flex flex-wrap gap-2">
              <!-- Dynamically populated -->
            </div>
          </div>

          <!-- Changelog Commits Timeline -->
          <div>
            <h4 class="text-sm font-semibold uppercase tracking-wider opacity-60 mb-3">Changelog & Commits:</h4>
            <div id="commits-list-container" class="space-y-2 max-h-72 overflow-y-auto pr-2">
              <!-- Dynamically populated -->
            </div>
          </div>

          <!-- Action & Backup Bar -->
          <div class="p-4 rounded-xl border bg-black/5 flex flex-col md:flex-row items-center justify-between gap-4" style="border-color: var(--theme-card-border);">
            <div class="flex items-center gap-3">
              <input type="checkbox" id="check-backup-before" checked class="w-4 h-4 rounded text-blue-600 focus:ring-blue-500 cursor-pointer">
              <label for="check-backup-before" class="text-sm cursor-pointer select-none">
                <strong>Maak vooraf automatisch een database back-up</strong>
                <span class="block text-xs opacity-70">Slaat een momentopname op in DB/backups/ voor eventuele rollback.</span>
              </label>
            </div>
            <button onclick="confirmAndPerformUpdate()" class="theme-bg-primary hover:opacity-80 text-white font-bold px-6 py-2.5 rounded-lg shadow-md transition flex items-center gap-2 flex-shrink-0">
              <i class="fas fa-bolt"></i>
              <span>Nu Bijwerken naar Nieuwste Versie</span>
            </button>
          </div>

        </div>
      </div>

      <!-- Card 3: Systeem Back-ups -->
      <div class="theme-card rounded-xl border shadow-sm overflow-hidden mb-6">
        <div class="theme-card-header px-6 py-4 border-b text-white flex justify-between items-center" style="background-color: var(--theme-sidebar-active); border-color: var(--theme-card-border);">
          <h3 class="text-xl font-bold flex items-center gap-2">
            <i class="fas fa-database"></i> <span>Systeem Back-ups (Database & Foto's)</span>
          </h3>
          <div class="flex items-center gap-2">
            <input type="file" id="input-upload-backup" accept=".tar.gz,.tar.xz" class="hidden" onchange="handleUploadBackup(event)">
            <button onclick="document.getElementById('input-upload-backup').click()" id="btn-upload-backup" class="bg-white/20 hover:bg-white/30 text-white text-xs font-semibold px-3 py-1.5 rounded-lg transition flex items-center gap-1.5">
              <i class="fas fa-upload"></i>
              <span>Upload Back-up</span>
            </button>
            <button onclick="createBackupNow()" id="btn-create-backup" class="bg-white/20 hover:bg-white/30 text-white text-xs font-semibold px-3 py-1.5 rounded-lg transition flex items-center gap-1.5">
              <i id="icon-backup-spin" class="fas fa-plus"></i>
              <span id="text-backup-btn">Nieuwe Back-up Maken</span>
            </button>
          </div>
        </div>
        <div class="p-0 overflow-x-auto">
          <table class="w-full text-sm text-left">
            <thead class="text-xs uppercase bg-black/5 border-b" style="border-color: var(--theme-card-border);">
              <tr>
                <th class="px-4 py-3 font-bold">Bestandsnaam</th>
                <th class="px-4 py-3 font-bold w-28 whitespace-nowrap">Type</th>
                <th class="px-4 py-3 font-bold w-28 whitespace-nowrap">Build / Versie</th>
                <th class="px-4 py-3 font-bold w-36 whitespace-nowrap">Datum & Tijd</th>
                <th class="px-4 py-3 font-bold w-24 whitespace-nowrap">Grootte</th>
                <th class="px-4 py-3 font-bold w-24 whitespace-nowrap text-right">Acties</th>
              </tr>
            </thead>
            <tbody id="backups-table-body" class="divide-y" style="border-color: var(--theme-card-border);">
              <tr>
                <td colspan="6" class="px-6 py-6 text-center opacity-60">Back-ups worden geladen...</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </main>

  <!-- Modal: Live Update Uitvoering -->
  <div id="modal-update" class="fixed inset-0 bg-black/70 z-50 hidden flex items-center justify-center p-4">
    <div class="theme-card rounded-2xl border shadow-2xl w-full max-w-2xl overflow-hidden animate-fadeIn" style="border-color: var(--theme-card-border);">
      <div class="px-6 py-4 border-b text-white flex items-center justify-between" style="background-color: var(--theme-sidebar-active); border-color: var(--theme-card-border);">
        <h3 class="text-lg font-bold flex items-center gap-2">
          <i id="modal-icon" class="fas fa-rotate animate-spin"></i>
          <span id="modal-title">Systeemupdate Wordt Uitgevoerd...</span>
        </h3>
      </div>
      <div class="p-6 space-y-6">
        
        <!-- Status Step List -->
        <div id="modal-steps-container" class="space-y-3">
          <!-- Populated by JS -->
        </div>

        <!-- Terminal Output Log -->
        <div class="p-3 bg-black/90 rounded-xl border border-white/10 font-mono text-xs text-emerald-400 max-h-48 overflow-y-auto" id="modal-console-log">
          <div>[INFO] Update proces gestart...</div>
        </div>

        <div id="modal-actions" class="hidden flex justify-end gap-3 pt-2">
          <button onclick="window.location.reload()" class="theme-bg-primary hover:opacity-80 text-white font-bold px-6 py-2.5 rounded-lg shadow transition flex items-center gap-2">
            <i class="fas fa-arrows-rotate"></i>
            <span>Pagina Herladen</span>
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal: Wisselen van Branch Bevestigen -->
  <div id="modal-switch-branch" class="fixed inset-0 bg-black/70 z-50 hidden flex items-center justify-center p-4">
    <div class="theme-card rounded-2xl border shadow-2xl w-full max-w-md overflow-hidden" style="border-color: var(--theme-card-border);">
      <div class="px-6 py-4 border-b text-white flex items-center justify-between" style="background-color: var(--theme-sidebar-active); border-color: var(--theme-card-border);">
        <h3 class="text-lg font-bold flex items-center gap-2">
          <i class="fas fa-code-branch"></i>
          <span>Wisselen van Git Branch</span>
        </h3>
        <button onclick="closeSwitchBranchModal()" class="opacity-70 hover:opacity-100"><i class="fas fa-times"></i></button>
      </div>
      <div class="p-6 space-y-4">
        <p class="text-sm">Weet je zeker dat je wilt wisselen naar branch <strong id="switch-target-branch" class="font-mono"></strong>?</p>
        <p class="text-xs opacity-70">De server voert een checkout en pull uit van deze branch. Eventuele gewijzigde codebestanden worden gesynchroniseerd.</p>
        <div class="flex justify-end gap-3 pt-2">
          <button onclick="closeSwitchBranchModal()" class="px-4 py-2 text-sm font-semibold rounded-lg border hover:bg-black/5 transition" style="border-color: var(--theme-card-border);">
            Annuleren
          </button>
          <button id="btn-confirm-switch" onclick="executeSwitchBranch()" class="theme-bg-primary hover:opacity-80 text-white text-sm font-bold px-4 py-2 rounded-lg shadow transition">
            Wissel Branch
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal: Back-up Herstellen -->
  <div id="modal-restore" class="fixed inset-0 bg-black/70 z-50 hidden flex items-center justify-center p-4">
    <div class="theme-card rounded-2xl border shadow-2xl w-full max-w-lg overflow-hidden animate-fadeIn" style="border-color: var(--theme-card-border);">
      <div class="px-6 py-4 border-b text-white flex items-center justify-between bg-amber-600">
        <h3 class="text-lg font-bold flex items-center gap-2">
          <i class="fas fa-triangle-exclamation"></i>
          <span>Back-up Herstellen</span>
        </h3>
        <button onclick="closeRestoreModal()" class="text-white/80 hover:text-white"><i class="fas fa-times"></i></button>
      </div>
      <div class="p-6 space-y-4">
        <div id="restore-warning-user" class="hidden p-3.5 rounded-lg bg-red-500/15 border border-red-500/40 text-red-700 dark:text-red-300 text-xs flex items-start gap-2.5">
          <i class="fas fa-exclamation-circle text-base flex-shrink-0 mt-0.5"></i>
          <div>
            <strong class="block font-bold">Gebruikersaccount Waarschuwing!</strong>
            <span id="restore-warning-user-text">Jouw account is niet aanwezig in deze back-up!</span>
          </div>
        </div>

        <p class="text-sm">
          Weet je zeker dat je back-up <strong id="restore-target-filename" class="font-mono theme-primary"></strong> wilt terugzetten?
        </p>

        <div class="p-3.5 rounded-lg border bg-black/5 text-xs space-y-2" style="border-color: var(--theme-card-border);">
          <div class="flex items-center gap-2 text-amber-500 font-semibold">
            <i class="fas fa-info-circle"></i>
            <span>Gevolgen van het herstel:</span>
          </div>
          <ul class="list-disc pl-5 space-y-1 opacity-80">
            <li>Overschrijft alle huidige tabellen en gegevens in de database.</li>
            <li>Zet opgeslagen profielfoto's terug naar de staat van de back-up.</li>
            <li>Downgradet het actieve systeem naar commit <strong id="restore-target-commit" class="font-mono"></strong>.</li>
          </ul>
        </div>

        <div class="flex items-center gap-3 pt-2">
          <input type="checkbox" id="check-backup-before-restore" checked class="w-4 h-4 rounded text-blue-600 focus:ring-blue-500 cursor-pointer">
          <label for="check-backup-before-restore" class="text-xs cursor-pointer select-none">
            <strong>Maak eerst een veiligheidsback-up van de huidige staat</strong>
          </label>
        </div>

        <div class="pt-4 border-t flex items-center justify-end gap-3" style="border-color: var(--theme-card-border);">
          <button onclick="closeRestoreModal()" class="px-4 py-2 text-sm font-semibold rounded-lg border hover:bg-black/5 transition" style="border-color: var(--theme-card-border);">
            Annuleren
          </button>
          <button id="btn-confirm-restore" onclick="executeRestoreBackup()" class="bg-amber-600 hover:bg-amber-700 text-white font-bold px-5 py-2 text-sm rounded-lg shadow transition flex items-center gap-2">
            <i class="fas fa-history"></i>
            <span>Ja, Herstel Back-up</span>
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal: Back-up Verwijderen Bevestigen -->
  <div id="modal-delete-backup" class="fixed inset-0 bg-black/70 z-50 hidden flex items-center justify-center p-4">
    <div class="theme-card rounded-2xl border shadow-2xl w-full max-w-md overflow-hidden animate-fadeIn" style="border-color: var(--theme-card-border);">
      <div class="px-6 py-4 border-b text-white flex items-center justify-between bg-red-600">
        <h3 class="text-lg font-bold flex items-center gap-2">
          <i class="fas fa-trash-alt"></i>
          <span>Back-up Verwijderen</span>
        </h3>
        <button onclick="closeDeleteBackupModal()" class="text-white/80 hover:text-white"><i class="fas fa-times"></i></button>
      </div>
      <div class="p-6 space-y-4">
        <p class="text-sm">
          Weet je zeker dat je back-up <strong id="delete-target-filename" class="font-mono text-red-500 break-all"></strong> definitief wilt verwijderen?
        </p>
        <div class="p-3.5 rounded-lg border bg-black/5 text-xs space-y-1.5 opacity-80" style="border-color: var(--theme-card-border);">
          <div class="flex items-center gap-2 text-red-500 font-semibold">
            <i class="fas fa-triangle-exclamation"></i>
            <span>Onomkeerbare bewerking</span>
          </div>
          <p>Dit archiefbestand en alle daarin opgeslagen databasegegevens en foto's worden permanent van de server gewist.</p>
        </div>
        <div class="pt-4 border-t flex items-center justify-end gap-3" style="border-color: var(--theme-card-border);">
          <button onclick="closeDeleteBackupModal()" class="px-4 py-2 text-sm font-semibold rounded-lg border hover:bg-black/5 transition" style="border-color: var(--theme-card-border);">
            Annuleren
          </button>
          <button id="btn-confirm-delete-backup" onclick="executeDeleteBackup()" class="bg-red-600 hover:bg-red-700 text-white font-bold px-5 py-2 text-sm rounded-lg shadow transition flex items-center gap-2">
            <i class="fas fa-trash-alt"></i>
            <span>Ja, Verwijder Definitief</span>
          </button>
        </div>
      </div>
    </div>
  </div>


  <?php include_once('../includes/footer.php') ?>
</div>

<script src="../js/admin_system.js"></script>
</body>
</html>
