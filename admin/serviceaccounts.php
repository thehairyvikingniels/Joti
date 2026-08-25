<?php
// Manages kiosk and service accounts: create, edit, delete, regenerate tokens, and configure target pages and permissions.
define("PAGE_NAME", "a_serviceaccounts");
require_once(__DIR__ . '/../includes/auth.php');
if ($privilege < 2) {
  header("Location: ../home");
  exit();
}

$succes = ($_GET['msg'] ?? '') === 'success';
$error_msg = $_GET['error'] ?? '';

// Fetch Kiosk service accounts
$kiosk_data = [];
$stmt_kiosk = $conn->prepare("SELECT * FROM Kiosk_Accounts ORDER BY id DESC");
$stmt_kiosk->execute();
$res = $stmt_kiosk->get_result();
if ($res) {
    while($row = $res->fetch_assoc()) {
        $kiosk_data[] = $row;
    }
}

// Get global site settings (for topbar/sidebar)
$site_settings = [];
$stmt_settings = $conn->prepare("SELECT * FROM Site_Instellingen");
$stmt_settings->execute();
$result_settings = $stmt_settings->get_result();
if ($result_settings->num_rows > 0) {
    while($row = $result_settings->fetch_assoc()) {
      $site_settings[$row['Instelling']] = $row['Waarde'];
    }
}
$stmt_settings->close();
?>

