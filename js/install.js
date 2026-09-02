/**
 * js/install.js — Client-Side Logic for Jotify Setup Wizard
 */

let currentStep = 1;

document.addEventListener('DOMContentLoaded', () => {
    generateRandomDbPass();
    runSystemChecks();
});

// =============================================================================
// Step Navigation
// =============================================================================
function goToStep(step) {
    document.querySelectorAll('.step-card').forEach(el => el.classList.add('hidden'));
    const target = document.getElementById(`step-${step}`);
    if (target) {
        target.classList.remove('hidden');
    }

    // Update Stepper Tabs
    for (let i = 1; i <= 6; i++) {
        const tab = document.getElementById(`step-tab-${i}`);
        if (!tab) continue;
        tab.className = 'border-b-2 pb-2 transition-all ';
        if (i < step) {
            tab.className += 'step-done';
        } else if (i === step) {
            tab.className += 'step-active';
        } else {
            tab.className += 'step-pending';
        }
    }

    currentStep = step;
    if (step === 4 && fetchedGroupsList.length === 0) {
        fetchJotihuntGroups();
    }
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// =============================================================================
// Alert Banner Utilities
// =============================================================================
function showAlert(msg, type = 'error') {
    const box = document.getElementById('alert-box');
    const text = document.getElementById('alert-msg');
    const icon = document.getElementById('alert-icon');
    if (!box || !text) return;

    text.textContent = msg;
    box.className = 'mb-6 p-4 rounded-xl text-sm border flex items-center justify-between ';

    if (type === 'error') {
        box.className += 'bg-red-500/10 border-red-500/30 text-red-400';
        icon.className = 'fa-solid fa-circle-exclamation text-lg text-red-400';
    } else if (type === 'success') {
        box.className += 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400';
        icon.className = 'fa-solid fa-circle-check text-lg text-emerald-400';
    } else {
        box.className += 'bg-blue-500/10 border-blue-500/30 text-blue-400';
        icon.className = 'fa-solid fa-circle-info text-lg text-blue-400';
    }

    box.classList.remove('hidden');
}

function hideAlert() {
    const box = document.getElementById('alert-box');
    if (box) box.classList.add('hidden');
}

// =============================================================================
// 1. System Requirements Check
// =============================================================================
async function runSystemChecks() {
    hideAlert();
    const loading = document.getElementById('syscheck-loading');
    const results = document.getElementById('syscheck-results');
    const btnNext = document.getElementById('btn-step-1');

    loading.classList.remove('hidden');
    results.classList.add('hidden');
    btnNext.disabled = true;

    try {
        const resp = await fetch('install_helper.php?action=check_requirements');
        const data = await resp.json();

        loading.classList.add('hidden');
        results.classList.remove('hidden');

        if (!data.ok) {
            showAlert(data.error || 'Kon systeemcontrole niet uitvoeren.', 'error');
            return;
        }

        // Render Extensions
        const extGrid = document.getElementById('extensions-grid');
        extGrid.innerHTML = '';
        for (const [key, ext] of Object.entries(data.extensions)) {
            const el = document.createElement('div');
            el.className = 'flex items-center justify-between p-3 rounded-xl bg-slate-900/60 border border-slate-700/60 text-xs';
            el.innerHTML = `
                <div>
                    <span class="font-bold text-white">${ext.name}</span>
                    <span class="text-slate-400 block text-[11px]">${ext.description}</span>
                </div>
                ${ext.loaded 
                    ? '<span class="text-emerald-400 flex items-center gap-1 font-semibold"><i class="fa-solid fa-check"></i> Actief</span>'
                    : '<span class="text-red-400 flex items-center gap-1 font-semibold"><i class="fa-solid fa-xmark"></i> Ontbreekt</span>'}
            `;
            extGrid.appendChild(el);
        }

        // Render Directories
        const dirGrid = document.getElementById('directories-grid');
        dirGrid.innerHTML = '';
        data.directories.forEach(dir => {
            const el = document.createElement('div');
            el.className = 'flex items-center justify-between p-3 rounded-xl bg-slate-900/60 border border-slate-700/60 text-xs';
            el.innerHTML = `
                <div>
                    <span class="font-bold text-white">${dir.label}</span>
                    <span class="text-slate-400 block text-[11px] font-mono">${dir.path.split('/').slice(-2).join('/')}</span>
                </div>
                ${dir.writable 
                    ? '<span class="text-emerald-400 flex items-center gap-1 font-semibold"><i class="fa-solid fa-check"></i> Schrijfbaar</span>'
                    : '<span class="text-red-400 flex items-center gap-1 font-semibold"><i class="fa-solid fa-lock"></i> Geen schrijfrechten</span>'}
            `;
            dirGrid.appendChild(el);
        });

        if (data.all_passed) {
            btnNext.disabled = false;
        } else {
            showAlert('Enkele vereiste extensies of maprechten ontbreken. Los deze eerst op.', 'error');
        }

    } catch (err) {
        loading.classList.add('hidden');
        showAlert('Fout bij uitvoeren van systeemcontrole: ' + err.message, 'error');
    }
}

// =============================================================================
// 2. Database Setup
// =============================================================================
function generateRandomDbPass() {
    const chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$%';
    let pass = '';
    for (let i = 0; i < 18; i++) {
        pass += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    const input = document.getElementById('db_pass');
    if (input) input.value = pass;
}

async function submitDatabase(e) {
    e.preventDefault();
    hideAlert();

    const btn = document.getElementById('btn-submit-db');
    const btnText = document.getElementById('db-btn-text');
    btn.disabled = true;
    btnText.textContent = 'Database initialiseren...';

    const formData = new FormData();
    formData.append('action', 'setup_database');
    formData.append('db_host', document.getElementById('db_host').value);
    formData.append('db_name', document.getElementById('db_name').value);
    formData.append('db_user', document.getElementById('db_user').value);
    formData.append('db_pass', document.getElementById('db_pass').value);
    formData.append('root_user', document.getElementById('root_user').value);
    formData.append('root_pass', document.getElementById('root_pass').value);

    try {
        const resp = await fetch('install_helper.php', { method: 'POST', body: formData });
        const data = await resp.json();

        if (data.ok) {
            showAlert('Database en tabellenschema succesvol geïnstalleerd!', 'success');
            setTimeout(() => goToStep(3), 800);
        } else {
            showAlert(data.error || 'Database installatie mislukt.', 'error');
            btn.disabled = false;
            btnText.textContent = 'Database Installeren & Schema Importeren';
        }
    } catch (err) {
        showAlert('Communicatiefout met de server: ' + err.message, 'error');
        btn.disabled = false;
        btnText.textContent = 'Database Installeren & Schema Importeren';
    }
}

// =============================================================================
// 3. Admin Account Creation
// =============================================================================
async function submitAdmin(e) {
    e.preventDefault();
    hideAlert();

    const btn = document.getElementById('btn-submit-admin');
    btn.disabled = true;

    const formData = new FormData();
    formData.append('action', 'create_admin');
    formData.append('voornaam', document.getElementById('admin_voornaam').value);
    formData.append('achternaam', document.getElementById('admin_achternaam').value);
    formData.append('email', document.getElementById('admin_email').value);
    formData.append('phone', document.getElementById('admin_phone').value);
    formData.append('username', document.getElementById('admin_username').value);
    formData.append('password', document.getElementById('admin_password').value);

    try {
        const resp = await fetch('install_helper.php', { method: 'POST', body: formData });
        const data = await resp.json();

        if (data.ok) {
            showAlert(`Beheerderaccount '${data.username}' succesvol aangemaakt!`, 'success');
            setTimeout(() => goToStep(4), 800);
        } else {
            showAlert(data.error || 'Aanmaken beheerderaccount mislukt.', 'error');
            btn.disabled = false;
        }
    } catch (err) {
        showAlert('Fout bij aanmaken beheerder: ' + err.message, 'error');
        btn.disabled = false;
    }
}

// =============================================================================
// 4. Jotihunt Scraping, Group Selection & Logo Upload
// =============================================================================
let fetchedGroupsList = [];

async function fetchJotihuntGroups() {
    const btn = document.getElementById('btn-fetch-groups');
    const statusBox = document.getElementById('group-fetch-status');
    const select = document.getElementById('group_select');

    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Ophalen...';
    }
    if (statusBox) {
        statusBox.classList.remove('hidden');
        statusBox.className = 'text-xs text-blue-400 mt-1 block';
        statusBox.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> Deelnemende groepen ophalen van Jotihunt.nl API...';
    }

    try {
        const formData = new FormData();
        formData.append('action', 'fetch_jotihunt_groups');
        const resp = await fetch('install_helper.php', { method: 'POST', body: formData });
        const data = await resp.json();

        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-cloud-arrow-down mr-1"></i> Haal Groepen Op';
        }

        if (data.ok && data.groups && data.groups.length > 0) {
            fetchedGroupsList = data.groups;
            if (select) {
                select.innerHTML = '';
                data.groups.forEach(g => {
                    const opt = document.createElement('option');
                    opt.value = g.id;
                    opt.dataset.name = g.name;
                    opt.dataset.city = g.city || '';
                    opt.dataset.area = g.area || '';
                    opt.textContent = `${g.id} - ${g.name} (${g.city || g.area || 'Gelderland'})`;
                    select.appendChild(opt);
                });

                const optManual = document.createElement('option');
                optManual.value = 'custom';
                optManual.textContent = '+ Handmatig ander groepsnummer invoeren';
                select.appendChild(optManual);

                onGroupSelect(select);
            }

            if (statusBox) {
                statusBox.className = 'text-xs text-emerald-400 mt-1 block font-medium';
                statusBox.innerHTML = `<i class="fa-solid fa-circle-check mr-1"></i> ${data.count} groepen succesvol opgehaald en gesynchroniseerd!`;
            }
        } else {
            if (statusBox) {
                statusBox.className = 'text-xs text-amber-400 mt-1 block';
                statusBox.innerHTML = '<i class="fa-solid fa-info-circle mr-1"></i> Geen groepen gevonden op API. Standaard placeholder groep ingesteld.';
            }
        }
    } catch (err) {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-cloud-arrow-down mr-1"></i> Haal Groepen Op';
        }
        if (statusBox) {
            statusBox.className = 'text-xs text-red-400 mt-1 block';
            statusBox.innerHTML = '<i class="fa-solid fa-circle-xmark mr-1"></i> Fout bij ophalen: ' + err.message;
        }
    }
}

