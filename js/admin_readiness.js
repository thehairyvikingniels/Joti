/**
 * Pre-Flight Readiness Hub JavaScript Controller
 * Handles diagnostic probes, optimistic checklist interactions, edition archiving, and modal management.
 */

document.addEventListener('DOMContentLoaded', () => {
    initReadinessHub();
});

let state = {
    activeTab: 'diagnostics',
    checklistFilter: 'all',
    checklistItems: [],
    diagnosticChecks: {},
    diagScore: 0,
    checklistPercentage: 0,
    operationalCounts: [],
    archivedEditions: [],
    isDiagnosing: false
};

/**
 * Initialize the Readiness Hub
 */
async function initReadinessHub() {
    setupTabListeners();
    setupChecklistFilterListeners();
    await loadOverview();
    runAllDiagnostics();
}

/**
 * Switch tabs between Diagnostics, Archival, and Checklist
 */
function setupTabListeners() {
    const tabButtons = document.querySelectorAll('[data-tab-target]');
    tabButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const target = btn.getAttribute('data-tab-target');
            switchTab(target);
        });
    });
}

function switchTab(tabId) {
    state.activeTab = tabId;

    // Update tab button styles
    document.querySelectorAll('[data-tab-target]').forEach(btn => {
        const isSelected = btn.getAttribute('data-tab-target') === tabId;
        if (isSelected) {
            btn.classList.add('border-b-2', 'theme-border-primary', 'theme-primary', 'font-bold');
            btn.classList.remove('border-transparent', 'opacity-60');
        } else {
            btn.classList.remove('border-b-2', 'theme-border-primary', 'theme-primary', 'font-bold');
            btn.classList.add('border-transparent', 'opacity-60');
        }
    });

    // Toggle tab panels
    document.querySelectorAll('[data-tab-panel]').forEach(panel => {
        if (panel.getAttribute('data-tab-panel') === tabId) {
            panel.classList.remove('hidden');
        } else {
            panel.classList.add('hidden');
        }
    });
}

/**
 * Category filter for checklist
 */
function setupChecklistFilterListeners() {
    const filterBtns = document.querySelectorAll('[data-checklist-filter]');
    filterBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            state.checklistFilter = btn.getAttribute('data-checklist-filter');
            
            filterBtns.forEach(b => {
                b.classList.remove('theme-bg-primary', 'text-white', 'font-bold', 'active');
                b.classList.add('bg-black/5', 'opacity-70');
            });
            btn.classList.add('theme-bg-primary', 'text-white', 'font-bold', 'active');
            btn.classList.remove('bg-black/5', 'opacity-70');

            renderChecklist();
        });
    });
}

/**
 * Load overview data (checklist, counts, archived editions)
 */
async function loadOverview() {
    try {
        const resp = await fetch('readiness_helper.php?action=get_overview');
        const data = await resp.json();

        if (!data.ok) {
            showAlert(data.error || 'Fout bij het laden van overzicht.', 'error');
            return;
        }

        state.checklistItems = data.checklist.items || [];
        state.checklistPercentage = data.checklist.percentage || 0;
        state.operationalCounts = data.operational.tables || [];
        state.archivedEditions = data.archived_editions || [];

        renderChecklist();
        renderOperationalCounts();
        renderArchivedEditions();
        updateOverallScore();
    } catch (err) {
        console.error('Failed to load overview:', err);
        showAlert('Kan overzichtsgegevens niet ophalen.', 'error');
    }
}

/**
 * Run all 10 diagnostic health probes
 */
