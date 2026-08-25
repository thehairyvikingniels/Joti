// js/gps.js ??? Periodic GPS location reporting to functies.php
function initGpsTracking(enabled, intervalMs = 5555) {
    if (enabled !== 'true') return;
    const interval = parseInt(intervalMs, 10) || 5555;
    
    function reportGps() {
        if (!navigator.geolocation) return;
        navigator.geolocation.getCurrentPosition((position) => {
            const accuracy = position.coords.accuracy || 10;
            fetch(`functies.php?lat=${position.coords.latitude}&lon=${position.coords.longitude}&accuracy=${accuracy}`)
                .catch(err => console.error('GPS update failed:', err));
        }, (err) => {
            console.warn('Geolocation warning:', err.message);
        }, {
            enableHighAccuracy: true,
            timeout: 5000,
            maximumAge: 0
        });
    }

    reportGps();
    setInterval(reportGps, interval);
}
