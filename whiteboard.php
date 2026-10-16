<?php
// Interactive tactical whiteboard for organizing real-time assignments of members and vehicles to fox hunting areas.
define("PAGE_NAME", "whiteboard");
require_once('includes/auth.php');

$is_guest = ($privilege < 1 && !$is_kiosk); // Guests can't see the board unless they are a Kiosk
if ($is_guest) {
    header("Location: home");
    exit();
}

require_once('includes/globals.php');
require_once('includes/helpers.php');

// Fetch data
$users = [];
$first_name = "Gebruiker";
$stmt = $conn->prepare("SELECT id, voornaam, achternaam, profile_picture FROM Gebruikers ORDER BY voornaam");
$stmt->execute();
$res = $stmt->get_result();
while($row = $res->fetch_assoc()) {
    $users[$row['id']] = $row;
    if ($row['id'] == $_SESSION['id']) {
        $first_name = $row['voornaam'];
    }
}
$stmt->close();

$categories = [];
$stmt = $conn->prepare("SELECT * FROM Whiteboard_Categorieen");
$stmt->execute();
$res = $stmt->get_result();
while($row = $res->fetch_assoc()) {
    $categories[$row['id']] = $row;
}
$stmt->close();

// Fetch Toewijzingen
$user_assignments = [];
$stmt = $conn->prepare("SELECT * FROM Toewijzingen");
$stmt->execute();
$res = $stmt->get_result();
while($row = $res->fetch_assoc()) {
    $user_assignments[$row['gebruiker_id']] = [
        'type' => $row['type'],
        'ref_id' => $row['referentie_id']
    ];
}
$stmt->close();

// Fetch Cars (uitsluitend auto's)
$cars = [];
$stmt = $conn->prepare("SELECT a.kenteken, a.hunter_type, a.rdw_kleur, a.aantal_zitplaatsen, g.voornaam as eigenaar_naam FROM Auto a JOIN Gebruikers g ON a.eigenaar = g.id WHERE a.hunter_type = 'car' OR a.hunter_type IS NULL OR a.hunter_type = '' ORDER BY a.kenteken ASC");
$stmt->execute();
$res = $stmt->get_result();
while($row = $res->fetch_assoc()) {
    if (!empty($row['hunter_type']) && $row['hunter_type'] !== 'car') {
        continue;
    }
    $row['bijrijders'] = [];
    $row['bestuurder'] = null;
    $cars[$row['kenteken']] = $row;
}
$stmt->close();

// Fetch Car users
$stmt = $conn->prepare("SELECT * FROM Auto_Bijrijders");
$stmt->execute();
$res = $stmt->get_result();
while($row = $res->fetch_assoc()) {
    $is_driver = !empty($row['is_driver']) || !empty($row['is_bestuurder']);
    if (isset($cars[$row['auto']])) {
        if ($is_driver) {
            $cars[$row['auto']]['bestuurder'] = $row['gebruiker_id'];
        } else {
            $cars[$row['auto']]['bijrijders'][] = $row['gebruiker_id'];
        }
        $user_assignments[$row['gebruiker_id']] = [
            'type' => 'auto',
            'ref_id' => $row['auto']
        ];
    }
}
$stmt->close();

// Fetch Car Assignments (Auto_Toewijzingen)
$car_assignments = [];
$stmt = $conn->prepare("SELECT * FROM Auto_Toewijzingen");
$stmt->execute();
$res = $stmt->get_result();
while($row = $res->fetch_assoc()) {
    if (isset($cars[$row['auto']])) {
        $car_assignments[$row['auto']] = [
            'type' => $row['type'],
            'ref_id' => $row['referentie_id']
        ];
    }
}
$stmt->close();

