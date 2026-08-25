/**
 * js/assignments.js ??? Real-time assignment claiming, conflict resolution modal, and avatar tooltips.
 */

let customTooltip = null;
let currentAssignedUserId = null;

/**
 * Initialize assignment tracking.
 * @param {number} userId
 */
function initAssignments(userId) {
    currentAssignedUserId = parseInt(userId, 10);
    document.addEventListener("click", hideAvatarTooltip);
    window.addEventListener("scroll", hideAvatarTooltip, { capture: true, passive: true });
}

/**
 * Update DOM elements for a specific hint or opdracht card.
 * @param {string} type
 * @param {string|number} id
 * @param {Array} users
 */
function updateAssignmentUI(type, id, users) {
    const avatarContainer = document.getElementById(`toewijzingen-avatars-${type}-${id}`);
    const btn = document.getElementById(`toewijzingen-btn-${type}-${id}`);
    if (!avatarContainer || !btn) return;

    let isAssigned = false;
    let avatarsHtml = "";

    if (users && users.length > 0) {
        users.forEach((u) => {
            if (parseInt(u.id, 10) === currentAssignedUserId) {
                isAssigned = true;
            }
            const fullName = `${u.voornaam.charAt(0).toUpperCase() + u.voornaam.slice(1)} ${u.achternaam.charAt(0).toUpperCase() + u.achternaam.slice(1)}`;
            let avatarContent = "";
            if (u.profile_picture) {
                avatarContent = `<img class="inline-block h-10 w-10 rounded-full ring-2 ring-white object-cover bg-white pointer-events-none" src="profile_image.php?hash=${encodeURIComponent(u.profile_picture)}&res=low" alt="${fullName}"/>`;
            } else {
                const initial = u.voornaam.charAt(0).toUpperCase();
                avatarContent = `<div class="inline-flex items-center justify-center h-10 w-10 rounded-full ring-2 ring-white bg-blue-500 text-white font-bold text-xs pointer-events-none">${initial}</div>`;
            }
            const safeName = fullName.replace(/'/g, "\'");
            avatarsHtml += `<div class="inline-block flex-shrink-0 cursor-pointer" onmouseenter="showAvatarTooltip(event, this, '${safeName}')" onmouseleave="hideAvatarTooltip()" onclick="showAvatarTooltip(event, this, '${safeName}')">${avatarContent}</div>`;
        });
    } else {
        avatarsHtml = "<span class='text-xs opacity-50 italic mr-2'>Nog niemand toegewezen</span>";
    }

    avatarContainer.innerHTML = avatarsHtml;

    if (isAssigned) {
        btn.className = "text-sm font-bold bg-red-100 text-red-700 hover:bg-red-200 px-3 py-1.5 rounded transition shadow-sm whitespace-nowrap ml-4";
        btn.innerHTML = "<i class='fas fa-times mr-1'></i> Stop hiermee";
    } else {
        btn.className = "text-sm font-bold bg-blue-100 text-blue-700 hover:bg-blue-200 px-3 py-1.5 rounded transition shadow-sm whitespace-nowrap ml-4";
        btn.innerHTML = "<i class='fas fa-hand-paper mr-1'></i> Ga hiermee aan de slag";
    }
}

/**
 * Toggle task assignment for the current user.
 * @param {string} type
 * @param {string|number} id
 * @param {boolean} force
 */
function toggleToewijzing(type, id, force = false) {
    const formData = new FormData();
    formData.append("toggle_toewijzing", "1");
    formData.append("type", type);
    formData.append("referentie_id", id);
    if (force) formData.append("force", "1");

    fetch("functies.php", {
        method: "POST",
        body: formData
    })
        .then((res) => res.json())
        .then((data) => {
            if (data.status === "conflict") {
                showConflictModal(data.conflict_name, data.target_name, () => toggleToewijzing(type, id, true));
            } else if (data.status === "unassigned" || data.status === "assigned") {
                updateAssignmentUI(data.target_type, data.target_id, data.users);
                if (data.unassigned_type && data.unassigned_id) {
                    updateAssignmentUI(data.unassigned_type, data.unassigned_id, data.unassigned_users);
                }
            }
        })
        .catch(() => {
            location.reload();
        });
}

/**
 * Display conflict confirmation modal when switching active tasks.
 * @param {string} conflictName
 * @param {string} targetName
 * @param {Function} confirmCallback
 */
function showConflictModal(conflictName, targetName, confirmCallback) {
    const overlay = document.createElement("div");
    overlay.className = "fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4 backdrop-blur-sm";
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

    document.getElementById("modal-cancel").onclick = () => {
        document.body.removeChild(overlay);
    };
    document.getElementById("modal-confirm").onclick = () => {
        document.body.removeChild(overlay);
        confirmCallback();
    };
}

/**
 * Display avatar user hover tooltip.
 * @param {Event} e
 * @param {HTMLElement} el
 * @param {string} text
 */
function showAvatarTooltip(e, el, text) {
    if (e) e.stopPropagation();
    if (!customTooltip) {
        customTooltip = document.createElement("div");
        customTooltip.className = "fixed z-[9999] px-3 py-1.5 text-xs font-bold rounded shadow-lg whitespace-nowrap theme-bg-primary text-white border pointer-events-none transition-opacity duration-200 opacity-0";
        customTooltip.style.borderColor = "var(--theme-card-border)";
        document.body.appendChild(customTooltip);
    }
    customTooltip.innerHTML = text;
    customTooltip.style.top = "0px";
    customTooltip.style.left = "0px";
    customTooltip.style.display = "block";

    const rect = el.getBoundingClientRect();
    const tooltipRect = customTooltip.getBoundingClientRect();

    let top = rect.top - tooltipRect.height - 8;
    let left = rect.left + rect.width / 2 - tooltipRect.width / 2;

    if (left < 8) left = 8;
    if (left + tooltipRect.width > window.innerWidth - 8) {
        left = window.innerWidth - tooltipRect.width - 8;
    }
    if (top < 8) top = rect.bottom + 8;

    customTooltip.style.top = `${top}px`;
    customTooltip.style.left = `${left}px`;
    customTooltip.style.opacity = "1";
}

/**
 * Hide active avatar user hover tooltip.
 */
function hideAvatarTooltip() {
    if (customTooltip) {
        customTooltip.style.opacity = "0";
        setTimeout(() => {
            if (customTooltip && customTooltip.style.opacity === "0") {
                customTooltip.style.top = "-9999px";
                customTooltip.style.display = "none";
            }
        }, 200);
    }
}