async function runAllDiagnostics() {
    if (state.isDiagnosing) return;
    state.isDiagnosing = true;

    const spinIcon = document.getElementById('icon-diag-spin');
    const refreshBtn = document.getElementById('btn-refresh-diag');
    if (spinIcon) spinIcon.classList.add('fa-spin');
    if (refreshBtn) refreshBtn.disabled = true;

    // Set cards to analyzing state
    document.querySelectorAll('.diag-status-badge').forEach(badge => {
        badge.innerHTML = '<i class="fas fa-spinner fa-spin text-blue-400"></i> Analyseren...';
        badge.className = 'diag-status-badge text-xs px-2.5 py-1 rounded-full font-medium bg-blue-500/10 text-blue-400 border border-blue-500/30';
    });

    try {
        const resp = await fetch('readiness_helper.php?action=run_diagnostics');
        const data = await resp.json();

        if (!data.ok) {
            showAlert(data.error || 'Diagnose mislukt.', 'error');
            return;
        }

        state.diagnosticChecks = data.checks || {};
        state.diagScore = data.score || 0;

        renderDiagnosticCards();
        updateOverallScore();
    } catch (err) {
        console.error('Failed to run diagnostics:', err);
        showAlert('Fout bij uitvoeren van systeemanalyse.', 'error');
    } finally {
        state.isDiagnosing = false;
        if (spinIcon) spinIcon.classList.remove('fa-spin');
        if (refreshBtn) refreshBtn.disabled = false;
    }
}

/**
 * Render diagnostic cards with status, latency, and details
 */
function renderDiagnosticCards() {
    for (const [key, check] of Object.entries(state.diagnosticChecks)) {
        const card = document.getElementById(`diag-card-${key}`);
        if (!card) continue;

        const badge = card.querySelector('.diag-status-badge');
        const msgEl = card.querySelector('.diag-msg');
        const detailEl = card.querySelector('.diag-detail');
        const latencyEl = card.querySelector('.diag-latency');

        if (msgEl) msgEl.textContent = check.message;
        if (detailEl) detailEl.textContent = check.details;

        if (latencyEl) {
            if (check.latency_ms !== null && check.latency_ms !== undefined) {
                latencyEl.textContent = `${check.latency_ms}ms`;
                latencyEl.classList.remove('hidden');
            } else {
                latencyEl.classList.add('hidden');
            }
        }

        if (badge) {
            if (check.status === 'ok') {
                badge.innerHTML = '<i class="fas fa-check-circle mr-1"></i> Gereed';
                badge.className = 'diag-status-badge text-xs px-2.5 py-1 rounded-full font-semibold bg-green-500/15 text-green-700 dark:text-green-300 border border-green-500/30';
            } else if (check.status === 'warning') {
                badge.innerHTML = '<i class="fas fa-triangle-exclamation mr-1"></i> Aandacht';
                badge.className = 'diag-status-badge text-xs px-2.5 py-1 rounded-full font-semibold bg-amber-500/15 text-amber-700 dark:text-amber-300 border border-amber-500/30';
            } else {
                badge.innerHTML = '<i class="fas fa-circle-xmark mr-1"></i> Probleem';
                badge.className = 'diag-status-badge text-xs px-2.5 py-1 rounded-full font-semibold bg-red-500/15 text-red-700 dark:text-red-300 border border-red-500/30';
            }
        }
    }
}

/**
 * Render operational data table rows for archival tab
 */
function renderOperationalCounts() {
    const tbody = document.getElementById('operational-table-body');
    if (!tbody) return;

    tbody.innerHTML = '';
    let grandTotal = 0;

    state.operationalCounts.forEach(item => {
        grandTotal += item.count;
        const tr = document.createElement('tr');
        tr.className = 'border-b transition hover:bg-black/5';
        tr.style.borderColor = 'var(--theme-card-border)';
        tr.innerHTML = `
            <td class="py-3 px-4 font-mono text-sm font-semibold theme-primary">${escapeHtml(item.table)}</td>
            <td class="py-3 px-4 text-sm">${escapeHtml(item.label)}</td>
            <td class="py-3 px-4 text-right font-mono text-sm ${item.count > 0 ? 'font-bold' : 'opacity-40'}">
                ${item.count.toLocaleString('nl-NL')}
            </td>
        `;
        tbody.appendChild(tr);
    });

    const totalEl = document.getElementById('total-operational-records');
    if (totalEl) totalEl.textContent = grandTotal.toLocaleString('nl-NL');
}

/**
 * Render list of archived editions
 */
