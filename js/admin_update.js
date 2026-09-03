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
        tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-6 text-center opacity-60">Nog geen eerdere back-ups gevonden.</td></tr>';
        return;
    }

    let html = '';
    backups.forEach(b => {
        const isAuto = (b.type === 'auto');
        const typeBadge = isAuto 
            ? '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-500/20 text-blue-600 border border-blue-500/30">Automatisch</span>'
            : '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-purple-500/20 text-purple-600 border border-purple-500/30">Handmatig</span>';

        const commitDisplay = (b.commit && b.commit !== 'onbekend')
            ? `<a href="https://github.com/thehairyvikingniels/Joti/commit/${b.commit}" target="_blank" class="font-mono text-xs font-bold hover:underline theme-primary">${escapeHtml(b.commit)}</a>`
            : '<span class="text-xs opacity-50">-</span>';

        html += `
        <tr class="hover:bg-black/5 transition">
          <td class="px-6 py-4 font-mono font-medium text-xs flex items-center gap-2">
            <i class="fas fa-file-archive text-amber-500"></i>
            <span title="${escapeHtml(b.filename)}">${escapeHtml(b.filename)}</span>
          </td>
          <td class="px-6 py-4">${typeBadge}</td>
          <td class="px-6 py-4">${commitDisplay}</td>
          <td class="px-6 py-4 text-xs opacity-75">${escapeHtml(b.date)}</td>
          <td class="px-6 py-4 text-xs font-mono opacity-75">${escapeHtml(b.size)}</td>
          <td class="px-6 py-4 text-right">
            <div class="inline-flex items-center gap-1.5">
              <a href="update_helper.php?action=download_backup&filename=${encodeURIComponent(b.filename)}" 
                 class="p-1.5 rounded hover:bg-black/10 text-blue-500 transition" title="Downloaden">
                <i class="fas fa-download"></i>
              </a>
              <button onclick="openRestoreModal('${escapeHtml(b.filename)}', '${escapeHtml(b.commit || '')}')" 
                      class="p-1.5 rounded hover:bg-black/10 text-amber-500 transition" title="Herstellen">
                <i class="fas fa-history"></i>
              </button>
              <button onclick="confirmDeleteBackup('${escapeHtml(b.filename)}')" 
                      class="p-1.5 rounded hover:bg-black/10 text-red-500 transition" title="Verwijderen">
                <i class="fas fa-trash-alt"></i>
              </button>
            </div>
          </td>
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
    const btn = document.getElementById('btn-create-backup');
    const icon = document.getElementById('icon-backup-spin');
    const text = document.getElementById('text-backup-btn');

    if (btn) btn.disabled = true;
    if (icon) icon.className = 'fas fa-spinner animate-spin';
    if (text) text.textContent = 'Back-up Maken...';

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
    } finally {
        if (btn) btn.disabled = false;
        if (icon) icon.className = 'fas fa-plus';
        if (text) text.textContent = 'Nieuwe Back-up Maken';
    }
}

async function handleUploadBackup(event) {
    const input = event.target;
    if (!input.files || input.files.length === 0) return;

    const file = input.files[0];
    if (!file.name.endsWith('.tar.gz')) {
        showAlert('Alleen .tar.gz archieven zijn toegestaan.', 'error');
        input.value = '';
        return;
    }

    const uploadBtn = document.getElementById('btn-upload-backup');
    const origHtml = uploadBtn ? uploadBtn.innerHTML : '';
    if (uploadBtn) {
        uploadBtn.disabled = true;
        uploadBtn.innerHTML = '<i class="fas fa-spinner animate-spin"></i><span>Uploaden...</span>';
    }

    try {
        const formData = new FormData();
        formData.append('action', 'upload_backup');
        formData.append('backup_file', file);

        const data = await fetchJsonSafely('update_helper.php', {
            method: 'POST',
            body: formData
        });

        if (data.ok) {
            showAlert(data.message || 'Back-up succesvol geüpload!', 'success');
            checkUpdates();
        } else {
            showAlert(data.error || 'Fout bij uploaden back-up.', 'error');
        }
    } catch (err) {
        console.error('Upload backup error:', err);
        showAlert('Fout bij uploaden back-up: ' + err.message, 'error');
    } finally {
        if (uploadBtn) {
            uploadBtn.disabled = false;
            uploadBtn.innerHTML = origHtml;
        }
        input.value = '';
    }
}

let targetRestoreFilename = '';
let targetRestoreCommit = '';
let restoreForce = false;

function openRestoreModal(filename, commit) {
    targetRestoreFilename = filename;
    targetRestoreCommit = commit || 'onbekend';
    restoreForce = false;

    const modal = document.getElementById('modal-restore');
    const fnElem = document.getElementById('restore-target-filename');
    const cElem = document.getElementById('restore-target-commit');
    const warnElem = document.getElementById('restore-warning-user');
    const btn = document.getElementById('btn-confirm-restore');

    if (fnElem) fnElem.textContent = filename;
    if (cElem) cElem.textContent = commit || 'onbekend';
    if (warnElem) warnElem.classList.add('hidden');
    if (btn) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-history"></i><span>Ja, Herstel Back-up</span>';
    }

    if (modal) modal.classList.remove('hidden');
}

function closeRestoreModal() {
    const modal = document.getElementById('modal-restore');
    if (modal) modal.classList.add('hidden');
    targetRestoreFilename = '';
    targetRestoreCommit = '';
    restoreForce = false;
}

async function executeRestoreBackup() {
    if (!targetRestoreFilename) return;

    const btn = document.getElementById('btn-confirm-restore');
    const doSafetyBackup = document.getElementById('check-backup-before-restore')?.checked ? '1' : '0';

    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner animate-spin mr-1"></i> Herstellen...';
    }

    try {
        // Step 1: Optional safety backup before restore
        if (doSafetyBackup === '1') {
            const safeData = new FormData();
            safeData.append('action', 'create_backup');
            await fetchJsonSafely('update_helper.php', { method: 'POST', body: safeData });
        }

        // Step 2: Perform restore
        const formData = new FormData();
        formData.append('action', 'restore_backup');
        formData.append('filename', targetRestoreFilename);
        if (restoreForce) {
            formData.append('force', '1');
        }

        const data = await fetchJsonSafely('update_helper.php', {
            method: 'POST',
            body: formData
        });

        if (data.requires_confirmation) {
            const warnElem = document.getElementById('restore-warning-user');
            const warnText = document.getElementById('restore-warning-user-text');
            if (warnElem && warnText) {
                warnText.textContent = data.message;
                warnElem.classList.remove('hidden');
            }
            restoreForce = true;
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-exclamation-triangle mr-1"></i> Toch Doorgaan & Herstellen';
            }
            return;
        }

        if (data.ok) {
            closeRestoreModal();
            showAlert(data.message, 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showAlert(data.error || 'Kan back-up niet herstellen.', 'error');
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-history"></i><span>Ja, Herstel Back-up</span>';
            }
        }
    } catch (err) {
        console.error('Restore error:', err);
        showAlert('Fout bij herstellen: ' + err.message, 'error');
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-history"></i><span>Ja, Herstel Back-up</span>';
        }
    }
}

async function confirmDeleteBackup(filename) {
    if (!confirm(`Weet je zeker dat je back-up '${filename}' permanent wilt verwijderen?`)) {
        return;
    }

    try {
        const formData = new FormData();
        formData.append('action', 'delete_backup');
        formData.append('filename', filename);

        const data = await fetchJsonSafely('update_helper.php', {
            method: 'POST',
            body: formData
        });

        if (data.ok) {
            showAlert(data.message || 'Back-up succesvol verwijderd!', 'success');
            checkUpdates();
        } else {
            showAlert(data.error || 'Fout bij het verwijderen van de back-up.', 'error');
        }
    } catch (err) {
        console.error('Delete backup error:', err);
        showAlert('Fout bij verwijderen: ' + err.message, 'error');
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
