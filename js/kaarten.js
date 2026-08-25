/**
 * js/kaarten.js ??? Map iframe manager, layer toggle persistence, and fullscreen controls.
 */

let savedMapSettings = null;
let lastKnownMapState = null;
let mapSaveTimeout = null;

function initKaarten(initialSettings) {
    savedMapSettings = initialSettings;
    lastKnownMapState = savedMapSettings && savedMapSettings.mapState ? savedMapSettings.mapState : null;

    window.addEventListener("message", (event) => {
        if (event.data && event.data.type === "mapUpdate") {
            lastKnownMapState = event.data.state;
            clearTimeout(mapSaveTimeout);
            mapSaveTimeout = setTimeout(() => {
                saveMapSettings();
            }, 1000);
        }
    }, false);

    document.addEventListener("fullscreenchange", () => {
        const icon = document.getElementById("fullscreen-icon");
        if (!icon) return;
        if (!document.fullscreenElement) {
            icon.classList.remove("fa-compress");
            icon.classList.add("fa-expand");
        } else {
            icon.classList.remove("fa-expand");
            icon.classList.add("fa-compress");
        }
    });

    if (savedMapSettings) {
        if (savedMapSettings.checkboxes) {
            Object.keys(savedMapSettings.checkboxes).forEach((id) => {
                const el = document.getElementById(id);
                if (el) el.checked = savedMapSettings.checkboxes[id];
            });
        }
        if (savedMapSettings.teams) {
            document.querySelectorAll(".team-filter").forEach((el) => {
                el.checked = savedMapSettings.teams.includes(el.id);
            });
        }
    }
    kaartveranderen();
}

function saveMapSettings() {
    const layers = ["groepen", "personen", "autos", "hints", "vossenpad", "predicted_route", "zoekcirkel"];
    const teams = Array.from(document.querySelectorAll(".team-filter:checked")).map((el) => el.id);
    const savePayload = {
        checkboxes: {
            helft1: document.getElementById("helft1") ? document.getElementById("helft1").checked : false,
            helft2: document.getElementById("helft2") ? document.getElementById("helft2").checked : false
        },
        teams: teams,
        mapState: lastKnownMapState
    };
    layers.forEach((id) => {
        const el = document.getElementById(id);
        if (el) savePayload.checkboxes[id] = el.checked;
    });

    fetch("functies.php?save_map_settings=1", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(savePayload)
    }).catch((e) => console.error("Map settings save error:", e));
}

function kaartveranderen() {
    const layers = ["groepen", "personen", "autos", "hints", "vossenpad", "predicted_route", "zoekcirkel"];
    const params = layers.map((id) => {
        const el = document.getElementById(id);
        return `${id}=${el ? el.checked : false}`;
    });

    const h1 = document.getElementById("helft1");
    const h2 = document.getElementById("helft2");
    if (h1) params.push(`helft1=${h1.checked}`);
    if (h2) params.push(`helft2=${h2.checked}`);

    const teams = Array.from(document.querySelectorAll(".team-filter:checked")).map((el) => el.id);
    if (teams.length > 0) {
        params.push(`teams=${teams.join(",")}`);
    }

    if (lastKnownMapState) {
        params.push(`lon=${lastKnownMapState.lon}`);
        params.push(`lat=${lastKnownMapState.lat}`);
        params.push(`zoom=${lastKnownMapState.zoom}`);
    }

    const iframe = document.getElementById("iframe01");
    if (iframe) iframe.src = `maps.php?${params.join("&")}`;
    saveMapSettings();
}

function toggleCheckbox(id) {
    const checkbox = document.getElementById(id);
    if (checkbox && !checkbox.disabled) {
        checkbox.checked = !checkbox.checked;
        kaartveranderen();
    }
}

function togglePanel(panelId) {
    const panel = document.getElementById(panelId);
    if (!panel) return;
    if (panel.style.display === "block") {
        panel.style.display = "none";
    } else {
        if (panelId === "layers-panel" && document.getElementById("filters-panel")) {
            document.getElementById("filters-panel").style.display = "none";
        }
        if (panelId === "filters-panel" && document.getElementById("layers-panel")) {
            document.getElementById("layers-panel").style.display = "none";
        }
        panel.style.display = "block";
    }
}

function toggleFullScreen() {
    const elem = document.querySelector(".map-view-wrapper");
    if (!elem) return;
    if (!document.fullscreenElement) {
        elem.requestFullscreen().catch((err) => alert(`Error: ${err.message}`));
    } else {
        document.exitFullscreen();
    }
}