function renderArchivedEditions() {
    const listEl = document.getElementById('archived-editions-list');
    const emptyEl = document.getElementById('archived-editions-empty');
    if (!listEl) return;

    listEl.innerHTML = '';

    if (state.archivedEditions.length === 0) {
        if (emptyEl) emptyEl.classList.remove('hidden');
        return;
    }

    if (emptyEl) emptyEl.classList.add('hidden');

    state.archivedEditions.forEach(ed => {
        const card = document.createElement('div');
        card.className = 'theme-card border rounded-xl p-4 flex flex-col md:flex-row md:items-center justify-between gap-4 shadow-sm';
        card.style.borderColor = 'var(--theme-card-border)';

        const rc = ed.row_counts || {};
        const hints = rc.Hints ?? 0;
        const hunts = rc.Voslocaties ?? 0;
        const coords = rc.Auto_Positie ?? 0;

        card.innerHTML = `
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <span class="font-bold text-base sm:text-lg">${escapeHtml(ed.edition_name)}</span>
                    <span class="text-xs px-2 py-0.5 rounded-full font-semibold border bg-black/5" style="border-color: var(--theme-card-border);">
                        ${ed.edition_year}
                    </span>
                    <span class="text-xs opacity-60">(${ed.file_size_formatted})</span>
                </div>
                <div class="text-xs opacity-70 flex flex-wrap items-center gap-3">
                    <span><i class="fas fa-calendar-check mr-1 opacity-60"></i> ${ed.archived_at}</span>
                    <span><i class="fas fa-user-shield mr-1 opacity-60"></i> ${escapeHtml(ed.archived_by_name)}</span>
                    <span><i class="fas fa-database mr-1 theme-primary"></i> ${hints} hints, ${hunts} vossen, ${coords} GPS punten</span>
                </div>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
                <a href="readiness_helper.php?action=download_edition&file=${encodeURIComponent(ed.backup_filename)}" 
                   class="px-3 py-1.5 rounded-lg bg-black/5 hover:bg-black/10 border text-xs font-semibold flex items-center gap-1.5 transition" style="border-color: var(--theme-card-border);">
                    <i class="fas fa-download"></i> Download Archief
                </a>
            </div>
        `;
        listEl.appendChild(card);
    });
}

/**
 * Render preflight checklist items
 */
