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
    openModal(modal);
}

function showDeleteModal(id, naam) {
    const delId = document.getElementById("deleteAccountId");
    const delName = document.getElementById("deleteAccountName");
    if (delId) delId.value = id;
    if (delName) delName.textContent = naam;
    const modal = document.getElementById("deleteModal");
    openModal(modal);
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

function openCreateModal() {
    const title = document.getElementById("modalTitle");
    if (title) title.textContent = "Nieuw Service Account";
    const act = document.getElementById("formAction");
    if (act) act.value = "create";
    const accId = document.getElementById("formAccountId");
    if (accId) accId.value = "";
    const naam = document.getElementById("formNaam");
    if (naam) naam.value = "";
    const doel = document.getElementById("formDoel");
    if (doel) doel.value = "home";
    const rechten = document.getElementById("formRechten");
    if (rechten) rechten.value = "0";
    const ip = document.getElementById("formIp");
    if (ip) ip.value = "";
    const refresh = document.getElementById("formRefresh");
    if (refresh) refresh.value = "0";
    const modal = document.getElementById("createModal");
    openModal(modal);
}

function editAccount(account) {
    const title = document.getElementById("modalTitle");
    if (title) title.textContent = "Service Account Bewerken";
    const act = document.getElementById("formAction");
    if (act) act.value = "edit";
    const accId = document.getElementById("formAccountId");
    if (accId) accId.value = account.id;
    const naam = document.getElementById("formNaam");
    if (naam) naam.value = account.naam;
    const doel = document.getElementById("formDoel");
    if (doel) doel.value = account.doel_pagina;
    const rechten = document.getElementById("formRechten");
    if (rechten) rechten.value = account.rechten;
    const ip = document.getElementById("formIp");
    if (ip) ip.value = account.ip_whitelist || "";
    const refresh = document.getElementById("formRefresh");
    if (refresh) refresh.value = account.refresh_interval || 0;
    const modal = document.getElementById("createModal");
    openModal(modal);
}

function closeEditModal() {
    const modal = document.getElementById("createModal");
    closeModal(modal);
}
