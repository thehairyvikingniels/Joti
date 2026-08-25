/**
 * js/tegenhunt.js ??? Client-side Mapbox GL GIS engine for Tegenhunt 90?? search cone, fog-of-war, live breadcrumbs, and modal controllers.
 */

let map = null;
let searchersData = [];
let searcherMarkers = {};
let breadcrumbPollInterval = null;
let timerTickInterval = null;

function initTegenhunt() {
    if (typeof initGpsTracking === 'function') {
        initGpsTracking('true');
    }

    const timerEl = document.getElementById('tegenhunt-big-timer');
    if (timerEl && ACTIVE_SESSION) {
        updateTimer();
        timerTickInterval = setInterval(updateTimer, 1000);
    }

    if (ACTIVE_SESSION && document.getElementById('tegenhunt-map')) {
        initMapboxRadar();
    }
}

function updateTimer() {
    const timerEl = document.getElementById('tegenhunt-big-timer');
    const topbarTimerEl = document.getElementById('topbar-tegenhunt-timer');
    const bannerTimerEl = document.getElementById('banner-tegenhunt-timer');

    const endAttr = timerEl?.getAttribute('data-end') || topbarTimerEl?.getAttribute('data-end') || bannerTimerEl?.getAttribute('data-end');
    if (!endAttr) return;

    const endTs = parseInt(endAttr, 10);
    const nowTs = Math.floor(Date.now() / 1000);
    const remaining = Math.max(0, endTs - nowTs);

    const mins = Math.floor(remaining / 60);
    const secs = remaining % 60;
    const fmt = `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;

    if (timerEl) {
        timerEl.textContent = fmt;
        if (remaining <= 300) {
            timerEl.className = 'text-2xl sm:text-3xl font-mono font-black text-red-500 animate-pulse tracking-wider';
        } else if (remaining <= 600) {
            timerEl.className = 'text-2xl sm:text-3xl font-mono font-black text-orange-400 tracking-wider';
        }
        if (remaining <= 0) {
            timerEl.textContent = "VERLOPEN";
            if (timerTickInterval) clearInterval(timerTickInterval);
        }
    }

    if (topbarTimerEl) {
        topbarTimerEl.textContent = fmt;
    }
    if (bannerTimerEl) {
        bannerTimerEl.textContent = fmt;
    }
}

function selectDirection(dir, deg, btn) {
    document.getElementById('input-direction').value = dir;
    document.getElementById('input-degrees').value = deg;

    document.querySelectorAll('.btn-dir').forEach(b => {
        b.className = 'btn-dir py-3 px-2 rounded-xl text-center transition flex flex-col items-center justify-center bg-black/5 hover:bg-red-500/20 font-bold';
    });
    btn.className = 'btn-dir py-3 px-2 rounded-xl text-center transition flex flex-col items-center justify-center ring-2 ring-red-500 bg-red-500 text-white font-black';
}

async function startTegenhunt() {
    const dir = document.getElementById('input-direction').value;
    const deg = document.getElementById('input-degrees').value;
    const duration = document.getElementById('input-duration').value;
    const message = document.getElementById('input-message').value;

    const formData = new FormData();
    formData.append('start', '1');
    formData.append('direction', dir);
    formData.append('degrees', deg);
    formData.append('duration', duration);
    formData.append('message', message);

    try {
        const res = await fetch('tegenhunt_helper.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
            window.location.reload();
        } else {
            alert(data.error || 'Fout bij starten tegenhunt.');
        }
    } catch (e) {
        console.error('Error starting tegenhunt:', e);
    }
}

function openStopModal() {
    document.getElementById('modal-stop-tegenhunt').classList.remove('hidden');
}

function closeStopModal() {
    document.getElementById('modal-stop-tegenhunt').classList.add('hidden');
}

async function confirmStopTegenhunt(sessionId) {
    const formData = new FormData();
    formData.append('stop', '1');
    formData.append('id', sessionId);
    formData.append('status', 'cancelled');

    try {
        const res = await fetch('tegenhunt_helper.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
            window.location.reload();
        }
    } catch (e) {
        console.error('Error stopping tegenhunt:', e);
    }
}

// ----------------------------------------------------
// MAPBOX GL RADAR & 90?? FOG-OF-WAR GENERATOR
// ----------------------------------------------------
function initMapboxRadar() {
    mapboxgl.accessToken = MAPBOX_ACCESS_TOKEN;
    const centerLon = parseFloat(HOME_COORDS.lon) || 5.87625;
    const centerLat = parseFloat(HOME_COORDS.lat) || 51.98778;
    const compassDeg = parseInt(ACTIVE_SESSION.compass_degrees, 10) || 0;

    map = new mapboxgl.Map({
        container: 'tegenhunt-map',
        style: 'mapbox://styles/mapbox/satellite-streets-v12',
        center: [centerLon, centerLat],
        zoom: 15.5
    });

    map.addControl(new mapboxgl.NavigationControl(), 'top-left');

    map.on('load', () => {
        const hqEl = document.createElement('div');
        hqEl.className = 'w-10 h-10 rounded-full bg-red-600 text-white flex items-center justify-center text-lg font-bold border-2 border-white shadow-xl';
        hqEl.innerHTML = '<i class="fas fa-home"></i>';
        new mapboxgl.Marker(hqEl)
            .setLngLat([centerLon, centerLat])
            .setPopup(new mapboxgl.Popup({ offset: 25 }).setHTML(`<b>${HOME_COORDS.naam || 'Clubhuis HQ'}</b>`))
            .addTo(map);

        const sectorPoints = calculateSectorPolygon(centerLon, centerLat, 500, compassDeg, 90);
        
        const worldMask = [
            [-180, -90], [180, -90], [180, 90], [-180, 90], [-180, -90]
        ];

        const sectorHole = [...sectorPoints].reverse();

        map.addSource('fog-of-war', {
            type: 'geojson',
            data: {
                type: 'Feature',
                geometry: {
                    type: 'Polygon',
                    coordinates: [worldMask, sectorHole]
                }
            }
        });

        map.addLayer({
            id: 'fog-layer',
            type: 'fill',
            source: 'fog-of-war',
            paint: {
                'fill-color': '#000000',
                'fill-opacity': 0.65
            }
        });

        map.addSource('search-sector', {
            type: 'geojson',
            data: {
                type: 'Feature',
                geometry: {
                    type: 'Polygon',
                    coordinates: [sectorPoints]
                }
            }
        });

        map.addLayer({
            id: 'sector-line',
            type: 'line',
            source: 'search-sector',
            paint: {
                'line-color': '#22c55e',
                'line-width': 3.5,
                'line-dasharray': [2, 1]
            }
        });

        pollBreadcrumbs();
        breadcrumbPollInterval = setInterval(pollBreadcrumbs, 3333);
    });
}

function calculateSectorPolygon(lon, lat, radiusMeters, bearing, beamWidth) {
    const points = [[lon, lat]];
    const startAngle = bearing - (beamWidth / 2);
    const endAngle = bearing + (beamWidth / 2);
    const step = 2;

    const R = 6371000;
    const d = radiusMeters;
    const latRad = lat * (Math.PI / 180);
    const lonRad = lon * (Math.PI / 180);

    for (let a = startAngle; a <= endAngle; a += step) {
        const brng = a * (Math.PI / 180);
        const pLat = Math.asin(Math.sin(latRad) * Math.cos(d / R) + Math.cos(latRad) * Math.sin(d / R) * Math.cos(brng));
        const pLon = lonRad + Math.atan2(Math.sin(brng) * Math.sin(d / R) * Math.cos(latRad), Math.cos(d / R) - Math.sin(latRad) * Math.sin(pLat));

        points.push([pLon * (180 / Math.PI), pLat * (180 / Math.PI)]);
    }

    points.push([lon, lat]);
    return points;
}

// ----------------------------------------------------
// LIVE SEARCHER BREADCRUMBS & SWEPT CORRIDORS
// ----------------------------------------------------
async function pollBreadcrumbs() {
    try {
        const res = await fetch('tegenhunt_helper.php?breadcrumbs');
        if (!res.ok) return;
        const data = await res.json();
        searchersData = data.searchers || [];

        renderSearcherList();
        renderSearcherMapLayers();
    } catch (e) {
        console.error('Error fetching breadcrumbs:', e);
    }
}

function calculateDistanceMeters(lat1, lon1, lat2, lon2) {
    const R = 6371000; // Earth radius in meters
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLon = (lon2 - lon1) * Math.PI / 180;
    const a = 
        Math.sin(dLat / 2) * Math.sin(dLat / 2) +
        Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * 
        Math.sin(dLon / 2) * Math.sin(dLon / 2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    return R * c;
}

function calculateTrailDistanceMeters(trail) {
    if (!trail || !Array.isArray(trail) || trail.length < 2) return 0;
    let total = 0;
    for (let i = 1; i < trail.length; i++) {
        const [lon1, lat1] = trail[i - 1];
        const [lon2, lat2] = trail[i];
        if (typeof lat1 === 'number' && typeof lon1 === 'number' && typeof lat2 === 'number' && typeof lon2 === 'number') {
            const d = calculateDistanceMeters(lat1, lon1, lat2, lon2);
            if (d >= 2) { // filter minute GPS drift/jitter
                total += d;
            }
        }
    }
    return total;
}

function renderSearcherList() {
    const countEl = document.getElementById('searcher-count');
    const listEl = document.getElementById('searcher-list');
    if (!countEl || !listEl) return;

    countEl.textContent = searchersData.length;

    if (searchersData.length === 0) {
        listEl.innerHTML = '<div class="text-center opacity-50 py-4 theme-text">Nog geen zoekers actief met GPS...</div>';
        return;
    }

    let html = '';
    searchersData.forEach(s => {
        const walkedMeters = calculateTrailDistanceMeters(s.trail);
        let distText = walkedMeters >= 1000 ? `${(walkedMeters / 1000).toFixed(1)} km` : `${Math.round(walkedMeters)} m`;

        html += `
        <div class="flex items-center justify-between p-2.5 rounded-lg border theme-card transition hover:opacity-90" style="background-color: var(--theme-card-bg); border-color: var(--theme-card-border);">
          <div class="flex items-center gap-2.5 min-w-0">
            <span class="w-3 h-3 rounded-full flex-shrink-0 shadow-sm" style="background-color: ${s.color};"></span>
            <span class="font-semibold text-xs sm:text-sm theme-text truncate">${s.name}</span>
          </div>
          <span class="text-xs font-mono font-bold px-2 py-0.5 rounded ml-2 flex-shrink-0" style="background-color: rgba(0,0,0,0.08); color: var(--theme-text);" title="Afgelegde afstand tijdens zoekactie">
            <i class="fas fa-shoe-prints text-[10px] mr-1 opacity-60"></i>${distText}
          </span>
        </div>`;
    });
    listEl.innerHTML = html;
}

function renderSearcherMapLayers() {
    if (!map || !map.isStyleLoaded()) return;

    searchersData.forEach(s => {
        const sourceId = `trail-src-${s.user_id}`;
        const layerId = `trail-layer-${s.user_id}`;

        const geojson = {
            type: 'Feature',
            geometry: {
                type: 'LineString',
                coordinates: s.trail
            }
        };

        if (map.getSource(sourceId)) {
            map.getSource(sourceId).setData(geojson);
        } else {
            map.addSource(sourceId, { type: 'geojson', data: geojson });
            map.addLayer({
                id: layerId,
                type: 'line',
                source: sourceId,
                paint: {
                    'line-color': s.color,
                    'line-width': 4.5,
                    'line-opacity': 0.85
                }
            });
        }

        if (s.trail.length > 0) {
            const latest = s.trail[s.trail.length - 1];
            if (searcherMarkers[s.user_id]) {
                searcherMarkers[s.user_id].setLngLat(latest);
            } else {
                const el = document.createElement('div');
                el.className = 'w-7 h-7 rounded-full text-white flex items-center justify-center font-bold text-xs shadow-lg border-2 border-white';
                el.style.backgroundColor = s.color;
                el.innerHTML = s.name.substring(0, 1).toUpperCase();

                searcherMarkers[s.user_id] = new mapboxgl.Marker(el)
                    .setLngLat(latest)
                    .setPopup(new mapboxgl.Popup({ offset: 15 }).setHTML(`<b>${s.name}</b>`))
                    .addTo(map);
            }
        }
    });
}

// ----------------------------------------------------
// STICKER FOUND MODAL & SUBMISSION
// ----------------------------------------------------
function openStickerFoundModal() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition((pos) => {
            document.getElementById('sticker-lat').value = pos.coords.latitude;
            document.getElementById('sticker-lon').value = pos.coords.longitude;
        });
    }
    document.getElementById('modal-sticker-found').classList.remove('hidden');
    document.getElementById('sticker-code').focus();
}

function closeStickerFoundModal() {
    document.getElementById('modal-sticker-found').classList.add('hidden');
}

async function submitStickerFound() {
    const code = document.getElementById('sticker-code').value.trim();
    const photoInput = document.getElementById('sticker-photo');
    const lat = document.getElementById('sticker-lat').value;
    const lon = document.getElementById('sticker-lon').value;
    const btn = document.getElementById('btn-submit-found');

    if (!code) {
        alert('Vul a.u.b. de stickercode in.');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Bezig met inleveren...';

    const formData = new FormData();
    formData.append('submit_found', '1');
    formData.append('code', code);
    formData.append('lat', lat);
    formData.append('lon', lon);
    if (photoInput.files.length > 0) {
        formData.append('photo', photoInput.files[0]);
    }

    try {
        const res = await fetch('tegenhunt_helper.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
            closeStickerFoundModal();
            const successModal = document.getElementById('modal-sticker-success');
            const msgEl = document.getElementById('sticker-success-message');
            if (msgEl) msgEl.textContent = data.message || `Sticker ${code} succesvol ingeleverd!`;
            if (successModal) successModal.classList.remove('hidden');
        } else {
            alert(data.error || 'Fout bij inleveren sticker.');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane"></i> INLEVEREN & TEGENHUNT SLUITEN';
        }
    } catch (e) {
        console.error('Error submitting sticker:', e);
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> INLEVEREN & TEGENHUNT SLUITEN';
    }
}

document.addEventListener('DOMContentLoaded', initTegenhunt);