function renderChecklist() {
    const listEl = document.getElementById('checklist-items-container');
    if (!listEl) return;

    listEl.innerHTML = '';

    const filter = state.checklistFilter;
    const filtered = state.checklistItems.filter(item => {
        if (filter === 'all') return true;
        return item.category === filter;
    });

    if (filtered.length === 0) {
        listEl.innerHTML = `
            <div class="py-12 text-center opacity-60">
                <i class="fas fa-tasks text-3xl mb-2 opacity-40"></i>
                <p class="text-sm">Geen taken gevonden in deze categorie.</p>
            </div>
        `;
        return;
    }

    const categoryLabels = {
        'dispatch': { label: 'HQ & Meldkamer', color: 'bg-indigo-500/10 text-indigo-700 dark:text-indigo-300 border border-indigo-500/30' },
        'fleet': { label: 'Vloot & Jagers', color: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border border-emerald-500/30' },
        'comms': { label: 'Communicatie', color: 'bg-purple-500/10 text-purple-700 dark:text-purple-300 border border-purple-500/30' },
        'general': { label: 'Algemeen', color: 'bg-black/5 text-inherit opacity-80 border border-black/10 dark:border-white/10' }
    };

    filtered.forEach(item => {
        const catMeta = categoryLabels[item.category] || categoryLabels.general;
        const row = document.createElement('div');
        row.className = `p-4 rounded-xl border transition flex items-start justify-between gap-4 bg-black/5 hover:bg-black/10 ${
            item.is_checked ? 'opacity-60' : ''
        }`;
        row.style.borderColor = 'var(--theme-card-border)';

        row.innerHTML = `
            <div class="flex items-start gap-3.5 flex-1 min-w-0">
                <div class="pt-0.5">
                    <input type="checkbox" id="check-item-${item.id}" ${item.is_checked ? 'checked' : ''} 
                           class="w-5 h-5 rounded text-blue-600 focus:ring-blue-500 cursor-pointer"
                           onchange="toggleCheckItem(${item.id}, this.checked)">
                </div>
                <div class="space-y-1 flex-1 min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <label for="check-item-${item.id}" class="text-sm font-semibold cursor-pointer select-none ${item.is_checked ? 'line-through opacity-60' : ''}">
                            ${escapeHtml(item.title)}
                        </label>
                        <span class="text-[10px] px-2 py-0.5 rounded-full font-semibold ${catMeta.color}">
                            ${catMeta.label}
                        </span>
                        ${item.is_custom ? '<span class="text-[10px] px-1.5 py-0.5 rounded bg-amber-500/15 text-amber-700 dark:text-amber-300 border border-amber-500/30 font-semibold">Aangepast</span>' : ''}
                    </div>
                    ${item.description ? `<p class="text-xs opacity-70 select-none">${escapeHtml(item.description)}</p>` : ''}
                    ${item.is_checked && item.checked_by_name ? `
                        <div class="text-[11px] text-green-600 dark:text-green-400 flex items-center gap-1.5 pt-0.5">
                            <i class="fas fa-check text-[10px]"></i> Afgevinkt door <strong>${escapeHtml(item.checked_by_name)}</strong> om ${item.checked_at}
                        </div>
                    ` : ''}
                </div>
            </div>
            ${item.is_custom ? `
                <button type="button" onclick="confirmDeleteTask(${item.id})" class="opacity-40 hover:opacity-100 hover:text-red-500 transition p-1.5 text-xs" title="Taak verwijderen">
                    <i class="fas fa-trash-alt"></i>
                </button>
            ` : ''}
        `;

        listEl.appendChild(row);
    });
}

/**
 * Optimistically toggle a checklist item
 */
async function toggleCheckItem(id, isChecked) {
    const item = state.checklistItems.find(i => i.id === id);
    if (!item) return;

    // Optimistic local update
    const prevChecked = item.is_checked;
    item.is_checked = isChecked;
    recalcChecklistStats();
    renderChecklist();
    updateOverallScore();

    try {
        const formData = new FormData();
        formData.append('id', id);
        formData.append('checked', isChecked ? 1 : 0);

        const resp = await fetch('readiness_helper.php?action=toggle_check', {
            method: 'POST',
            body: formData
        });
        const data = await resp.json();

        if (!data.ok) {
            throw new Error(data.error || 'Server error');
        }

        // Apply authoritative server data
        item.is_checked = data.is_checked;
        item.checked_by_name = data.checked_by_name;
        item.checked_at = data.checked_at;
        renderChecklist();
    } catch (err) {
        console.error('Failed to toggle check:', err);
        // Rollback
        item.is_checked = prevChecked;
        recalcChecklistStats();
        renderChecklist();
        updateOverallScore();
        showAlert('Kan taakstatus niet opslaan.', 'error');
    }
}

/**
 * Recalculate checked stats
 */
function recalcChecklistStats() {
    const total = state.checklistItems.length;
    const checked = state.checklistItems.filter(i => i.is_checked).length;
    state.checklistPercentage = total > 0 ? Math.round((checked / total) * 100) : 100;

    const badge = document.getElementById('checklist-summary-badge');
    const progBar = document.getElementById('checklist-progress-bar');
    if (badge) badge.textContent = `${checked}/${total} voltooid (${state.checklistPercentage}%)`;
    if (progBar) progBar.style.width = `${state.checklistPercentage}%`;
}

/**
 * Calculate and update overall readiness score
 */
function updateOverallScore() {
    const diag = state.diagScore || 0;
    const check = state.checklistPercentage || 0;
    const overall = Math.round((diag * 0.5) + (check * 0.5));

    const scoreNumber = document.getElementById('overall-readiness-score');
    const scoreBar = document.getElementById('overall-score-bar');
    const statusText = document.getElementById('overall-readiness-status');
    const banner = document.getElementById('overall-score-banner');

    if (scoreNumber) scoreNumber.textContent = `${overall}%`;
    if (scoreBar) scoreBar.style.width = `${overall}%`;

    let statusMsg = '';
    let barColor = 'bg-rose-500';
    let textColor = 'text-rose-400';

    if (overall >= 90) {
        statusMsg = 'Systeem & Uitrusting zijn 100% gereed voor de start!';
        barColor = 'bg-emerald-500';
        textColor = 'text-emerald-400';
    } else if (overall >= 70) {
        statusMsg = 'Grotendeels operationeel. Los openstaande aandachtspunten op.';
        barColor = 'bg-amber-500';
        textColor = 'text-amber-400';
    } else {
        statusMsg = 'Niet gereed. Voer essentiële controles en checklist-taken uit.';
        barColor = 'bg-rose-500';
        textColor = 'text-rose-400';
    }

    if (statusText) {
        statusText.textContent = statusMsg;
        statusText.className = `text-sm font-semibold ${textColor}`;
    }

    if (scoreBar) {
        scoreBar.className = `h-3 rounded-full transition-all duration-500 ${barColor}`;
    }
}

/**
 * Modal: Add Custom Task
 */
function openAddTaskModal() {
    document.getElementById('add-task-form')?.reset();
    document.getElementById('modal-add-task')?.classList.remove('hidden');
}

function closeAddTaskModal() {
    document.getElementById('modal-add-task')?.classList.add('hidden');
}

async function submitAddTask(e) {
    e.preventDefault();
    const title = document.getElementById('new-task-title')?.value.trim();
    const category = document.getElementById('new-task-category')?.value;
    const description = document.getElementById('new-task-desc')?.value.trim();

    if (!title) {
        showAlert('Vul een titel in voor de nieuwe taak.', 'warning');
        return;
    }

    const btn = document.getElementById('btn-submit-add-task');
    if (btn) btn.disabled = true;

    try {
        const fd = new FormData();
        fd.append('title', title);
        fd.append('category', category);
        fd.append('description', description);

        const resp = await fetch('readiness_helper.php?action=add_check_item', {
            method: 'POST',
            body: fd
        });
        const data = await resp.json();

        if (!data.ok) {
            showAlert(data.error || 'Kon taak niet toevoegen.', 'error');
            return;
        }

        closeAddTaskModal();
        showAlert('Taak succesvol toegevoegd!', 'success');
        await loadOverview();
    } catch (err) {
        console.error('Failed to add task:', err);
        showAlert('Fout bij toevoegen van taak.', 'error');
    } finally {
        if (btn) btn.disabled = false;
    }
}

/**
 * Modal: Delete Custom Task Confirmation
 */
let taskToDeleteId = null;

function confirmDeleteTask(id) {
    taskToDeleteId = id;
    document.getElementById('modal-delete-task')?.classList.remove('hidden');
}

function closeDeleteTaskModal() {
    taskToDeleteId = null;
    document.getElementById('modal-delete-task')?.classList.add('hidden');
}

async function executeDeleteTask() {
    if (!taskToDeleteId) return;

    try {
        const fd = new FormData();
        fd.append('id', taskToDeleteId);

        const resp = await fetch('readiness_helper.php?action=delete_check_item', {
            method: 'POST',
            body: fd
        });
        const data = await resp.json();

        if (!data.ok) {
            showAlert(data.error || 'Kon taak niet verwijderen.', 'error');
            return;
        }

        closeDeleteTaskModal();
        showAlert('Taak verwijderd.', 'info');
        await loadOverview();
    } catch (err) {
        console.error('Failed to delete task:', err);
        showAlert('Fout bij verwijderen van taak.', 'error');
    }
}

/**
 * Modal: Reset Checklist Confirmation
 */
function confirmResetChecklist() {
    document.getElementById('modal-reset-checklist')?.classList.remove('hidden');
}

function closeResetChecklistModal() {
    document.getElementById('modal-reset-checklist')?.classList.add('hidden');
}

async function executeResetChecklist() {
    try {
        const resp = await fetch('readiness_helper.php?action=reset_checklist', { method: 'POST' });
        const data = await resp.json();

        if (!data.ok) {
            showAlert(data.error || 'Kon checklist niet resetten.', 'error');
            return;
        }

        closeResetChecklistModal();
        showAlert('Checklist gereset voor het nieuwe jachtseizoen.', 'success');
        await loadOverview();
    } catch (err) {
        console.error('Failed to reset checklist:', err);
        showAlert('Fout bij resetten van checklist.', 'error');
    }
}

/**
 * Modal: Season Archival & Reset Wizard
 */
function openArchiveModal() {
    const currentYear = new Date().getFullYear();
    const nameInput = document.getElementById('archive-edition-name');
    const yearInput = document.getElementById('archive-edition-year');
    const confirmInput = document.getElementById('archive-confirm-text');

    if (nameInput) nameInput.value = `Jotihunt ${currentYear}`;
    if (yearInput) yearInput.value = currentYear;
    if (confirmInput) confirmInput.value = '';

    document.getElementById('modal-archive-season')?.classList.remove('hidden');
}

function closeArchiveModal() {
    document.getElementById('modal-archive-season')?.classList.add('hidden');
}

async function executeArchiveAndReset(e) {
    e.preventDefault();

    const name = document.getElementById('archive-edition-name')?.value.trim();
    const year = parseInt(document.getElementById('archive-edition-year')?.value, 10);
    const confirmText = document.getElementById('archive-confirm-text')?.value.trim();

    if (!name) {
        showAlert('Vul een editienaam in (bijv. Jotihunt 2025).', 'warning');
        return;
    }
    if (confirmText !== 'RESET') {
        showAlert('Typ exact "RESET" in om de seizoensreset te bevestigen.', 'warning');
        return;
    }

    const btn = document.getElementById('btn-execute-archive');
    const spinner = document.getElementById('archive-progress-spinner');
    if (btn) btn.disabled = true;
    if (spinner) spinner.classList.remove('hidden');

    try {
        const fd = new FormData();
        fd.append('edition_name', name);
        fd.append('edition_year', year);
        fd.append('confirm_text', confirmText);

        const resp = await fetch('readiness_helper.php?action=archive_and_reset', {
            method: 'POST',
            body: fd
        });
        const data = await resp.json();

        if (!data.ok) {
            showAlert(data.error || 'Seizoensarchivering is mislukt.', 'error');
            return;
        }

        closeArchiveModal();
        showAlert(data.message || 'Seizoensarchief gemaakt en data gereset!', 'success');
        
        // Refresh all tabs
        await loadOverview();
        runAllDiagnostics();
    } catch (err) {
        console.error('Failed to archive season:', err);
        showAlert('Fout tijdens archivering en reset.', 'error');
    } finally {
        if (btn) btn.disabled = false;
        if (spinner) spinner.classList.add('hidden');
    }
}

/**
 * Show a styled in-DOM alert banner (never native browser alert)
 */
function showAlert(message, type = 'info') {
    const alertBox = document.getElementById('status-alert');
    const alertText = document.getElementById('status-alert-text');
    const alertIcon = document.getElementById('status-alert-icon');
    if (!alertBox || !alertText) return;

    alertText.textContent = message;

    // Set styling based on type
    alertBox.className = 'px-4 py-3 rounded-lg border relative shadow-sm flex items-center justify-between mb-6 transition-all';
    
    if (type === 'success') {
        alertBox.classList.add('bg-emerald-500/10', 'border-emerald-500/30', 'text-emerald-300');
        if (alertIcon) alertIcon.className = 'fas fa-check-circle text-lg text-emerald-400';
    } else if (type === 'warning') {
        alertBox.classList.add('bg-amber-500/10', 'border-amber-500/30', 'text-amber-300');
        if (alertIcon) alertIcon.className = 'fas fa-triangle-exclamation text-lg text-amber-400';
    } else if (type === 'error') {
        alertBox.classList.add('bg-rose-500/10', 'border-rose-500/30', 'text-rose-300');
        if (alertIcon) alertIcon.className = 'fas fa-circle-xmark text-lg text-rose-400';
    } else {
        alertBox.classList.add('bg-blue-500/10', 'border-blue-500/30', 'text-blue-300');
        if (alertIcon) alertIcon.className = 'fas fa-info-circle text-lg text-blue-400';
    }

    alertBox.classList.remove('hidden');

    // Auto dismiss after 7 seconds
    clearTimeout(window.__alertTimeout);
    window.__alertTimeout = setTimeout(() => {
        alertBox.classList.add('hidden');
    }, 7000);
}

/**
 * HTML Escaping helper
 */
function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