function onGroupSelect(select) {
    const val = select.value;
    const selectedOpt = select.options[select.selectedIndex];
    const idInput = document.getElementById('group_id');
    const nameInput = document.getElementById('group_name');

    if (val === 'custom') {
        if (idInput) idInput.focus();
        return;
    }

    if (idInput) idInput.value = val;
    if (nameInput && selectedOpt && selectedOpt.dataset.name) {
        nameInput.value = selectedOpt.dataset.name;
    }
}

async function uploadLogoFile(e) {
    const file = e.target.files[0];
    if (!file) return;

    const statusEl = document.getElementById('logo-upload-status');
    const btn = document.getElementById('btn-upload-logo');
    const preview = document.getElementById('logo_preview');
    const urlInput = document.getElementById('group_logo_large_url');

    if (statusEl) {
        statusEl.classList.remove('hidden');
        statusEl.className = 'text-xs text-blue-400 mt-1';
        statusEl.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> Logo uploaden...';
    }
    if (btn) btn.disabled = true;

    const formData = new FormData();
    formData.append('action', 'upload_logo');
    formData.append('logo_file', file);

    try {
        const resp = await fetch('install_helper.php', { method: 'POST', body: formData });
        const data = await resp.json();
        if (btn) btn.disabled = false;

        if (data.ok && data.file_url) {
            if (statusEl) {
                statusEl.className = 'text-xs text-emerald-400 font-medium mt-1';
                statusEl.innerHTML = '<i class="fa-solid fa-circle-check mr-1"></i> ' + data.message;
            }
            if (urlInput) urlInput.value = data.file_url;
            if (preview) preview.src = data.file_url;
        } else {
            if (statusEl) {
                statusEl.className = 'text-xs text-red-400 mt-1';
                statusEl.innerHTML = '<i class="fa-solid fa-circle-xmark mr-1"></i> ' + (data.error || 'Upload mislukt');
            }
        }
    } catch (err) {
        if (btn) btn.disabled = false;
        if (statusEl) {
            statusEl.className = 'text-xs text-red-400 mt-1';
            statusEl.innerHTML = '<i class="fa-solid fa-circle-xmark mr-1"></i> Fout: ' + err.message;
        }
    }
}

