/**
 * js/maps.js ??? Interactive Mapbox visualizer for fox tracks, search circles, cars, groups, and users.
 */

let mapInstance = null;
let activeCarPaths = {};

/**
 * Generate a GeoJSON polygon representing a circular geographic radius.
 * @param {Array<number>} center [lon, lat]
 * @param {number} radiusInKm
 * @param {number} points
 * @returns {object}
 */
function createGeoJSONCircle(center, radiusInKm, points = 64) {
    const coords = { latitude: center[1], longitude: center[0] };
    const distanceX = radiusInKm / (111.320 * Math.cos(coords.latitude * Math.PI / 180));
    const distanceY = radiusInKm / 110.574;
    const ret = [];
    for (let i = 0; i < points; i++) {
        const theta = (i / points) * (2 * Math.PI);
        const x = distanceX * Math.cos(theta);
        const y = distanceY * Math.sin(theta);
        ret.push([coords.longitude + x, coords.latitude + y]);
    }
    ret.push(ret[0]);
    return {
        type: "geojson",
        data: {
            type: "FeatureCollection",
            features: [{ type: "Feature", geometry: { type: "Polygon", coordinates: [ret] } }]
        }
    };
}

/**
 * Post current center coordinates and zoom level to parent frame.
 */
function sendMapState() {
    if (!mapInstance) return;
    const center = mapInstance.getCenter();
    const zoom = mapInstance.getZoom();
    parent.postMessage(
        {
            type: "mapUpdate",
            state: {
                lon: center.lng,
                lat: center.lat,
                zoom: zoom
            }
        },
        "*"
    );
}

/**
 * Draw historical route polyline for a specific vehicle.
 * @param {string} carKey
 */
function drawCarPath(carKey) {
    removeCarPath();
    const coords = activeCarPaths[carKey];
    if (!coords || coords.length === 0 || !mapInstance) return;

    if (!mapInstance.getSource("car_path_source")) {
        mapInstance.addSource("car_path_source", {
            type: "geojson",
            data: {
                type: "Feature",
                properties: {},
                geometry: {
                    type: "LineString",
                    coordinates: coords
                }
            }
        });
        mapInstance.addLayer({
            id: "car_path_layer",
            type: "line",
            source: "car_path_source",
            layout: { "line-join": "round", "line-cap": "round" },
            paint: { "line-color": "#3B82F6", "line-width": 4, "line-opacity": 0.8 }
        });
    } else {
        mapInstance.getSource("car_path_source").setData({
            type: "Feature",
            properties: {},
            geometry: {
                type: "LineString",
                coordinates: coords
            }
        });
    }
}

/**
 * Remove active vehicle route polyline.
 */
function removeCarPath() {
    if (!mapInstance) return;
    if (mapInstance.getLayer("car_path_layer")) {
        mapInstance.removeLayer("car_path_layer");
    }
    if (mapInstance.getSource("car_path_source")) {
        mapInstance.removeSource("car_path_source");
    }
}

/**
 * Initialize Mapbox map with full layer data.
 * @param {object} data
 */
