<?php
if (isset($_SESSION['kiosk_id'])) {
    return;
}
$pagelist = array(
    'home' => array(
        'active' => null,
        'filename' => 'home.php'
    ),
    'kaarten' => array(
        'active' => null,
        'filename' => 'kaarten.php'
    ),
    'vossen' => array(
        'active' => null,
        'filename' => 'vossen.php'
    ),
    'voslocaties' => array(
        'active' => null,
        'filename' => 'voslocaties.php'
    ),
    'nieuws' => array(
        'active' => null,
        'filename' => 'nieuws.php'
    ),
    'opdrachten' => array(
        'active' => null,
        'filename' => 'opdrachten.php'
    ),
    'hints' => array(
        'active' => null,
        'filename' => 'hints.php'
    ),
    'punten' => array(
        'active' => null,
        'filename' => 'punten.php'
    ),
    'groepen' => array(
        'active' => null,
        'filename' => 'groepen.php'
    ),
    'instellingen' => array(
        'active' => null,
        'filename' => 'instellingen.php'
    ),
    'whiteboard' => array(
        'active' => null,
        'filename' => 'whiteboard.php'
    ),
    'autos' => array(
        'active' => null,
        'filename' => 'autos.php'
    ),
    'a_users' => array(
        'active' => null,
        'filename' => 'admin/users.php'
    ),
    'a_cronjobs' => array(
        'active' => null,
        'filename' => 'cronjobs.php'
    ),
    'a_serviceaccounts' => array(
        'active' => null,
        'filename' => 'serviceaccounts.php'
    ),
    'a_database' => array(
        'active' => null,
        'filename' => 'admin/database.php'
    ),
    'sa_settings' => array(
        'active' => null,
        'filename' => 'admin/settings.php'
    ),
    'sa_notifications' => array(
        'active' => null,
        'filename' => 'admin/notifications.php'
    )
);
$pagelist[PAGE_NAME]['active'] = " theme-sidebar-active theme-border-primary text-white";

$inactive_classes = " border-transparent hover-theme-border-primary transition-colors";
foreach ($pagelist as $key => $val) {
    if ($key !== PAGE_NAME) {
        $pagelist[$key]['active'] = $inactive_classes;
    }
}

$adminpagelist = array(
    'a_users',
    'a_database',
    'a_cronjobs',
    'a_serviceaccounts',
    'sa_settings',
    'sa_notifications'
);
if (in_array(PAGE_NAME, $adminpagelist)) {
    $inAdminfolder = "";
    $notInAdminfolder = "../";
} else {
    $inAdminfolder = "admin/";
    $notInAdminfolder = "";
}
?>

