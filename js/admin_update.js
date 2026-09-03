/**
 * Jotify System Auto-Update Client Logic
 */

let currentBranchState = '';
let targetBranchToSwitch = '';

document.addEventListener('DOMContentLoaded', () => {
    checkUpdates();
});

function showAlert(text, type = 'success') {
    const alertBox = document.getElementById('status-alert');
    const alertText = document.getElementById('status-alert-text');
    const alertIcon = document.getElementById('status-alert-icon');
    if (!alertBox || !alertText || !alertIcon) return;

    alertBox.className = `px-4 py-3 rounded-lg border relative shadow-sm flex items-center justify-between ${
        type === 'success' 
            ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-600 dark:text-emerald-400' 
            : 'bg-red-500/10 border-red-500/30 text-red-600 dark:text-red-400'
    }`;
    alertIcon.className = type === 'success' ? 'fas fa-check-circle text-lg' : 'fas fa-triangle-exclamation text-lg';
    alertText.textContent = text;
    alertBox.classList.remove('hidden');

    setTimeout(() => {
        alertBox.classList.add('hidden');
    }, 8000);
}

async function fetchJsonSafely(url, options = {}) {
    const response = await fetch(url, options);
    const text = await response.text();
    try {
        return JSON.parse(text);
    } catch (e) {
        console.error('Non-JSON response received:', text);
        const cleanMsg = text.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
        throw new Error(cleanMsg.substring(0, 160) || 'Ongeldig serverantwoord.');
    }
}

async function checkUpdates() {
    const btn = document.getElementById('btn-check-updates');
    const icon = document.getElementById('icon-check-spin');
    if (icon) icon.classList.add('animate-spin');
    if (btn) btn.disabled = true;

    try {
        const data = await fetchJsonSafely('update_helper.php?action=check_updates');

        if (!data.ok) {
            showAlert(data.error || 'Fout bij het controleren op updates.', 'error');
            return;
        }

        currentBranchState = data.branch;

        // 1. Update current commit display
        const commitLink = document.getElementById('display-commit-link');
        if (commitLink) {
            commitLink.textContent = data.current_commit;
            commitLink.href = `https://github.com/thehairyvikingniels/Joti/commit/${data.current_full_hash}`;
        }

        const dateElem = document.getElementById('display-commit-date');
        if (dateElem) {
            dateElem.textContent = `${data.current_date} • ${data.current_author}`;
        }

        const msgElem = document.getElementById('display-commit-msg');
        if (msgElem) {
            msgElem.textContent = data.current_message;
        }

        // 2. Branch dropdown
        const selectBranch = document.getElementById('select-branch');
        if (selectBranch && data.available_branches) {
            selectBranch.innerHTML = '';
            data.available_branches.forEach(b => {
                const opt = document.createElement('option');
                opt.value = b;
                opt.textContent = b;
                if (b === data.branch) opt.selected = true;
                selectBranch.appendChild(opt);
            });
        }

        // 3. Status Badge & Card Visibility
        const statusBadge = document.getElementById('status-badge');
        const statusSubtext = document.getElementById('status-subtext');
        const updatesCard = document.getElementById('card-updates-available');

        const nowTime = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        const lastCheckTime = document.getElementById('last-check-time');
        if (lastCheckTime) lastCheckTime.textContent = nowTime;

        if (data.update_available) {
            if (statusBadge) {
                statusBadge.className = 'inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-500/20 text-amber-600 border border-amber-500/30';
                statusBadge.innerHTML = '<i class="fas fa-arrow-circle-up"></i> <span>Update Beschikbaar</span>';
            }
            if (statusSubtext) {
                statusSubtext.textContent = `${data.commits_behind} nieuwe commit(s) op ${data.branch}`;
            }

            if (updatesCard) {
                updatesCard.classList.remove('hidden');
            }

            const countBadge = document.getElementById('badge-commits-count');
            if (countBadge) {
                countBadge.textContent = `${data.commits_behind} commit(s) achter`;
            }

            // Impact tags
            renderImpactTags(data.impact);

            // Commits timeline
            renderCommitsList(data.commits);

        } else {
            if (statusBadge) {
                statusBadge.className = 'inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-500/20 text-emerald-600 border border-emerald-500/30';
                statusBadge.innerHTML = '<i class="fas fa-check-circle"></i> <span>Up-to-date</span>';
            }
            if (statusSubtext) {
                statusSubtext.textContent = `Laatste versie geïnstalleerd op ${data.branch}`;
            }
            if (updatesCard) {
                updatesCard.classList.add('hidden');
            }
        }

        // 4. Backups table
        renderBackupsTable(data.recent_backups);

    } catch (err) {
        console.error('Update check error:', err);
        showAlert('Kan niet communiceren met de update server.', 'error');
    } finally {
        if (icon) icon.classList.remove('animate-spin');
        if (btn) btn.disabled = false;
    }
}