<!DOCTYPE html>
<html lang="nl">
<head>
<title>Jotify - Service Accounts Beheer</title>
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

    <?php if ($succes): ?>
        <div class="mb-4 bg-green-100/10 border border-green-500 text-green-600 px-4 py-3 rounded relative flex items-center shadow-sm" role="alert">
            <i class="fas fa-check-circle mr-2"></i>
            <span class="block sm:inline">Actie succesvol uitgevoerd!</span>
        </div>
    <?php endif; ?>
    <?php if (!empty($error_msg)): ?>
        <div class="mb-4 bg-red-100/10 border border-red-500 text-red-600 px-4 py-3 rounded relative flex items-center shadow-sm" role="alert">
            <i class="fas fa-exclamation-circle mr-2"></i>
            <span class="block sm:inline"><?php echo htmlspecialchars($error_msg); ?></span>
        </div>
    <?php endif; ?>

    <div class="space-y-6 mb-24">
      
      <!-- Table Card -->
      <div class="theme-card rounded-xl border shadow-sm overflow-hidden w-full mb-6">
        <div class="theme-card-header px-6 py-4 border-b text-white flex justify-between items-center flex-wrap gap-2" style="background-color: var(--theme-sidebar-active); border-color: var(--theme-card-border);">
          <div>
            <h2 class="text-xl font-bold flex items-center">
              <i class="fas fa-tv mr-2"></i> Service Accounts / Kiosks
            </h2>
            <p class="text-xs opacity-80 mt-0.5">Beheer set-and-forget schermen en externe API verbindingen</p>
          </div>
          <button onclick="openCreateModal()" class="bg-white/20 hover:bg-white/30 text-white font-bold py-2 px-4 rounded-xl text-sm transition shadow-sm flex items-center gap-2 border border-white/30">
            <i class="fas fa-plus"></i> Nieuw Account
          </button>
        </div>
        
        <div class="overflow-x-auto w-full">
          <table class="w-full text-left border-collapse">
            <thead>
              <tr class="theme-card-header text-xs uppercase tracking-wider opacity-70 border-b">
                <th class="p-3 font-semibold w-16">ID</th>
                <th class="p-3 font-semibold">Naam</th>
                <th class="p-3 font-semibold">Doel Pagina</th>
                <th class="p-3 font-semibold text-center">Rechten</th>
                <th class="p-3 font-semibold">Laatst Gezien</th>
                <th class="p-3 font-semibold text-right">Acties</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200/20 text-sm">
              <?php if(empty($kiosk_data)): ?>
              <tr>
                  <td colspan="6" class="p-6 text-center opacity-60 italic">Geen service accounts gevonden.</td>
              </tr>
              <?php endif; ?>
              <?php foreach($kiosk_data as $account): ?>
              <tr class="hover:bg-gray-50/5 transition-colors">
                <td class="p-3 opacity-60 font-mono text-xs">#<?= $account['id'] ?></td>
                <td class="p-3 font-medium">
                  <?= htmlspecialchars($account['naam']) ?>
                  <?php if(!empty($account['ip_whitelist'])): ?>
                    <span class="inline-flex items-center gap-1 ml-2 text-[10px] bg-green-500/10 text-green-600 px-1.5 py-0.5 rounded uppercase font-bold tracking-wider" title="<?= htmlspecialchars($account['ip_whitelist']) ?>">IP Beveiligd</span>
                  <?php endif; ?>
                </td>
                <td class="p-3"><code class="text-xs bg-black/10 px-1 py-0.5 rounded"><?= htmlspecialchars($account['doel_pagina']) ?: '-' ?></code></td>
                <td class="p-3 text-center">
                  <?php if($account['rechten'] == 0): ?>
                    <span class="text-xs bg-gray-500/10 text-gray-500 px-2 py-1 rounded-full"><i class="fas fa-eye mr-1"></i> Read</span>
                  <?php else: ?>
                    <span class="text-xs bg-blue-500/10 text-blue-500 px-2 py-1 rounded-full"><i class="fas fa-pen mr-1"></i> Write</span>
                  <?php endif; ?>
                </td>
                <td class="p-3 text-xs opacity-80 whitespace-nowrap">
                  <?php if ($account['laatst_gezien']): ?>
                    <span title="Laatst IP: <?= htmlspecialchars($account['laatst_ip'] ?? 'Onbekend') ?>" class="cursor-help border-b border-dashed border-gray-400">
                        <?= date('d-m-Y H:i', strtotime($account['laatst_gezien'])) ?>
                    </span>
                  <?php else: ?>
                    <span class="opacity-50 italic">Nooit</span>
                  <?php endif; ?>
                </td>
                <td class="p-3 text-right">
                  <div class="flex items-center justify-end gap-2">
                    <button onclick="showToken('<?= $account['auth_token'] ?>', <?= $account['id'] ?>)" class="w-8 h-8 rounded-md bg-green-500/10 text-green-600 hover:bg-green-500 hover:text-white transition-colors flex items-center justify-center" title="Toon Token">
                      <i class="fas fa-key"></i>
                    </button>
                    <button onclick="editAccount(<?= htmlspecialchars(json_encode($account)) ?>)" class="w-8 h-8 rounded-md bg-blue-500/10 text-blue-600 hover:bg-blue-500 hover:text-white transition-colors flex items-center justify-center" title="Bewerken">
                      <i class="fas fa-edit"></i>
                    </button>
                    <button onclick="showDeleteModal(<?= $account['id'] ?>, '<?= htmlspecialchars(addslashes($account['naam'])) ?>')" class="w-8 h-8 rounded-md bg-red-500/10 text-red-600 hover:bg-red-500 hover:text-white transition-colors flex items-center justify-center" title="Verwijderen">
                      <i class="fas fa-trash"></i>
                    </button>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>
  <?php require_once('../includes/footer.php') ?>
</div>

<!-- Modal Token View -->
<div id="tokenModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-black/60 backdrop-blur-sm modal-backdrop flex items-center justify-center p-4" aria-labelledby="modal-title" role="dialog" aria-modal="true" onclick="if(event.target === this) closeModal(this)">
    <div class="relative inline-block theme-card border rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all max-w-md w-full" onclick="event.stopPropagation()">
        <header class="theme-card-header px-6 py-4 border-b text-white flex justify-between items-center" style="background-color: var(--theme-sidebar-active); border-color: var(--theme-card-border);">
            <h3 class="text-lg font-bold flex items-center gap-2"><i class="fas fa-link"></i> <span>Account URL</span></h3>
            <button onclick="closeModal(this)" class="text-white opacity-70 hover:opacity-100 transition"><i class="fas fa-times text-xl"></i></button>
        </header>
        <div class="p-6">
            <p class="text-sm opacity-80 mb-4">Kopieer de onderstaande URL om direct toegang in te stellen op het kiosk apparaat.</p>
            
            <div class="flex items-center gap-2 mb-6">
                <input type="text" id="tokenDisplay" readonly class="theme-override-bg theme-override-text border rounded-xl w-full py-2.5 px-3.5 focus:outline-none focus:ring-2 focus:ring-blue-500 font-mono text-xs" value="">
                <button onclick="copyTokenUrl()" class="theme-bg-primary text-white px-4 py-2.5 rounded-xl hover:opacity-90 transition flex-shrink-0 shadow-sm" title="Kopieer"><i class="fas fa-copy"></i></button>
            </div>
            
            <div class="flex justify-between items-center mt-6 pt-4 border-t" style="border-color: var(--theme-card-border);">
                <form id="regen_form" method="POST" action="serviceaccounts_helper.php" class="inline">
                    <input type="hidden" name="action" value="regenerate">
                    <input type="hidden" name="account_id" id="regenAccountId" value="">
                    <button type="button" onclick="document.getElementById('regenConfirmModal').classList.remove('hidden'); document.getElementById('regenConfirmModal').style.display='flex';" class="text-yellow-600 hover:text-yellow-700 text-sm font-bold transition flex items-center gap-1"><i class="fas fa-sync-alt"></i> Regenereer</button>
                </form>
                <button onclick="closeModal(this)" class="theme-card border hover:bg-black/5 dark:hover:bg-white/5 py-2.5 px-5 rounded-xl font-bold text-sm transition">Sluiten</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Regen Confirm -->
