<?php
// Displays game assignments and challenges with their time limits, completion statuses, and formatted instructions.
define("PAGE_NAME", "opdrachten");

require_once('includes/auth.php');

?>

<!DOCTYPE html>
<html lang="nl">

<head>
    <title>Jotify - Opdrachten</title>
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
                <?php
                // Get assignments
                $stmt = $conn->prepare("SELECT * FROM Opdrachten ORDER BY datum DESC");
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    echo '<div class="space-y-6">';
                    while ($row = $result->fetch_assoc()) {
                        $isAfgelopen = (strtotime($row['eindtijd']) < strtotime(date('Y-m-d H:i:s')));
                        $statusTekst = $isAfgelopen ? "Afgelopen" : "Niet afgelopen";
                        $statusClass = $isAfgelopen ? "text-red-500" : "text-green-500";
                        $statusIcon = $isAfgelopen ? "fa-clock" : "fa-hourglass-half";

                        // create new html object in PHP
                        $content = $row['inhoud'];
                        $doc = new DOMDocument();
                        @$doc->loadHTML('<?xml encoding="utf-8" ?>' . $content);
                        $imgNodes = $doc->getElementsByTagName('img');
                        foreach ($imgNodes as $node) {
                            $existingClass = $node->getAttribute('class');
                            $node->setAttribute('class', trim($existingClass . ' max-w-full h-auto rounded-lg shadow-sm'));
                            $node->removeAttribute('width');
                            $node->removeAttribute('height');
                        }

                        // Get inner HTML of the loaded doc, skipping html and body tags
                        $bodyNodes = $doc->getElementsByTagName('body');
                        $htmlContent = '';
                        if ($bodyNodes->length > 0) {
                            foreach ($bodyNodes->item(0)->childNodes as $child) {
                                $htmlContent .= $doc->saveHTML($child);
                            }
                        } else {
                            $htmlContent = $content;
                        }

                        echo '
          <article class="theme-card rounded border shadow-sm overflow-hidden">
            <header class="theme-card-header px-6 py-4 border-b text-white flex flex-col md:flex-row md:justify-between md:items-center gap-2" style="background-color: var(--theme-sidebar-active); border-color: var(--theme-card-border);">
              <h3 class="text-xl font-bold">' . $row['titel'] . '</h3>
              <div class="text-sm font-medium opacity-80 md:text-right">
                <span>' . time2str($row['datum']) . '</span><br>
                <span class="' . $statusClass . ' font-bold"><i class="fas ' . $statusIcon . ' mr-1"></i>' . $statusTekst . '</span>
              </div>
            </header>
            
            <div class="p-6 prose max-w-none prose-img:rounded-xl prose-a:text-blue-600 hover:prose-a:text-blue-500 mb-4">
              ' . $htmlContent . '
            </div>';

                        $stmt_toew = $conn->prepare("SELECT g.id, g.voornaam, g.achternaam, g.profile_picture FROM Toewijzingen t JOIN Gebruikers g ON t.gebruiker_id = g.id WHERE t.type = 'opdracht' AND t.referentie_id = ?");
                        $stmt_toew->bind_param("i", $row['id']);
                        $stmt_toew->execute();
                        $res_toew = $stmt_toew->get_result();

                        $is_assigned = false;
                        $avatars_html = "";
                        if ($res_toew->num_rows > 0) {
                            while ($t_row = $res_toew->fetch_assoc()) {
                                if ($t_row['id'] == $_SESSION['id'])
                                    $is_assigned = true;

                                $volledige_naam = htmlspecialchars(ucfirst($t_row['voornaam']) . ' ' . ucfirst($t_row['achternaam']));
                                $avatar_content = '';
                                if ($t_row['profile_picture']) {
                                    $avatar_content = '<img class="inline-block h-10 w-10 rounded-full ring-2 ring-white object-cover bg-white pointer-events-none" src="profile_image.php?hash=' . urlencode($t_row['profile_picture']) . '&res=low" alt="' . $volledige_naam . '"/>';
                                } else {
                                    $initial = strtoupper(substr($t_row['voornaam'], 0, 1));
                                    $avatar_content = '<div class="inline-flex items-center justify-center h-10 w-10 rounded-full ring-2 ring-white bg-blue-500 text-white font-bold text-xs pointer-events-none">' . $initial . '</div>';
                                }
                                $safe_naam = htmlspecialchars(addslashes($volledige_naam));
                                $avatars_html .= '<div class="inline-block flex-shrink-0 cursor-pointer" onmouseenter="showAvatarTooltip(event, this, \'' . $safe_naam . '\')" onmouseleave="hideAvatarTooltip()" onclick="showAvatarTooltip(event, this, \'' . $safe_naam . '\')">' . $avatar_content . '</div>';
                            }
                        } else {
                            $avatars_html = "<span class='text-xs opacity-50 italic mr-2'>Nog niemand toegewezen</span>";
                        }
                        $stmt_toew->close();

                        $btn_class = $is_assigned ? "bg-red-100 text-red-700 hover:bg-red-200" : "bg-blue-100 text-blue-700 hover:bg-blue-200";
                        $btn_text = $is_assigned ? "<i class='fas fa-times mr-1'></i> Stop hiermee" : "<i class='fas fa-hand-paper mr-1'></i> Ga hiermee aan de slag";
                        echo '<div class="px-6 pb-4 flex items-center justify-between border-t pt-4" style="border-color: var(--theme-card-border);">
                <div id="toewijzingen-avatars-opdracht-' . $row['id'] . '" class="flex -space-x-2 overflow-visible items-center p-1">
                    ' . $avatars_html . '
                </div>';
                        if ($privilege > 0) {
                            echo '<button id="toewijzingen-btn-opdracht-' . $row['id'] . '" onclick="toggleToewijzing(\'opdracht\', ' . $row['id'] . ')" class="text-sm font-bold ' . $btn_class . ' px-3 py-1.5 rounded transition shadow-sm whitespace-nowrap ml-4">
                    ' . $btn_text . '
                </button>';
                        }
                        echo '
            </div>
            
            <footer class="bg-black/5 p-4 flex flex-col sm:flex-row items-center justify-between gap-4 border-t" style="border-color: var(--theme-card-border);">
              <button class="theme-bg-primary text-white font-bold py-2 px-6 rounded shadow-sm hover:opacity-90 transition w-full sm:w-auto flex items-center justify-center" onclick="window.location.href = \'https://jotihunt.nl/article/' . $row['id'] . '\';">
                <i class="fas fa-paper-plane mr-2"></i>Lever in!
              </button>
              
              <div class="text-sm text-center sm:text-right opacity-80 font-medium">
                <p>Max punten: <span class="font-bold">' . $row['maxpunten'] . '</span></p>
                <p>Eind tijd: <span class="font-bold">' . time2str($row['eindtijd']) . '</span></p>              
              </div>
            </footer>
          </article>';
                    }
                    echo '</div>';
                } else {
                    echo '<div class="theme-card rounded border shadow-sm p-6 text-center opacity-70">
                <i class="far fa-folder-open text-4xl mb-3 block"></i>
                <p>Geen opdrachten gevonden.</p>
              </div>';
                }
                $stmt->close();
                ?>
            </div>
        </main>

        <?php require_once('includes/footer.php') ?>
    </div>

    <script src="js/gps.js"></script>
