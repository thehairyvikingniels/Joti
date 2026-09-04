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

// Fetch Cars
$cars = [];
$stmt = $conn->prepare("SELECT a.kenteken, g.voornaam as eigenaar_naam FROM Auto a JOIN Gebruikers g ON a.eigenaar = g.id");
$stmt->execute();
$res = $stmt->get_result();
while($row = $res->fetch_assoc()) {
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
    }
    $user_assignments[$row['gebruiker_id']] = [
        'type' => 'auto',
        'ref_id' => $row['auto']
    ];
}
$stmt->close();

// Fetch Car Assignments (Auto_Toewijzingen)
$car_assignments = [];
$stmt = $conn->prepare("SELECT * FROM Auto_Toewijzingen");
$stmt->execute();
$res = $stmt->get_result();
while($row = $res->fetch_assoc()) {
    $car_assignments[$row['auto']] = [
        'type' => $row['type'],
        'ref_id' => $row['referentie_id']
    ];
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

// Cleanup invalid assignments
foreach ($car_assignments as $k => $ass) {
    $valid = false;
    if ($ass['type'] === 'hint' && isset($active_hints[$ass['ref_id']])) $valid = true;
    if ($ass['type'] === 'opdracht' && isset($active_opdrachten[$ass['ref_id']])) $valid = true;
    if ($ass['type'] === 'custom' && array_search($ass['ref_id'], array_column($categories, 'id')) !== false) $valid = true;
    if ($ass['type'] === 'hunt' && isset($fox_hunts[$ass['ref_id']])) $valid = true;
    if (!$valid) unset($car_assignments[$k]);
}

foreach ($user_assignments as $uid => $ass) {
    $valid = false;
    if ($ass['type'] === 'auto' && isset($cars[$ass['ref_id']])) $valid = true;
    if ($ass['type'] === 'hint' && isset($active_hints[$ass['ref_id']])) $valid = true;
    if ($ass['type'] === 'opdracht' && isset($active_opdrachten[$ass['ref_id']])) $valid = true;
    if ($ass['type'] === 'custom' && array_search($ass['ref_id'], array_column($categories, 'id')) !== false) $valid = true;
    if ($ass['type'] === 'hunt' && isset($fox_hunts[$ass['ref_id']])) $valid = true;
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
    min-height: 80px;
    transition: background-color 0.2s;
}
.wb-zone.drag-over {
    background-color: rgba(0, 0, 0, 0.05);
    border: 2px dashed var(--theme-primary);
}
.wb-zone { min-height: 50px; transition: background-color 0.2s; }
.wb-zone.drag-over { background-color: var(--theme-card-bg-hover); }
.wb-user { 
    transition: transform 0.2s; 
    justify-self: center;
    min-width: 48px;
}
.wb-user:hover { transform: scale(1.1); }
.car-draggable .user-name { color: #ffffff !important; }
.car-draggable {
    cursor: move;
}
.passenger-zone {
    display: grid;
    grid-template-columns: 32px 32px 32px;
    grid-auto-rows: 70px;
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
    align-items: flex-start;
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
    margin: 4px;
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

  <main class="p-4 md:p-6 max-w-[1600px] mx-auto w-full flex-1">
    
    <div class="flex items-center justify-end mb-6">
        <button onclick="document.getElementById('add-cat-modal').classList.remove('hidden')" class="theme-bg-primary text-white px-4 py-2 rounded font-bold shadow hover:opacity-90 transition">
            <i class="fas fa-plus mr-2"></i>Nieuwe categorie
        </button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        
        <!-- Left Column: Unassigned Users -->
        <div class="lg:col-span-1">
            <div class="theme-card rounded border shadow-sm p-4 sticky top-4">
                <h3 class="font-bold mb-3 border-b pb-2" style="border-color: var(--theme-card-border);"><i class="fas fa-users mr-2 opacity-50"></i>Niet Toegewezen</h3>
                <div class="wb-zone flex flex-wrap gap-2" id="zone_unassigned" data-type="unassigned" data-ref="" ondrop="drop(event)" ondragover="allowDrop(event)">
                    <?php
                    foreach ($users as $uid => $user) {
                        if (!isset($user_assignments[$uid])) {
                            echo renderUser($user);
                        }
                    }
                    ?>
                    <div class="w-full h-0 mb-1 border-b border-gray-200/50" id="unassigned-separator"></div>
                    <?php
                    foreach ($cars as $kenteken => $car) {
                        if (!isset($car_assignments[$kenteken])) {
                            echo renderCompactCar($kenteken, $car, $users);
                        }
                    }
                    ?>
                </div>
            </div>
        </div>

        <!-- Right Columns: Zones -->
        <div class="lg:col-span-3 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
            
            <!-- Hunten -->
            <div class="col-span-full">
                <h2 class="text-xl font-bold mb-4 border-b pb-2" style="border-color: var(--theme-card-border);"><i class="fas fa-crosshairs mr-2"></i>Hunten</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    <?php foreach ($fox_hunts as $idx => $fox): ?>
                        <div class="theme-card rounded border shadow-sm p-4 h-full flex flex-col">
                            <h3 class="font-bold mb-3 border-b pb-2 flex justify-between items-center" style="border-color: var(--theme-card-border);">
                                <span class="flex items-center gap-2">
                                    <?php 
                                    $vos_status_kleur = isset($vos[$fox['naam']]) ? $vos[$fox['naam']]['Kleur'] : 'grey';
                                    $tw_status_kleur = 'bg-gray-200';
                                    if ($vos_status_kleur == 'red') $tw_status_kleur = 'bg-red-500';
                                    elseif ($vos_status_kleur == 'orange') $tw_status_kleur = 'bg-orange-500';
                                    elseif ($vos_status_kleur == 'green') $tw_status_kleur = 'bg-green-500';
                                    ?>
                                    <span class="w-3 h-3 rounded-full <?php echo $tw_status_kleur; ?> inline-block shadow-sm"></span>
                                    <span><i class="fas fa-paw mr-2 opacity-50"></i><?php echo htmlspecialchars($fox['naam']); ?></span>
                                </span>
                                <?php 
                                if ($fox['laatste_hunt']) {
                                    $huntbaar_time = strtotime($fox['laatste_hunt']) + 3600;
                                    $huntbaar_text = ($huntbaar_time <= time()) ? 'Ja!' : date('H:i', $huntbaar_time);
                                } else {
                                    $huntbaar_text = 'Ja!';
                                }
                                ?>
                                <span class="text-xs opacity-70 bg-black/10 px-2 py-1 rounded">Huntbaar: <?php echo $huntbaar_text; ?></span>
                            </h3>
                            <div class="wb-zone flex-1 flex flex-wrap gap-2 content-start" id="zone_hunt_<?php echo $idx; ?>" data-type="hunt" data-ref="<?php echo $idx; ?>" ondrop="drop(event)" ondragover="allowDrop(event)">
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
            <div class="col-span-full mt-4">
                <h2 class="text-xl font-bold mb-4 border-b pb-2" style="border-color: var(--theme-card-border);"><i class="fas fa-search mr-2"></i>Hints</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                    <?php if (empty($active_hints)): ?>
                        <div class="col-span-full opacity-60 italic text-sm">Er zijn momenteel geen actieve hints.</div>
                    <?php else: ?>
                        <?php foreach ($active_hints as $hid => $hint): ?>
                            <div class="theme-card rounded border shadow-sm p-4">
                                <h3 class="font-bold mb-2 truncate" title="<?php echo htmlspecialchars($hint['titel']); ?>"><?php echo htmlspecialchars($hint['titel']); ?></h3>
                                <div class="wb-zone flex flex-wrap gap-2 min-h-[60px]" id="zone_hint_<?php echo $hid; ?>" data-type="hint" data-ref="<?php echo $hid; ?>" ondrop="drop(event)" ondragover="allowDrop(event)">
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
            <div class="col-span-full mt-4">
                <h2 class="text-xl font-bold mb-4 border-b pb-2" style="border-color: var(--theme-card-border);"><i class="fas fa-tasks mr-2"></i>Opdrachten</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                    <?php if (empty($active_opdrachten)): ?>
                        <div class="col-span-full opacity-60 italic text-sm">Er zijn momenteel geen actieve opdrachten.</div>
                    <?php else: ?>
                        <?php foreach ($active_opdrachten as $oid => $opdracht): ?>
                            <div class="theme-card rounded border shadow-sm p-4">
                                <h3 class="font-bold mb-2 truncate" title="<?php echo htmlspecialchars($opdracht['titel']); ?>"><?php echo htmlspecialchars($opdracht['titel']); ?></h3>
                                <div class="wb-zone flex flex-wrap gap-2 min-h-[60px]" id="zone_opdracht_<?php echo $oid; ?>" data-type="opdracht" data-ref="<?php echo $oid; ?>" ondrop="drop(event)" ondragover="allowDrop(event)">
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

            <!-- Custom Categories -->
            <div class="col-span-full mt-4">
                <h2 class="text-xl font-bold mb-4 border-b pb-2" style="border-color: var(--theme-card-border);"><i class="fas fa-tags mr-2"></i>Overig</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                    <?php foreach ($categories as $cat): ?>
                        <div class="theme-card rounded border shadow-sm p-4 relative group" style="border-left: 4px solid <?php echo htmlspecialchars($cat['kleur']); ?>;">
                            <h3 class="font-bold mb-2 pr-8 truncate" title="<?php echo htmlspecialchars($cat['naam']); ?>"><i class="fas fa-tag mr-2" style="color: <?php echo htmlspecialchars($cat['kleur']); ?>;"></i><?php echo htmlspecialchars($cat['naam']); ?></h3>
                            <button onclick="delCategory(<?php echo $cat['id']; ?>)" class="absolute top-4 right-4 text-red-500 opacity-0 group-hover:opacity-100 transition"><i class="fas fa-trash"></i></button>
                            <div class="wb-zone flex flex-wrap gap-2 min-h-[60px]" id="zone_custom_<?php echo $cat['id']; ?>" data-type="custom" data-ref="<?php echo $cat['id']; ?>" ondrop="drop(event)" ondragover="allowDrop(event)">
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
                </div>
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

  <?php require_once('includes/footer.php') ?>
</div>

<script src="js/whiteboard.js"></script>

</body>
</html>
