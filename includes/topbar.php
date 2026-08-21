<?php
if (isset($_SESSION['kiosk_id'])) {
    return;
}
$vos = array();
$topbarGroupName = 'Jotify';
if (!empty($siteSettings['GROUP_ID'])) {
    $stmt_gn = $conn->prepare("SELECT naam FROM Groepen WHERE id = ?");
    $stmt_gn->bind_param("i", $siteSettings['GROUP_ID']);
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

$topbar_profile_picture = null;
if (isset($_SESSION['id'])) {
    $stmt_pp = $conn->prepare("SELECT profile_picture FROM Gebruikers WHERE id = ?");
    $stmt_pp->bind_param("i", $_SESSION['id']);
    $stmt_pp->execute();
    $res_pp = $stmt_pp->get_result();
    if ($res_pp->num_rows > 0) {
        $row_pp = $res_pp->fetch_assoc();
        $topbar_profile_picture = $row_pp['profile_picture'];
    }
    $stmt_pp->close();
}

foreach ($vossen_names as $vosnaam) {
    $vos[$vosnaam]["Kleur"] = "grey"; // Standaard w3css kleur bij geen data
    $vos[$vosnaam]["duratie"] = "-";
    $vos[$vosnaam]["Status"] = 0;

    // Fetch latest status
    $stmt = $conn->prepare("SELECT status, datumtijd FROM Voslog WHERE vos = ? ORDER BY datumtijd DESC LIMIT 1");
    $stmt->bind_param("s", $vosnaam);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $latest_status = $row['status'];
        $vos[$vosnaam]["Status"] = $latest_status;

        // Find when this status originally started (the first row after the last time it was DIFFERENT)
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

                // dynamic duration
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

    // Check for recent hunts
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
    class="h-14 theme-card flex items-center justify-between px-6 sticky top-0 z-30 border-b shadow-sm flex-shrink-0">
    <div class="flex items-center">
        <button class="md:hidden opacity-60 hover:opacity-100 mr-3 transition" onclick="w3_open()"><i
                class="fas fa-bars"></i></button>
        <h2 class="text-base sm:text-lg font-semibold whitespace-nowrap overflow-hidden text-ellipsis cursor-pointer md:cursor-auto"
            onclick="if(window.innerWidth < 768) w3_open()"><?= htmlspecialchars(ucfirst(PAGE_NAME)) ?></h2>
        <span
            class="ml-2 sm:ml-4 text-xs sm:text-sm font-medium opacity-60 border-l pl-2 sm:pl-4 whitespace-nowrap overflow-hidden text-ellipsis max-w-[200px] sm:max-w-none"
            style="border-color: var(--theme-card-border);"><?= htmlspecialchars($topbarGroupName) ?></span>
    </div>

    <div
        class="hidden xl:flex items-center space-x-2 mx-4 flex-1 justify-center max-w-2xl overflow-hidden whitespace-nowrap">
        <?php
        if (isset($vossen_names)) {
            foreach ($vossen_names as $n) {
                $tw_color = 'bg-gray-200 text-gray-700';
                if ($vos[$n]['Kleur'] == 'red')
                    $tw_color = 'bg-red-500 text-white';
                elseif ($vos[$n]['Kleur'] == 'orange')
                    $tw_color = 'bg-orange-500 text-white';
                elseif ($vos[$n]['Kleur'] == 'green')
                    $tw_color = 'bg-green-500 text-white';

                echo '<div class="px-2 py-1 rounded text-xs font-bold flex items-center shadow-sm ' . $tw_color . ' whitespace-nowrap"';
                if (isset($vos[$n]["immune_until"])) {
                    echo ' style="background-image: repeating-linear-gradient(45deg, rgba(100, 116, 139, 0.4), rgba(100, 116, 139, 0.4) 8px, rgba(100, 116, 139, 0.1) 8px, rgba(100, 116, 139, 0.1) 16px);"';
                }
                echo '>';

                echo '<span class="mr-1">' . htmlspecialchars(substr($n, 0, 1)) . '</span>';
                if (isset($vos[$n]["immune_until"])) {
                    $diff = $vos[$n]["immune_until"] - time();
                    $initial_text = ($diff > 0) ? floor($diff / 60) . 'm ' . ($diff % 60) . 's' : '0m 0s';
                    echo '<span class="immune-countdown" data-until="' . $vos[$n]["immune_until"] . '" data-duratie="' . htmlspecialchars($vos[$n]["duratie"]) . '">' . $initial_text . '</span>';
                } else {
                    echo '<span>' . htmlspecialchars($vos[$n]["duratie"]) . '</span>';
                }
                echo '</div>';
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
            class="flex items-center space-x-2 border-l pl-3 sm:pl-4 hover:opacity-80 transition"
            style="border-color: var(--theme-card-border);">
            <?php if ($topbar_profile_picture): ?>
                <img src="<?= $notInAdminfolder ?? '' ?>profile_image.php?hash=<?= urlencode($topbar_profile_picture) ?>&res=low" alt="Profile" class="w-8 h-8 rounded-full object-cover shadow-sm flex-shrink-0">
            <?php else: ?>
                <div
                    class="w-8 h-8 rounded-full theme-bg-primary text-white flex items-center justify-center font-bold text-sm shadow-sm flex-shrink-0">
                    <?php echo strtoupper(substr($vn ?? 'U', 0, 1)); ?>
                </div>
            <?php endif; ?>
            <span
                class="text-sm font-medium hidden sm:block"><?php echo htmlspecialchars(ucfirst($vn ?? 'User')); ?></span>
        </a>
    </div>
</header>

<script>
    function updateImmuneCountdowns() {
        const now = Math.floor(Date.now() / 1000);
        document.querySelectorAll('.immune-countdown').forEach(function (el) {
            const until = parseInt(el.getAttribute('data-until'), 10);
            const diff = until - now;
            if (diff > 0) {
                const m = Math.floor(diff / 60);
                const s = diff % 60;
                el.textContent = m + 'm ' + s + 's';
            } else {
                // Immunity over: restore original duration text and remove striped background
                el.textContent = el.getAttribute('data-duratie');
                if (el.parentElement) {
                    el.parentElement.style.backgroundImage = '';
                }
                el.classList.remove('immune-countdown');
            }
        });
    }
    setInterval(updateImmuneCountdowns, 1000);
    updateImmuneCountdowns();
</script>