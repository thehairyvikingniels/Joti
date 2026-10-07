/**
 * js/admin_cronjobs.js
 *
 * Real-time countdown timers, master cron runner heartbeat synchronization,
 * manual execution trigger, and execution logs modal viewer for Jotify.
 */

let countAmount = 0;
const cronTimers = [];

/**
 * Toast notification helper for in-DOM non-blocking alerts.
 * @param {string} message
 * @param {string} type ('success' | 'warning' | 'error' | 'info')
 */
function showToast(message, type = 'info', duration = 3500) {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = 'toast-animate pointer-events-auto flex items-center justify-between gap-3 px-4 py-3 rounded-xl border shadow-xl text-xs font-medium transition-all duration-300';

    let icon = 'fa-info-circle';
    let colorClasses = 'bg-blue-500/15 border-blue-500/40 text-blue-700 dark:text-blue-300';

    if (type === 'success') {
        icon = 'fa-check-circle';
        colorClasses = 'bg-emerald-500/15 border-emerald-500/40 text-emerald-700 dark:text-emerald-300';
    } else if (type === 'error') {
        icon = 'fa-triangle-exclamation';
        colorClasses = 'bg-red-500/15 border-red-500/40 text-red-700 dark:text-red-300';
    } else if (type === 'warning') {
        icon = 'fa-circle-exclamation';
        colorClasses = 'bg-amber-500/15 border-amber-500/40 text-amber-700 dark:text-amber-300';
    }

    toast.className += ` ${colorClasses}`;
    toast.innerHTML = `
        <div class="flex items-center gap-2.5">
            <i class="fas ${icon} text-base flex-shrink-0"></i>
            <span class="break-words">${escapeHtml(message)}</span>
        </div>
        <button type="button" class="opacity-60 hover:opacity-100 transition p-1" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;

    container.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(10px)';
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

/**
 * Initialize cron timers from rendered DOM and start intervals.
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

    // Escape listener for modal
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeCronLogsModal();
        }
    });
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
        timerEl.className = "font-medium theme-primary";
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
 * Toggle active status of a cronjob.
 * @param {string} name
 */
async function toggleCron(name) {
    try {
        const response = await fetch(`cronjobs_helper.php?toggleCron=${encodeURIComponent(name)}`);
        if (response.ok) {
            CronRefresh();
            showToast(`Status van '${name}' succesvol aangepast.`, 'success', 2500);
        } else {
            showToast(`Fout bij aanpassen status van '${name}'.`, 'error');
        }
    } catch (err) {
        console.error("Error toggling cronjob:", err);
        showToast("Netwerkfout bij aanpassen status.", 'error');
    }
}

/**
 * Trigger immediate execution of the master cron runner (cron/index.php).
 */
async function triggerMasterCron() {
    const btn = document.getElementById('btn-run-master-cron');
    const icon = document.getElementById('icon-run-master');
    if (btn) btn.disabled = true;
    if (icon) icon.className = 'fas fa-circle-notch fa-spin text-[10px]';

    try {
        const response = await fetch('cronjobs_helper.php?run_master=1');
        const data = await response.json();

        if (data && data.success) {
            showToast('Master cron runner succesvol aangeroepen!', 'success');
            if (data.master_cron) {
                updateMasterCronUI(data.master_cron);
            }
            CronRefresh();
        } else {
            showToast('Fout bij uitvoeren van master cron.', 'error');
        }
    } catch (err) {
        console.error('Error triggering master cron:', err);
        showToast('Netwerkfout bij aanroepen master cron.', 'error');
    } finally {
        if (btn) btn.disabled = false;
        if (icon) icon.className = 'fas fa-play text-[10px]';
    }
}

/**
 * Update the Master Cron Checker card UI elements.
 * @param {object} master
 */
function updateMasterCronUI(master) {
    if (!master) return;

    const badge = document.getElementById('master-cron-badge');
    const badgeIcon = document.getElementById('master-cron-badge-icon');
    const badgeText = document.getElementById('master-cron-badge-text');
    const relativeText = document.getElementById('master-cron-relative');
    const timestampText = document.getElementById('master-cron-timestamp');
    const durationText = document.getElementById('master-cron-duration');
    const tasksText = document.getElementById('master-cron-tasks');

    if (badge && badgeIcon && badgeText) {
        if (master.status === 'healthy') {
            badge.className = 'px-2.5 py-1 rounded-full text-xs font-bold border flex items-center gap-1.5 bg-emerald-500/15 border-emerald-500/40 text-emerald-600 dark:text-emerald-400';
            badgeIcon.className = 'fas fa-circle-check';
            badgeText.textContent = 'Actief (Gezond)';
        } else if (master.status === 'warning') {
            badge.className = 'px-2.5 py-1 rounded-full text-xs font-bold border flex items-center gap-1.5 bg-amber-500/15 border-amber-500/40 text-amber-600 dark:text-amber-400';
            badgeIcon.className = 'fas fa-triangle-exclamation';
            badgeText.textContent = 'Vertraagd (Let op)';
        } else {
            badge.className = 'px-2.5 py-1 rounded-full text-xs font-bold border flex items-center gap-1.5 bg-red-500/15 border-red-500/40 text-red-600 dark:text-red-400';
            badgeIcon.className = 'fas fa-circle-xmark';
            badgeText.textContent = master.last_run ? 'Inactief / Gestopt' : 'Nooit uitgevoerd';
        }
    }

    if (relativeText) {
        if (master.last_run && master.seconds_ago !== null) {
            relativeText.textContent = `${master.seconds_ago}s geleden`;
        } else {
            relativeText.textContent = 'Geen data';
        }
    }

    if (timestampText) {
        timestampText.textContent = master.last_run_formatted || 'Nooit';
    }

    if (durationText && master.info) {
        const dur = master.info.duration_ms !== undefined ? `${master.info.duration_ms} ms` : '—';
        const sapi = master.info.sapi ? ` (${master.info.sapi.toUpperCase()})` : '';
        durationText.innerHTML = `${dur} <span class="text-xs opacity-60 font-normal">${sapi}</span>`;
    }

    if (tasksText && master.info) {
        const tasks = master.info.tasks_dispatched !== undefined ? `${master.info.tasks_dispatched} taken` : '—';
        tasksText.textContent = tasks;
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

        // Support both array and object response structure
        const cronList = Array.isArray(json) ? json : (json.cronjobs || []);
        const masterCron = json.master_cron || null;

        if (masterCron) {
            updateMasterCronUI(masterCron);
        }

        countAmount = cronList.length;

        for (let i = 0; i < cronList.length; i++) {
            const isEnabled = (cronList[i].raw_enabled !== undefined)
                ? (cronList[i].raw_enabled === 1)
                : (cronList[i].enabled ? cronList[i].enabled.includes("toggle-on") : true);

            let seconds = cronList[i].raw_seconds;
            if (typeof seconds === "undefined") {
                seconds = parseInt(cronList[i].exec_next, 10);
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
                if (cronList[i].exec_status === 200) {
                    colorClass = "text-green-500";
                } else if (cronList[i].exec_status === 429) {
                    colorClass = "text-yellow-500";
                } else if (cronList[i].exec_status === 500) {
                    colorClass = "text-red-500";
                } else if (cronList[i].exec_status !== null) {
                    colorClass = "text-red-500";
                }
                cronStatus.className = `${colorClass} text-sm`;
                cronStatus.title = `HTML ${cronList[i].exec_status} code.`;
            }

            if (cronName) {
                cronName.textContent = cronList[i].name;
            }
            const cronDesc = document.getElementById(`cron_desc_${i}`);
            if (cronDesc && cronList[i].description) {
                cronDesc.textContent = cronList[i].description;
            }
            if (cronInterval) cronInterval.innerHTML = cronList[i].interval;
            if (cronExecTime) cronExecTime.innerHTML = cronList[i].exec_time;
            if (cronExecLength) cronExecLength.innerHTML = cronList[i].exec_length;
        }
    } catch (e) {
        console.error("Error updating cronjobs status from helper:", e);
    }
}

// ==========================================
// Cron Execution Logs Modal
// ==========================================

async function openCronLogsModal(cronName) {
    const modal = document.getElementById('modal-cron-logs');
    const titleEl = document.getElementById('modal-logs-cron-name');
    const loadingEl = document.getElementById('modal-logs-loading');
    const emptyEl = document.getElementById('modal-logs-empty');
    const container = document.getElementById('modal-logs-container');
    const countInfo = document.getElementById('modal-logs-count-info');

    if (!modal) return;

    if (titleEl) titleEl.textContent = cronName;
    modal.classList.remove('hidden');

    if (loadingEl) loadingEl.classList.remove('hidden');
    if (emptyEl) emptyEl.classList.add('hidden');
    if (container) container.innerHTML = '';
    if (countInfo) countInfo.textContent = 'Laden...';

    try {
        const response = await fetch(`cronjobs_helper.php?logs=${encodeURIComponent(cronName)}`);
        const data = await response.json();

        if (loadingEl) loadingEl.classList.add('hidden');

        if (!data || !data.success || !data.logs || data.logs.length === 0) {
            if (emptyEl) emptyEl.classList.remove('hidden');
            if (countInfo) countInfo.textContent = '0 logs gevonden';
            return;
        }

        if (countInfo) {
            countInfo.textContent = `${data.logs.length} recente logs getoond`;
        }

        renderLogsList(data.logs);
    } catch (err) {
        console.error('Error fetching cron logs:', err);
        if (loadingEl) loadingEl.classList.add('hidden');
        if (emptyEl) {
            emptyEl.classList.remove('hidden');
            emptyEl.innerHTML = `<p class="text-red-500 font-semibold">Fout bij ophalen van logs: ${escapeHtml(err.message)}</p>`;
        }
        showToast('Fout bij ophalen van logs.', 'error');
    }
}

function closeCronLogsModal() {
    const modal = document.getElementById('modal-cron-logs');
    if (modal) {
        modal.classList.add('hidden');
    }
}

/**
 * Render the logs array in the modal container.
 * @param {Array} logs
 */
function renderLogsList(logs) {
    const container = document.getElementById('modal-logs-container');
    if (!container) return;
    container.innerHTML = '';

    logs.forEach((log, index) => {
        let statColorClass = 'bg-emerald-500/15 border-emerald-500/40 text-emerald-600 dark:text-emerald-400';
        let statIcon = 'fa-check';

        if (log.exec_stat === 429) {
            statColorClass = 'bg-amber-500/15 border-amber-500/40 text-amber-600 dark:text-amber-400';
            statIcon = 'fa-hourglass-end';
        } else if (log.exec_stat === 500 || log.exec_stat !== 200) {
            statColorClass = 'bg-red-500/15 border-red-500/40 text-red-600 dark:text-red-400';
            statIcon = 'fa-triangle-exclamation';
        }

        const isFirst = (index === 0);
        const card = document.createElement('div');
        card.className = 'border rounded-xl bg-black/5 dark:bg-white/5 overflow-hidden transition';
        card.style.borderColor = 'var(--theme-card-border)';

        const logId = `log-output-${index}`;
        const outputText = log.exec_output ? log.exec_output.trim() : '(Geen uitvoer geregistreerd)';

        card.innerHTML = `
            <div class="p-3.5 flex items-center justify-between cursor-pointer hover:bg-black/5 dark:hover:bg-white/5 transition" onclick="toggleLogOutput('${logId}')">
                <div class="flex items-center gap-3">
                    <span class="px-2 py-0.5 rounded-full text-xs font-bold border flex items-center gap-1 ${statColorClass}">
                        <i class="fas ${statIcon} text-[10px]"></i>
                        <span>${log.exec_stat}</span>
                    </span>
                    <div>
                        <span class="font-mono text-xs font-bold block">${escapeHtml(log.exec_time_formatted)}</span>
                        <span class="text-[11px] opacity-60">Looptijd: <strong>${log.exec_length_formatted}</strong> (${log.exec_length_ms} ms)</span>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <span class="text-xs opacity-60 text-right hidden sm:block max-w-[200px] truncate">${escapeHtml(outputText)}</span>
                    <i id="${logId}-chevron" class="fas ${isFirst ? 'fa-chevron-up' : 'fa-chevron-down'} text-xs opacity-60 transition-transform"></i>
                </div>
            </div>

            <div id="${logId}" class="${isFirst ? '' : 'hidden'} border-t p-3 bg-black/10 dark:bg-black/40 space-y-2" style="border-color: var(--theme-card-border);">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-wider opacity-60">Uitvoer (Output):</span>
                    <button type="button" onclick="copyLogOutput('${logId}-code')" class="text-xs opacity-70 hover:opacity-100 transition flex items-center gap-1" style="color: var(--theme-text);">
                        <i class="fas fa-copy"></i>
                        <span>Kopiëren</span>
                    </button>
                </div>
                <pre id="${logId}-code" class="p-3 rounded-lg text-xs font-mono overflow-x-auto whitespace-pre-wrap break-all bg-slate-900 text-slate-100 max-h-60 leading-relaxed shadow-inner border border-slate-700/50">${escapeHtml(outputText)}</pre>
            </div>
        `;

        container.appendChild(card);
    });
}

function toggleLogOutput(elementId) {
    const el = document.getElementById(elementId);
    const chevron = document.getElementById(`${elementId}-chevron`);
    if (!el) return;

    if (el.classList.contains('hidden')) {
        el.classList.remove('hidden');
        if (chevron) chevron.className = 'fas fa-chevron-up text-xs opacity-60 transition-transform';
    } else {
        el.classList.add('hidden');
        if (chevron) chevron.className = 'fas fa-chevron-down text-xs opacity-60 transition-transform';
    }
}

function copyLogOutput(codeElementId) {
    const el = document.getElementById(codeElementId);
    if (!el) return;

    navigator.clipboard.writeText(el.textContent).then(() => {
        showToast('Uitvoer gekopieerd naar klembord!', 'success', 2000);
    }).catch(() => {
        showToast('Kopiëren mislukt.', 'error');
    });
}

document.addEventListener("DOMContentLoaded", initCronjobs);