function renderImpactTags(impact) {
    const container = document.getElementById('impact-tags-container');
    if (!container || !impact) return;

    let html = '';
    if (impact.has_db_changes) {
        html += '<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-bold bg-purple-500/20 text-purple-600 border border-purple-500/30"><i class="fas fa-database"></i> Database Schema Sync</span>';
    }
    if (impact.has_composer_changes) {
        html += '<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-bold bg-blue-500/20 text-blue-600 border border-blue-500/30"><i class="fas fa-cubes"></i> Composer Pakketten</span>';
    }
    if (impact.has_python_changes) {
        html += '<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-bold bg-yellow-500/20 text-yellow-600 border border-yellow-500/30"><i class="fab fa-python"></i> Python Services</span>';
    }
    if (impact.has_cron_changes) {
        html += '<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-bold bg-orange-500/20 text-orange-600 border border-orange-500/30"><i class="fas fa-stopwatch"></i> Cron Subsystem</span>';
    }

    html += `<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-medium bg-black/5 text-current opacity-70 border" style="border-color: var(--theme-card-border);"><i class="fas fa-file-code"></i> ${impact.changed_files_count || 0} gewijzigde bestanden</span>`;

    container.innerHTML = html;
}

function renderCommitsList(commits) {
    const container = document.getElementById('commits-list-container');
    if (!container) return;

    if (!commits || commits.length === 0) {
        container.innerHTML = '<p class="text-xs opacity-60 italic">Geen commit details beschikbaar.</p>';
        return;
    }

    let html = '';
    commits.forEach(c => {
        html += `
        <div class="p-3 rounded-lg border bg-black/5 hover:bg-black/10 transition flex items-start justify-between gap-3 text-xs" style="border-color: var(--theme-card-border);">
          <div class="space-y-1 truncate">
            <div class="flex items-center gap-2">
              <span class="font-mono font-bold theme-primary px-1.5 py-0.5 rounded bg-black/10">${escapeHtml(c.hash)}</span>
              <span class="font-semibold text-sm truncate">${escapeHtml(c.message)}</span>
            </div>
            <p class="opacity-60">${escapeHtml(c.author)} • ${escapeHtml(c.time_ago)}</p>
          </div>
          <a href="https://github.com/thehairyvikingniels/Joti/commit/${escapeHtml(c.hash)}" target="_blank" class="opacity-50 hover:opacity-100 transition p-1" title="Bekijk commit op GitHub">
            <i class="fas fa-external-link-alt"></i>
          </a>
        </div>`;
    });

    container.innerHTML = html;
}

function renderBackupsTable(backups) {
    const tbody = document.getElementById('backups-table-body');
    if (!tbody) return;

    if (!backups || backups.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="px-6 py-6 text-center opacity-60">Nog geen eerdere back-ups gevonden.</td></tr>';
        return;
    }

    let html = '';
    backups.forEach(b => {
        html += `
        <tr class="hover:bg-black/5 transition">
          <td class="px-6 py-4 font-mono font-medium text-xs flex items-center gap-2">
            <i class="fas fa-file-lines opacity-60"></i>
            <span>${escapeHtml(b.filename)}</span>
          </td>
          <td class="px-6 py-4 text-xs opacity-75">${escapeHtml(b.date)}</td>
          <td class="px-6 py-4 text-xs font-mono opacity-75">${escapeHtml(b.size)}</td>
          <td class="px-6 py-4 text-right text-xs opacity-50 font-mono">DB/backups/</td>
        </tr>`;
    });

    tbody.innerHTML = html;
}