<aside id="mySidebar" class="w-64 theme-sidebar hidden md:flex flex-col flex-shrink-0 z-40 fixed md:relative h-full transition-transform transform -translate-x-full md:translate-x-0">
  <div class="h-14 flex items-center justify-between md:justify-center px-4 md:px-0 border-b border-black/10 bg-black/10">
    <h1 class="text-lg font-bold tracking-wider theme-primary"><?= htmlspecialchars($siteSettings['GROUP_ID'] ? ($topbarGroupName ?? 'JOTIFY') : 'JOTIFY') ?></h1>
    <button class="md:hidden text-white/70 hover:text-white" onclick="w3_close()"><i class="fas fa-times"></i></button>
  </div>
  
  <div class="px-5 py-4 flex items-center justify-between border-b border-black/10">
    <div class="flex items-center space-x-3">
      <div class="w-10 h-10 bg-white rounded-full flex items-center justify-center overflow-hidden flex-shrink-0 border shadow-sm" style="border-color: var(--theme-card-border);">
        <img src="<?= $notInAdminfolder.$siteSettings['GROUP_LOGO_SMALL_URL']?>" class="w-full h-full object-contain p-1">
      </div>
      <div>
          <p class="text-sm font-semibold">Welkom, <strong><?php echo ucfirst($vn); ?></strong></p>
          <?php
          $roleNames = [0 => 'Gast', 1 => 'Vossenjager', 2 => 'Admin', 3 => 'Superadmin'];
          $userPriv = $_SESSION['priv'] ?? 0;
          ?>
          <p class="text-xs opacity-70"><?php echo $roleNames[$userPriv] ?? "Onbekend"; ?></p>
      </div>
    </div>
    <a href="/logout" class="opacity-70 hover:opacity-100 hover:text-red-500 transition-colors" title="Uitloggen">
      <i class="fas fa-sign-out-alt text-xl"></i>
    </a>
  </div>

  <nav class="flex-1 py-4 space-y-1 overflow-y-auto">
    <a href="<?=$notInAdminfolder?>home" class="flex items-center space-x-3 px-5 py-2.5 font-semibold border-l-4 <?= $pagelist['home']['active']?>"><i class="fa fa-users fa-fw w-5 opacity-70"></i><span>Overzicht</span></a>
    <?php if ($priv > 0): ?>
    <a href="<?=$notInAdminfolder?>kaarten" class="flex items-center space-x-3 px-5 py-2.5 font-semibold border-l-4 <?= $pagelist['kaarten']['active']?>"><i class="fas fa-map-marked-alt fa-fw w-5 opacity-70"></i><span>Kaarten</span></a>
    <a href="<?=$notInAdminfolder?>vossen" class="flex items-center space-x-3 px-5 py-2.5 font-semibold border-l-4 <?= $pagelist['vossen']['active']?>"><i class="fas fa-bullseye fa-fw w-5 opacity-70"></i><span>Vossen</span></a>
    <a href="<?=$notInAdminfolder?>voslocaties" class="flex items-center space-x-3 px-5 py-2.5 font-semibold border-l-4 <?= $pagelist['voslocaties']['active']?>"><i class="fas fa-circle-nodes fa-fw w-5 opacity-70"></i><span>Voslocaties</span></a>
    <?php endif; ?>
    <a href="<?=$notInAdminfolder?>nieuws" class="flex items-center space-x-3 px-5 py-2.5 font-semibold border-l-4 <?= $pagelist['nieuws']['active']?>"><i class="far fa-newspaper fa-fw w-5 opacity-70"></i><span>Nieuws</span></a>
    <a href="<?=$notInAdminfolder?>opdrachten" class="flex items-center space-x-3 px-5 py-2.5 font-semibold border-l-4 <?= $pagelist['opdrachten']['active']?>"><i class="far fa-bell fa-fw w-5 opacity-70"></i><span>Opdrachten</span></a>
    <a href="<?=$notInAdminfolder?>hints" class="flex items-center space-x-3 px-5 py-2.5 font-semibold border-l-4 <?= $pagelist['hints']['active']?>"><i class="fas fa-question-circle fa-fw w-5 opacity-70"></i><span>Hints</span></a>
    <?php if ($priv > 0): ?>
    <a href="<?=$notInAdminfolder?>punten" class="flex items-center space-x-3 px-5 py-2.5 font-semibold border-l-4 <?= $pagelist['punten']['active']?>"><i class="fas fa-trophy fa-fw w-5 opacity-70"></i><span>Punten</span></a>
    <?php endif; ?>
    <a href="<?=$notInAdminfolder?>groepen" class="flex items-center space-x-3 px-5 py-2.5 font-semibold border-l-4 <?= $pagelist['groepen']['active']?>"><i class="fas fa-home fa-fw w-5 opacity-70"></i><span>Groepen</span></a>
    <a href="<?=$notInAdminfolder?>instellingen" class="flex items-center space-x-3 px-5 py-2.5 font-semibold border-l-4 <?= $pagelist['instellingen']['active']?>"><i class="fas fa-cog fa-fw w-5 opacity-70"></i><span>Instellingen</span></a>
    <?php if ($priv > 0): ?>
    <a href="<?=$notInAdminfolder?>autos" class="flex items-center space-x-3 px-5 py-2.5 font-semibold border-l-4 <?= $pagelist['autos']['active']?>"><i class="fas fa-car fa-fw w-5 opacity-70"></i><span>Auto's</span></a>
    <a href="<?=$notInAdminfolder?>whiteboard" class="flex items-center space-x-3 px-5 py-2.5 font-semibold border-l-4 <?= $pagelist['whiteboard']['active']?>"><i class="fas fa-chalkboard fa-fw w-5 opacity-70"></i><span>Whiteboard</span></a>
    <?php endif; ?>
    <?php if ($priv > 1): ?>
    <div class="px-5 pt-4 pb-2"><p class="text-xs font-bold uppercase tracking-wider opacity-50">Admin</p></div>
    <a href="<?=$inAdminfolder?>users" class="flex items-center space-x-3 px-5 py-2.5 font-semibold border-l-4 <?= $pagelist['a_users']['active']?>"><i class="fas fa-user-cog fa-fw w-5 opacity-70"></i><span>Users</span></a>
    <a href="<?=$inAdminfolder?>serviceaccounts" class="flex items-center space-x-3 px-5 py-2.5 font-semibold border-l-4 <?= $pagelist['a_serviceaccounts']['active']?>"><i class="fas fa-user-tag fa-fw w-5 opacity-70"></i><span>Service Accounts</span></a>
    <a href="<?=$inAdminfolder?>cronjobs" class="flex items-center space-x-3 px-5 py-2.5 font-semibold border-l-4 <?= $pagelist['a_cronjobs']['active']?>"><i class="fas fa-stopwatch fa-fw w-5 opacity-70"></i><span>Cronjobs</span></a>
    <a href="<?=$inAdminfolder?>database" class="flex items-center space-x-3 px-5 py-2.5 font-semibold border-l-4 <?= $pagelist['a_database']['active']?>"><i class="fas fa-database fa-fw w-5 opacity-70"></i><span>Database</span></a>
    <a href="<?=$inAdminfolder?>notifications" class="flex items-center space-x-3 px-5 py-2.5 font-semibold border-l-4 <?= $pagelist['sa_notifications']['active']?>"><i class="fas fa-bell fa-fw w-5 opacity-70"></i><span>Notifications</span></a>
    <?php endif; ?>
    <?php if ($priv > 2): ?>
    <a href="<?=$inAdminfolder?>settings" class="flex items-center space-x-3 px-5 py-2.5 font-semibold border-l-4 <?= $pagelist['sa_settings']['active']?>"><i class="fas fa-toolbox fa-fw w-5 opacity-70"></i><span>Settings</span></a>
    <?php endif; ?>
  </nav>
</aside>

<!-- Overlay effect when opening sidebar on small screens -->
<div id="myOverlay" class="fixed inset-0 bg-black/50 z-30 hidden md:hidden transition-opacity" onclick="w3_close()"></div>

<script>
var mySidebar = document.getElementById("mySidebar");
var overlayBg = document.getElementById("myOverlay");

function w3_open() {
    mySidebar.classList.remove("-translate-x-full");
    mySidebar.classList.remove("hidden");
    mySidebar.classList.add("flex");
    overlayBg.classList.remove("hidden");
}

function w3_close() {
    mySidebar.classList.add("-translate-x-full");
    setTimeout(() => {
        mySidebar.classList.add("hidden");
        mySidebar.classList.remove("flex");
    }, 300); // match transition duration
    overlayBg.classList.add("hidden");
}
</script>