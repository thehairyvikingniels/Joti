/**
 * js/offline.js ??? Automated connectivity monitoring and auto-recovery for offline screen.
 */

let remainingSeconds = 10;
let countdownElement = null;

function checkConnection() {
    fetch('/functies.php?onlinecheck=1', { cache: 'no-store' })
        .then((response) => {
            if (response.ok || response.status === 401) {
                if (document.referrer && new URL(document.referrer).origin === window.location.origin) {
                    window.location.href = document.referrer;
                } else {
                    window.location.href = '/';
                }
            }
        })
        .catch(() => {
            console.log('[Offline] Server nog niet bereikbaar...');
        });
}

document.addEventListener('DOMContentLoaded', () => {
    countdownElement = document.getElementById('countdown-text');
    setInterval(() => {
        remainingSeconds--;
        if (remainingSeconds < 0) {
            remainingSeconds = 10;
        }
        if (countdownElement) {
            countdownElement.textContent = `${remainingSeconds}s`;
        }
    }, 1000);
    setInterval(checkConnection, 10000);
});
