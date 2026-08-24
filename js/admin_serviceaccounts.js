/**
 * js/admin_serviceaccounts.js ??? Kiosk token viewer, copy-to-clipboard, and account modal manager.
 */

function showToken(token, id) {
    const fullUrl = `${window.location.origin}/kiosk.php?auth=${token}`;
    const display = document.getElementById("tokenDisplay");
    const regenId = document.getElementById("regenAccountId");
    if (display) display.value = fullUrl;
    if (regenId) regenId.value = id;
    const modal = document.getElementById("tokenModal");
    if (modal) modal.classList.remove("hidden");
}

function showDeleteModal(id, naam) {
    const delId = document.getElementById("deleteAccountId");
    const delName = document.getElementById("deleteAccountName");
    if (delId) delId.value = id;
    if (delName) delName.textContent = naam;
    const modal = document.getElementById("deleteModal");
    if (modal) modal.classList.remove("hidden");
}

function copyToken() {
    const tokenInput = document.getElementById("tokenDisplay");
    if (!tokenInput) return;
    tokenInput.select();
    tokenInput.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(tokenInput.value).then(() => {
        const copyBtn = document.getElementById("copyBtn");
        if (copyBtn) {
            const orig = copyBtn.innerHTML;
            copyBtn.innerHTML = '<i class="fas fa-check mr-2"></i>Gekopieerd!';
            setTimeout(() => {
                copyBtn.innerHTML = orig;
            }, 2000);
        }
    });
}

function editAccount(account) {
    document.getElementById("edit_account_id").value = account.id;
    document.getElementById("edit_naam").value = account.naam;
    document.getElementById("edit_doel_pagina").value = account.doel_pagina;
    document.getElementById("edit_rechten").value = account.rechten;
    document.getElementById("edit_ip_whitelist").value = account.ip_whitelist || "";
    document.getElementById("edit_refresh_interval").value = account.refresh_interval || 0;
    document.getElementById("editModal").classList.remove("hidden");
}

function closeEditModal() {
    const modal = document.getElementById("editModal");
    if (modal) modal.classList.add("hidden");
}