// Fetch Active Hints (active = assigned OR date within 1 hour)
$active_hints = [];
$stmt = $conn->prepare("SELECT id, titel, datum FROM Hints ORDER BY datum DESC");
$stmt->execute();
$res = $stmt->get_result();
$now = time();
while($row = $res->fetch_assoc()) {
    $is_active = false;
    $time = strtotime($row['datum']);
    if (($now - $time) < 3600) { // Active if less than 1 hr old
        $is_active = true;
    } else {
        // Check if assigned to any user
        foreach($user_assignments as $uid => $ass) {
            if ($ass['type'] === 'hint' && $ass['ref_id'] == $row['id']) {
                $is_active = true; break;
            }
        }
        if (!$is_active) {
            // Check if assigned to any car
            foreach($car_assignments as $kenteken => $ass) {
                if ($ass['type'] === 'hint' && $ass['ref_id'] == $row['id']) {
                    $is_active = true; break;
                }
            }
        }
    }
    if ($is_active) {
        $active_hints[$row['id']] = $row;
    }
}
$stmt->close();

// Fetch Active Opdrachten
$active_opdrachten = [];
$stmt = $conn->prepare("SELECT id, titel, eindtijd FROM Opdrachten ORDER BY eindtijd ASC");
$stmt->execute();
$res = $stmt->get_result();
while($row = $res->fetch_assoc()) {
    $is_active = false;
    $time = strtotime($row['eindtijd']);
    if ($time > $now) { // Active if not expired
        $is_active = true;
    } else {
        // Check if assigned
        foreach($user_assignments as $uid => $ass) {
            if ($ass['type'] === 'opdracht' && $ass['ref_id'] == $row['id']) {
                $is_active = true; break;
            }
        }
        if (!$is_active) {
            foreach($car_assignments as $kenteken => $ass) {
                if ($ass['type'] === 'opdracht' && $ass['ref_id'] == $row['id']) {
                    $is_active = true; break;
                }
            }
        }
    }
    if ($is_active) {
        $active_opdrachten[$row['id']] = $row;
    }
}
$stmt->close();

// Fetch Active Fotoopdrachten
$active_fotoopdrachten = [];
$stmt = $conn->prepare("SELECT id, titel, start_at, end_at FROM Fotoopdrachten ORDER BY end_at ASC");
if ($stmt) {
    $stmt->execute();
    $res = $stmt->get_result();
    while($row = $res->fetch_assoc()) {
        $is_active = false;
        $endTime = strtotime($row['end_at']);
        if ($endTime > $now) { // Active if not expired
            $is_active = true;
        } else {
            foreach($user_assignments as $uid => $ass) {
                if ($ass['type'] === 'fotoopdracht' && $ass['ref_id'] == $row['id']) {
                    $is_active = true; break;
                }
            }
        }
        if ($is_active) {
            $active_fotoopdrachten[$row['id']] = $row;
        }
    }
    $stmt->close();
}

// Fetch latest hunt times for foxes
$fox_hunts = [];
foreach ($fox_names as $k => $v) {
    $fox_hunts[$k] = ['naam' => $v, 'laatste_hunt' => null];
}