function initJotiMap(data) {
    if (typeof mapboxgl === "undefined") return;
    mapboxgl.accessToken = data.apiKey || "";

    mapInstance = new mapboxgl.Map({
        container: "map",
        style: "mapbox://styles/mapbox/streets-v11",
        center: data.center || [5.910389, 52.121581],
        zoom: data.zoom || 9
    });

    mapInstance.on("moveend", sendMapState);
    mapInstance.on("zoomend", sendMapState);
    mapInstance.on("load", sendMapState);

    // 1. Target single point marker if specified
    if (data.targetPoint) {
        new mapboxgl.Marker().setLngLat(data.targetPoint).addTo(mapInstance);
    }

    // 2. Scout group markers
    if (data.groups && data.groups.length > 0) {
        data.groups.forEach((g) => {
            const el = document.createElement("div");
            el.className = "marker";
            el.style.backgroundImage = `url(media/icons/pin_hut_${g.deelgebied || "unknown"}.png)`;
            el.style.width = "40px";
            el.style.height = "32px";
            el.style.backgroundSize = "100%";

            const popup = new mapboxgl.Popup().setHTML(
                `<h4>${g.naam}</h4><p>Deelgebied: ${g.deelgebied}</p><a href='https://www.google.com/maps/dir/?api=1&origin=&destination=${encodeURIComponent(g.address)}' target='_blank'>Navigeer</a>`
            );
            new mapboxgl.Marker(el).setLngLat([g.lon, g.lat]).setPopup(popup).addTo(mapInstance);
        });
    }

    // 3. Vehicle markers and path associations
    if (data.cars && data.cars.length > 0) {
        data.cars.forEach((c) => {
            const carKey = c.kenteken.replace(/-/g, "_");
            activeCarPaths[carKey] = c.path || [];

            const el = document.createElement("div");
            el.className = "marker";
            el.style.backgroundImage = "url(media/icons/pin_auto.png)";
            el.style.width = "40px";
            el.style.height = "32px";
            el.style.backgroundSize = "100%";

            const popupHtml = `<h4>${c.kenteken.toUpperCase()}</h4><p><strong>Inzittenden:</strong> ${c.bijrijders || "Onbekend"}</p><p>${c.timeAgo}</p><a href='https://www.google.com/maps/dir/?api=1&destination=${c.lat},${c.lon}' target='_blank'>Navigeer</a>`;
            const popup = new mapboxgl.Popup().setHTML(popupHtml);

            const marker = new mapboxgl.Marker(el).setLngLat([c.lon, c.lat]).setPopup(popup).addTo(mapInstance);
            marker.getElement().addEventListener("click", () => {
                drawCarPath(carKey);
            });
            popup.on("close", () => {
                removeCarPath();
            });
        });
    }

    // 4. People markers
    if (data.people && data.people.length > 0) {
        data.people.forEach((p) => {
            const el = document.createElement("div");
            el.className = "marker";
            el.style.backgroundImage = "url(media/icons/pin_persoon.png)";
            el.style.width = "40px";
            el.style.height = "32px";
            el.style.backgroundSize = "100%";

            const popup = new mapboxgl.Popup().setHTML(`<h4>${p.voornaam}</h4><p>${p.timeAgo}</p>`);
            new mapboxgl.Marker(el).setLngLat([p.lon, p.lat]).setPopup(popup).addTo(mapInstance);
        });
    }

    // 5. Hint & Hunt location markers
    if (data.hints && data.hints.length > 0) {
        data.hints.forEach((h) => {
            let popupHtml = `<h4>${h.deelgebied} <small>(${h.helft})</small></h4><p><strong>Tijd:</strong> ${h.time}</p><p><strong>Type:</strong> ${h.type}</p>`;
            if (h.door) popupHtml += `<p><strong>Door:</strong> ${h.door}</p>`;
            if (h.code) popupHtml += `<p><strong>Code:</strong> ${h.code}</p>`;
            if (h.opmerking) popupHtml += `<p><strong>Opmerking:</strong> ${h.opmerking}</p>`;
            if (h.status) popupHtml += `<p><strong>Status:</strong> ${h.status}</p>`;
            popupHtml += `<a href='https://www.google.com/maps/dir/?api=1&destination=${h.lat},${h.lon}' target='_blank'>Navigeer</a>`;

            const popup = new mapboxgl.Popup().setHTML(popupHtml);
            new mapboxgl.Marker({ color: h.color || "#000000" })
                .setLngLat([h.lon, h.lat])
                .setPopup(popup)
                .addTo(mapInstance);
        });
    }

    // 6. Add GeoJSON layers on map load
    mapInstance.on("load", () => {
        // Search circles
        if (data.searchCircles && data.searchCircles.length > 0) {
            data.searchCircles.forEach((sc) => {
                const circleSource = createGeoJSONCircle(sc.center, sc.radiusKm);
                mapInstance.addSource(`polygon_${sc.id}`, circleSource);
                mapInstance.addLayer({
                    id: `circle_fill_${sc.id}`,
                    type: "fill",
                    source: `polygon_${sc.id}`,
                    paint: { "fill-color": sc.color, "fill-opacity": 0.2 }
                });
                mapInstance.addLayer({
                    id: `circle_border_${sc.id}`,
                    type: "line",
                    source: `polygon_${sc.id}`,
                    paint: { "line-color": sc.color, "line-width": 2 }
                });
            });
        }

        // Fox paths
        if (data.foxPaths && data.foxPaths.length > 0) {
            data.foxPaths.forEach((fp) => {
                mapInstance.addSource(`path_source_${fp.id}`, {
                    type: "geojson",
                    data: {
                        type: "Feature",
                        properties: {},
                        geometry: { type: "LineString", coordinates: fp.coords }
                    }
                });
                mapInstance.addLayer({
                    id: `path_layer_${fp.id}`,
                    type: "line",
                    source: `path_source_${fp.id}`,
                    layout: { "line-join": "round", "line-cap": "round" },
                    paint: { "line-color": fp.color, "line-width": 4, "line-opacity": 0.8 }
                });
            });
        }

        // Predicted routes
        if (data.predictedRoutes && data.predictedRoutes.length > 0) {
            data.predictedRoutes.forEach((pr) => {
                mapInstance.addSource(`pred_source_${pr.id}`, {
                    type: "geojson",
                    data: {
                        type: "Feature",
                        properties: {},
                        geometry: { type: "LineString", coordinates: pr.coords }
                    }
                });
                mapInstance.addLayer({
                    id: `pred_layer_${pr.id}`,
                    type: "line",
                    source: `pred_source_${pr.id}`,
                    layout: { "line-join": "round", "line-cap": "round" },
                    paint: { "line-color": pr.color, "line-width": 3, "line-dasharray": [2, 2], "line-opacity": 0.8 }
                });
            });
        }
    });
}