<script>initGpsTracking('<?php echo $_SESSION['gps'] ?? 'false'; ?>');</script>

    <script>
        const currentUserId = <?= isset($_SESSION['id']) ? $_SESSION['id'] : 0 ?>;

        function updateAssignmentUI(type, id, users) {
            const avatarContainer = document.getElementById(`toewijzingen-avatars-${type}-${id}`);
            const btn = document.getElementById(`toewijzingen-btn-${type}-${id}`);
            if (!avatarContainer || !btn) return;

            let isAssigned = false;
            let avatarsHtml = '';

            if (users && users.length > 0) {
                users.forEach(u => {
                    if (u.id == currentUserId) isAssigned = true;
                    const fullName = u.voornaam.charAt(0).toUpperCase() + u.voornaam.slice(1) + ' ' + u.achternaam.charAt(0).toUpperCase() + u.achternaam.slice(1);
                    let avatarContent = '';
                    if (u.profile_picture) {
                        avatarContent = `<img class="inline-block h-10 w-10 rounded-full ring-2 ring-white object-cover bg-white pointer-events-none" src="profile_image.php?hash=${encodeURIComponent(u.profile_picture)}&res=low" alt="${fullName}"/>`;
                    } else {
                        const initial = u.voornaam.charAt(0).toUpperCase();
                        avatarContent = `<div class="inline-flex items-center justify-center h-10 w-10 rounded-full ring-2 ring-white bg-blue-500 text-white font-bold text-xs pointer-events-none">${initial}</div>`;
                    }
                    const safeName = fullName.replace(/'/g, "\\'");
                    avatarsHtml += `<div class="inline-block flex-shrink-0 cursor-pointer" onmouseenter="showAvatarTooltip(event, this, '${safeName}')" onmouseleave="hideAvatarTooltip()" onclick="showAvatarTooltip(event, this, '${safeName}')">${avatarContent}</div>`;
                });
            } else {
                avatarsHtml = `<span class='text-xs opacity-50 italic mr-2'>Nog niemand toegewezen</span>`;
            }

            avatarContainer.innerHTML = avatarsHtml;

            if (isAssigned) {
                btn.className = `text-sm font-bold bg-red-100 text-red-700 hover:bg-red-200 px-3 py-1.5 rounded transition shadow-sm whitespace-nowrap ml-4`;
                btn.innerHTML = `<i class='fas fa-times mr-1'></i> Stop hiermee`;
            } else {
                btn.className = `text-sm font-bold bg-blue-100 text-blue-700 hover:bg-blue-200 px-3 py-1.5 rounded transition shadow-sm whitespace-nowrap ml-4`;
                btn.innerHTML = `<i class='fas fa-hand-paper mr-1'></i> Ga hiermee aan de slag`;
            }
        }

        function toggleToewijzing(type, id, force = false) {
            const formData = new FormData();
            formData.append('toggle_toewijzing', '1');
            formData.append('type', type);
            formData.append('referentie_id', id);
            if (force) formData.append('force', '1');

            fetch('functies.php', {
                method: 'POST',
                body: formData
            }).then(res => res.json()).then(data => {
                if (data.status === 'conflict') {
                    showConflictModal(data.conflict_name, data.target_name, () => toggleToewijzing(type, id, true));
                } else if (data.status === 'unassigned' || data.status === 'assigned') {
                    updateAssignmentUI(data.target_type, data.target_id, data.users);
                    if (data.unassigned_type && data.unassigned_id) {
                        updateAssignmentUI(data.unassigned_type, data.unassigned_id, data.unassigned_users);
                    }
                }
            }).catch(e => {
                location.reload();
            });
        }

        function showConflictModal(conflictName, targetName, confirmCallback) {
            const overlay = document.createElement('div');
            overlay.className = 'fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4 backdrop-blur-sm';
            overlay.innerHTML = `
        <div class="theme-card rounded-xl max-w-md w-full p-6 shadow-2xl border" style="border-color: var(--theme-card-border); background-color: var(--theme-bg);">
            <div class="flex items-center gap-4 mb-4">
                <div class="h-12 w-12 rounded-full bg-orange-100 text-orange-500 flex items-center justify-center text-xl shrink-0">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold theme-text">Taak Wisselen?</h3>
                    <p class="text-sm opacity-80 theme-text mt-1">Je bent al met iets anders bezig.</p>
                </div>
            </div>
            
            <p class="theme-text mb-6">
                Je bent momenteel toegewezen aan <strong>${conflictName}</strong>.<br><br>
                Wil je hiermee stoppen en overschakelen naar <strong>${targetName}</strong>?
            </p>
            
            <div class="flex gap-3 justify-end">
                <button id="modal-cancel" class="px-4 py-2 rounded font-bold transition theme-text" style="background: rgba(128,128,128,0.2);">Annuleer</button>
                <button id="modal-confirm" class="px-4 py-2 rounded font-bold theme-bg-primary text-white hover:opacity-90 transition">Ja, wissel taak</button>
            </div>
        </div>
    `;
            document.body.appendChild(overlay);

            document.getElementById('modal-cancel').onclick = () => {
                document.body.removeChild(overlay);
            };
            document.getElementById('modal-confirm').onclick = () => {
                document.body.removeChild(overlay);
                confirmCallback();
            };
        }

        let customTooltip = null;

        function showAvatarTooltip(e, el, text) {
            if (e) e.stopPropagation();
            if (!customTooltip) {
                customTooltip = document.createElement('div');
                customTooltip.className = 'fixed z-[9999] px-3 py-1.5 text-xs font-bold rounded shadow-lg whitespace-nowrap theme-bg-primary text-white border pointer-events-none transition-opacity duration-200 opacity-0';
                customTooltip.style.borderColor = 'var(--theme-card-border)';
                document.body.appendChild(customTooltip);
            }
            customTooltip.innerHTML = text;
            
            // Temporarily make it visible to get dimensions, but keep opacity 0
            customTooltip.style.top = '0px';
            customTooltip.style.left = '0px';
            customTooltip.style.display = 'block';
            
            const rect = el.getBoundingClientRect();
            const tooltipRect = customTooltip.getBoundingClientRect();
            
            let top = rect.top - tooltipRect.height - 8;
            let left = rect.left + (rect.width / 2) - (tooltipRect.width / 2);
            
            if (left < 8) left = 8;
            if (left + tooltipRect.width > window.innerWidth - 8) {
                left = window.innerWidth - tooltipRect.width - 8;
            }
            if (top < 8) top = rect.bottom + 8;
            
            customTooltip.style.top = top + 'px';
            customTooltip.style.left = left + 'px';
            customTooltip.style.opacity = '1';
        }

        function hideAvatarTooltip() {
            if (customTooltip) {
                customTooltip.style.opacity = '0';
                setTimeout(() => {
                    if (customTooltip && customTooltip.style.opacity === '0') {
                        customTooltip.style.top = '-9999px';
                        customTooltip.style.display = 'none';
                    }
                }, 200);
            }
        }

        document.addEventListener('click', hideAvatarTooltip);
        window.addEventListener('scroll', hideAvatarTooltip, { capture: true, passive: true });
    </script>

</body>

</html>
