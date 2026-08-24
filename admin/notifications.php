<?php
// Administrative dashboard for composing and dispatching web push notifications and viewing the notification backlog.
define("PAGE_NAME", "sa_notifications");
require_once(__DIR__ . '/../includes/auth.php');
// Controleer admin rechten
if ($priv < 2){
  header("Location: ../home");
  exit();
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_push') {
    $title = $_POST['title'] ?? '';
    $message = $_POST['message'] ?? '';
    $url = $_POST['url'] ?? '/';
    $target_users = $_POST['users'] ?? [];
    
    if (!empty($title) && !empty($message) && !empty($target_users)) {
        if (in_array('ALL', $target_users)) {
            send_push_notification('ALL', $title, $message, $url, 'admin/notifications');
        } else {
            send_push_notification($target_users, $title, $message, $url, 'admin/notifications');
        }
        $success_msg = "Notificatie is in de wachtrij geplaatst.";
    } else {
        $error_msg = "Vul alle velden in en selecteer minimaal één gebruiker.";
    }
}

// Haal gebruikers op die een abonnement hebben
$subRes = $conn->query("SELECT DISTINCT g.id, g.voornaam, g.achternaam, g.priv FROM Gebruikers g JOIN Notification_Subscriptions s ON g.id = s.user_id ORDER BY g.voornaam");
$subscribed_users = [];
while ($r = $subRes->fetch_assoc()) {
    $subscribed_users[] = $r;
}

// Haal backlog op
$backlogRes = $conn->query("SELECT b.*, g.voornaam, g.achternaam FROM Notification_Backlog b LEFT JOIN Gebruikers g ON b.user_id = g.id ORDER BY b.added_on DESC LIMIT 100");
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notificaties - <?php echo htmlspecialchars($siteSettings['GROUP_ID'] ? 'Jotify' : 'Jotify'); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            <div class="theme-card rounded-lg shadow-sm border overflow-hidden">
                <div class="px-6 py-4 theme-card-header border-b">
                    <h3 class="text-lg font-semibold"><i class="fas fa-paper-plane mr-2"></i>Nieuwe Notificatie Versturen</h3>
                </div>
                <div class="p-6">
                    <form method="POST" action="notifications.php">
                        <input type="hidden" name="action" value="send_push">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Left Column -->
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium mb-1">Titel</label>
                                    <input type="text" name="title" required class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 theme-override-bg theme-override-text border-gray-300">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Bericht</label>
                                    <textarea name="message" rows="3" required class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 theme-override-bg theme-override-text border-gray-300"></textarea>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">URL (Doel link bij klikken)</label>
                                    <input type="text" name="url" value="/" required class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 theme-override-bg theme-override-text border-gray-300">
                                </div>
                            </div>
                            
                            <!-- Right Column -->
                            <div class="space-y-2">
                                <label class="block text-sm font-medium mb-1">Selecteer Ontvangers</label>
                                
                                <div class="mb-2">
                                    <label class="inline-flex items-center">
                                        <input type="checkbox" id="selectAll" name="users[]" value="ALL" class="form-checkbox h-4 w-4 text-blue-600 transition duration-150 ease-in-out">
                                        <span class="ml-2 font-semibold">Iedereen selecteren (ALL)</span>
                                    </label>
                                </div>
                                
                                <div class="border rounded-md border-gray-300 max-h-48 overflow-y-auto p-3 theme-override-bg">
                                    <?php if (empty($subscribed_users)): ?>
                                        <p class="text-sm opacity-60 italic">Geen gebruikers met actieve push-abonnementen.</p>
                                    <?php else: ?>
                                        <?php foreach ($subscribed_users as $u): ?>
                                        <?php
                                        $roleNames = [0 => 'Gast', 1 => 'Vossenjager', 2 => 'Admin', 3 => 'Superadmin'];
                                        $u_role = $roleNames[$u['priv']] ?? 'Onbekend';
                                        ?>
                                        <div class="mb-1">
                                            <label class="inline-flex items-center">
                                                <input type="checkbox" name="users[]" value="<?= htmlspecialchars($u['id']) ?>" class="user-cb form-checkbox h-4 w-4 text-blue-600 transition duration-150 ease-in-out">
                                                <span class="ml-2 text-sm"><?= htmlspecialchars($u['voornaam'] . ' ' . $u['achternaam']) ?> <span class="opacity-50 text-xs">(<?= $u_role ?>)</span></span>
                                            </label>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <p class="text-xs opacity-60 mt-1">Alleen gebruikers die permissie hebben gegeven in hun browser worden hier weergegeven.</p>
                            </div>
                        </div>
                        
                        <div class="mt-6">
                            <button type="submit" class="w-full md:w-auto px-6 py-2 theme-bg-primary text-white font-medium rounded shadow-sm hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-offset-2">
                                <i class="fas fa-paper-plane mr-2"></i>Versturen
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Table Card -->
            <div class="theme-card rounded-lg shadow-sm border overflow-hidden">
                <div class="px-6 py-4 theme-card-header border-b">
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
    // Handle 'Select All' logic
    document.getElementById('selectAll').addEventListener('change', function(e) {
        let checkboxes = document.querySelectorAll('.user-cb');
        if (e.target.checked) {
            checkboxes.forEach(cb => {
                cb.checked = false;
                cb.disabled = true;
            });
        } else {
            checkboxes.forEach(cb => {
                cb.disabled = false;
            });
        }
    });
</script>
</body>
</html>
