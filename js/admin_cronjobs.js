/**
 * js/admin_cronjobs.js ??? Real-time countdown refresh and JSON status polling for cron jobs.
 */

let countAmount = 0;

function initCronjobs() {
    countAmount = document.getElementsByClassName("cronTimer").length;
    setInterval(() => {
        TimerRefresh();
    }, 1000);

    setInterval(() => {
        cronjobs();
    }, 5000);
}

function TimerRefresh() {
    for (let i = 0; i < countAmount; i++) {
        const timerEl = document.getElementById(`timer${i}`);
        if (!timerEl) continue;
        let value = parseInt(timerEl.getAttribute("data-interval"), 10);
        if (value <= 0) {
            timerEl.innerHTML = "Wachten op cronjob...";
        } else {
            value -= 1;
            timerEl.setAttribute("data-interval", value);
            timerEl.innerHTML = `Over: ${value} sec`;
        }
    }
}

async function cronjobs() {
    try {
        const res = await fetch("cronjobs_helper.php");
        const json = await res.json();

        for (let i = 0; i < json.length; i++) {
            const timerEl = document.getElementById(`timer${i}`);
            const stateEl = document.getElementById(`state${i}`);
            if (timerEl) timerEl.setAttribute("data-interval", json[i].interval);
            if (stateEl) {
                stateEl.className = "flex items-center space-x-2";
                if (json[i].status === "actief") {
                    stateEl.innerHTML = '<span class="inline-block w-2.5 h-2.5 bg-green-500 rounded-full animate-pulse"></span><span class="text-sm font-semibold text-green-700 dark:text-green-400">Actief</span>';
                } else if (json[i].status === "inactief") {
                    stateEl.innerHTML = '<span class="inline-block w-2.5 h-2.5 bg-red-500 rounded-full"></span><span class="text-sm font-semibold text-red-700 dark:text-red-400">Inactief</span>';
                } else {
                    stateEl.innerHTML = '<span class="inline-block w-2.5 h-2.5 bg-yellow-500 rounded-full"></span><span class="text-sm font-semibold text-yellow-700 dark:text-yellow-400">Onbekend</span>';
                }
            }
        }
    } catch (e) {
        console.error("Invalid JSON from cronjobs_helper.php:", e);
    }
}

document.addEventListener("DOMContentLoaded", initCronjobs);
