<?php
// Displays continuous and periodic photo assignments with time limits, submission tracking, jury remarks, and team dispatch.
define("PAGE_NAME", "fotoopdrachten");
require_once('includes/auth.php');

$now = time();
$userId = (int)($_SESSION['id'] ?? 0);

// 1. Fetch Foto-opdrachten whiteboard team
$catId = 0;
$stmt_cat = $conn->prepare("SELECT id FROM Whiteboard_Categorieen WHERE naam = 'Foto-opdrachten' LIMIT 1");
if ($stmt_cat) {
    $stmt_cat->execute();
    $res = $stmt_cat->get_result();
    if ($r = $res->fetch_assoc()) {
        $catId = (int)$r['id'];
    }
    $stmt_cat->close();
}

$teamMembers = [];
$isUserInTeam = false;
if ($catId > 0) {
    $stmt_tm = $conn->prepare("
        SELECT g.id, g.voornaam, g.achternaam, g.profile_picture 
        FROM Toewijzingen t 
        JOIN Gebruikers g ON t.gebruiker_id = g.id 
        WHERE t.type = 'custom' AND t.referentie_id = ?
        ORDER BY g.voornaam ASC
    ");
    $stmt_tm->bind_param("i", $catId);
    $stmt_tm->execute();
    $res_tm = $stmt_tm->get_result();
    while ($m = $res_tm->fetch_assoc()) {
        $teamMembers[] = $m;
        if ((int)$m['id'] === $userId) {
            $isUserInTeam = true;
        }
    }
    $stmt_tm->close();
}

// 2. Fetch all Fotoopdrachten
$fotoopdrachten = [];
$counts = ['all' => 0, 'active' => 0, 'submitted' => 0, 'expired' => 0];

$stmt_fo = $conn->prepare("SELECT * FROM Fotoopdrachten ORDER BY start_at DESC, id DESC");
if ($stmt_fo) {
    $stmt_fo->execute();
    $res_fo = $stmt_fo->get_result();
    while ($row = $res_fo->fetch_assoc()) {
        $startTs = strtotime($row['start_at']);
        $endTs = strtotime($row['end_at']);

        $isSubmitted = !empty($row['ingestuurd_op']);
        $isGraded = ($row['toegekende_punten'] !== null);
        $isActive = ($now >= $startTs && $now <= $endTs);
        $isFuture = ($now < $startTs);
        $isExpired = ($now > $endTs);

        $statusKey = 'active';
        if ($isGraded) {
            $statusKey = 'graded';
        } elseif ($isSubmitted) {
            $statusKey = 'submitted';
        } elseif ($isExpired) {
            $statusKey = 'expired';
        } elseif ($isFuture) {
            $statusKey = 'future';
        }

        $row['start_ts'] = $startTs;
        $row['end_ts'] = $endTs;
        $row['is_active'] = $isActive;
        $row['is_future'] = $isFuture;
        $row['is_expired'] = $isExpired;
        $row['is_submitted'] = $isSubmitted;
        $row['is_graded'] = $isGraded;
        $row['status_key'] = $statusKey;

        // Count totals
        $counts['all']++;
        if ($isActive) $counts['active']++;
        if ($isSubmitted || $isGraded) $counts['submitted']++;
        if ($isExpired && !$isSubmitted) $counts['expired']++;

        // Fetch assigned users to this specific assignment
        $assignedUsers = [];
        $isAssignedToMe = false;
        $stmt_toew = $conn->prepare("
            SELECT g.id, g.voornaam, g.achternaam, g.profile_picture 
            FROM Toewijzingen t 
            JOIN Gebruikers g ON t.gebruiker_id = g.id 
            WHERE t.type = 'fotoopdracht' AND t.referentie_id = ?
            ORDER BY g.voornaam ASC
        ");
        if ($stmt_toew) {
            $stmt_toew->bind_param("i", $row['id']);
            $stmt_toew->execute();
            $res_toew = $stmt_toew->get_result();
            while ($u = $res_toew->fetch_assoc()) {
                $assignedUsers[] = $u;
                if ((int)$u['id'] === $userId) {
                    $isAssignedToMe = true;
                }
            }
            $stmt_toew->close();
        }
        $row['assigned_users'] = $assignedUsers;
        $row['is_assigned_to_me'] = $isAssignedToMe;

        $fotoopdrachten[] = $row;
    }
    $stmt_fo->close();
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <title>Jotify - Foto-opdrachten</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="shortcut icon" type="image/png" href="media/geusje.png" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://kit.fontawesome.com/870ab34ea3.js" crossorigin="anonymous"></script>
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <?php include_once('includes/theme.php'); ?>
</head>

<body class="flex h-screen overflow-hidden">
    <!-- Sidebar -->
    <?php include_once('includes/sidebar.php') ?>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col h-screen overflow-y-auto w-full relative">
        <!-- Topbar -->
        <?php include_once('includes/topbar.php') ?>

        <main class="p-4 md:p-6 max-w-[1400px] mx-auto w-full flex-1">
            
            <div class="space-y-6">
                
                <!-- Team Banner Card (Uniform theme-card styling) -->
                <div class="theme-card rounded-xl border shadow-sm p-4 sm:p-5 flex flex-col md:flex-row items-start md:items-center justify-between gap-4" style="border-left: 5px solid var(--theme-primary); border-color: var(--theme-card-border);">
                    <div class="flex items-center gap-3 sm:gap-4">
                        <div class="w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0 text-xl font-bold theme-bg-primary text-white shadow-sm">
                            <i class="fas fa-camera"></i>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h2 class="font-bold text-base sm:text-lg">Foto-opdrachten Team</h2>
                                <span id="foto-team-count" class="text-xs font-bold px-2.5 py-0.5 rounded-full border shadow-sm" style="background-color: var(--theme-bg); border-color: var(--theme-card-border); color: var(--theme-text);">
                                    <?= count($teamMembers) === 1 ? '1 lid' : count($teamMembers) . ' leden' ?>
                                </span>
                            </div>
                            <p class="text-xs opacity-75 mt-0.5" style="color: var(--theme-text);">Scouts en creatievelingen die werken aan de doorlopende foto-opdrachten.</p>
                            <div id="foto-team-avatars" class="flex -space-x-2 overflow-visible items-center mt-2">
                                <?php if (empty($teamMembers)): ?>
                                    <span class="text-xs italic" style="color: var(--theme-text); opacity: 0.7;">Nog niemand in het team</span>
                                <?php else: ?>
                                    <?php foreach ($teamMembers as $m): 
                                        $mName = htmlspecialchars(ucfirst($m['voornaam']) . ' ' . ucfirst($m['achternaam']));
                                        $safeName = htmlspecialchars(addslashes($mName));
                                    ?>
                                        <div class="inline-block flex-shrink-0 cursor-pointer" onmouseenter="showAvatarTooltip(event, this, '<?= $safeName ?>')" onmouseleave="hideAvatarTooltip()" onclick="showAvatarTooltip(event, this, '<?= $safeName ?>')">
                                            <?php if (!empty($m['profile_picture'])): ?>
                                                <img class="inline-block h-9 w-9 rounded-full ring-2 ring-white object-cover bg-white pointer-events-none" src="profile_image.php?hash=<?= urlencode($m['profile_picture']) ?>&res=low" alt="<?= $mName ?>" />
                                            <?php else: ?>
                                                <div class="inline-flex items-center justify-center h-9 w-9 rounded-full ring-2 ring-white bg-blue-500 text-white font-bold text-xs pointer-events-none">
                                                    <?= strtoupper(substr($m['voornaam'], 0, 1)) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <?php if ($privilege > 0): ?>
                    <div class="flex items-center gap-2 w-full md:w-auto justify-end">
                        <button id="btn-toggle-team" onclick="toggleFotoTeam()" class="text-sm font-bold <?= $isUserInTeam ? 'bg-red-100 text-red-700 hover:bg-red-200' : 'bg-blue-100 text-blue-700 hover:bg-blue-200' ?> px-4 py-2 rounded transition shadow-sm whitespace-nowrap">
                            <?= $isUserInTeam ? "<i class='fas fa-times mr-1'></i> Stop hiermee" : "<i class='fas fa-hand-paper mr-1'></i> Ga hiermee aan de slag" ?>
                        </button>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Filter & Search Toolbar -->
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <!-- Filter Tabs -->
                    <div class="flex flex-wrap gap-2">
                        <button onclick="setFotoFilter('all', this)" class="filter-tab-btn px-3.5 py-1.5 rounded-lg text-xs font-bold transition border theme-bg-primary text-white shadow-sm" style="border-color: var(--theme-card-border);">
                            Alle (<?= $counts['all'] ?>)
                        </button>
                        <button onclick="setFotoFilter('active', this)" class="filter-tab-btn px-3.5 py-1.5 rounded-lg text-xs font-bold transition border opacity-70 hover:opacity-100 flex items-center gap-1.5" style="border-color: var(--theme-card-border);">
                            <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span>
                            Actief (<?= $counts['active'] ?>)
                        </button>
                        <button onclick="setFotoFilter('submitted', this)" class="filter-tab-btn px-3.5 py-1.5 rounded-lg text-xs font-bold transition border opacity-70 hover:opacity-100 flex items-center gap-1.5" style="border-color: var(--theme-card-border);">
                            <i class="fas fa-check text-blue-500 text-xs"></i>
                            Ingezonden (<?= $counts['submitted'] ?>)
                        </button>
                        <button onclick="setFotoFilter('expired', this)" class="filter-tab-btn px-3.5 py-1.5 rounded-lg text-xs font-bold transition border opacity-70 hover:opacity-100 flex items-center gap-1.5" style="border-color: var(--theme-card-border);">
                            <i class="fas fa-clock opacity-60 text-xs"></i>
                            Gesloten (<?= $counts['expired'] ?>)
                        </button>
                    </div>

                    <!-- Search Input -->
                    <div class="relative w-full md:w-72">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 opacity-50 text-xs"></i>
                        <input type="text" id="foto-search-input" oninput="applyCardFilters()" placeholder="Zoek op titel of omschrijving..." class="w-full pl-9 pr-3 py-1.5 text-xs rounded-lg border bg-transparent focus:outline-none focus:ring-2 focus:ring-blue-500 transition" style="border-color: var(--theme-card-border);" />
                    </div>
                </div>

                <!-- Empty Filter Notice -->
                <div id="foto-empty-filter-notice" class="theme-card rounded-xl border shadow-sm p-8 text-center opacity-70 hidden">
                    <i class="fas fa-filter text-4xl mb-3 block opacity-50"></i>
                    <p class="font-bold text-base">Geen foto-opdrachten gevonden</p>
                    <p class="text-xs opacity-70 mt-1">Geen opdrachten komen overeen met het actieve filter of de zoekopdracht.</p>
                </div>

                <!-- Photo Assignments List -->
                <?php if (empty($fotoopdrachten)): ?>
                    <div class="theme-card rounded-xl border shadow-sm p-12 text-center opacity-70">
                        <div class="w-16 h-16 rounded-full bg-blue-500/10 text-blue-500 flex items-center justify-center mx-auto mb-4 text-2xl font-bold">
                            <i class="fas fa-camera"></i>
                        </div>
                        <h3 class="text-lg font-bold">Nog geen foto-opdrachten beschikbaar</h3>
                        <p class="text-sm opacity-70 mt-2 max-w-md mx-auto">
                            Zodra de Jotihunt organisatie nieuwe foto-opdrachten publiceert via de officiële API, verschijnen ze hier automatisch met actieve aftellers en instructies.
                        </p>
                    </div>
                <?php else: ?>
                    <div class="space-y-6">
                        <?php foreach ($fotoopdrachten as $item): 
                            $isAfgelopen = $item['is_expired'];
                            $statusTekst = $isAfgelopen ? "Afgelopen" : "Niet afgelopen";
                            $statusClass = $isAfgelopen ? "text-red-500" : "text-green-500";
                            $statusIcon = $isAfgelopen ? "fa-clock" : "fa-hourglass-half";

                            if ($item['is_graded']) {
                                $statusTekst = "Beoordeeld (+{$item['toegekende_punten']} pt)";
                                $statusClass = "text-green-500";
                                $statusIcon = "fa-star";
                            } elseif ($item['is_submitted']) {
                                $statusTekst = "Ingezonden";
                                $statusClass = "text-blue-500";
                                $statusIcon = "fa-paper-plane";
                            }

                            // Format description HTML with responsive images if any
                            $content = $item['omschrijving'];
                            $doc = new DOMDocument();
                            @$doc->loadHTML('<?xml encoding="utf-8" ?>' . $content);
                            $imgNodes = $doc->getElementsByTagName('img');
                            foreach ($imgNodes as $node) {
                                $existingClass = $node->getAttribute('class');
                                $node->setAttribute('class', trim($existingClass . ' max-w-full h-auto rounded-lg shadow-sm'));
                                $node->removeAttribute('width');
                                $node->removeAttribute('height');
                            }
                            $bodyNodes = $doc->getElementsByTagName('body');
                            $htmlContent = '';
                            if ($bodyNodes->length > 0) {
                                foreach ($bodyNodes->item(0)->childNodes as $child) {
                                    $htmlContent .= $doc->saveHTML($child);
                                }
                            } else {
                                $htmlContent = nl2br(htmlspecialchars($content));
                            }

                            // Format avatars
                            $avatars_html = "";
                            if (!empty($item['assigned_users'])) {
                                foreach ($item['assigned_users'] as $u) {
                                    $volledige_naam = htmlspecialchars(ucfirst($u['voornaam']) . ' ' . ucfirst($u['achternaam']));
                                    $avatar_content = '';
                                    if (!empty($u['profile_picture'])) {
                                        $avatar_content = '<img class="inline-block h-10 w-10 rounded-full ring-2 ring-white object-cover bg-white pointer-events-none" src="profile_image.php?hash=' . urlencode($u['profile_picture']) . '&res=low" alt="' . $volledige_naam . '"/>';
                                    } else {
                                        $initial = strtoupper(substr($u['voornaam'], 0, 1));
                                        $avatar_content = '<div class="inline-flex items-center justify-center h-10 w-10 rounded-full ring-2 ring-white bg-blue-500 text-white font-bold text-xs pointer-events-none">' . $initial . '</div>';
                                    }
                                    $safe_naam = htmlspecialchars(addslashes($volledige_naam));
                                    $avatars_html .= '<div class="inline-block flex-shrink-0 cursor-pointer" onmouseenter="showAvatarTooltip(event, this, \'' . $safe_naam . '\')" onmouseleave="hideAvatarTooltip()" onclick="showAvatarTooltip(event, this, \'' . $safe_naam . '\')">' . $avatar_content . '</div>';
                                }
                            } else {
                                $avatars_html = "<span class='text-xs opacity-50 italic mr-2'>Nog niemand toegewezen</span>";
                            }

                            $btn_class = $item['is_assigned_to_me'] ? "bg-red-100 text-red-700 hover:bg-red-200" : "bg-blue-100 text-blue-700 hover:bg-blue-200";
                            $btn_text = $item['is_assigned_to_me'] ? "<i class='fas fa-times mr-1'></i> Stop hiermee" : "<i class='fas fa-hand-paper mr-1'></i> Ga hiermee aan de slag";
                        ?>
                            <article id="opdracht-<?= $item['id'] ?>" class="foto-card theme-card rounded-xl border shadow-sm overflow-hidden mb-6" 
                                     data-status="<?= $item['status_key'] ?>" 
                                     data-title="<?= htmlspecialchars($item['titel']) ?>" 
                                     data-desc="<?= htmlspecialchars(strip_tags($item['omschrijving'])) ?>">
                                
                                <!-- Header (Matches opdrachten.php and nieuws.php exactly) -->
                                <header class="theme-card-header px-6 py-4 border-b text-white flex flex-col md:flex-row md:justify-between md:items-center gap-2" style="background-color: var(--theme-sidebar-active); border-color: var(--theme-card-border);">
                                    <div>
                                        <h3 class="text-xl font-bold"><?= htmlspecialchars($item['titel']) ?></h3>
                                        <div class="flex items-center gap-2 mt-1">
                                            <span class="countdown-timer inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-mono font-bold bg-white/15 text-white border border-white/20" 
                                                  data-start-ts="<?= $item['start_ts'] ?>" 
                                                  data-end-ts="<?= $item['end_ts'] ?>" 
                                                  data-submitted="<?= $item['is_submitted'] ? '1' : '0' ?>">
                                            </span>
                                        </div>
                                    </div>
                                    <div class="text-sm font-medium opacity-80 md:text-right">
                                        <span><?= time2str($item['start_at']) ?></span><br>
                                        <span class="<?= $statusClass ?> font-bold"><i class="fas <?= $statusIcon ?> mr-1"></i><?= $statusTekst ?></span>
                                    </div>
                                </header>

                                <!-- Content (Matches opdrachten.php exactly) -->
                                <div class="p-6 prose max-w-none prose-img:rounded-xl prose-a:text-blue-600 hover:prose-a:text-blue-500 mb-4">
                                    <?= $htmlContent ?>
                                </div>

                                <!-- Jury Feedback / Evaluation Box (Adapted for all themes) -->
                                <?php if ($item['is_graded'] || !empty($item['opmerkingen'])): ?>
                                    <div class="mx-6 mb-4 p-4 rounded-xl border flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-sm" style="background-color: rgba(16, 185, 129, 0.08); border-color: rgba(16, 185, 129, 0.35); color: var(--theme-text);">
                                        <div class="flex items-start gap-3">
                                            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 text-base font-bold bg-green-500 text-white shadow-sm">
                                                <i class="fas fa-trophy"></i>
                                            </div>
                                            <div>
                                                <div class="flex items-center gap-2">
                                                    <h4 class="font-bold text-sm text-green-600 dark:text-green-400">
                                                        Jurybeoordeling: <?= (int)$item['toegekende_punten'] ?> / <?= (int)$item['max_punten'] ?> punten
                                                    </h4>
                                                    <?php if (!empty($item['ingestuurd_op'])): ?>
                                                        <span class="text-xs opacity-60">(Ingezonden: <?= date('H:i', strtotime($item['ingestuurd_op'])) ?>)</span>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if (!empty($item['opmerkingen'])): ?>
                                                    <p class="text-xs opacity-90 mt-1 italic">
                                                        <i class="fas fa-comment-dots mr-1 opacity-70"></i>
                                                        &ldquo;<?= htmlspecialchars($item['opmerkingen']) ?>&rdquo;
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php elseif ($item['is_submitted']): ?>
                                    <div class="mx-6 mb-4 p-3.5 rounded-xl border flex items-center gap-3 shadow-sm" style="background-color: rgba(59, 130, 246, 0.08); border-color: rgba(59, 130, 246, 0.35); color: var(--theme-text);">
                                        <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 text-sm bg-blue-500 text-white shadow-sm">
                                            <i class="fas fa-paper-plane"></i>
                                        </div>
                                        <div class="text-xs">
                                            <span class="font-bold text-blue-600 dark:text-blue-400">Ingezonden</span>
                                            <span class="opacity-70 ml-1">op <?= time2str($item['ingestuurd_op']) ?>. In afwachting van jurering en punten.</span>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Toewijzingen Row (Identical to opdrachten.php) -->
                                <div class="px-6 pb-4 flex items-center justify-between border-t pt-4" style="border-color: var(--theme-card-border);">
                                    <div id="toewijzingen-avatars-fotoopdracht-<?= $item['id'] ?>" class="flex -space-x-2 overflow-visible items-center p-1">
                                        <?= $avatars_html ?>
                                    </div>
                                    <?php if ($privilege > 0): ?>
                                        <button id="toewijzingen-btn-fotoopdracht-<?= $item['id'] ?>" onclick="toggleToewijzing('fotoopdracht', <?= $item['id'] ?>)" class="text-sm font-bold <?= $btn_class ?> px-3 py-1.5 rounded transition shadow-sm whitespace-nowrap ml-4">
                                            <?= $btn_text ?>
                                        </button>
                                    <?php endif; ?>
                                </div>

                                <!-- Footer (Identical to opdrachten.php) -->
                                <footer class="bg-black/5 p-4 flex flex-col sm:flex-row items-center justify-between gap-4 border-t" style="border-color: var(--theme-card-border);">
                                    <div class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
                                        <button class="theme-bg-primary text-white font-bold py-2 px-6 rounded shadow-sm hover:opacity-90 transition w-full sm:w-auto flex items-center justify-center" onclick="window.open('https://jotihunt.nl', '_blank');">
                                            <i class="fas fa-paper-plane mr-2"></i>Lever in!
                                        </button>

                                        <?php if ($privilege > 0 && !$item['is_submitted'] && !$item['is_graded']): ?>
                                            <button onclick="openSubmitModal(<?= $item['id'] ?>, '<?= htmlspecialchars(addslashes($item['titel'])) ?>')" class="px-3.5 py-2 rounded text-xs font-bold border transition hover:bg-black/5 flex items-center justify-center w-full sm:w-auto" style="border-color: var(--theme-card-border);" title="Optioneel handmatig markeren als ingezonden">
                                                <i class="fas fa-check-circle mr-1.5 text-blue-500"></i>Markeer als ingeleverd
                                            </button>
                                        <?php endif; ?>
                                    </div>

                                    <div class="text-sm text-center sm:text-right opacity-80 font-medium">
                                        <p>Max punten: <span class="font-bold"><?= $item['max_punten'] ?></span></p>
                                        <p>Eind tijd: <span class="font-bold"><?= time2str($item['end_at']) ?></span></p>              
                                    </div>
                                </footer>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            </div>
        </main>

        <?php require_once('includes/footer.php') ?>
    </div>

    <!-- Modal: Handmatig Markeren als Ingezonden -->
    <div id="modal-mark-submitted" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="theme-card max-w-md w-full rounded-2xl border shadow-2xl p-6 relative" style="border-color: var(--theme-card-border);">
            <button onclick="closeSubmitModal()" class="absolute top-4 right-4 opacity-50 hover:opacity-100 text-lg">
                <i class="fas fa-times"></i>
            </button>
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-xl bg-blue-500/10 text-blue-600 dark:text-blue-400 flex items-center justify-center text-xl font-bold">
                    <i class="fas fa-paper-plane"></i>
                </div>
                <div>
                    <h3 class="font-bold text-lg">Inzending Bevestigen</h3>
                    <p class="text-xs opacity-70">Markeer foto-opdracht als ingeleverd</p>
                </div>
            </div>
            <p class="text-sm mb-4">
                Heb je de foto voor <strong id="submit-modal-title" class="theme-primary"></strong> ingeleverd op het Jotihunt-portaal?
            </p>
            <p class="text-xs opacity-70 mb-6">
                Zodra je bevestigt, wordt dit geregistreerd in Jotify en ontvangt het team een push-notificatie. Zodra de jury punten toekent, haalt de scraper deze automatisch op.
            </p>
            <div class="flex items-center justify-end gap-3">
                <button onclick="closeSubmitModal()" class="px-4 py-2 rounded-lg text-xs font-semibold border hover:bg-black/5 transition" style="border-color: var(--theme-card-border);">
                    Annuleren
                </button>
                <button id="btn-confirm-submit" onclick="confirmMarkSubmitted()" class="px-4 py-2 rounded-lg text-xs font-bold theme-bg-primary text-white hover:opacity-90 transition shadow-sm">
                    Bevestig Inzending
                </button>
            </div>
        </div>
    </div>

    <script src="js/gps.js"></script>
    <script src="js/assignments.js"></script>
    <script src="js/fotoopdrachten.js"></script>
    <script>
        initAssignments(<?= (int)$userId ?>);
    </script>
</body>
</html>