async function scrapeJotihuntPortal() {
    const user = document.getElementById('joti_user').value.trim();
    const pass = document.getElementById('joti_pass').value.trim();
    const statusBox = document.getElementById('scrape-status');
    const btn = document.getElementById('btn-scrape');

    if (!user || !pass) {
        showAlert('Vul eerst je Jotihunt.nl inloggegevens in.', 'error');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Bezig...';
    statusBox.className = 'text-xs mt-2 p-2.5 rounded bg-slate-800 border border-slate-700 text-blue-400 block';
    statusBox.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> Inloggen op Jotihunt.nl en gegevens scrapen...';

    const formData = new FormData();
    formData.append('action', 'scrape_jotihunt');
    formData.append('joti_user', user);
    formData.append('joti_pass', pass);

    try {
        const resp = await fetch('install_helper.php', { method: 'POST', body: formData });
        const data = await resp.json();

        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-bolt"></i> Gegevens Ophalen';

        if (data.ok && data.scraped) {
            statusBox.className = 'text-xs mt-2 p-2.5 rounded bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 block';
            statusBox.innerHTML = '<i class="fa-solid fa-circle-check mr-1"></i> Gegevens succesvol opgehaald van Jotihunt.nl!';

            if (data.group_name) {
                const nameInput = document.getElementById('group_name');
                if (nameInput) nameInput.value = data.group_name;
            }
            if (data.group_id) {
                const idInput = document.getElementById('group_id');
                if (idInput) idInput.value = data.group_id;

                const select = document.getElementById('group_select');
                if (select) {
                    let found = false;
                    for (let i = 0; i < select.options.length; i++) {
                        if (select.options[i].value == data.group_id) {
                            select.selectedIndex = i;
                            found = true;
                            break;
                        }
                    }
                    if (!found && data.group_name) {
                        const opt = document.createElement('option');
                        opt.value = data.group_id;
                        opt.dataset.name = data.group_name;
                        opt.textContent = `${data.group_id} - ${data.group_name}`;
                        select.insertBefore(opt, select.firstChild);
                        select.selectedIndex = 0;
                    }
                }
            }
            if (data.group_url) document.getElementById('group_url').value = data.group_url;
        } else {
            statusBox.className = 'text-xs mt-2 p-2.5 rounded bg-red-500/10 border border-red-500/30 text-red-400 block';
            statusBox.innerHTML = '<i class="fa-solid fa-circle-xmark mr-1"></i> ' + (data.error || 'Kon gegevens niet ophalen.');
        }
    } catch (err) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-bolt"></i> Gegevens Ophalen';
        statusBox.className = 'text-xs mt-2 p-2.5 rounded bg-red-500/10 border border-red-500/30 text-red-400 block';
        statusBox.innerHTML = '<i class="fa-solid fa-circle-xmark mr-1"></i> Fout: ' + err.message;
    }
}