<div id="regenConfirmModal" class="hidden fixed inset-0 z-[60] overflow-y-auto bg-black/60 backdrop-blur-sm modal-backdrop flex items-center justify-center p-4" aria-labelledby="modal-title" role="dialog" aria-modal="true" onclick="if(event.target === this) closeModal(this)">
    <div class="relative inline-block theme-card border rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all max-w-md w-full" onclick="event.stopPropagation()">
        <header class="theme-card-header px-6 py-4 border-b text-white flex justify-between items-center" style="background-color: var(--theme-sidebar-active); border-color: var(--theme-card-border);">
            <h3 class="text-lg font-bold flex items-center gap-2 text-yellow-300"><i class="fas fa-exclamation-circle"></i> <span>Token Vernieuwen</span></h3>
            <button onclick="closeModal(this)" class="text-white opacity-70 hover:opacity-100 transition"><i class="fas fa-times text-xl"></i></button>
        </header>
        <div class="p-6">
            <p class="text-sm opacity-90 mb-6 leading-relaxed">Weet je zeker dat je het token wilt regenereren? Gekoppelde apparaten verliezen direct toegang!</p>
            <div class="flex justify-end gap-3">
                <button onclick="closeModal(this)" class="theme-card border hover:bg-black/5 dark:hover:bg-white/5 py-2.5 px-5 rounded-xl font-bold text-sm transition">Annuleren</button>
                <button onclick="document.getElementById('regen_form').submit()" class="bg-yellow-500 hover:bg-yellow-600 text-white py-2.5 px-6 rounded-xl font-bold text-sm transition shadow">Vernieuwen</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Delete -->
<div id="deleteModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-black/60 backdrop-blur-sm modal-backdrop flex items-center justify-center p-4" aria-labelledby="modal-title" role="dialog" aria-modal="true" onclick="if(event.target === this) closeModal(this)">
    <div class="relative inline-block theme-card border rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all max-w-md w-full" onclick="event.stopPropagation()">
        <header class="theme-card-header px-6 py-4 border-b text-white flex justify-between items-center" style="background-color: var(--theme-sidebar-active); border-color: var(--theme-card-border);">
            <h3 class="text-lg font-bold flex items-center gap-2"><i class="fas fa-trash-alt"></i> <span>Account Verwijderen</span></h3>
            <button onclick="closeModal(this)" class="text-white opacity-70 hover:opacity-100 transition"><i class="fas fa-times text-xl"></i></button>
        </header>
        <div class="p-6">
            <p class="text-sm opacity-90 mb-6 leading-relaxed">Weet je zeker dat je het service account <strong id="deleteAccountName"></strong> wilt verwijderen? Dit kan niet ongedaan gemaakt worden.</p>
            <div class="flex justify-end gap-3">
                <button onclick="closeModal(this)" class="theme-card border hover:bg-black/5 dark:hover:bg-white/5 py-2.5 px-5 rounded-xl font-bold text-sm transition">Annuleren</button>
                <form id="delete_form" method="POST" action="serviceaccounts_helper.php" class="inline m-0 p-0">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="account_id" id="deleteAccountId" value="">
                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white py-2.5 px-6 rounded-xl font-bold text-sm transition shadow">Verwijderen</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal Create / Edit -->
