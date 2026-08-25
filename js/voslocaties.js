/**
 * js/voslocaties.js ??? Coordinate inputs manager, geolocation querying, and Mapbox modal picker.
 */

let mapModal = null;
let modalMarker = null;
let mapInitialized = false;
let mapboxApiKey = "";
let defaultCenter = [5.3872, 52.1551];

function initVoslocaties(config) {
    if (config) {
        mapboxApiKey = config.mapboxKey || "";
        if (config.center) defaultCenter = config.center;
    }
    if (typeof mapboxgl !== "undefined" && mapboxApiKey) {
        mapboxgl.accessToken = mapboxApiKey;
    }
    toggleCodeInput();
}

function showCoords(type) {
    const latInput = document.querySelector('input[name="lat"]');
    const lonInput = document.querySelector('input[name="lon"]');
    const rdXInput = document.querySelector('input[name="rd_x"]');
    const rdYInput = document.querySelector('input[name="rd_y"]');
    const gpsStatus = document.getElementById("gps-status");

    if (gpsStatus) gpsStatus.textContent = "";
    if (rdXInput) rdXInput.value = "";
    if (rdYInput) rdYInput.value = "";

    document.getElementById("latlon_coords").style.display = "none";
    document.getElementById("rd_coords").style.display = "none";
    document.getElementById("group_coords").style.display = "none";

    if (latInput) latInput.required = false;
    if (lonInput) lonInput.required = false;
    if (rdXInput) rdXInput.required = false;
    if (rdYInput) rdYInput.required = false;

    if (type === "latlon") {
        document.getElementById("latlon_coords").style.display = "block";
        if (latInput) latInput.required = true;
        if (lonInput) lonInput.required = true;
    } else if (type === "rd") {
        document.getElementById("rd_coords").style.display = "block";
        if (rdXInput) rdXInput.required = true;
        if (rdYInput) rdYInput.required = true;
    } else if (type === "group") {
        document.getElementById("group_coords").style.display = "block";
    }
}

function getGPSLocation() {
    const gpsStatus = document.getElementById("gps-status");
    const latInput = document.querySelector('input[name="lat"]');
    const lonInput = document.querySelector('input[name="lon"]');

    if (navigator.geolocation) {
        if (gpsStatus) gpsStatus.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Locatie ophalen...';
        navigator.geolocation.getCurrentPosition(
            (position) => {
                if (latInput) latInput.value = position.coords.latitude.toFixed(6);
                if (lonInput) lonInput.value = position.coords.longitude.toFixed(6);
                if (gpsStatus) gpsStatus.innerHTML = '<i class="fas fa-check-circle text-green-500 mr-1"></i>Locatie succesvol opgehaald.';
            },
            (error) => {
                let errorMessage = "Een onbekende fout is opgetreden.";
                if (error.code === error.PERMISSION_DENIED) errorMessage = "Toegang tot locatie geweigerd.";
                else if (error.code === error.POSITION_UNAVAILABLE) errorMessage = "Locatie informatie niet beschikbaar.";
                else if (error.code === error.TIMEOUT) errorMessage = "Timeout bij het ophalen van locatie.";
                if (gpsStatus) gpsStatus.innerHTML = `<i class="fas fa-times-circle text-red-500 mr-1"></i>${errorMessage}`;
            }
        );
    } else if (gpsStatus) {
        gpsStatus.textContent = "Geolocation wordt niet ondersteund door deze browser.";
    }
}

function toggleCodeInput() {
    const typeSelect = document.getElementById("type_select");
    const codeInput = document.getElementById("code_input");
    if (!typeSelect || !codeInput) return;

    if (typeSelect.value === "Hunt") {
        codeInput.disabled = false;
        codeInput.required = false;
        codeInput.classList.remove("bg-gray-100");
        codeInput.classList.add("bg-white");
    } else {
        codeInput.disabled = true;
        codeInput.required = false;
        codeInput.value = "";
        codeInput.classList.add("bg-gray-100");
        codeInput.classList.remove("bg-white");
    }
}

function selectGroup(id, rowElement) {
    const targetInput = document.getElementById("selected_group_id");
    if (targetInput) targetInput.value = id;
    document.querySelectorAll("#group_list .group-row").forEach((row) => row.classList.remove("bg-blue-100"));
    if (rowElement) rowElement.classList.add("bg-blue-100");
}

function filterGroups() {
    const input = document.getElementById("group_search");
    if (!input) return;
    const filter = input.value.toLowerCase();
    document.querySelectorAll("#group_list .group-row").forEach((row) => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? "" : "none";
    });
}

function openMapModal() {
    const modal = document.getElementById("map-modal");
    if (modal) modal.classList.remove("hidden");

    if (!mapInitialized && typeof mapboxgl !== "undefined") {
        mapModal = new mapboxgl.Map({
            container: "modal-map",
            style: "mapbox://styles/mapbox/streets-v12",
            center: defaultCenter,
            zoom: 11
        });

        mapModal.on("click", (e) => {
            if (modalMarker) modalMarker.remove();
            modalMarker = new mapboxgl.Marker().setLngLat(e.lngLat).addTo(mapModal);
        });

        mapInitialized = true;
    }

    setTimeout(() => {
        if (mapModal) mapModal.resize();
        const latInput = document.querySelector('input[name="lat"]');
        const lonInput = document.querySelector('input[name="lon"]');
        const currentLat = latInput ? parseFloat(latInput.value) : NaN;
        const currentLon = lonInput ? parseFloat(lonInput.value) : NaN;

        if (!isNaN(currentLat) && !isNaN(currentLon) && typeof mapboxgl !== "undefined" && mapModal) {
            if (modalMarker) modalMarker.remove();
            modalMarker = new mapboxgl.Marker().setLngLat([currentLon, currentLat]).addTo(mapModal);
            mapModal.setCenter([currentLon, currentLat]);
            mapModal.setZoom(14);
        }
    }, 200);
}

function closeMapModal() {
    const modal = document.getElementById("map-modal");
    if (modal) modal.classList.add("hidden");
}

function confirmMapLocation() {
    if (modalMarker) {
        const lngLat = modalMarker.getLngLat();
        const latInput = document.querySelector('input[name="lat"]');
        const lonInput = document.querySelector('input[name="lon"]');
        if (latInput) latInput.value = lngLat.lat.toFixed(6);
        if (lonInput) lonInput.value = lngLat.lng.toFixed(6);

        const gpsStatus = document.getElementById("gps-status");
        if (gpsStatus) gpsStatus.innerHTML = '<i class="fas fa-check-circle text-green-500 mr-1"></i>Locatie succesvol gekozen via kaart.';
        closeMapModal();
    } else {
        alert("Klik eerst ergens op de kaart om een locatie te selecteren.");
    }
}
