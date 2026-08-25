/**
 * js/admin_cronjobs.js ??? Real-time countdown timer and JSON status polling for cron jobs.
 */

let countAmount = 0;
const cronTimers = [];

/**
 * Initialize cron timers from rendered DOM and start 1s tick and 5s polling intervals.
 */
function initCronjobs() {
    const cards = document.getElementsByClassName("cronTimer");
    countAmount = cards.length;

    for (let i = 0; i < countAmount; i++) {
        const nextEl = document.getElementById(`cron_exec_next_${i}`);
        const enabledEl = document.getElementById(`cron_enabled_${i}`);
        if (!nextEl) continue;

        let isEnabled = true;
        if (nextEl.hasAttribute("data-enabled")) {
            isEnabled = nextEl.getAttribute("data-enabled") === "1";
        } else if (enabledEl) {
            isEnabled = !enabledEl.innerHTML.includes("toggle-off");
        }

        let seconds = parseInt(nextEl.getAttribute("data-seconds"), 10);
        if (isNaN(seconds)) {
            seconds = parseInt(nextEl.textContent, 10);
            if (isNaN(seconds)) seconds = 0;
        }

        cronTimers[i] = {
            seconds: seconds,
            enabled: isEnabled
        };
        renderTimer(i);
    }

    // 1-second countdown tick
    setInterval(TimerRefresh, 1000);

    // 5-second backend status poll
    setInterval(CronRefresh, 5000);
}

/**
 * Toggle active status of a cronjob.
 * @param {string} name
 */
async function toggleCron(name) {
    try {
        const response = await fetch(`cronjobs_helper.php?toggleCron=${encodeURIComponent(name)}`);
        if (response.ok) {
            CronRefresh();
        }
    } catch (err) {
        console.error("Error toggling cronjob:", err);
    }
}

/**
 * Render a single cron timer element based on current state.
 * @param {number} i
 */
function renderTimer(i) {
    const timerEl = document.getElementById(`cron_exec_next_${i}`);
    if (!timerEl || !cronTimers[i]) return;

    if (!cronTimers[i].enabled) {
        timerEl.textContent = " - disabled - ";
        timerEl.className = "font-medium opacity-50";
        return;
    }

    if (cronTimers[i].seconds <= 0) {
        timerEl.textContent = "executing...";
        timerEl.className = "font-bold text-orange-500 animate-pulse";
    } else {
        timerEl.textContent = `${cronTimers[i].seconds} sec`;
        timerEl.className = "font-medium text-blue-600 dark:text-blue-400";
    }
}

/**
 * Decrement each active cron countdown timer by 1 second.
 */
function TimerRefresh() {
    for (let i = 0; i < countAmount; i++) {
        if (!cronTimers[i]) continue;
        if (cronTimers[i].enabled && cronTimers[i].seconds > 0) {
            cronTimers[i].seconds--;
        }
        renderTimer(i);
    }
}

/**
 * Poll cronjobs_helper.php and sync timers and status indicators with backend.
 */
async function CronRefresh() {
    try {
        const response = await fetch("cronjobs_helper.php?cronjobs");
        if (!response.ok) return;
        const json = await response.json();
        countAmount = json.length;

        for (let i = 0; i < json.length; i++) {
            const isEnabled = (json[i].raw_enabled !== undefined)
                ? (json[i].raw_enabled === 1)
                : (json[i].enabled ? json[i].enabled.includes("toggle-on") : true);

            let seconds = json[i].raw_seconds;
            if (typeof seconds === "undefined") {
                seconds = parseInt(json[i].exec_next, 10);
                if (isNaN(seconds)) seconds = 0;
            }

            cronTimers[i] = {
                seconds: seconds,
                enabled: isEnabled
            };
            renderTimer(i);

            const cronEnabled = document.getElementById(`cron_enabled_${i}`);
            const cronStatus = document.getElementById(`cron_status_${i}`);
            const cronName = document.getElementById(`cron_name_${i}`);
            const cronInterval = document.getElementById(`cron_interval_${i}`);
            const cronExecTime = document.getElementById(`cron_exec_time_${i}`);
            const cronExecLength = document.getElementById(`cron_exec_length_${i}`);

            if (cronEnabled) {
                if (isEnabled) {
                    cronEnabled.innerHTML = '<i class="fas fa-toggle-on fa-fw text-green-500 text-xl align-middle"></i>';
                } else {
                    cronEnabled.innerHTML = '<i class="fas fa-toggle-off fa-fw text-gray-400 text-xl align-middle"></i>';
                }
            }

            if (cronStatus) {
                let colorClass = "text-gray-400";
                if (json[i].exec_status === 200) {
                    colorClass = "text-green-500";
                } else if (json[i].exec_status === 429) {
                    colorClass = "text-yellow-500";
                } else if (json[i].exec_status === 500) {
                    colorClass = "text-red-500";
                } else if (json[i].exec_status !== null) {
                    colorClass = "text-red-500";
                }
                cronStatus.className = `${colorClass} text-sm`;
                cronStatus.title = `HTML ${json[i].exec_status} code.`;
            }
            if (cronName) {
                cronName.innerHTML = json[i].name;
                cronName.title = json[i].description;
            }
            if (cronInterval) cronInterval.innerHTML = json[i].interval;
            if (cronExecTime) cronExecTime.innerHTML = json[i].exec_time;
            if (cronExecLength) cronExecLength.innerHTML = json[i].exec_length;
        }
    } catch (e) {
        console.error("Error updating cronjobs status from helper:", e);
    }
}

document.addEventListener("DOMContentLoaded", initCronjobs);
