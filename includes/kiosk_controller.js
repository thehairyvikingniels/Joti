/**
 * js/kiosk_controller.js ??? Background client-side daemon for active Kiosk screens.
 */

(function() {
    console.log("[Kiosk] Kiosk Controller active");

    let consecutiveFailures = 0;
    let currentRefreshInterval = 0;
    let idleTimer = null;
    let lastActivityTime = Date.now();

    function getRandomPollingInterval() {
        return Math.floor(Math.random() * (7000 - 4000 + 1)) + 4000;
    }

    function checkKioskStatus() {
        fetch('/kiosk.php?action=status', { cache: 'no-store' })
            .then(response => {
                if (response.status === 401 || response.status === 404) {
                    console.log("[Kiosk] No active kiosk session or terminated. Ceasing kiosk daemon.");
                    return null;
                }
                if (!response.ok) {
                    throw new Error('Kiosk status request failed: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (!data) return;
                consecutiveFailures = 0;

                if (data.error) {
                    console.warn("[Kiosk] Status error:", data.error);
                    return;
                }

                if (data.target_page) {
                    let currentPath = window.location.pathname.replace(/^\//, '');
                    let targetPage = data.target_page.replace(/^\//, '');
                    
                    let normCurrent = currentPath.replace(/\.php$/, '');
                    let normTarget = targetPage.replace(/\.php$/, '');

                    if (normCurrent !== normTarget && normTarget !== '') {
                        console.log("[Kiosk] Remote redirecting from", currentPath, "to", targetPage);
                        window.location.href = '/' + targetPage;
                        return;
                    }
                }

                if (typeof data.refresh_interval !== 'undefined') {
                    setupIdleRefresh(parseInt(data.refresh_interval, 10));
                }
            })
            .catch(err => {
                console.warn("[Kiosk] Status check failed:", err.message);
                consecutiveFailures++;
                // Only redirect to offline.php if browser is truly offline or 5 consecutive failures occur
                if (!navigator.onLine && consecutiveFailures >= 3) {
                    if (window.location.pathname !== '/offline.php') {
                        window.location.href = '/offline.php';
                    }
                }
            })
            .finally(() => {
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