async function confirmAndPerformUpdate() {
    const doBackup = document.getElementById('check-backup-before')?.checked ? '1' : '0';

    if (!confirm('Weet je zeker dat je Jotify nu wilt bijwerken naar de nieuwste versie?')) {
        return;
    }

    const modal = document.getElementById('modal-update');
    const modalSteps = document.getElementById('modal-steps-container');
    const modalConsole = document.getElementById('modal-console-log');
    const modalIcon = document.getElementById('modal-icon');
    const modalTitle = document.getElementById('modal-title');
    const modalActions = document.getElementById('modal-actions');

    if (modal) modal.classList.remove('hidden');
    if (modalActions) modalActions.classList.add('hidden');
    if (modalIcon) {
        modalIcon.className = 'fas fa-rotate animate-spin';
    }
    if (modalTitle) {
        modalTitle.textContent = 'Systeemupdate Wordt Uitgevoerd...';
    }
    if (modalSteps) {
        modalSteps.innerHTML = '<div class="flex items-center gap-3 text-sm"><i class="fas fa-spinner animate-spin theme-primary"></i><span>Update pipeline wordt geïnitieerd...</span></div>';
    }
    if (modalConsole) {
        modalConsole.innerHTML = '<div>[START] Jotify auto-updater gestart...</div>';
    }

    try {
        const formData = new FormData();
        formData.append('action', 'perform_update');
        formData.append('do_backup', doBackup);

        const data = await fetchJsonSafely('update_helper.php', {
            method: 'POST',
            body: formData
        });

        if (data.steps && modalSteps) {
            let stepsHtml = '';
            data.steps.forEach(s => {
                const icon = s.status === 'success' 
                    ? '<i class="fas fa-check-circle text-emerald-500"></i>'
                    : (s.status === 'warning' ? '<i class="fas fa-triangle-exclamation text-amber-500"></i>' : '<i class="fas fa-times-circle text-red-500"></i>');
                
                stepsHtml += `
                <div class="flex items-start gap-3 text-xs p-2.5 rounded-lg border bg-black/5" style="border-color: var(--theme-card-border);">
                  <div class="pt-0.5">${icon}</div>
                  <div>
                    <strong class="block text-sm font-semibold">${escapeHtml(s.title)}</strong>
                    <span class="opacity-70">${escapeHtml(s.details)}</span>
                  </div>
                </div>`;
            });
            modalSteps.innerHTML = stepsHtml;
        }

        if (modalConsole) {
            modalConsole.innerHTML += `<div>[INFO] Pipeline voltooid met status: ${data.ok ? 'SUCCESS' : 'FAILED'}</div>`;
            if (data.new_commit) {
                modalConsole.innerHTML += `<div>[INFO] Geïnstalleerde commit: ${escapeHtml(data.new_commit)}</div>`;
            }
        }

        if (data.ok) {
            if (modalIcon) modalIcon.className = 'fas fa-check-circle text-emerald-500';
            if (modalTitle) modalTitle.textContent = 'Jotify Succesvol Bijgewerkt!';
            if (modalActions) modalActions.classList.remove('hidden');
        } else {
            if (modalIcon) modalIcon.className = 'fas fa-times-circle text-red-500';
            if (modalTitle) modalTitle.textContent = 'Fout Tijdens Update';
            if (modalActions) modalActions.classList.remove('hidden');
            showAlert(data.error || 'Fout tijdens het bijwerken.', 'error');
        }

    } catch (err) {
        console.error('Perform update error:', err);
        if (modalConsole) {
            modalConsole.innerHTML += `<div class="text-red-400">[ERROR] Netwerkfout of time-out: ${err.message}</div>`;
        }
        showAlert('Fout bij het communiceren met de update server.', 'error');
    }
}

function promptSwitchBranch(branch) {
    if (branch === currentBranchState) return;
    targetBranchToSwitch = branch;

    const modal = document.getElementById('modal-switch-branch');
    const targetLabel = document.getElementById('switch-target-branch');
    if (targetLabel) targetLabel.textContent = branch;
    if (modal) modal.classList.remove('hidden');
}

function closeSwitchBranchModal() {
    const modal = document.getElementById('modal-switch-branch');
    if (modal) modal.classList.add('hidden');
    // Reset dropdown back to current
    const select = document.getElementById('select-branch');
    if (select) select.value = currentBranchState;
}

async function executeSwitchBranch() {
    const btn = document.getElementById('btn-confirm-switch');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner animate-spin mr-1"></i> Wisselen...';
    }

    try {
        const formData = new FormData();
        formData.append('action', 'switch_branch');
        formData.append('branch', targetBranchToSwitch);

        const data = await fetchJsonSafely('update_helper.php', {
            method: 'POST',
            body: formData
        });
        closeSwitchBranchModal();

        if (data.ok) {
            showAlert(data.message, 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showAlert(data.error || 'Kan niet wisselen van branch.', 'error');
        }
    } catch (err) {
        console.error('Switch branch error:', err);
        showAlert('Fout bij wisselen van branch: ' + err.message, 'error');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Wissel Branch';
        }
    }
}

async function createBackupNow() {
    try {
        const formData = new FormData();
        formData.append('action', 'create_backup');

        const data = await fetchJsonSafely('update_helper.php', {
            method: 'POST',
            body: formData
        });
        if (data.ok) {
            showAlert(`Back-up ${data.backup.filename} (${data.backup.size_formatted}) succesvol aangemaakt!`, 'success');
            checkUpdates();
        } else {
            showAlert(data.error || 'Fout bij aanmaken back-up.', 'error');
        }
    } catch (err) {
        console.error('Backup error:', err);
        showAlert('Fout bij back-up maken: ' + err.message, 'error');
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