async function testApiKey(type) {
    let keyInput = null;
    let statusEl = null;

    if (type === 'mapbox') {
        keyInput = document.getElementById('api_key_mapbox');
        statusEl = document.getElementById('test-status-mapbox');
    } else if (type === 'telegram') {
        keyInput = document.getElementById('telegram_bot_token');
        statusEl = document.getElementById('test-status-telegram');
    }

    if (!keyInput || !keyInput.value.trim()) {
        showAlert('Vul eerst een sleutel of token in om te testen.', 'error');
        return;
    }

    statusEl.classList.remove('hidden');
    statusEl.className = 'text-xs mt-1 text-blue-400';
    statusEl.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> Verifiëren...';

    const formData = new FormData();
    formData.append('action', 'test_api_key');
    formData.append('type', type);
    formData.append('key', keyInput.value.trim());

    try {
        const resp = await fetch('install_helper.php', { method: 'POST', body: formData });
        const data = await resp.json();

        if (data.ok && data.valid) {
            statusEl.className = 'text-xs mt-1 text-emerald-400 font-medium';
            statusEl.innerHTML = `<i class="fa-solid fa-circle-check mr-1"></i> ${data.message}`;
        } else {
            statusEl.className = 'text-xs mt-1 text-red-400';
            statusEl.innerHTML = `<i class="fa-solid fa-circle-xmark mr-1"></i> ${data.error || 'Validatie mislukt'}`;
        }
    } catch (err) {
        statusEl.className = 'text-xs mt-1 text-red-400';
        statusEl.innerHTML = `<i class="fa-solid fa-circle-xmark mr-1"></i> Fout: ${err.message}`;
    }
}

