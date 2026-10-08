<?php
// Renders the top navigation header with page title, real-time fox statuses, GPS sharing toggle, user profile info, and breaking news alert.

// 1. Global Breaking News Banner (Rendered for both standard users and Kiosk screens)
$activeTegenhunt = function_exists('getActiveTegenhunt') ? getActiveTegenhunt($conn) : null;
?>
<?php if ($activeTegenhunt): 
    $rem = $activeTegenhunt['remaining_seconds'];
    $rem_min = floor($rem / 60);
    $rem_sec = $rem % 60;
    $rem_fmt = sprintf('%02d:%02d', $rem_min, $rem_sec);
?>
<div id="tegenhunt-breaking-banner" class="bg-red-600 text-white px-4 py-2 shadow-lg border-b border-yellow-400 flex flex-wrap items-center justify-between gap-2 z-50 flex-shrink-0">
  <div class="flex items-center gap-2">
    <span class="bg-yellow-400 text-red-900 font-extrabold px-2 py-0.5 rounded text-xs tracking-wider uppercase flex items-center gap-1 shadow-sm">
      <i class="fas fa-bullhorn text-xs"></i> BREAKING NEWS
    </span>
    <span class="font-bold text-xs sm:text-sm">TEGENHUNT GESTART! Richting: <span class="underline decoration-yellow-300 font-extrabold text-yellow-200 uppercase"><?= htmlspecialchars($activeTegenhunt['wind_direction']) ?></span> (binnen 450m)</span>
  </div>
  <div class="flex items-center gap-3">
    <div class="bg-black/30 px-2.5 py-0.5 rounded font-mono font-black text-xs sm:text-sm text-yellow-300 border border-yellow-400/30">
      <i class="far fa-clock mr-1 text-yellow-400"></i> <span id="banner-tegenhunt-timer" data-end="<?= strtotime($activeTegenhunt['end_time']) ?>"><?= $rem_fmt ?></span>
    </div>
    <?php if (!defined('PAGE_NAME') || PAGE_NAME !== 'tegenhunt'): ?>
    <a href="<?= $notInAdminfolder ?? '' ?>tegenhunt" class="bg-yellow-400 hover:bg-yellow-300 text-red-900 font-extrabold px-3 py-0.5 rounded text-xs sm:text-sm transition shadow flex items-center gap-1">
      <span>Open Zoekkaart</span> &rarr;
    </a>
    <?php elseif (($privilege ?? $_SESSION['priv'] ?? 0) >= 2): ?>
    <button onclick="openStopModal()" class="bg-black/30 hover:bg-black/50 text-white font-extrabold px-3 py-0.5 rounded text-xs sm:text-sm transition border border-white/30 flex items-center gap-1">
      <i class="fas fa-stop text-xs"></i> <span>Stop</span>
    </button>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php
// 2. Kiosk session early exit (Kiosks display full-screen without top navigation header)
if (isset($_SESSION['kiosk_id'])) {
    return;
}

$vos = array();
$topbarGroupName = 'Jotify';
if (!empty($site_settings['GROUP_ID'])) {
    $stmt_gn = $conn->prepare("SELECT naam FROM Groepen WHERE id = ?");
    $stmt_gn->bind_param("i", $site_settings['GROUP_ID']);
    $stmt_gn->execute();
    $result_gn = $stmt_gn->get_result();
    if ($result_gn->num_rows > 0) {
        $row_gn = $result_gn->fetch_assoc();
        if (!empty($row_gn['naam'])) {
            $topbarGroupName = $row_gn['naam'];
        }
    }
    $stmt_gn->close();
}

foreach ($fox_names as $vosnaam) {
    $vos[$vosnaam]["Kleur"] = "grey";
    $vos[$vosnaam]["duratie"] = "-";
    $vos[$vosnaam]["Status"] = 0;

    $stmt = $conn->prepare("SELECT status, datumtijd FROM Voslog WHERE vos = ? ORDER BY datumtijd DESC LIMIT 1");
    $stmt->bind_param("s", $vosnaam);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $latest_status = $row['status'];
        $vos[$vosnaam]["Status"] = $latest_status;

        $sql_change = "SELECT MIN(datumtijd) as changed_time FROM Voslog 
                       WHERE vos = ? AND datumtijd > (
                           SELECT COALESCE(MAX(datumtijd), '2000-01-01') FROM Voslog WHERE vos = ? AND status <> ?
                       )";
        $stmt_change = $conn->prepare($sql_change);
        $stmt_change->bind_param("ssi", $vosnaam, $vosnaam, $latest_status);
        $stmt_change->execute();
        $res_change = $stmt_change->get_result();

        if ($res_change->num_rows > 0) {
            $row_change = $res_change->fetch_assoc();
            if (!empty($row_change['changed_time'])) {
                $vos[$vosnaam]["verandering"] = $row_change['changed_time'];

                $diff = time() - strtotime($row_change['changed_time']);
                if ($diff < 60) {
                    $vos[$vosnaam]["duratie"] = $diff . " sec";
                } elseif ($diff < 3600) {
                    $vos[$vosnaam]["duratie"] = round($diff / 60) . " min";
                } elseif ($diff < 86400) {
                    $vos[$vosnaam]["duratie"] = round($diff / 3600, 1) . " uur";
                } else {
                    $vos[$vosnaam]["duratie"] = ">24u";
                }
            }
        }
        $stmt_change->close();

        if ($vos[$vosnaam]["Status"] == 0) {
            $vos[$vosnaam]["Kleur"] = "red";
        } elseif ($vos[$vosnaam]["Status"] == 1) {
            $vos[$vosnaam]["Kleur"] = "orange";
        } elseif ($vos[$vosnaam]["Status"] == 2) {
            $vos[$vosnaam]["Kleur"] = "green";
        }
    }
    $stmt->close();

    $stmt_hunt = $conn->prepare("SELECT ingestuurd_op FROM Voslocaties WHERE type = 'Hunt' AND ((status = 'Correct') OR (status LIKE '%HAPPY%') OR (status IS NULL)) AND deelgebied = ? ORDER BY ingestuurd_op DESC LIMIT 1");
    $stmt_hunt->bind_param("s", $vosnaam);
    $stmt_hunt->execute();
    $res_hunt = $stmt_hunt->get_result();

    if ($res_hunt->num_rows > 0) {
        $row_hunt = $res_hunt->fetch_assoc();
        $last_hunt_time = strtotime($row_hunt['ingestuurd_op']);
        $immune_until = $last_hunt_time + 3600;
        if ($immune_until > time()) {
            $vos[$vosnaam]["immune_until"] = $immune_until;
        }
    }
    $stmt_hunt->close();
}
?>


