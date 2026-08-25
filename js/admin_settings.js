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