<div id="createModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-black/60 backdrop-blur-sm modal-backdrop flex items-center justify-center p-4" aria-labelledby="modal-title" role="dialog" aria-modal="true" onclick="if(event.target === this) closeModal(this)">
    <div class="relative inline-block theme-card border rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all max-w-xl w-full max-h-[90vh] flex flex-col" onclick="event.stopPropagation()">
        <header class="theme-card-header px-6 py-4 border-b text-white flex justify-between items-center" style="background-color: var(--theme-sidebar-active); border-color: var(--theme-card-border);">
            <h3 class="text-lg font-bold flex items-center gap-2"><i class="fas fa-tv"></i> <span id="modalTitle">Nieuw Service Account</span></h3>
            <button onclick="closeModal(this)" class="text-white opacity-70 hover:opacity-100 transition"><i class="fas fa-times text-xl"></i></button>
        </header>
        <form method="POST" action="serviceaccounts_helper.php" class="p-6 overflow-y-auto space-y-4">
            <input type="hidden" name="action" id="formAction" value="create">
            <input type="hidden" name="account_id" id="formAccountId" value="">
            
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider mb-1 opacity-70">Naam <span class="text-red-500">*</span></label>
                <input type="text" name="naam" id="formNaam" required class="theme-override-bg theme-override-text border rounded-xl w-full py-2.5 px-3.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-sm" placeholder="bijv. Meldkamer Scherm">
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider mb-1 opacity-70">Doel Pagina</label>
                <input type="text" name="doel_pagina" id="formDoel" list="paginas_list" class="theme-override-bg theme-override-text border rounded-xl w-full py-2.5 px-3.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-sm" value="home" placeholder="bijv. home of /whiteboard">
                <datalist id="paginas_list">
                    <option value="home">Home / Dashboard</option>
                    <option value="vossen">Vossen overzicht</option>
                    <option value="kaarten">Kaarten</option>
                    <option value="voslocaties">Voslocaties</option>
                    <option value="opdrachten">Opdrachten</option>
                    <option value="hints">Hints</option>
                    <option value="groepen">Groepen</option>
                    <option value="nieuws">Nieuws</option>
                    <option value="punten">Punten</option>
                    <option value="whiteboard">Whiteboard</option>
                    <option value="autos">Auto's</option>
                </datalist>
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider mb-1 opacity-70">Rechten</label>
                <select name="rechten" id="formRechten" class="theme-override-bg theme-override-text border rounded-xl w-full py-2.5 px-3.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-sm cursor-pointer">
                    <option value="0">Alleen Lezen (0)</option>
                    <option value="1">Lezen & Schrijven (1)</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider mb-1 opacity-70">IP Whitelist (optioneel)</label>
                <input type="text" name="ip_whitelist" id="formIp" class="theme-override-bg theme-override-text border rounded-xl w-full py-2.5 px-3.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-sm font-mono text-xs" placeholder="bijv. 192.168.1.1, 192.168.1.5">
                <p class="text-xs opacity-60 mt-1">Komma gescheiden lijst van IP-adressen die zijn toegestaan.</p>
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider mb-1 opacity-70">Refresh Interval (seconden)</label>
                <input type="number" name="refresh_interval" id="formRefresh" class="theme-override-bg theme-override-text border rounded-xl w-full py-2.5 px-3.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-sm" value="0" min="0">
                <p class="text-xs opacity-60 mt-1">0 is uitgeschakeld. Bij inactiviteit ververst de pagina automatisch na x seconden.</p>
            </div>
            
            <div class="mt-6 pt-4 border-t flex justify-end gap-3" style="border-color: var(--theme-card-border);">
                <button type="button" onclick="closeModal(this)" class="theme-card border hover:bg-black/5 dark:hover:bg-white/5 py-2.5 px-5 rounded-xl font-bold text-sm transition">Annuleren</button>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white py-2.5 px-6 rounded-xl font-bold text-sm transition shadow">Opslaan</button>
            </div>
        </form>
    </div>
</div>

<script src="../js/admin_serviceaccounts.js"></script>

</body>
</html>