async function submitSettings(e) {
    e.preventDefault();
    hideAlert();

    const btn = document.getElementById('btn-submit-settings');
    btn.disabled = true;

    const formData = new FormData();
    formData.append('action', 'save_settings');
    formData.append('group_id', document.getElementById('group_id').value);
    formData.append('group_name', document.getElementById('group_name') ? document.getElementById('group_name').value : '');
    formData.append('group_url', document.getElementById('group_url').value);
    formData.append('group_logo_large_url', document.getElementById('group_logo_large_url').value);
    formData.append('api_key_mapbox', document.getElementById('api_key_mapbox').value);
    formData.append('telegram_bot_token', document.getElementById('telegram_bot_token').value);
    formData.append('telegram_api_id', document.getElementById('telegram_api_id').value);
    formData.append('telegram_api_hash', document.getElementById('telegram_api_hash').value);
    formData.append('game_startdate', document.getElementById('game_startdate').value);
    formData.append('game_enddate', document.getElementById('game_enddate').value);
    formData.append('foxexchange_startdate', document.getElementById('foxexchange_startdate').value);
    formData.append('foxexchange_enddate', document.getElementById('foxexchange_enddate').value);
    formData.append('fox_names', document.getElementById('fox_names').value);
    formData.append('joti_user', document.getElementById('joti_user').value);
    formData.append('joti_pass', document.getElementById('joti_pass').value);

    try {
        const resp = await fetch('install_helper.php', { method: 'POST', body: formData });
        const data = await resp.json();

        if (data.ok) {
            showAlert('Spel- en API instellingen succesvol opgeslagen!', 'success');
            setTimeout(() => goToStep(5), 800);
        } else {
            showAlert(data.error || 'Opslaan van instellingen mislukt.', 'error');
            btn.disabled = false;
        }
    } catch (err) {
        showAlert('Fout bij opslaan van instellingen: ' + err.message, 'error');
        btn.disabled = false;
    }
}

// =============================================================================
// 5. Background Crontab Setup
// =============================================================================
async function submitCrontab(e) {
    e.preventDefault();
    hideAlert();

    const btn = document.getElementById('btn-submit-cron');
    btn.disabled = true;

    const formData = new FormData();
    formData.append('action', 'setup_crontab');
    formData.append('enable_cron', document.getElementById('enable_cron').checked ? '1' : '0');
    formData.append('cron_interval', document.getElementById('cron_interval').value);

    try {
        const resp = await fetch('install_helper.php', { method: 'POST', body: formData });
        const data = await resp.json();

        if (data.ok) {
            showAlert('Achtergrondtaken en crontab succesvol ingesteld!', 'success');
            setTimeout(() => goToStep(6), 800);
        } else {
            showAlert(data.error || 'Instellen van crontab mislukt.', 'error');
            btn.disabled = false;
        }
    } catch (err) {
        showAlert('Fout bij instellen crontab: ' + err.message, 'error');
        btn.disabled = false;
    }
}

// =============================================================================
// 6. Finalize & Start Jotify
// =============================================================================
async function finalizeInstallation() {
    hideAlert();
    const btn = document.getElementById('btn-finalize');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Bezig met afronden...';

    const formData = new FormData();
    formData.append('action', 'finalize_installation');

    try {
        const resp = await fetch('install_helper.php', { method: 'POST', body: formData });
        const data = await resp.json();

        if (data.ok) {
            window.location.href = data.redirect || 'login';
        } else {
            showAlert(data.error || 'Afronden mislukt.', 'error');
            btn.disabled = false;
            btn.innerHTML = 'Start Jotify <i class="fa-solid fa-rocket"></i>';
        }
    } catch (err) {
        showAlert('Fout bij afronden: ' + err.message, 'error');
        btn.disabled = false;
        btn.innerHTML = 'Start Jotify <i class="fa-solid fa-rocket"></i>';
    }
}
