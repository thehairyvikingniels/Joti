<?php
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
        'filename' => 'admin/cronjobs.php'
    ),
    'a_database' => array(
        'active' => null,
        'filename' => 'admin/database.php'
    ),
    'sa_settings' => array(
        'active' => null,
        'filename' => 'admin/settings.php'
    )
);
$pagelist[PAGE_NAME]['active'] = " w3-blue";

$adminpagelist = array(
    'a_users',
    'a_database',
    'a_cronjobs',
    'sa_settings'
);
if (in_array(PAGE_NAME, $adminpagelist)) {
    $inAdminfolder = "";
    $notInAdminfolder = "../";
} else {
    $inAdminfolder = "admin/";
    $notInAdminfolder = "";
}
?>

<nav class="w3-sidebar w3-collapse w3-white w3-animate-left" style="z-index:3;width:200px;" id="mySidebar"><br>

  <div class="w3-container w3-row">

    <div class="w3-col s4">

      <img src="<?= $notInAdminfolder.$siteSettings['GROUP_LOGO_SMALL_URL']?>" class="w3-margin-right" style="width:46px">

    </div>

    <div class="w3-col s8 w3-bar">

      <span>Welkom, <strong><?php echo ucfirst($vn); ?></strong></span><br>

      <a href="<?= $notInAdminfolder?>index" class="w3-bar-item w3-button"><i class="fas fa-sign-out-alt"></i></a>

      <a href="<?= $notInAdminfolder?>functies?gpstoggle=true&return=<?= $pagelist[PAGE_NAME]['filename']?>" class="w3-bar-item w3-button <?php if ($_SESSION['gps'] == "true"){echo "w3-green";}else{echo "w3-red";} ?>"><i class="fas fa-location-arrow"></i></a>

    </div>

  </div>

  <hr>

  <div class="w3-container">

    <h5>Dashboard</h5>

  </div>

  <div class="w3-bar-block">
    <a href="#" class="w3-bar-item w3-button w3-padding-16 w3-hide-large w3-dark-grey w3-hover-black" onclick="w3_close()" title="close menu"><i class="fa fa-remove fa-fw"></i>  Sluit Menu</a>
    <a href="<?=$notInAdminfolder?>home" class="w3-bar-item w3-button w3-padding<?= $pagelist['home']['active']?>"><i class="fa fa-users fa-fw"></i>  Overzicht</a>
    <?php if ($priv > 0){echo '<a href="'.$notInAdminfolder.'kaarten" class="w3-bar-item w3-button w3-padding'.$pagelist['kaarten']['active'].'"><i class="fas fa-map-marked-alt fa-fw"></i>  Kaarten</a>';}?>
    <?php if ($priv > 0){echo '<a href="'.$notInAdminfolder.'vossen" class="w3-bar-item w3-button w3-padding'.$pagelist['vossen']['active'].'"><i class="fas fa-bullseye fa-fw"></i>  Vossen</a>';}?>
    <?php if ($priv > 0){echo '<a href="'.$notInAdminfolder.'voslocaties" class="w3-bar-item w3-button w3-padding'.$pagelist['voslocaties']['active'].'"><i class="fas fa-circle-nodes fa-fw"></i>  Voslocaties</a>';}?>
    <a href="<?=$notInAdminfolder?>nieuws" class="w3-bar-item w3-button w3-padding<?= $pagelist['nieuws']['active']?>"><i class="far fa-newspaper fa-fw"></i>  Nieuws</a>
    <a href="<?=$notInAdminfolder?>opdrachten" class="w3-bar-item w3-button w3-padding<?= $pagelist['opdrachten']['active']?>"><i class="far fa-bell fa-fw"></i>  Opdrachten</a>
    <a href="<?=$notInAdminfolder?>hints" class="w3-bar-item w3-button w3-padding<?= $pagelist['hints']['active']?>"><i class="fas fa-question-circle fa-fw"></i>  Hints</a>
    <?php if ($priv > 0){echo '<a href="'.$notInAdminfolder.'punten" class="w3-bar-item w3-button w3-padding'.$pagelist['punten']['active'].'"><i class="fas fa-trophy fa-fw"></i>  Punten</a>';}?>
    <a href="<?=$notInAdminfolder?>groepen" class="w3-bar-item w3-button w3-padding<?= $pagelist['groepen']['active']?>"><i class="fas fa-home fa-fw"></i>  Groepen</a>
    <a href="<?=$notInAdminfolder?>instellingen" class="w3-bar-item w3-button w3-padding<?= $pagelist['instellingen']['active']?>"><i class="fas fa-cog fa-fw"></i>  Instellingen</a>
    <?php if ($priv > 0){echo '<a href="'.$notInAdminfolder.'autos" class="w3-bar-item w3-button w3-padding'.$pagelist['autos']['active'].'"><i class="fas fa-car fa-fw"></i>  Auto\'s</a>';}?>
    <?php if ($priv > 1){echo '<a href="'.$inAdminfolder.'users" class="w3-bar-item w3-button w3-padding'.$pagelist['a_users']['active'].'"><i class="fas fa-user-cog fa-fw"></i>  [Admin] Users</a>';} ?>
    <?php if ($priv > 1){echo '<a href="'.$inAdminfolder.'cronjobs" class="w3-bar-item w3-button w3-padding"'.$pagelist['a_cronjobs']['active'].'><i class="fas fa-stopwatch fa-fw"></i>  [Admin] Cronjobs</a>';} ?>
    <?php if ($priv > 1){echo '<a href="'.$inAdminfolder.'database" class="w3-bar-item w3-button w3-padding'.$pagelist['a_database']['active'].'"><i class="fas fa-database fa-fw"></i>  [Admin] Database</a>';} ?>
    <?php if ($priv > 2){echo '<a href="'.$inAdminfolder.'settings" class="w3-bar-item w3-button w3-padding'.$pagelist['sa_settings']['active'].'"><i class="fas fa-toolbox fa-fw"></i>  [Admin] Settings</a>';} ?><br><br>
  </div>

</nav>

</nav>

<!-- Overlay effect when opening sidebar on small screens -->
<div class="w3-overlay w3-hide-large w3-animate-opacity" onclick="w3_close()" style="cursor:pointer" title="close side menu" id="myOverlay"></div>

<script>
// Get the Sidebar
var mySidebar = document.getElementById("mySidebar");

// Get the DIV with overlay effect
var overlayBg = document.getElementById("myOverlay");

// Toggle between showing and hiding the sidebar, and add overlay effect
function w3_open() {
    if (mySidebar.style.display === 'block') {
        mySidebar.style.display = 'none';
        overlayBg.style.display = "none";
    } else {
        mySidebar.style.display = 'block';
        overlayBg.style.display = "block";
    }
}

// Close the sidebar with the close button
function w3_close() {
    mySidebar.style.display = "none";
    overlayBg.style.display = "none";
}
</script>