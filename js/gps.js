// js/gps.js — Periodic GPS location reporting to functies.php
function initGpsTracking(enabled) {
    if (enabled !== 'true') return;
    setInterval(() => {
        if (!navigator.geolocation) return;
        navigator.geolocation.getCurrentPosition((position) => {
            fetch(`functies.php?lat=${position.coords.latitude}&lon=${position.coords.longitude}`)
                .catch(err => console.error('GPS update failed:', err));
        });
    }, 5555);
}
