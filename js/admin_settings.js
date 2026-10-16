/**
 * js/admin_settings.js — Delete confirmation modal handler for site settings.
 */

function confirmDelete(settingName) {
    const textEl = document.getElementById("deleteModalText");
    const confirmBtn = document.getElementById("confirmDeleteButton");
    const modal = document.getElementById("deleteModal");

    if (textEl) {
        textEl.innerHTML = `Weet je zeker dat je de instelling '<strong>${settingName}</strong>' wilt verwijderen?`;
    }
    if (confirmBtn) {
        confirmBtn.href = `settings_helper.php?delete_setting=${encodeURIComponent(settingName)}`;
    }
    if (modal) {
        modal.classList.remove("hidden");
    }
}

/**
 * Whiteboard allowed foxes toggle chips
 */
function toggleFoxSetting(foxName) {
    const input = document.getElementById("setting_WHITEBOARD_FOX_NAMES");
    if (!input) return;

    const raw = input.value.trim();
    let current = [];
    if (raw === "") {
        // If currently empty, all foxes are selected; clicking one excludes that fox
        document.querySelectorAll(".fox-toggle-btn").forEach((btn) => {
            const f = btn.getAttribute("data-fox");
            if (f) current.push(f);
        });
    } else {
        current = raw.split(",").map((s) => s.trim()).filter((s) => s.length > 0);
    }

    const lowerTarget = foxName.toLowerCase();
    const idx = current.findIndex((s) => s.toLowerCase() === lowerTarget);

    if (idx !== -1) {
        current.splice(idx, 1);
    } else {
        current.push(foxName);
    }

    input.value = current.join(", ");
    updateFoxToggleButtons();
}

function selectAllFoxes() {
    const input = document.getElementById("setting_WHITEBOARD_FOX_NAMES");
    if (input) {
        input.value = "";
        updateFoxToggleButtons();
    }
}

function updateFoxToggleButtons() {
    const input = document.getElementById("setting_WHITEBOARD_FOX_NAMES");
    if (!input) return;
    const raw = input.value.trim();
    const isAll = raw === "";
    const active = raw.split(",").map((s) => s.trim().toLowerCase()).filter((s) => s.length > 0);

    document.querySelectorAll(".fox-toggle-btn").forEach((btn) => {
        const fox = (btn.getAttribute("data-fox") || btn.textContent).trim().toLowerCase();
        if (isAll || active.includes(fox)) {
            btn.className = "fox-toggle-btn text-xs px-2.5 py-1 rounded-full border transition font-medium bg-blue-600 text-white border-blue-600";
        } else {
            btn.className = "fox-toggle-btn text-xs px-2.5 py-1 rounded-full border transition font-medium bg-black/5 dark:bg-white/5 opacity-50 border-gray-300 dark:border-gray-700";
        }
    });
}

document.addEventListener("DOMContentLoaded", () => {
    const input = document.getElementById("setting_WHITEBOARD_FOX_NAMES");
    if (input) {
        input.addEventListener("input", updateFoxToggleButtons);
    }
});
