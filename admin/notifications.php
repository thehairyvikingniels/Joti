<?php
// Administrative dashboard for composing and dispatching web push notifications and viewing the notification backlog.
define("PAGE_NAME", "sa_notifications");
require_once(__DIR__ . '/../includes/auth.php');
require_once(__DIR__ . '/../includes/telegram_parser.php');

// Check admin privileges
if ($privilege < 2){
  header("Location: ../home");
  exit();
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_push') {
    $title = trim($_POST['title'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $url = trim($_POST['url'] ?? '/');
    $target_users = $_POST['users'] ?? [];
    
    if (!empty($title) && !empty($message) && !empty($target_users)) {
        if (in_array('ALL', $target_users, true)) {
            send_push_notification('ALL', $title, $message, $url, 'admin/notifications');
            $success_msg = "Notificatie is in de wachtrij geplaatst voor alle ontvangers en wordt verzonden door de achtergrondwerker.";
        } else {
            send_push_notification($target_users, $title, $message, $url, 'admin/notifications');
            $count = count($target_users);
            $success_msg = "Notificatie is in de wachtrij geplaatst voor {$count} geselecteerde ontvanger(s) en wordt verzonden door de achtergrondwerker.";
        }
    } else {
        $error_msg = "Vul alle velden in en selecteer minimaal één ontvanger.";
    }
}

// Fetch users who have active notification channels (Web Push or Telegram)
$stmt_users = $conn->prepare("
    SELECT g.id, g.voornaam, g.achternaam, g.priv,
           (CASE WHEN g.telegram_enabled = 1 AND g.telegram_chat_id IS NOT NULL AND g.telegram_chat_id != '' THEN 1 ELSE 0 END) AS has_telegram,
           COUNT(s.id) AS web_count
    FROM Gebruikers g
    LEFT JOIN Notification_Subscriptions s ON g.id = s.user_id
    WHERE g.priv >= 1
    GROUP BY g.id, g.voornaam, g.achternaam, g.priv, g.telegram_enabled, g.telegram_chat_id
    HAVING has_telegram = 1 OR web_count > 0
    ORDER BY g.voornaam, g.achternaam
");
$stmt_users->execute();
$usersRes = $stmt_users->get_result();
$notifyable_users = [];
while ($r = $usersRes->fetch_assoc()) {
    $notifyable_users[] = $r;
}
$stmt_users->close();

// Haal backlog op
$stmt_backlog = $conn->prepare("SELECT b.*, g.voornaam, g.achternaam FROM Notification_Backlog b LEFT JOIN Gebruikers g ON b.user_id = g.id ORDER BY b.added_on DESC LIMIT 100");
$stmt_backlog->execute();
$backlogRes = $stmt_backlog->get_result();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notificaties - <?php echo htmlspecialchars($site_settings['GROUP_ID'] ? 'Jotify' : 'Jotify'); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../includes/switch.css">
    <?php include_once('../includes/theme.php'); ?>
</head>
<body class="flex h-screen overflow-hidden bg-gray-50 text-gray-900">

  <?php include_once('../includes/sidebar.php') ?>

  <!-- Main content area -->
  <div class="flex-1 flex flex-col h-screen overflow-y-auto w-full relative">
    <?php include_once('../includes/topbar.php') ?>

    <!-- Main scrollable content -->
    <main class="p-4 md:p-6 max-w-[1400px] mx-auto w-full flex-1">
        <div class="max-w-6xl mx-auto space-y-6">
            
            <h2 class="text-3xl font-bold mb-4 theme-primary">Notificaties Beheer</h2>
            
            <?php if(isset($success_msg)): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded mb-4">
                <?php echo htmlspecialchars($success_msg); ?>
            </div>
            <?php endif; ?>
            
            <?php if(isset($error_msg)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded mb-4">
                <?php echo htmlspecialchars($error_msg); ?>
            </div>
            <?php endif; ?>

            <!-- Form Card -->
            <div class="theme-card rounded-xl shadow-sm border overflow-hidden mb-6">
                <div class="theme-card-header px-6 py-4 border-b text-white flex justify-between items-center" style="background-color: var(--theme-sidebar-active); border-color: var(--theme-card-border);">
                    <h3 class="text-xl font-bold"><i class="fas fa-paper-plane mr-2"></i> Nieuwe Notificatie Versturen</h3>
                </div>
                <div class="p-6">
                    <form method="POST" action="notifications">
                        <input type="hidden" name="action" value="send_push">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Left Column -->
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium mb-1">Titel</label>
                                    <input type="text" name="title" required class="w-full theme-override-bg theme-override-text border rounded-xl px-3.5 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-500 shadow-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Bericht</label>
                                    <textarea name="message" rows="3" required class="w-full theme-override-bg theme-override-text border rounded-xl px-3.5 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-500 shadow-sm"></textarea>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">URL (Doel link bij klikken)</label>
                                    <input type="text" name="url" value="/" required class="w-full theme-override-bg theme-override-text border rounded-xl px-3.5 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-500 shadow-sm">
                                </div>
                            </div>
                            
                            <!-- Right Column -->
                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <label class="block text-sm font-medium">Selecteer Ontvangers</label>
                                    <span id="selectedCountBadge" class="text-xs px-2.5 py-0.5 rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300 font-semibold">0 geselecteerd</span>
                                </div>
                                
                                <!-- Quick filter / select buttons -->
                                <div class="flex flex-wrap gap-1.5">
                                    <button type="button" id="btnSelectAll" class="px-2.5 py-1 text-xs rounded-lg font-medium border border-gray-300 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-800 transition">
                                        <i class="fas fa-check-double mr-1 text-green-600"></i>Iedereen
                                    </button>
                                    <button type="button" id="btnSelectTg" class="px-2.5 py-1 text-xs rounded-lg font-medium border border-blue-300 dark:border-blue-700 text-blue-700 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/30 transition">
                                        <i class="fab fa-telegram mr-1"></i>Alle Telegram
                                    </button>
                                    <button type="button" id="btnSelectWeb" class="px-2.5 py-1 text-xs rounded-lg font-medium border border-purple-300 dark:border-purple-700 text-purple-700 dark:text-purple-400 hover:bg-purple-50 dark:hover:bg-purple-900/30 transition">
                                        <i class="fas fa-globe mr-1"></i>Alle Web
                                    </button>
                                    <button type="button" id="btnSelectNone" class="px-2.5 py-1 text-xs rounded-lg font-medium border border-gray-300 dark:border-gray-600 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800 transition">
                                        <i class="fas fa-times mr-1 text-red-500"></i>Geen
                                    </button>
                                </div>

                                <!-- Table of notifyable users -->
                                <div class="border rounded-xl border-gray-300 dark:border-gray-700 max-h-72 overflow-y-auto theme-override-bg shadow-inner">
                                    <?php if (empty($notifyable_users)): ?>
                                        <p class="text-sm opacity-60 italic p-4 text-center">Geen gebruikers met actieve Web Push of Telegram kanalen gevonden.</p>
                                    <?php else: ?>
                                        <table class="w-full text-left text-xs">
                                            <thead class="sticky top-0 bg-gray-100 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 font-semibold z-10">
                                                <tr>
                                                    <th class="p-2.5 w-8 text-center">
                                                        <input type="checkbox" id="headerCheckbox" class="rounded text-blue-600 h-3.5 w-3.5 cursor-pointer">
                                                    </th>
                                                    <th class="p-2.5">Naam & Rol</th>
                                                    <th class="p-2.5 text-right pr-3">Kanalen</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                                <?php 
                                                $roleNames = [0 => 'Gast', 1 => 'Vossenjager', 2 => 'Admin', 3 => 'Superadmin'];
                                                foreach ($notifyable_users as $u): 
                                                    $u_role = $roleNames[$u['priv']] ?? 'Onbekend';
                                                    $hasTg = (int)$u['has_telegram'] === 1;
                                                    $webCount = (int)$u['web_count'];
                                                    $hasWeb = $webCount > 0;
                                                ?>
                                                <tr class="user-row hover:bg-blue-50/40 dark:hover:bg-blue-900/20 cursor-pointer transition-colors" data-has-telegram="<?= $hasTg ? '1' : '0' ?>" data-has-web="<?= $hasWeb ? '1' : '0' ?>">
                                                    <td class="p-2.5 text-center" onclick="event.stopPropagation()">
                                                        <input type="checkbox" name="users[]" value="<?= htmlspecialchars((string)$u['id']) ?>" class="user-cb form-checkbox h-4 w-4 text-blue-600 rounded cursor-pointer" data-has-telegram="<?= $hasTg ? '1' : '0' ?>" data-has-web="<?= $hasWeb ? '1' : '0' ?>">
                                                    </td>
                                                    <td class="p-2.5">
                                                        <span class="font-medium text-gray-900 dark:text-gray-100"><?= htmlspecialchars($u['voornaam'] . ' ' . $u['achternaam']) ?></span>
                                                        <span class="opacity-60 text-[11px] block"><?= $u_role ?></span>
                                                    </td>
                                                    <td class="p-2.5 text-right pr-3 space-x-1 whitespace-nowrap">
                                                        <?php if ($hasTg): ?>
                                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-blue-100 text-blue-800 dark:bg-blue-900/60 dark:text-blue-300">
                                                                <i class="fab fa-telegram mr-1"></i>Telegram
                                                            </span>
                                                        <?php endif; ?>
                                                        <?php if ($hasWeb): ?>
                                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-purple-100 text-purple-800 dark:bg-purple-900/60 dark:text-purple-300">
                                                                <i class="fas fa-globe mr-1"></i>Web (<?= $webCount ?>)
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php endif; ?>
                                </div>
                                <p class="text-xs opacity-60">Alleen gebruikers met een actieve browser Web Push koppeling of een gekoppeld Telegram account kunnen worden geselecteerd.</p>
                            </div>
                        </div>

                        <div class="mt-6">
                            <button type="submit" class="w-full md:w-auto px-8 py-2.5 theme-bg-primary text-white font-bold rounded-xl shadow-sm hover:opacity-90 transition">
                                <i class="fas fa-paper-plane mr-2"></i>Versturen
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Table Card -->
            <div class="theme-card rounded-xl shadow-sm border overflow-hidden mb-6">
                <div class="theme-card-header px-6 py-4 border-b text-white flex justify-between items-center" style="background-color: var(--theme-sidebar-active); border-color: var(--theme-card-border);">
                    <h3 class="text-lg font-semibold"><i class="fas fa-list mr-2"></i>Recente Notificaties (Backlog)</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm whitespace-nowrap">
                        <thead class="uppercase tracking-wider border-b border-gray-200 bg-gray-50/50">
                            <tr>
                                <th scope="col" class="px-6 py-3 font-medium opacity-80">Ontvanger</th>
                                <th scope="col" class="px-6 py-3 font-medium opacity-80">Titel</th>
                                <th scope="col" class="px-6 py-3 font-medium opacity-80">Bericht</th>
                                <th scope="col" class="px-6 py-3 font-medium opacity-80">Aangemaakt</th>
                                <th scope="col" class="px-6 py-3 font-medium opacity-80">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if ($backlogRes->num_rows === 0): ?>
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-center opacity-60 italic">De backlog is momenteel leeg.</td>
                            </tr>
                            <?php else: ?>
                                <?php while ($row = $backlogRes->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50/50 transition-colors">
                                    <td class="px-6 py-4">
                                        <?= htmlspecialchars($row['voornaam'] . ' ' . $row['achternaam']) ?>
                                        <span class="text-xs opacity-50 block">(ID: <?= $row['user_id'] ?>)</span>
                                    </td>
                                    <td class="px-6 py-4 font-medium"><?= htmlspecialchars($row['title']) ?></td>
                                    <td class="px-6 py-4 truncate max-w-xs" title="<?= htmlspecialchars($row['message']) ?>"><?= htmlspecialchars($row['message']) ?></td>
                                    <td class="px-6 py-4 opacity-80 text-xs"><?= date('d-m-Y H:i', strtotime($row['added_on'])) ?></td>
                                    <td class="px-6 py-4">
                                        <?php if ($row['status'] === 'failed'): ?>
                                            <span class="px-2 py-1 bg-red-100 text-red-800 rounded-full text-xs font-medium"><i class="fas fa-times mr-1"></i>Mislukt</span>
                                        <?php elseif ($row['status'] === 'sent' || !is_null($row['sent'])): ?>
                                            <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs font-medium"><i class="fas fa-check mr-1"></i>Verzonden</span>
                                        <?php else: ?>
                                            <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full text-xs font-medium"><i class="fas fa-clock mr-1"></i>In wachtrij</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>
    
    <?php require_once('../includes/footer.php') ?>
  </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const userCheckboxes = document.querySelectorAll('.user-cb');
    const headerCheckbox = document.getElementById('headerCheckbox');
    const badge = document.getElementById('selectedCountBadge');
    const rows = document.querySelectorAll('.user-row');

    function updateCount() {
        const total = userCheckboxes.length;
        const checked = document.querySelectorAll('.user-cb:checked').length;
        if (badge) {
            badge.textContent = `${checked} geselecteerd`;
        }
        if (headerCheckbox) {
            headerCheckbox.checked = (total > 0 && checked === total);
            headerCheckbox.indeterminate = (checked > 0 && checked < total);
        }
    }

    if (headerCheckbox) {
        headerCheckbox.addEventListener('change', function() {
            userCheckboxes.forEach(cb => { cb.checked = headerCheckbox.checked; });
            updateCount();
        });
    }

    userCheckboxes.forEach(cb => {
        cb.addEventListener('change', updateCount);
    });

    rows.forEach(row => {
        row.addEventListener('click', function(e) {
            if (e.target.tagName.toLowerCase() === 'input') return;
            const cb = row.querySelector('.user-cb');
            if (cb) {
                cb.checked = !cb.checked;
                updateCount();
            }
        });
    });

    document.getElementById('btnSelectAll')?.addEventListener('click', function() {
        userCheckboxes.forEach(cb => { cb.checked = true; });
        updateCount();
    });

    document.getElementById('btnSelectTg')?.addEventListener('click', function() {
        userCheckboxes.forEach(cb => {
            cb.checked = (cb.dataset.hasTelegram === '1');
        });
        updateCount();
    });

    document.getElementById('btnSelectWeb')?.addEventListener('click', function() {
        userCheckboxes.forEach(cb => {
            cb.checked = (cb.dataset.hasWeb === '1');
        });
        updateCount();
    });

    document.getElementById('btnSelectNone')?.addEventListener('click', function() {
        userCheckboxes.forEach(cb => { cb.checked = false; });
        updateCount();
    });

    updateCount();
});
</script>
</body>
</html>