<header
    class="m-4 md:m-6 border h-14 theme-card flex items-center justify-between px-6 sticky top-4 md:top-6 z-30 shadow-sm flex-shrink-0">
    <div class="flex items-center">
        <?php
        $topbarTitles = [
            'home' => 'Overzicht',
            'kaarten' => 'Kaarten',
            'tegenhunt' => 'Tegenhunt',
            'vossen' => 'Vossen',
            'voslocaties' => 'Voslocaties',
            'groepen' => 'Groepen',
            'gebruikers' => 'Gebruikers',
            'auto' => 'Auto Beheer',
            'nieuws' => 'Nieuws',
            'hints' => 'Hints',
            'opdrachten' => 'Opdrachten',
            'fotoopdrachten' => 'Foto Opdrachten',
            'whiteboard' => 'Whiteboard',
            'site' => 'Site Instellingen',
            'database' => 'Database Beheer',
            'kiosk' => 'Kiosk',
            'audit_log' => 'Audit Log',
            'users' => 'Gebruikers',
            'serviceaccounts' => 'Service Accounts',
            'cronjobs' => 'Cronjobs',
            'audit' => 'Audit Log',
            'notifications' => 'Notificaties',
            'telegram' => 'Telegram',
            'settings' => 'Instellingen',
            'system' => 'System',
            'readiness' => 'Readiness Hub',
            'instellingen' => 'Instellingen'
        ];
        $currentPage = basename($_SERVER['PHP_SELF'], '.php');
        $topbarTitle = $topbarTitles[$currentPage] ?? ucfirst($currentPage);
        ?>
        <div class="flex items-center cursor-pointer md:cursor-default" onclick="if(window.innerWidth < 768) w3_open()" title="Menu">
            <button type="button" class="md:hidden opacity-60 hover:opacity-100 mr-3 transition cursor-pointer" onclick="w3_open()" aria-label="Open menu"><i
                    class="fas fa-bars"></i></button>
            <h1 class="text-base sm:text-lg font-bold select-none"><?= htmlspecialchars($topbarTitle) ?></h1>
        </div>
        <span
            class="ml-2 sm:ml-4 text-xs sm:text-sm font-medium opacity-60 border-l pl-2 sm:pl-4 whitespace-nowrap overflow-hidden text-ellipsis max-w-[200px] sm:max-w-none"
            style="border-color: var(--theme-card-border);"><?= htmlspecialchars($topbarGroupName) ?></span>
    </div>

    <div
        class="hidden xl:flex items-center space-x-2 mx-4 flex-1 justify-center max-w-2xl overflow-hidden whitespace-nowrap">
        <?php
        if (isset($fox_names)) {
            foreach ($fox_names as $n) {
                echo renderFoxBadge($n, $vos[$n], false);
            }
        }
        ?>
    </div>

    <div class="flex items-center space-x-3 sm:space-x-4">
        <?php
        $gps_active = (isset($_SESSION['gps']) && $_SESSION['gps'] == "true");
        $gps_color = $gps_active ? "text-green-500 opacity-100" : "opacity-60 hover:opacity-100";
        ?>
        <a href="<?= $notInAdminfolder ?? '' ?>functies.php?gpstoggle=1&return=<?= urlencode($_SERVER['REQUEST_URI']) ?>"
            class="<?= $gps_color ?> transition-colors" title="Location sharing is <?= $gps_active ? 'ON' : 'OFF' ?>"><i
                class="fas fa-crosshairs text-lg"></i></a>
        <a href="<?= $notInAdminfolder ?? '' ?>instellingen"
            class="opacity-60 hover:opacity-100 transition-colors" title="Instellingen"><i
                class="fas fa-cog text-lg"></i></a>
    </div>
</header>
