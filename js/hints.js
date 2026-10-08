/**
 * js/hints.js — Real-time collaborative hints manager, Gelderland auto-prefill,
 * 2-second background sync scanner, and Mapbox "Probeer" probe modal.
 */

let mapboxApiKey = "";
let probeMap = null;
let probeMapInitialized = false;
let probeMarkers = [];
let activeProbeHintId = null;
let activeProbeSubarea = null;
let scannerInterval = null;

/**
 * Initializes the hints controller, input listeners, and starts background sync scanner.
 */
function initHints(config) {
    if (config && config.mapboxKey) {
        mapboxApiKey = config.mapboxKey;
        if (typeof mapboxgl !== "undefined" && mapboxApiKey) {
            mapboxgl.accessToken = mapboxApiKey;
        }
    }

    applyGelderlandPrefill();
    setupInputEventHandlers();
    startSyncScanner();
}

/**
 * Applies dynamic coordinate prefill based on subarea metadata.
 * Prefills "4" for all Y coordinates, and "1" only for subareas where X is 100% 1.
 */
function applyGelderlandPrefill() {
    document.querySelectorAll('input[name="rdX"]').forEach((input) => {
        const prefill = input.dataset.prefillX || "";
        if (!input.value.trim() && prefill) {
            input.value = prefill;
        }
    });
    document.querySelectorAll('input[name="rdY"]').forEach((input) => {
        const prefill = input.dataset.prefillY || "4***";
        if (!input.value.trim() && prefill) {
            input.value = prefill;
        }
    });
}

/**
 * Configures input behaviors:
 * - Focus auto-select for prefilled values ("1***", "4***") or wildcards
 * - Auto-split 8-digit pasted coordinates ("1924 4452" -> X: 1924, Y: 4452)
 * - Strict 4-character maximum limit without 6-character autocompletion
 */
function setupInputEventHandlers() {
    // Helper to handle 8-digit pasted coordinates into either X or Y field
    const handlePaste8Digits = (e, currentInput) => {
        const pastedText = (e.clipboardData || window.clipboardData).getData("text").trim();
        const splitMatch = pastedText.match(/^(\d{4})[\s\/\-_,]?(\d{4})$/);
        if (splitMatch) {
            e.preventDefault();
            const row = currentInput.closest("[id^='hint_row_']");
            const xInput = row ? row.querySelector('input[name="rdX"]') : null;
            const yInput = row ? row.querySelector('input[name="rdY"]') : null;
            if (xInput) xInput.value = splitMatch[1];
            if (yInput) yInput.value = splitMatch[2];
        }
    };

    document.querySelectorAll('input[name="rdX"]').forEach((input) => {
        input.addEventListener("focus", () => {
            if (input.value === "1***" || input.value.includes("*")) {
                input.select();
            }
        });
        input.addEventListener("paste", (e) => handlePaste8Digits(e, input));
        input.addEventListener("input", () => {
            let val = input.value.trim();
            if (/^[xX\s:]+/.test(val)) {
                val = val.replace(/^[xX\s:]+/, "");
                input.value = val;
            }
            if (input.value.length > 4) {
                input.value = input.value.substring(0, 4);
            }
        });
        input.addEventListener("blur", () => {
            let val = input.value.trim();
            if (!val && input.dataset.prefillX) {
                input.value = input.dataset.prefillX;
            }
        });
    });

    document.querySelectorAll('input[name="rdY"]').forEach((input) => {
        input.addEventListener("focus", () => {
            if (input.value === "4***" || input.value.includes("*")) {
                input.select();
            }
        });
        input.addEventListener("paste", (e) => handlePaste8Digits(e, input));
        input.addEventListener("input", () => {
            let val = input.value.trim();
            if (/^[yY\s:]+/.test(val)) {
                val = val.replace(/^[yY\s:]+/, "");
                input.value = val;
            }
            if (input.value.length > 4) {
                input.value = input.value.substring(0, 4);
            }
        });
        input.addEventListener("blur", () => {
            let val = input.value.trim();
            if (!val && input.dataset.prefillY) {
                input.value = input.dataset.prefillY;
            }
        });
    });
}

/**
 * Saves complete 4-digit RD hectometer coordinates via AJAX directly into Voslocaties.
 */
