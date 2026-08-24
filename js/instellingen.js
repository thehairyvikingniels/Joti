/**
 * js/instellingen.js ??? Theme change handler and push notification device preferences modal.
 */

async function changeTheme(newTheme, prefix = "") {
    try {
        const response = await fetch(`${prefix}functies.php?set_theme=${encodeURIComponent(newTheme)}`);
        if (response.ok) {
            window.location.href = window.location.pathname + window.location.search;
        }
    } catch (e) {
        console.error("Theme switch error:", e);
    }
}

function renameDevice(id, currentName) {
    const idField = document.getElementById("rename_device_id");
    const nameField = document.getElementById("new_device_name");
    const modal = document.getElementById("renameModal");
    if (idField) idField.value = id;
    if (nameField) nameField.value = currentName;
    if (modal) modal.classList.remove("hidden");
}

function unsubscribeDevice(endpoint) {
    if (confirm("Weet je zeker dat je de meldingen voor dit apparaat wilt uitschakelen?")) {
        doDeleteEndpoint(endpoint);
    }
}

function doDeleteEndpoint(endpoint) {
    fetch("includes/push_subscription.php", {
        method: "DELETE",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ endpoint: endpoint })
    })
        .then((r) => r.json())
        .then((res) => {
            if (res.status === "success") {
                window.location.reload();
            } else {
                alert(`Fout bij uitschakelen: ${res.message || "Onbekend"}`);
            }
        })
        .catch((err) => {
            console.error(err);
            alert("Er is een fout opgetreden bij het uitschakelen van de meldingen.");
        });
}