$stmt = $conn->prepare("
    SELECT deelgebied, MAX(ingestuurd_op) as laatste_hunt 
    FROM Voslocaties 
    WHERE type = 'Hunt' AND (status = 'Correct' OR status LIKE '%HAPPY%' OR status IS NULL)
    GROUP BY deelgebied
");
$stmt->execute();
$res = $stmt->get_result();
while($row = $res->fetch_assoc()) {
    $fox_name = ucfirst(strtolower($row['deelgebied']));
    $idx = array_search(trim($fox_name), $fox_names);
    if ($idx !== false) {
        $fox_hunts[$idx]['laatste_hunt'] = $row['laatste_hunt'];
    }
}
$stmt->close();

// Allowed whiteboard foxes filter from Site_Instellingen (can be set by admin or scraper)
$whiteboard_foxes_setting = trim($site_settings['WHITEBOARD_FOX_NAMES'] ?? '');
$allowed_foxes_filter = [];
if (!empty($whiteboard_foxes_setting)) {
    $allowed_foxes_filter = array_map('strtolower', array_filter(array_map('trim', explode(',', $whiteboard_foxes_setting))));
}

// Cleanup invalid assignments
foreach ($car_assignments as $k => $ass) {
    $valid = false;
    if ($ass['type'] === 'hint' && isset($active_hints[$ass['ref_id']])) $valid = true;
    if ($ass['type'] === 'opdracht' && isset($active_opdrachten[$ass['ref_id']])) $valid = true;
    if ($ass['type'] === 'fotoopdracht' && isset($active_fotoopdrachten[$ass['ref_id']])) $valid = true;
    if ($ass['type'] === 'custom' && array_search($ass['ref_id'], array_column($categories, 'id')) !== false) $valid = true;
    if ($ass['type'] === 'hunt' && isset($fox_hunts[$ass['ref_id']])) {
        $fName = strtolower(trim($fox_hunts[$ass['ref_id']]['naam']));
        if ($fName === 'oscar' || empty($allowed_foxes_filter) || in_array($fName, $allowed_foxes_filter, true)) {
            $valid = true;
        }
    }
    if (!$valid) unset($car_assignments[$k]);
}

foreach ($user_assignments as $uid => $ass) {
    $valid = false;
    if ($ass['type'] === 'auto' && isset($cars[$ass['ref_id']])) $valid = true;
    if ($ass['type'] === 'hint' && isset($active_hints[$ass['ref_id']])) $valid = true;
    if ($ass['type'] === 'opdracht' && isset($active_opdrachten[$ass['ref_id']])) $valid = true;
    if ($ass['type'] === 'fotoopdracht' && isset($active_fotoopdrachten[$ass['ref_id']])) $valid = true;
    if ($ass['type'] === 'custom' && array_search($ass['ref_id'], array_column($categories, 'id')) !== false) $valid = true;
    if ($ass['type'] === 'hunt' && isset($fox_hunts[$ass['ref_id']])) {
        $fName = strtolower(trim($fox_hunts[$ass['ref_id']]['naam']));
        if ($fName === 'oscar' || empty($allowed_foxes_filter) || in_array($fName, $allowed_foxes_filter, true)) {
            $valid = true;
        }
    }
    if (!$valid) unset($user_assignments[$uid]);
}

require_once('includes/whiteboard_components.php');
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<title>Jotify - Digitaal Whiteboard</title>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="shortcut icon" type="image/png" href="media/geusje.png"/>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://kit.fontawesome.com/870ab34ea3.js" crossorigin="anonymous"></script>
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/mobile-drag-drop@2.3.0-rc.2/default.css">
<script src="https://cdn.jsdelivr.net/npm/mobile-drag-drop@2.3.0-rc.2/index.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/mobile-drag-drop@2.3.0-rc.2/scroll-behaviour.min.js"></script>
<?php include_once('includes/theme.php'); ?>
<style>
.wb-zone {
    min-height: 50px;
    transition: background-color 0.2s, min-height 0.2s;
}
.wb-zone.drag-over {
    background-color: var(--theme-card-bg-hover) !important;
    border: 2px dashed var(--theme-primary) !important;
}
.wb-user { 
    transition: transform 0.2s; 
    justify-self: center;
    display: inline-flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    width: 44px;
    margin: 2px !important;
}
.wb-user:hover { transform: scale(1.1); }
.car-draggable .user-name { color: #ffffff !important; }
.car-draggable {
    cursor: move;
}
.passenger-zone {
    display: grid;
    grid-template-columns: 36px 36px 36px;
    grid-auto-rows: 68px;
    justify-content: center;
    justify-items: center;
    width: 100%;
    min-height: 100px;
}
/* child(1) is the driver zone div; passengers start at child(2) */
.passenger-zone > .wb-user:nth-child(2) { grid-column: 3; grid-row: 1; }
.passenger-zone > .wb-user:nth-child(3) { grid-column: 1; grid-row: 2; z-index: 1; }
.passenger-zone > .wb-user:nth-child(4) { grid-column: 3; grid-row: 2; z-index: 1; }
.passenger-zone > .wb-user:nth-child(5) { grid-column: 2; grid-row: 2; z-index: 2; }
.passenger-zone > .wb-user:nth-child(6) { grid-column: 1; grid-row: 3; z-index: 1; }
.passenger-zone > .wb-user:nth-child(7) { grid-column: 3; grid-row: 3; z-index: 1; }
.passenger-zone > .wb-user:nth-child(8) { grid-column: 2; grid-row: 3; z-index: 2; }
.compact-car {
    cursor: move;
    transition: transform 0.2s;
}
.compact-car:hover { transform: scale(1.05); }
.driver-seat {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: auto !important;
}
.steering-wheel-placeholder {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    border: 2px dashed #6b7280;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 2px auto;
}
.driver-seat:has(.wb-user) .steering-wheel-placeholder {
    display: none !important;
}

/* Vehicle card contrast & typography */
.vehicle-card .user-name {
    color: #ffffff !important;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.95), 0 0 4px rgba(0, 0, 0, 0.8) !important;
}
.vehicle-card {
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.vehicle-card:hover {
    transform: translateY(-2px);
}

/* Fox Card Adaptive Sizing: smaller when unassigned */
.fox-card {
    transition: all 0.2s ease;
}
.fox-card .fox-card-header {
    padding-bottom: 0.5rem;
    margin-bottom: 0.5rem;
}
.fox-card .fox-zone {
    min-height: 48px;
    transition: min-height 0.2s ease, background-color 0.2s ease;
}

/* Empty Fox Card (no user and no car assigned) */
.fox-card.is-empty,
.fox-card:not(:has(.wb-user)):not(:has(.car-draggable)) {
    opacity: 0.88;
}
.fox-card.is-empty:hover,
.fox-card:not(:has(.wb-user)):not(:has(.car-draggable)):hover {
    opacity: 1;
}
.fox-card.is-empty .fox-card-header,
.fox-card:not(:has(.wb-user)):not(:has(.car-draggable)) .fox-card-header {
    padding-bottom: 0.35rem;
    margin-bottom: 0.35rem;
    border-bottom: 1px dashed var(--theme-card-border);
}
.fox-card.is-empty .fox-zone,
.fox-card:not(:has(.wb-user)):not(:has(.car-draggable)) .fox-zone {
    min-height: 32px;
    border: 1px dashed rgba(156, 163, 175, 0.25);
    border-radius: 0.375rem;
}
.fox-card.is-empty .fox-zone.drag-over,
.fox-card:not(:has(.wb-user)):not(:has(.car-draggable)) .fox-zone.drag-over {
    min-height: 48px;
    border: 2px dashed var(--theme-primary) !important;
    background-color: var(--theme-card-bg-hover);
}
.fox-card.is-empty .fox-empty-indicator,
.fox-card:not(:has(.wb-user)):not(:has(.car-draggable)) .fox-empty-indicator {
    display: flex;
}
.fox-card:has(.wb-user) .fox-empty-indicator,
.fox-card:has(.car-draggable) .fox-empty-indicator {
    display: none !important;
}

/* Active Fox Card */
.fox-card:has(.wb-user),
.fox-card:has(.car-draggable) {
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08);
}

/* Huntbaar pill contrast guarantee */
.huntbaar-pill {
    color: var(--theme-text) !important;
}
.huntbaar-pill .huntbaar-time {
    color: var(--theme-text) !important;
    font-weight: 800;
}
</style>
</head>
<body class="flex h-screen overflow-hidden">

<!-- Sidebar -->
<?php include_once('includes/sidebar.php') ?>

<!-- Main Content -->
<div class="flex-1 flex flex-col h-screen overflow-y-auto w-full relative">
  <!-- Topbar -->
  <?php include_once('includes/topbar.php') ?>

  <main class="px-3 pb-6 md:px-6 md:pb-6 pt-0 max-w-[1850px] mx-auto w-full flex-1 flex flex-col gap-4">
    
    <!-- Top Tray: Niet Toegewezen -->
    <div class="theme-card rounded border shadow-xs p-3">
        <div class="flex items-center justify-between pb-1.5 mb-2 border-b" style="border-color: var(--theme-card-border);">
            <h3 class="font-bold text-xs uppercase tracking-wider opacity-70 flex items-center gap-1.5">
                <i class="fas fa-users opacity-60"></i>
                <span>Niet Toegewezen</span>
                <span class="text-[11px] font-normal opacity-70" id="unassigned-badge">
                    <?php
                    $u_unassigned = count(array_filter($users, fn($u) => !isset($user_assignments[$u['id']])));
                    $c_unassigned = count(array_filter($cars, fn($c, $k) => !isset($car_assignments[$k]), ARRAY_FILTER_USE_BOTH));
                    echo "({$u_unassigned} jager" . ($u_unassigned === 1 ? '' : 's') . ($c_unassigned > 0 ? ", {$c_unassigned} auto" . ($c_unassigned === 1 ? '' : '\'s') : '') . ')';
                    ?>
                </span>
            </h3>
            <span class="text-[11px] opacity-40 italic hidden sm:inline select-none">Sleep jagers of voertuigen naar een vossenteam of categorie</span>
        </div>
        <div class="wb-zone flex flex-wrap items-center gap-2 min-h-[46px]" id="zone_unassigned" data-type="unassigned" data-ref="" ondrop="drop(event)" ondragover="allowDrop(event)">
            <?php
            foreach ($users as $uid => $user) {
                if (!isset($user_assignments[$uid])) {
                    echo renderUser($user);
                }
            }
            foreach ($cars as $kenteken => $car) {
                if (!isset($car_assignments[$kenteken])) {
                    echo renderCompactCar($kenteken, $car, $users);
                }
            }
            ?>
        </div>
    </div>

    <!-- Main Board Grid -->
    <div class="flex flex-col gap-5 w-full">
        
        <!-- Hunten -->
        <div>
            <h2 class="text-lg md:text-xl font-bold mb-3 border-b pb-2 flex items-center gap-2" style="border-color: var(--theme-card-border);">
                <i class="fas fa-crosshairs opacity-60"></i>
                <span>Hunten</span>
            </h2>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3 items-start">
                <?php foreach ($fox_hunts as $idx => $fox): ?>
                    <?php
                    $fName = strtolower(trim($fox['naam']));
                    // Oscar is always huntable by every group
                    if ($fName !== 'oscar' && !empty($allowed_foxes_filter) && !in_array($fName, $allowed_foxes_filter, true)) {
                        continue;
                    }

                    // Determine if any cars or users are assigned to this fox
                    $fox_has_assignments = false;
                    foreach ($cars as $kenteken => $car) {
                        $c_ass = $car_assignments[$kenteken] ?? null;
                        if ($c_ass && $c_ass['type'] === 'hunt' && $c_ass['ref_id'] == $idx) {
                            $fox_has_assignments = true;
                            break;
                        }
                    }
                    if (!$fox_has_assignments) {
                        foreach ($users as $uid => $user) {
                            $ass = $user_assignments[$uid] ?? null;
                            if ($ass && $ass['type'] === 'hunt' && $ass['ref_id'] == $idx) {
                                $fox_has_assignments = true;
                                break;
                            }
                        }
                    }
                    $empty_class = $fox_has_assignments ? '' : 'is-empty';

                    $vos_status_kleur = isset($vos[$fox['naam']]) ? $vos[$fox['naam']]['Kleur'] : 'grey';
                    $tw_status_kleur = 'bg-gray-400';
                    if ($vos_status_kleur == 'red') $tw_status_kleur = 'bg-red-500';
                    elseif ($vos_status_kleur == 'orange') $tw_status_kleur = 'bg-orange-500';
                    elseif ($vos_status_kleur == 'green') $tw_status_kleur = 'bg-green-500';

                    $huntbaar_time = null;
                    $is_huntbaar = true;
                    if (!empty($fox['laatste_hunt'])) {
                        $huntbaar_time = strtotime($fox['laatste_hunt']) + 3600;
                        $is_huntbaar = ($huntbaar_time <= time());
                    }
                    $huntbaar_display = $is_huntbaar ? 'Ja!' : date('H:i', $huntbaar_time);
                    ?>
                    <div class="fox-card <?= $empty_class ?> theme-card rounded border shadow-xs transition-all duration-200 flex flex-col p-3.5 sm:p-4" data-fox-name="<?= htmlspecialchars($fox['naam']) ?>">
                        <div class="fox-card-header font-bold border-b flex justify-between items-center transition-all" style="border-color: var(--theme-card-border);">
                            <span class="flex items-center gap-1.5 min-w-0">
                                <span class="w-2.5 h-2.5 rounded-full <?= $tw_status_kleur ?> inline-block shadow-xs flex-shrink-0"></span>
                                <span class="text-xs sm:text-sm truncate"><i class="fas fa-paw mr-1 opacity-50 text-[10px]"></i><?= htmlspecialchars($fox['naam']) ?></span>
                            </span>
                            <span class="huntbaar-pill text-[10px] sm:text-[11px] px-2 py-0.5 rounded-full border shadow-xs font-medium whitespace-nowrap inline-flex items-center gap-1 flex-shrink-0" style="background-color: rgba(127, 127, 127, 0.08); border-color: var(--theme-card-border);">
                                <span class="opacity-75" style="color: var(--theme-text);">Huntbaar:</span>
                                <?php if ($is_huntbaar): ?>
                                    <span class="text-emerald-600 dark:text-emerald-400 font-extrabold tracking-wide">Ja!</span>
                                <?php else: ?>
                                    <span class="huntbaar-time font-extrabold" style="color: var(--theme-text);"><?= htmlspecialchars($huntbaar_display) ?></span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="wb-zone fox-zone flex-1 flex flex-wrap gap-2 content-start transition-all" id="zone_hunt_<?= $idx ?>" data-type="hunt" data-ref="<?= $idx ?>" ondrop="drop(event)" ondragover="allowDrop(event)">
                            <div class="fox-empty-indicator text-[10px] opacity-40 italic w-full justify-center items-center py-1 select-none pointer-events-none">Geen toewijzing</div>
                            <?php
                            // Render cars assigned to this fox
                            foreach ($cars as $kenteken => $car) {
                                $c_ass = $car_assignments[$kenteken] ?? null;
                                if ($c_ass && $c_ass['type'] === 'hunt' && $c_ass['ref_id'] == $idx) {
                                    echo renderCar($kenteken, $car, $users);
                                }
                            }
                            // Render users assigned to this fox
                            foreach ($users as $uid => $user) {
                                $ass = $user_assignments[$uid] ?? null;
                                if ($ass && $ass['type'] === 'hunt' && $ass['ref_id'] == $idx) {
                                    echo renderUser($user);
                                }
                            }
                            ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Hints -->
        <div class="mt-2">
            <h2 class="text-lg md:text-xl font-bold mb-3 border-b pb-2 flex items-center gap-2" style="border-color: var(--theme-card-border);">
                <i class="fas fa-search opacity-60"></i>
                <span>Hints</span>
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-3">
                <?php if (empty($active_hints)): ?>
                    <div class="col-span-full opacity-60 italic text-sm py-1">Er zijn momenteel geen actieve hints.</div>
                <?php else: ?>
                    <?php foreach ($active_hints as $hid => $hint): ?>
                        <div class="theme-card rounded border shadow-xs p-3">
                            <h3 class="font-bold text-sm mb-2 truncate" title="<?= htmlspecialchars($hint['titel']) ?>"><?= htmlspecialchars($hint['titel']) ?></h3>
                            <div class="wb-zone flex flex-wrap gap-2 min-h-[50px]" id="zone_hint_<?= $hid ?>" data-type="hint" data-ref="<?= $hid ?>" ondrop="drop(event)" ondragover="allowDrop(event)">
                                <?php
                                foreach ($cars as $kenteken => $car) {
                                    $c_ass = $car_assignments[$kenteken] ?? null;
                                    if ($c_ass && $c_ass['type'] === 'hint' && $c_ass['ref_id'] == $hid) {
                                        echo renderCar($kenteken, $car, $users);
                                    }
                                }
                                foreach ($user_assignments as $uid => $ass) {
                                    if ($ass['type'] === 'hint' && $ass['ref_id'] == $hid) {
                                        echo renderUser($users[$uid]);
                                    }
                                }
                                ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Opdrachten -->
        <div class="mt-2">
            <h2 class="text-lg md:text-xl font-bold mb-3 border-b pb-2 flex items-center gap-2" style="border-color: var(--theme-card-border);">
                <i class="fas fa-tasks opacity-60"></i>
                <span>Opdrachten</span>
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-3">
                <?php if (empty($active_opdrachten)): ?>
                    <div class="col-span-full opacity-60 italic text-sm py-1">Er zijn momenteel geen actieve opdrachten.</div>
                <?php else: ?>
                    <?php foreach ($active_opdrachten as $oid => $opdracht): ?>
                        <div class="theme-card rounded border shadow-xs p-3">
                            <h3 class="font-bold text-sm mb-2 truncate" title="<?= htmlspecialchars($opdracht['titel']) ?>"><?= htmlspecialchars($opdracht['titel']) ?></h3>
                            <div class="wb-zone flex flex-wrap gap-2 min-h-[50px]" id="zone_opdracht_<?= $oid ?>" data-type="opdracht" data-ref="<?= $oid ?>" ondrop="drop(event)" ondragover="allowDrop(event)">
                                <?php
                                foreach ($cars as $kenteken => $car) {
                                    $c_ass = $car_assignments[$kenteken] ?? null;
                                    if ($c_ass && $c_ass['type'] === 'opdracht' && $c_ass['ref_id'] == $oid) {
                                        echo renderCar($kenteken, $car, $users);
                                    }
                                }
                                foreach ($user_assignments as $uid => $ass) {
                                    if ($ass['type'] === 'opdracht' && $ass['ref_id'] == $oid) {
                                        echo renderUser($users[$uid]);
                                    }
                                }
                                ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Foto-opdrachten -->
        <div class="mt-2">
            <h2 class="text-lg md:text-xl font-bold mb-3 border-b pb-2 flex items-center gap-2" style="border-color: var(--theme-card-border);">
                <i class="fas fa-camera opacity-60"></i>
                <span>Foto-opdrachten</span>
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-3">
                <?php if (empty($active_fotoopdrachten)): ?>
                    <div class="col-span-full opacity-60 italic text-sm py-1">Er zijn momenteel geen actieve foto-opdrachten.</div>
                <?php else: ?>
                    <?php foreach ($active_fotoopdrachten as $fid => $fo): ?>
                        <div class="theme-card rounded border shadow-xs p-3">
                            <h3 class="font-bold text-sm mb-2 truncate" title="<?= htmlspecialchars($fo['titel']) ?>"><?= htmlspecialchars($fo['titel']) ?></h3>
                            <div class="wb-zone flex flex-wrap gap-2 min-h-[50px]" id="zone_fotoopdracht_<?= $fid ?>" data-type="fotoopdracht" data-ref="<?= $fid ?>" ondrop="drop(event)" ondragover="allowDrop(event)">
                                <?php
                                foreach ($user_assignments as $uid => $ass) {
                                    if ($ass['type'] === 'fotoopdracht' && $ass['ref_id'] == $fid) {
                                        echo renderUser($users[$uid]);
                                    }
                                }
                                ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Custom Categories -->
        <div class="mt-2">
            <div class="flex items-center justify-between mb-3 border-b pb-2" style="border-color: var(--theme-card-border);">
                <h2 class="text-lg md:text-xl font-bold flex items-center gap-2">
                    <i class="fas fa-tags opacity-60"></i>
                    <span>Overig</span>
                </h2>
                <button onclick="document.getElementById('add-cat-modal').classList.remove('hidden')" class="theme-bg-primary text-white text-xs md:text-sm px-3 py-1.5 rounded font-bold shadow hover:opacity-90 transition flex items-center gap-1.5 cursor-pointer">
                    <i class="fas fa-plus"></i>
                    <span>Nieuwe categorie</span>
                </button>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-3">
                <?php if (empty($categories)): ?>
                    <div class="col-span-full opacity-60 italic text-sm py-1">Nog geen eigen categorieën aangemaakt. Voeg er een toe via 'Nieuwe categorie'.</div>
                <?php else: ?>
                    <?php foreach ($categories as $cat): ?>
                        <div class="theme-card rounded border shadow-xs p-3 relative group" style="border-left: 4px solid <?= htmlspecialchars($cat['kleur']) ?>;">
                            <h3 class="font-bold text-sm mb-2 pr-8 truncate" title="<?= htmlspecialchars($cat['naam']) ?>"><i class="fas fa-tag mr-1.5 text-xs" style="color: <?= htmlspecialchars($cat['kleur']) ?>;"></i><?= htmlspecialchars($cat['naam']) ?></h3>
                            <button onclick="delCategory(<?= $cat['id'] ?>)" class="absolute top-3 right-3 text-red-500 opacity-0 group-hover:opacity-100 transition p-1"><i class="fas fa-trash text-xs"></i></button>
                            <div class="wb-zone flex flex-wrap gap-2 min-h-[50px]" id="zone_custom_<?= $cat['id'] ?>" data-type="custom" data-ref="<?= $cat['id'] ?>" ondrop="drop(event)" ondragover="allowDrop(event)">
                                <?php
                                foreach ($cars as $kenteken => $car) {
                                    $c_ass = $car_assignments[$kenteken] ?? null;
                                    if ($c_ass && $c_ass['type'] === 'custom' && $c_ass['ref_id'] == $cat['id']) {
                                        echo renderCar($kenteken, $car, $users);
                                    }
                                }
                                foreach ($user_assignments as $uid => $ass) {
                                    if ($ass['type'] === 'custom' && $ass['ref_id'] == $cat['id']) {
                                        echo renderUser($users[$uid]);
                                    }
                                }
                                ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>
  </main>

  <!-- Delete Category Modal -->
  <div id="del-cat-modal" class="fixed inset-0 bg-black/50 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md theme-card">
        <h3 class="text-xl font-bold mb-4 text-red-600">Categorie Verwijderen</h3>
        <p class="mb-6">Weet je zeker dat je deze categorie wilt verwijderen? Alle toewijzingen worden ongedaan gemaakt.</p>
        <div class="flex justify-end gap-2">
            <button onclick="closeDelCategoryModal()" class="px-4 py-2 border rounded hover:bg-black/5 transition">Annuleren</button>
            <button onclick="confirmDelCategory()" class="bg-red-600 text-white px-4 py-2 rounded shadow hover:opacity-90 transition">Verwijderen</button>
        </div>
    </div>
  </div>

  <!-- Add Category Modal -->
  <div id="add-cat-modal" class="fixed inset-0 bg-black/50 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md theme-card">
        <h3 class="text-xl font-bold mb-4">Nieuwe categorie toevoegen</h3>
        <input type="text" id="cat-name" placeholder="Naam (bijv. Slapen)" class="w-full border rounded p-2 mb-4 bg-transparent">
        <input type="color" id="cat-color" value="#3B82F6" class="w-full h-10 border rounded mb-4 cursor-pointer">
        <div class="flex justify-end gap-2">
            <button onclick="document.getElementById('add-cat-modal').classList.add('hidden')" class="px-4 py-2 border rounded hover:bg-black/5 transition">Annuleren</button>
            <button onclick="addCategory()" class="theme-bg-primary text-white px-4 py-2 rounded shadow hover:opacity-90 transition">Opslaan</button>
        </div>
    </div>
  </div>

  <!-- Whiteboard Toast Notifications -->
  <div id="wb-toast-container" class="fixed bottom-5 right-5 z-50 flex flex-col gap-2 pointer-events-none"></div>

  <?php require_once('includes/footer.php') ?>
</div>

<script src="js/whiteboard.js?v=<?= filemtime(__DIR__ . '/js/whiteboard.js') ?>"></script>

</body>
</html>