function saveCoordinates(hintId, subarea) {
    const xInput = document.getElementById(`rdX_${hintId}_${subarea}`);
    const yInput = document.getElementById(`rdY_${hintId}_${subarea}`);
    const saveBtn = document.getElementById(`save_btn_${hintId}_${subarea}`);

    if (!xInput || !yInput) return;

    let xVal = xInput.value.trim();
    let yVal = yInput.value.trim();

    // Check for wildcards
    if (xVal.includes("*") || yVal.includes("*")) {
        showHintToast("Coördinaten met een sterretje (*) kunnen niet worden opgeslagen. Gebruik de knop \"Probeer\" om wildcards te testen.", "warning");
        return;
    }

    if (!/^\d{4}$/.test(xVal) || !/^\d{4}$/.test(yVal)) {
        showHintToast("Vul voor zowel X als Y exact 4 cijfers in (bijv. 1924 en 4452).", "warning");
        return;
    }

    const originalBtnHtml = saveBtn ? saveBtn.innerHTML : "Opslaan";
    if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Opslaan...';
    }

    const formData = new FormData();
    formData.append("action", "save_coordinates");
    formData.append("hint_id", hintId);
    formData.append("subarea", subarea);
    formData.append("rd_x", xVal);
    formData.append("rd_y", yVal);

    fetch("hints_helper.php", {
        method: "POST",
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.innerHTML = originalBtnHtml;
        }

        if (data.success) {
            showHintToast(data.message || `Coördinaat voor ${subarea} opgeslagen!`, "success");
            markSavedVisual(hintId, subarea);
        } else {
            showHintToast(data.error || "Fout bij opslaan van coördinaten.", "error");
        }
    })
    .catch(err => {
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.innerHTML = originalBtnHtml;
        }
        showHintToast("Netwerkfout bij opslaan: " + err.message, "error");
    });
}

/**
 * Visual indicator on the form row after successful save.
 */
function markSavedVisual(hintId, subarea) {
    const row = document.getElementById(`hint_row_${hintId}_${subarea}`);
    if (row) {
        row.classList.add("ring-2", "ring-green-500", "bg-green-50/50");
        setTimeout(() => {
            row.classList.remove("ring-2", "ring-green-500", "bg-green-50/50");
        }, 1500);
    }
}

/**
 * 2-Second background scanner polling for updates made by teammates.
 */
function startSyncScanner() {
    if (scannerInterval) clearInterval(scannerInterval);

    scannerInterval = setInterval(() => {
        fetch("hints_helper.php?action=get_hint_coordinates")
            .then(r => r.json())
            .then(data => {
                if (!data.success || !data.coordinates) return;

                const coordsMap = data.coordinates;
                for (const hintId in coordsMap) {
                    for (const subarea in coordsMap[hintId]) {
                        const item = coordsMap[hintId][subarea];
                        const xInput = document.getElementById(`rdX_${hintId}_${subarea}`);
                        const yInput = document.getElementById(`rdY_${hintId}_${subarea}`);

                        if (!xInput || !yInput) continue;

                        // Focus defense: DO NOT overwrite if user is actively typing in either field
                        if (document.activeElement === xInput || document.activeElement === yInput) {
                            continue;
                        }

                        // Check if value changed
                        const currentX = xInput.value.trim();
                        const currentY = yInput.value.trim();
                        const newX = item.rd_x || "";
                        const newY = item.rd_y || "";

                        if (newX && newY && (currentX !== newX || currentY !== newY)) {
                            xInput.value = newX;
                            yInput.value = newY;

                            // Gentle sync pulse animation
                            xInput.classList.add("bg-green-100", "text-green-800");
                            yInput.classList.add("bg-green-100", "text-green-800");
                            setTimeout(() => {
                                xInput.classList.remove("bg-green-100", "text-green-800");
                                yInput.classList.remove("bg-green-100", "text-green-800");
                            }, 1800);
                        }
                    }
                }
            })
            .catch(() => {
                // Silently ignore scanner background network blips
            });
    }, 2000);
}

/**
 * Opens the "Probeer" Mapbox modal and fetches candidate geometry & historical trail.
 */
