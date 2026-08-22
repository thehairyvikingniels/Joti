(function() {
    console.log("[Kiosk] Kiosk Controller active");

    let idleTimer = null;
    let lastActivityTime = Date.now();
    let currentRefreshInterval = 0;

    function getRandomPollingInterval() {
        // Random between 5000ms (5s) and 15000ms (15s) to spread server load
        return Math.floor(Math.random() * 10000) + 5000;
    }

    function checkKioskStatus() {
        fetch('/kiosk.php?action=status', { credentials: 'same-origin' })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Kiosk status request failed: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.error) {
                    console.warn("[Kiosk] Status error:", data.error);
                    return;
                }

                // Check remote redirect
                if (data.doel_pagina) {
                    let targetPage = data.doel_pagina.trim();
                    let currentPath = window.location.pathname;

                    // Normalize targetPage and currentPath for comparison
                    let normalizedTarget = targetPage.startsWith('/') ? targetPage : '/' + targetPage;
                    let normalizedCurrent = currentPath.endsWith('/') && currentPath.length > 1 ? currentPath.slice(0, -1) : currentPath;

                    // Stripping file extension if any or trailing slashes for clean matching
                    if (normalizedTarget !== normalizedCurrent && !normalizedCurrent.endsWith(normalizedTarget)) {
                        console.log("[Kiosk] Remote redirecting from", currentPath, "to", targetPage);
                        window.location.href = targetPage;
                        return;
                    }
                }

                // Update idle refresh interval if changed
                if (typeof data.refresh_interval === 'number') {
                    setupIdleRefresh(data.refresh_interval);
                }
            })
            .catch(err => {
                console.warn("[Kiosk] Status check failed:", err.message);
                if (window.location.pathname !== '/offline.php') {
                    window.location.href = '/offline.php';
                }
            })
            .finally(() => {
                // Schedule next status check with random jitter
                setTimeout(checkKioskStatus, getRandomPollingInterval());
            });
    }

    function resetActivity() {
        lastActivityTime = Date.now();
    }

    function setupIdleRefresh(intervalSeconds) {
        currentRefreshInterval = intervalSeconds;
        if (idleTimer) {
            clearInterval(idleTimer);
            idleTimer = null;
        }

        if (intervalSeconds <= 0) return;

        // Listen for user interaction events to reset activity timer
        ['mousemove', 'mousedown', 'click', 'touchstart', 'scroll', 'keydown', 'input', 'pointerdown'].forEach(eventType => {
            window.addEventListener(eventType, resetActivity, { passive: true, capture: true });
        });

        idleTimer = setInterval(() => {
            if (currentRefreshInterval > 0) {
                let inactiveSeconds = (Date.now() - lastActivityTime) / 1000;
                if (inactiveSeconds >= currentRefreshInterval) {
                    console.log("[Kiosk] Idle timeout reached. Refreshing page...");
                    window.location.reload();
                }
            }
        }, 1000);
    }

    // Start initial status check
    setTimeout(checkKioskStatus, getRandomPollingInterval());

})();