function openProbeerModal(hintId, subarea) {
    activeProbeHintId = hintId;
    activeProbeSubarea = subarea;

    const xInput = document.getElementById(`rdX_${hintId}_${subarea}`);
    const yInput = document.getElementById(`rdY_${hintId}_${subarea}`);
    if (!xInput || !yInput) return;

    let xVal = xInput.value.trim().replace(/^[xX\s:]+/, "");
    let yVal = yInput.value.trim().replace(/^[yY\s:]+/, "");

    if (!xVal || !yVal) {
        showHintToast("Vul eerst (een deel van) de coördinaten in om te proberen.", "warning");
        return;
    }

    const modal = document.getElementById("probeer-map-modal");
    if (modal) modal.classList.remove("hidden");

    // Reset stats panel loading state
    const infoPanel = document.getElementById("probe-info-panel");
    if (infoPanel) {
        infoPanel.innerHTML = '<div class="text-center py-6 text-sm opacity-60"><i class="fas fa-spinner fa-spin mr-2"></i>Analyseren en kaart laden...</div>';
    }

    // Modal save button state
    const modalSaveBtn = document.getElementById("modal-direct-save-btn");
    if (modalSaveBtn) modalSaveBtn.classList.add("hidden");

    // Fetch probe analysis (pass hint_id so backend excludes current hint from history)
    fetch(`hints_helper.php?action=probe_coordinates&hint_id=${hintId}&subarea=${encodeURIComponent(subarea)}&rd_x=${encodeURIComponent(xVal)}&rd_y=${encodeURIComponent(yVal)}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                if (infoPanel) infoPanel.innerHTML = `<div class="p-3 bg-red-100 text-red-700 rounded text-sm">${data.error || "Onbekende fout."}</div>`;
                return;
            }

            renderProbeMap(data, hintId, subarea);
        })
        .catch(err => {
            if (infoPanel) infoPanel.innerHTML = `<div class="p-3 bg-red-100 text-red-700 rounded text-sm">Fout: ${err.message}</div>`;
        });
}

/**
 * Closes the "Probeer" modal.
 */
function closeProbeerModal() {
    const modal = document.getElementById("probeer-map-modal");
    if (modal) modal.classList.add("hidden");
}

/**
 * Direct save shortcut inside the "Probeer" modal for valid complete coordinates.
 */
function directSaveFromModal() {
    if (activeProbeHintId && activeProbeSubarea) {
        saveCoordinates(activeProbeHintId, activeProbeSubarea);
        closeProbeerModal();
    }
}

/**
 * Renders the candidate points/polygon, past 3 fox locations, and stats panel on Mapbox.
 */
function renderProbeMap(data, hintId, subarea) {
    const geo = data.geometry || {};
    const history = data.history_points || [];
    const latest = data.latest_known;
    const infoPanel = document.getElementById("probe-info-panel");
    const modalSaveBtn = document.getElementById("modal-direct-save-btn");

    // 1. Update stats panel with theme-consistent styling
    let statsHtml = `
        <div class="space-y-3 text-sm">
            <div class="flex items-center justify-between pb-2 border-b" style="border-color: var(--theme-card-border);">
                <div class="font-bold flex items-center gap-2">
                    <span class="w-3.5 h-3.5 rounded-full shadow-sm" style="background-color: ${getFoxColorByName(subarea)}"></span>
                    <span>Deelgebied ${subarea}</span>
                </div>
                <span class="text-xs px-2 py-0.5 rounded font-mono font-semibold ${geo.is_complete ? 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300'}">
                    ${geo.is_complete ? 'Volledig punt' : 'Zoekgebied / Wildcards'}
                </span>
            </div>
            
            <div class="grid grid-cols-2 gap-2 text-xs">
                <div class="p-2 rounded border" style="background-color: var(--theme-bg); border-color: var(--theme-card-border); color: var(--theme-text);">
                    <span class="opacity-60 block text-[11px]">RD X / Y:</span>
                    <span class="font-mono font-bold">${geo.rd_x || geo.rd_x_pattern || '-'} / ${geo.rd_y || geo.rd_y_pattern || '-'}</span>
                </div>
                <div class="p-2 rounded border" style="background-color: var(--theme-bg); border-color: var(--theme-card-border); color: var(--theme-text);">
                    <span class="opacity-60 block text-[11px]">WGS84 Lat/Lon:</span>
                    <span class="font-mono font-bold">${geo.lat ? `${geo.lat}, ${geo.lon}` : (geo.center ? `${geo.center.lat}, ${geo.center.lon}` : '-')}</span>
                </div>
            </div>`;

    if (geo.dimensions) {
        statsHtml += `
            <div class="p-2.5 rounded border text-xs" style="background-color: var(--theme-bg); border-color: var(--theme-card-border); color: var(--theme-text);">
                <div class="font-bold mb-0.5 text-blue-500"><i class="fas fa-vector-square mr-1"></i>Zoekgebied oppervlak:</div>
                <div class="opacity-90">${geo.dimensions} — <strong>${geo.area_km2} km²</strong></div>
            </div>`;
    }

    if (geo.note) {
        statsHtml += `
            <div class="p-2.5 rounded border text-xs text-amber-600 dark:text-amber-400" style="background-color: var(--theme-bg); border-color: var(--theme-card-border);">
                <i class="fas fa-lightbulb mr-1"></i>${geo.note}
            </div>`;
    }

    if (geo.candidates && geo.candidates.length > 0) {
        statsHtml += `
            <div class="p-2.5 rounded border text-xs" style="background-color: var(--theme-bg); border-color: var(--theme-card-border); color: var(--theme-text);">
                <div class="font-bold mb-0.5 text-indigo-500"><i class="fas fa-map-pin mr-1"></i>Mogelijke kandidaten (${geo.total_candidates} gevonden):</div>
                <div class="max-h-24 overflow-y-auto space-y-1 mt-1 pr-1 font-mono">
                    ${geo.candidates.map(c => `<div>${c.rd_x}, ${c.rd_y} (${c.distance_meters ? Math.round(c.distance_meters) + 'm' : '-'})</div>`).join('')}
                </div>
            </div>`;
    }

    if (latest && geo.distance_km !== null) {
        const distStr = geo.distance_meters < 1000 ? `${Math.round(geo.distance_meters)} m` : `${geo.distance_km} km`;
        statsHtml += `
            <div class="p-2.5 rounded border text-xs" style="background-color: var(--theme-bg); border-color: var(--theme-card-border); color: var(--theme-text);">
                <div class="font-bold mb-1 opacity-90"><i class="fas fa-route mr-1 text-blue-500"></i>Afstand tot laatste locatie:</div>
                <div><strong>${distStr}</strong> van laatste ${latest.type} (${latest.time_ago})</div>
            </div>`;
    } else if (!latest) {
        statsHtml += `
            <div class="p-2.5 rounded border text-xs opacity-90" style="background-color: var(--theme-bg); border-color: var(--theme-card-border);">
                <i class="fas fa-info-circle mr-1 text-amber-500"></i>Nog geen eerdere locaties bekend voor ${subarea}.
            </div>`;
    }

    // Historical trail points list
    if (history.length > 0) {
        statsHtml += `
            <div class="pt-2">
                <div class="text-xs font-bold opacity-70 mb-1.5 uppercase tracking-wider">Eerdere punten (${history.length}):</div>
                <div class="space-y-1.5 max-h-32 overflow-y-auto pr-1">
                    ${history.map((pt, idx) => `
                        <div class="flex items-center justify-between text-xs p-1.5 rounded border" style="background-color: var(--theme-bg); border-color: var(--theme-card-border); color: var(--theme-text);">
                            <div class="flex items-center gap-1.5">
                                <span class="w-4 h-4 rounded-full flex items-center justify-center text-[10px] text-white font-bold" style="background-color: ${getBadgeColorByType(pt.type)}">${idx + 1}</span>
                                <span class="font-semibold">${pt.type}</span>
                            </div>
                            <span class="opacity-60 text-[11px]">${pt.time_ago}</span>
                        </div>
                    `).join('')}
                </div>
            </div>`;
    }

    statsHtml += `</div>`;
    if (infoPanel) infoPanel.innerHTML = statsHtml;

    // Show Direct Save button if coordinate is complete
    if (modalSaveBtn) {
        if (geo.is_complete) {
            modalSaveBtn.classList.remove("hidden");
        } else {
            modalSaveBtn.classList.add("hidden");
        }
    }

    // 2. Initialize or Update Mapbox GL Map
    const mapContainer = document.getElementById("probe-modal-map");
    if (!mapContainer || typeof mapboxgl === "undefined") return;

    if (!probeMapInitialized) {
        probeMap = new mapboxgl.Map({
            container: "probe-modal-map",
            style: "mapbox://styles/mapbox/streets-v12",
            center: [5.8762, 51.9876], // Gelderland / HQ default
            zoom: 11
        });
        probeMap.addControl(new mapboxgl.NavigationControl(), "top-right");
        probeMapInitialized = true;
    }

    // Clear existing markers
    probeMarkers.forEach(m => m.remove());
    probeMarkers = [];

    // Ensure map is properly sized
    setTimeout(() => {
        if (!probeMap) return;
        probeMap.resize();

        // Clear existing polygon layer / source
        if (probeMap.getSource("bbox-source")) {
            if (probeMap.getLayer("bbox-layer-fill")) probeMap.removeLayer("bbox-layer-fill");
            if (probeMap.getLayer("bbox-layer-line")) probeMap.removeLayer("bbox-layer-line");
            probeMap.removeSource("bbox-source");
        }

        // Clear existing trail layer / source
        if (probeMap.getSource("trail-source")) {
            if (probeMap.getLayer("trail-layer-line")) probeMap.removeLayer("trail-layer-line");
            probeMap.removeSource("trail-source");
        }

        const bounds = new mapboxgl.LngLatBounds();
        let targetCenter = null;

        // Render Candidate
        if (geo.kind === "single" && geo.lat && geo.lon) {
            targetCenter = [geo.lon, geo.lat];
            bounds.extend(targetCenter);

            const el = document.createElement("div");
            el.className = "w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold shadow-lg border-2 border-white text-xs";
            el.innerHTML = '<i class="fas fa-crosshairs"></i>';

            const popup = new mapboxgl.Popup({ offset: 15 }).setHTML(`
                <div class="p-1 text-xs">
                    <strong class="text-blue-600 block mb-0.5">Kandidaat Punt</strong>
                    <div>RD: ${geo.rd_x}, ${geo.rd_y}</div>
                    <div class="opacity-70">${geo.lat}, ${geo.lon}</div>
                </div>
            `);

            const marker = new mapboxgl.Marker(el).setLngLat(targetCenter).setPopup(popup).addTo(probeMap);
            probeMarkers.push(marker);
        } else if (geo.kind === "bbox" && geo.polygon_coords) {
            targetCenter = [geo.center.lon, geo.center.lat];
            geo.polygon_coords.forEach(coord => bounds.extend(coord));

            probeMap.addSource("bbox-source", {
                type: "geojson",
                data: {
                    type: "Feature",
                    geometry: {
                        type: "Polygon",
                        coordinates: [geo.polygon_coords]
                    }
                }
            });

            probeMap.addLayer({
                id: "bbox-layer-fill",
                type: "fill",
                source: "bbox-source",
                paint: {
                    "fill-color": "#3B82F6",
                    "fill-opacity": 0.25
                }
            });

            probeMap.addLayer({
                id: "bbox-layer-line",
                type: "line",
                source: "bbox-source",
                paint: {
                    "line-color": "#2563EB",
                    "line-width": 2,
                    "line-dasharray": [2, 2]
                }
            });

            // Add center marker
            const el = document.createElement("div");
            el.className = "w-7 h-7 rounded-full bg-blue-500/80 text-white flex items-center justify-center font-bold text-xs border border-white shadow";
            el.innerHTML = '<i class="fas fa-vector-square"></i>';
            const marker = new mapboxgl.Marker(el).setLngLat(targetCenter).addTo(probeMap);
            probeMarkers.push(marker);
        } else if (geo.kind === "cluster" && geo.candidates) {
            geo.candidates.forEach((cand, idx) => {
                const pt = [cand.lon, cand.lat];
                bounds.extend(pt);
                if (idx === 0) targetCenter = pt;

                const el = document.createElement("div");
                el.className = "w-6 h-6 rounded-full bg-indigo-600 text-white flex items-center justify-center font-bold text-[10px] border border-white shadow";
                el.innerText = idx + 1;

                const popup = new mapboxgl.Popup({ offset: 12 }).setHTML(`
                    <div class="p-1 text-xs">
                        <strong class="text-indigo-600">Optie #${idx + 1}</strong>
                        <div>RD: ${cand.rd_x}, ${cand.rd_y}</div>
                    </div>
                `);

                const marker = new mapboxgl.Marker(el).setLngLat(pt).setPopup(popup).addTo(probeMap);
                probeMarkers.push(marker);
            });
        }

        // Render Historical Fox Trail Points
        const trailCoords = [];
        history.forEach((pt, idx) => {
            const ptCoords = [pt.lon, pt.lat];
            bounds.extend(ptCoords);
            trailCoords.push(ptCoords);

            const el = document.createElement("div");
            el.className = "w-7 h-7 rounded-full text-white flex items-center justify-center font-bold text-xs border-2 border-white shadow";
            el.style.backgroundColor = getBadgeColorByType(pt.type);
            el.innerText = idx + 1;

            const popup = new mapboxgl.Popup({ offset: 15 }).setHTML(`
                <div class="p-1 text-xs">
                    <strong class="block" style="color: ${getBadgeColorByType(pt.type)}">${pt.type} #${idx + 1}</strong>
                    <div class="opacity-80">${pt.time_ago}</div>
                    <div class="text-[11px] opacity-60">${pt.timestamp}</div>
                </div>
            `);

            const marker = new mapboxgl.Marker(el).setLngLat(ptCoords).setPopup(popup).addTo(probeMap);
            probeMarkers.push(marker);
        });

        // Add connecting trail polyline
        if (targetCenter && trailCoords.length > 0) {
            const allTrailPoints = [...trailCoords.slice().reverse(), targetCenter];
            probeMap.addSource("trail-source", {
                type: "geojson",
                data: {
                    type: "Feature",
                    geometry: {
                        type: "LineString",
                        coordinates: allTrailPoints
                    }
                }
            });

            probeMap.addLayer({
                id: "trail-layer-line",
                type: "line",
                source: "trail-source",
                paint: {
                    "line-color": "#64748B",
                    "line-width": 2,
                    "line-dasharray": [3, 2]
                }
            });
        }

        // Fit Bounds
        if (!bounds.isEmpty()) {
            probeMap.fitBounds(bounds, { padding: 50, maxZoom: 15 });
        } else if (targetCenter) {
            probeMap.setCenter(targetCenter);
            probeMap.setZoom(12);
        }
    }, 200);
}

/**
 * Toast notification widget.
 */
function showHintToast(message, type = "info") {
    let container = document.getElementById("hint-toast-container");
    if (!container) {
        container = document.createElement("div");
        container.id = "hint-toast-container";
        container.className = "fixed bottom-5 right-5 z-50 flex flex-col gap-2 pointer-events-none";
        document.body.appendChild(container);
    }

    const toast = document.createElement("div");
    const bg = type === "success" ? "bg-green-600" : (type === "warning" ? "bg-amber-600" : (type === "error" ? "bg-red-600" : "bg-blue-600"));
    const icon = type === "success" ? "fa-check-circle" : (type === "warning" ? "fa-exclamation-triangle" : (type === "error" ? "fa-times-circle" : "fa-info-circle"));

    toast.className = `${bg} text-white px-4 py-3 rounded-lg shadow-xl text-sm flex items-center gap-2 transform transition-all duration-300 translate-y-4 opacity-0 pointer-events-auto max-w-sm`;
    toast.innerHTML = `<i class="fas ${icon} text-base flex-shrink-0"></i><span>${message}</span>`;
    container.appendChild(toast);

    requestAnimationFrame(() => {
        toast.classList.remove("translate-y-4", "opacity-0");
    });

    setTimeout(() => {
        toast.classList.add("translate-y-4", "opacity-0");
        setTimeout(() => toast.remove(), 300);
    }, 3500);
}

function getBadgeColorByType(type) {
    if (type === "Hunt") return "#EF4444";
    if (type === "Spot") return "#F97316";
    if (type === "Hint") return "#8B5CF6";
    return "#3B82F6";
}

function getFoxColorByName(foxName) {
    const foxColors = {
        "Alpha": "#9829FF",
        "Bravo": "#36D12B",
        "Charlie": "#FF8A00",
        "Delta": "#F5F02C",
        "Echo": "#FFA12E",
        "Foxtrot": "#F52E2B",
        "Golf": "#FF6F6F",
        "Hotel": "#00BFA5",
        "Oscar": "#2563EB"
    };
    return foxColors[foxName] || "#000000";
}
