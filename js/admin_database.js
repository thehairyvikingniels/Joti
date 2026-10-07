/**
 * js/admin_database.js
 *
 * Full-featured Database Explorer client logic for Jotify Superadmins:
 * - Table selector & live schema detection
 * - Visual query builder (AND filters, column sorting, top X limits)
 * - Live generated SQL preview & transfer to manual mode
 * - Manual SQL editor with real-time status badge and dry-run preview
 * - Inline cell editing (double-click, Enter to save, Esc to cancel)
 * - Row edit & delete modals with confirmation and audit logging
 * - New row insertion modal
 * - In-memory row filtering & CSV export
 */

// Application State
const state = {
    tables: [],
    currentTable: '',
    schema: null,
    mode: 'builder', // 'builder' | 'manual'
    filters: [],     // array of { id, column, operator, value }
    filterIdCounter: 0,
    sortColumn: '',
    sortDirection: 'ASC',
    limit: 100,
    data: null,      // last result { columns, rows, count, execution_time_ms, primary_key, editable, editable_table }
    editingCell: null,
    deleteTarget: null,
    editTarget: null,
    pendingManualWrite: null
};

// ==========================================
// Toast & Utility Helpers
// ==========================================

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

async function fetchJsonSafely(url, options = {}) {
    const response = await fetch(url, options);
    const text = await response.text();
    try {
        return JSON.parse(text);
    } catch (e) {
        console.error('Non-JSON server response:', text);
        const cleanMsg = text.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
        throw new Error(cleanMsg.substring(0, 180) || 'Ongeldig serverantwoord.');
    }
}

// ==========================================
// Initialization & Global Event Listeners
// ==========================================

document.addEventListener('DOMContentLoaded', () => {
    loadTables();

    // Keyboard Shortcuts
    document.addEventListener('keydown', (e) => {
        // Ctrl+Enter or Cmd+Enter to execute query
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            e.preventDefault();
            executeQuery();
        }

        // Escape to cancel cell editing or close modals
        if (e.key === 'Escape') {
            if (state.editingCell) {
                cancelInlineCellEdit();
            } else {
                closeAllModals();
            }
        }
    });
});

function closeAllModals() {
    closeConfirmWriteModal();
    closeDeleteRowModal();
    closeEditRowModal();
    closeInsertRowModal();
}

// ==========================================
// Mode Switching
// ==========================================

function switchMode(mode) {
    state.mode = mode;

    const btnBuilder = document.getElementById('btn-mode-builder');
    const btnManual = document.getElementById('btn-mode-manual');
    const panelBuilder = document.getElementById('panel-builder');
    const panelManual = document.getElementById('panel-manual');

    if (mode === 'builder') {
        btnBuilder.className = 'px-3.5 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5 theme-bg-primary text-white shadow-sm';
        btnManual.className = 'px-3.5 py-1.5 rounded-lg text-xs font-semibold opacity-70 hover:opacity-100 transition flex items-center gap-1.5';
        panelBuilder.classList.remove('hidden');
        panelManual.classList.add('hidden');
    } else {
        btnManual.className = 'px-3.5 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5 theme-bg-primary text-white shadow-sm';
        btnBuilder.className = 'px-3.5 py-1.5 rounded-lg text-xs font-semibold opacity-70 hover:opacity-100 transition flex items-center gap-1.5';
        panelManual.classList.remove('hidden');
        panelBuilder.classList.add('hidden');

        const manualInput = document.getElementById('manual-sql-input');
        if (!manualInput.value.trim()) {
            manualInput.value = generateBuilderSql();
        }
        onManualSqlInput();
    }
}

// ==========================================
// Table Schema & Table Listing
// ==========================================

async function loadTables() {
    try {
        const formData = new FormData();
        formData.append('action', 'list_tables');

        const data = await fetchJsonSafely('database_helper.php', {
            method: 'POST',
            body: formData
        });

        if (!data.success) {
            showToast(data.error || 'Fout bij het ophalen van tabellen.', 'error');
            return;
        }

        state.tables = data.tables || [];
        const tableSelect = document.getElementById('builder-table');
        tableSelect.innerHTML = '<option value="" disabled selected>Selecteer een tabel...</option>';

        state.tables.forEach((t) => {
            const opt = document.createElement('option');
            opt.value = t;
            opt.textContent = t;
            tableSelect.appendChild(opt);
        });

        // Default to Gebruikers or Users or first table
        const defaultTable = state.tables.includes('Gebruikers') 
            ? 'Gebruikers' 
            : (state.tables.includes('Users') ? 'Users' : state.tables[0]);
        if (defaultTable) {
            tableSelect.value = defaultTable;
            onTableSelected();
        }
    } catch (err) {
        showToast(err.message, 'error');
    }
}

async function onTableSelected() {
    const tableSelect = document.getElementById('builder-table');
    const table = tableSelect.value;
    if (!table) return;

    state.currentTable = table;
    state.filters = [];
    state.filterIdCounter = 0;

    await loadTableSchema(table);
}

async function loadTableSchema(table) {
    try {
        const formData = new FormData();
        formData.append('action', 'table_schema');
        formData.append('table', table);

        const data = await fetchJsonSafely('database_helper.php', {
            method: 'POST',
            body: formData
        });

        if (!data.success) {
            showToast(data.error || 'Fout bij ophalen schema.', 'error');
            return;
        }

        state.schema = data.schema;

        // Populate Sort Column Dropdown
        const sortSelect = document.getElementById('builder-sort-col');
        sortSelect.innerHTML = '<option value="">(Geen sortering)</option>';

        const colNames = Object.keys(state.schema.columns || {});
        colNames.forEach((c) => {
            const opt = document.createElement('option');
            opt.value = c;
            const isPk = (state.schema.primary_key || []).includes(c);
            opt.textContent = isPk ? `${c} (PK)` : c;
            sortSelect.appendChild(opt);
        });

        // Default sort to primary key or first column
        if (state.schema.primary_key && state.schema.primary_key.length > 0) {
            sortSelect.value = state.schema.primary_key[0];
            state.sortColumn = state.schema.primary_key[0];
        } else if (colNames.length > 0) {
            sortSelect.value = colNames[0];
            state.sortColumn = colNames[0];
        }

        // Show/hide New Row button based on PK presence
        const btnInsert = document.getElementById('btn-open-insert-modal');
        if (state.schema.primary_key && state.schema.primary_key.length > 0) {
            btnInsert.classList.remove('hidden');
        } else {
            btnInsert.classList.add('hidden');
        }

        // Render filter rows (empty)
        renderFilterRows();

        // Update preview & execute automatically to show initial table contents
        updateQueryPreview();
        executeQuery();
    } catch (err) {
        showToast(err.message, 'error');
    }
}

// ==========================================
// Filter Builder
// ==========================================

function addFilterRow() {
    if (!state.schema || !state.schema.columns) {
        showToast('Selecteer eerst een tabel om filters toe te voegen.', 'warning');
        return;
    }

    const colNames = Object.keys(state.schema.columns);
    if (colNames.length === 0) return;

    state.filterIdCounter++;
    const newFilter = {
        id: state.filterIdCounter,
        column: colNames[0],
        operator: '=',
        value: ''
    };

    state.filters.push(newFilter);
    renderFilterRows();
    updateQueryPreview();
}

function removeFilterRow(id) {
    state.filters = state.filters.filter(f => f.id !== id);
    renderFilterRows();
    updateQueryPreview();
}

function renderFilterRows() {
    const container = document.getElementById('builder-filters-container');
    const badge = document.getElementById('filters-count-badge');
    badge.textContent = state.filters.length;

    if (state.filters.length === 0) {
        container.innerHTML = `
            <div id="empty-filters-placeholder" class="p-4 rounded-xl border border-dashed text-center opacity-60 text-xs" style="border-color: var(--theme-card-border);">
                Geen filters actief. Klik op <strong>+ Filter Toevoegen</strong> om rijen te filteren op kolomwaarden.
            </div>
        `;
        return;
    }

    container.innerHTML = '';
    const colNames = Object.keys(state.schema.columns || {});

    state.filters.forEach((filter, idx) => {
        const colDef = state.schema.columns[filter.column] || {};
        const isNullOp = (filter.operator === 'IS NULL' || filter.operator === 'IS NOT NULL');

        const row = document.createElement('div');
        row.className = 'flex flex-wrap items-center gap-2 p-2.5 rounded-xl border bg-black/5 dark:bg-white/5 text-xs';
        row.style.borderColor = 'var(--theme-card-border)';

        // 1. Column select
        const colSelect = document.createElement('select');
        colSelect.className = 'theme-input px-2.5 py-1.5 text-xs font-mono';
        colNames.forEach((c) => {
            const opt = document.createElement('option');
            opt.value = c;
            opt.textContent = c;
            if (c === filter.column) opt.selected = true;
            colSelect.appendChild(opt);
        });
        colSelect.onchange = (e) => {
            filter.column = e.target.value;
            filter.value = '';
            renderFilterRows();
            updateQueryPreview();
        };

        // 2. Operator select
        const opSelect = document.createElement('select');
        opSelect.className = 'theme-input px-2.5 py-1.5 text-xs font-mono font-bold';
        const operators = ['=', '!=', '<', '>', '<=', '>=', 'LIKE', 'NOT LIKE', 'IS NULL', 'IS NOT NULL', 'IN'];
        operators.forEach((op) => {
            const opt = document.createElement('option');
            opt.value = op;
            opt.textContent = op;
            if (op === filter.operator) opt.selected = true;
            opSelect.appendChild(opt);
        });
        opSelect.onchange = (e) => {
            filter.operator = e.target.value;
            renderFilterRows();
            updateQueryPreview();
        };

        // 3. Value Input
        let valueEl;
        if (isNullOp) {
            valueEl = document.createElement('input');
            valueEl.type = 'text';
            valueEl.disabled = true;
            valueEl.placeholder = '(Geen waarde nodig)';
            valueEl.className = 'theme-input px-2.5 py-1.5 text-xs opacity-50 flex-1 min-w-[120px]';
        } else if (colDef.data_type === 'enum' && colDef.enum_values && colDef.enum_values.length > 0 && filter.operator !== 'IN') {
            valueEl = document.createElement('select');
            valueEl.className = 'theme-input px-2.5 py-1.5 text-xs flex-1 min-w-[120px]';
            const emptyOpt = document.createElement('option');
            emptyOpt.value = '';
            emptyOpt.textContent = '-- Selecteer waarde --';
            valueEl.appendChild(emptyOpt);
            colDef.enum_values.forEach((v) => {
                const opt = document.createElement('option');
                opt.value = v;
                opt.textContent = v;
                if (v === filter.value) opt.selected = true;
                valueEl.appendChild(opt);
            });
            valueEl.onchange = (e) => {
                filter.value = e.target.value;
                updateQueryPreview();
            };
        } else {
            valueEl = document.createElement('input');
            if (filter.operator === 'IN') {
                valueEl.type = 'text';
                valueEl.placeholder = 'waarde1, waarde2, waarde3';
            } else if (colDef.data_type === 'int' || colDef.data_type === 'tinyint' || colDef.data_type === 'bigint') {
                valueEl.type = 'number';
                valueEl.placeholder = 'Numerieke waarde';
            } else if (colDef.data_type === 'datetime' || colDef.data_type === 'timestamp') {
                valueEl.type = 'datetime-local';
            } else if (colDef.data_type === 'date') {
                valueEl.type = 'date';
            } else {
                valueEl.type = 'text';
                valueEl.placeholder = 'Filterwaarde...';
            }
            valueEl.value = filter.value;
            valueEl.className = 'theme-input px-2.5 py-1.5 text-xs flex-1 min-w-[120px]';
            valueEl.oninput = (e) => {
                filter.value = e.target.value;
                updateQueryPreview();
            };
        }

        // 4. Delete filter button
        const btnDelete = document.createElement('button');
        btnDelete.type = 'button';
        btnDelete.className = 'p-1.5 rounded-lg text-red-500 hover:bg-red-500/10 transition';
        btnDelete.title = 'Filter verwijderen';
        btnDelete.innerHTML = '<i class="fas fa-trash-alt"></i>';
        btnDelete.onclick = () => removeFilterRow(filter.id);

        row.appendChild(colSelect);
        row.appendChild(opSelect);
        row.appendChild(valueEl);
        row.appendChild(btnDelete);

        container.appendChild(row);
    });
}

function setLimit(limit) {
    const input = document.getElementById('builder-limit');
    if (input) {
        input.value = limit;
        updateQueryPreview();
    }
}

function generateBuilderSql() {
    if (!state.currentTable) return 'SELECT * FROM `...` LIMIT 100;';

    let sql = `SELECT * FROM \`${state.currentTable}\``;

    const whereParts = [];
    state.filters.forEach((f) => {
        if (!f.column) return;
        if (f.operator === 'IS NULL' || f.operator === 'IS NOT NULL') {
            whereParts.push(`\`${f.column}\` ${f.operator}`);
        } else if (f.operator === 'IN') {
            const vals = f.value.split(',').map(s => s.trim()).filter(s => s.length > 0);
            if (vals.length > 0) {
                const escapedVals = vals.map(v => `'${v.replace(/'/g, "\\'")}'`).join(', ');
                whereParts.push(`\`${f.column}\` IN (${escapedVals})`);
            }
        } else {
            whereParts.push(`\`${f.column}\` ${f.operator} '${String(f.value).replace(/'/g, "\\'")}'`);
        }
    });

    if (whereParts.length > 0) {
        sql += ` WHERE ${whereParts.join(' AND ')}`;
    }

    const sortCol = document.getElementById('builder-sort-col')?.value || '';
    const sortDir = document.getElementById('builder-sort-dir')?.value || 'ASC';
    if (sortCol) {
        sql += ` ORDER BY \`${sortCol}\` ${sortDir}`;
    }

    let limit = parseInt(document.getElementById('builder-limit')?.value || '100', 10);
    if (isNaN(limit) || limit < 1) limit = 100;
    if (limit > 5000) limit = 5000;
    sql += ` LIMIT ${limit}`;

    return sql + ';';
}

function updateQueryPreview() {
    const previewBox = document.getElementById('builder-sql-preview');
    if (previewBox) {
        previewBox.textContent = generateBuilderSql();
    }
}

function copySqlPreview() {
    const sql = generateBuilderSql();
    navigator.clipboard.writeText(sql).then(() => {
        showToast('SQL-query gekopieerd naar klembord!', 'success', 2000);
    }).catch(() => {
        showToast('Kopiëren mislukt.', 'error');
    });
}

function transferToManualMode() {
    const sql = generateBuilderSql();
    const manualInput = document.getElementById('manual-sql-input');
    if (manualInput) {
        manualInput.value = sql;
    }
    switchMode('manual');
    onManualSqlInput();
}

// ==========================================
// Manual SQL Mode & Safety Checks
// ==========================================

function onManualSqlInput() {
    const sql = document.getElementById('manual-sql-input')?.value || '';
    const badge = document.getElementById('manual-query-type-badge');
    const dryRunBtn = document.getElementById('btn-manual-dry-run');
    if (!badge) return;

    const trimmed = sql.trim();
    if (!trimmed) {
        badge.className = 'px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-500/15 text-gray-500 border border-gray-500/30 flex items-center gap-1.5';
        badge.innerHTML = '<i class="fas fa-circle-question"></i><span>Voer een query in</span>';
        if (dryRunBtn) dryRunBtn.disabled = true;
        return;
    }

    // Check for DDL / dangerous keywords
    const forbiddenRegex = /\b(ALTER|DROP|TRUNCATE|CREATE|RENAME|GRANT|REVOKE|LOAD_FILE|INTO\s+OUTFILE|INTO\s+DUMPFILE|LOCK\s+TABLES|SET\s+GLOBAL)\b/i;
    if (forbiddenRegex.test(trimmed)) {
        badge.className = 'px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-500/15 text-red-600 dark:text-red-400 border border-red-500/30 flex items-center gap-1.5';
        badge.innerHTML = '<i class="fas fa-ban"></i><span>Geblokkeerd (DDL / Onveilig)</span>';
        if (dryRunBtn) dryRunBtn.disabled = true;
        return;
    }

    // Check for multi-statements (semicolon followed by non-whitespace)
    if (/;[ \t\r\n]*\S/.test(trimmed)) {
        badge.className = 'px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-500/15 text-red-600 dark:text-red-400 border border-red-500/30 flex items-center gap-1.5';
        badge.innerHTML = '<i class="fas fa-triangle-exclamation"></i><span>Meerdere opdrachten niet toegestaan</span>';
        if (dryRunBtn) dryRunBtn.disabled = true;
        return;
    }

    const firstWordMatch = trimmed.match(/^([a-zA-Z]+)/);
    const firstWord = firstWordMatch ? firstWordMatch[1].toUpperCase() : '';

    if (['UPDATE', 'DELETE', 'INSERT', 'REPLACE'].includes(firstWord)) {
        badge.className = 'px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/30 flex items-center gap-1.5';
        badge.innerHTML = `<i class="fas fa-triangle-exclamation"></i><span>Schrijfopdracht (${firstWord})</span>`;
        if (dryRunBtn) dryRunBtn.disabled = false;
    } else if (['SELECT', 'SHOW', 'DESCRIBE', 'EXPLAIN'].includes(firstWord)) {
        badge.className = 'px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30 flex items-center gap-1.5';
        badge.innerHTML = `<i class="fas fa-circle-check"></i><span>Alleen Lezen (${firstWord})</span>`;
        if (dryRunBtn) dryRunBtn.disabled = true;
    } else {
        badge.className = 'px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-500/15 text-gray-500 border border-gray-500/30 flex items-center gap-1.5';
        badge.innerHTML = `<i class="fas fa-question-circle"></i><span>Onbekend (${firstWord})</span>`;
        if (dryRunBtn) dryRunBtn.disabled = true;
    }
}

function resetManualToBuilder() {
    const manualInput = document.getElementById('manual-sql-input');
    if (manualInput) {
        manualInput.value = generateBuilderSql();
        onManualSqlInput();
        showToast('Query hersteld naar Visuele Bouwer.', 'info', 2000);
    }
}

async function executeDryRun() {
    const sql = document.getElementById('manual-sql-input')?.value || '';
    if (!sql.trim()) {
        showToast('Voer eerst een query in.', 'warning');
        return;
    }

    const statusEl = document.getElementById('manual-dry-run-status');
    if (statusEl) {
        statusEl.classList.remove('hidden');
        statusEl.textContent = 'Dry-run testen...';
    }

    try {
        const formData = new FormData();
        formData.append('action', 'dry_run');
        formData.append('query', sql.trim().replace(/;+$/, ''));

        const data = await fetchJsonSafely('database_helper.php', {
            method: 'POST',
            body: formData
        });

        if (!data.success) {
            showToast(data.error || 'Dry-run mislukt.', 'error');
            if (statusEl) statusEl.textContent = 'Dry-run fout: ' + data.error;
            return;
        }

        const count = data.affected_rows ?? 0;
        showToast(`Dry-run geslaagd: ${count} rij(en) beïnvloed (teruggedraaid).`, 'warning', 4500);
        if (statusEl) statusEl.textContent = `Dry-run: ${count} rijen beïnvloed`;
    } catch (err) {
        showToast(err.message, 'error');
        if (statusEl) statusEl.textContent = 'Fout: ' + err.message;
    }
}

// ==========================================
// Query Execution Handler
// ==========================================

async function executeQuery() {
    if (state.editingCell) {
        cancelInlineCellEdit();
    }

    setLoadingState(true);

    try {
        if (state.mode === 'builder') {
            await executeBuilderQuery();
        } else {
            await executeManualQuery();
        }
    } catch (err) {
        showToast(err.message, 'error');
        setLoadingState(false);
    }
}

async function executeBuilderQuery() {
    if (!state.currentTable) {
        showToast('Selecteer eerst een tabel.', 'warning');
        setLoadingState(false);
        return;
    }

    const sortCol = document.getElementById('builder-sort-col')?.value || '';
    const sortDir = document.getElementById('builder-sort-dir')?.value || 'ASC';
    const limit = document.getElementById('builder-limit')?.value || '100';

    const formData = new FormData();
    formData.append('action', 'build_query');
    formData.append('table', state.currentTable);
    formData.append('filters', JSON.stringify(state.filters));
    formData.append('sort_column', sortCol);
    formData.append('sort_direction', sortDir);
    formData.append('limit', limit);

    const data = await fetchJsonSafely('database_helper.php', {
        method: 'POST',
        body: formData
    });

    setLoadingState(false);

    if (!data.success) {
        showToast(data.error || 'Fout bij uitvoeren van query.', 'error');
        renderEmptyState(data.error);
        return;
    }

    renderGrid(data);
}

async function executeManualQuery() {
    const rawSql = (document.getElementById('manual-sql-input')?.value || '').trim();
    if (!rawSql) {
        showToast('Voer een SQL query in.', 'warning');
        setLoadingState(false);
        return;
    }

    const cleanedSql = rawSql.replace(/;+$/, '');
    const firstWordMatch = cleanedSql.match(/^([a-zA-Z]+)/);
    const firstWord = firstWordMatch ? firstWordMatch[1].toUpperCase() : '';

    // If it's a write query, perform dry-run first and open confirmation modal
    if (['UPDATE', 'DELETE', 'INSERT', 'REPLACE'].includes(firstWord)) {
        setLoadingState(false);
        await prepareManualWriteConfirmation(cleanedSql);
        return;
    }

    const formData = new FormData();
    formData.append('action', 'run_manual');
    formData.append('query', cleanedSql);

    const data = await fetchJsonSafely('database_helper.php', {
        method: 'POST',
        body: formData
    });

    setLoadingState(false);

    if (!data.success) {
        showToast(data.error || 'Fout bij handmatige query.', 'error');
        renderEmptyState(data.error);
        return;
    }

    renderGrid(data);
}

async function prepareManualWriteConfirmation(sql) {
    try {
        const formData = new FormData();
        formData.append('action', 'dry_run');
        formData.append('query', sql);

        const data = await fetchJsonSafely('database_helper.php', {
            method: 'POST',
            body: formData
        });

        if (!data.success) {
            showToast('Fout bij dry-run verificatie: ' + data.error, 'error');
            return;
        }

        const affected = data.affected_rows ?? 0;
        state.pendingManualWrite = { query: sql, affectedRows: affected };

        document.getElementById('confirm-write-query').textContent = sql;
        document.getElementById('confirm-write-impact').innerHTML = `Deze query beïnvloedt vermoedelijk <strong>${affected}</strong> rij(en) in de database.`;
        document.getElementById('check-confirm-write').checked = false;
        toggleConfirmWriteButton(false);

        openModal('modal-confirm-write');
    } catch (err) {
        showToast(err.message, 'error');
    }
}

function toggleConfirmWriteButton(isChecked) {
    const btn = document.getElementById('btn-execute-confirmed-write');
    if (btn) btn.disabled = !isChecked;
}

function closeConfirmWriteModal() {
    closeModal('modal-confirm-write');
    state.pendingManualWrite = null;
}

async function submitConfirmedWrite() {
    if (!state.pendingManualWrite) return;
    const { query } = state.pendingManualWrite;

    closeConfirmWriteModal();
    setLoadingState(true);

    try {
        const formData = new FormData();
        formData.append('action', 'run_manual');
        formData.append('query', query);
        formData.append('confirmed', '1');

        const data = await fetchJsonSafely('database_helper.php', {
            method: 'POST',
            body: formData
        });

        setLoadingState(false);

        if (!data.success) {
            showToast(data.error || 'Fout bij uitvoeren schrijfopdracht.', 'error');
            return;
        }

        const affected = data.affected_rows ?? 0;
        const elapsed = data.execution_time_ms ?? 0;

        showToast(`Schrijfopdracht voltooid! ${affected} rij(en) beïnvloed in ${elapsed} ms.`, 'success', 4000);

        // Show write success panel
        document.getElementById('grid-loading').classList.add('hidden');
        document.getElementById('grid-empty').classList.add('hidden');
        document.getElementById('grid-table-wrapper').classList.add('hidden');
        document.getElementById('grid-footer').classList.add('hidden');

        const writeCard = document.getElementById('grid-write-result');
        writeCard.classList.remove('hidden');
        document.getElementById('grid-write-details').textContent = `${affected} rij(en) beïnvloed in ${elapsed} ms.`;

        // Update stats
        document.getElementById('stat-row-count').textContent = affected;
        document.getElementById('stat-exec-time').textContent = elapsed;
        document.getElementById('stat-editable-badge').textContent = 'Schrijfopdracht';
        document.getElementById('stat-editable-badge').className = 'px-2 py-0.5 rounded font-bold text-[11px] bg-amber-500/20 text-amber-600 dark:text-amber-400';
    } catch (err) {
        showToast(err.message, 'error');
        setLoadingState(false);
    }
}

// ==========================================
// Loading & Empty States
// ==========================================

function setLoadingState(isLoading) {
    const loadingEl = document.getElementById('grid-loading');
    const emptyEl = document.getElementById('grid-empty');
    const wrapperEl = document.getElementById('grid-table-wrapper');
    const writeEl = document.getElementById('grid-write-result');
    const footerEl = document.getElementById('grid-footer');

    if (isLoading) {
        loadingEl.classList.remove('hidden');
        emptyEl.classList.add('hidden');
        wrapperEl.classList.add('hidden');
        writeEl.classList.add('hidden');
        footerEl.classList.add('hidden');
    } else {
        loadingEl.classList.add('hidden');
    }
}

function renderEmptyState(message) {
    const emptyEl = document.getElementById('grid-empty');
    const emptyText = document.getElementById('grid-empty-text');
    const wrapperEl = document.getElementById('grid-table-wrapper');
    const footerEl = document.getElementById('grid-footer');
    const writeEl = document.getElementById('grid-write-result');
    const tbody = document.getElementById('grid-tbody');

    if (tbody) tbody.innerHTML = '';
    const statCount = document.getElementById('stat-row-count');
    if (statCount) statCount.textContent = '0';

    writeEl.classList.add('hidden');
    wrapperEl.classList.add('hidden');
    footerEl.classList.add('hidden');
    emptyEl.classList.remove('hidden');

    if (emptyText) {
        emptyText.textContent = message || 'Geen gegevens gevonden voor deze query.';
    }
}

// ==========================================
// Grid Rendering
// ==========================================

function renderGrid(data) {
    state.data = data;

    // Update Status Bar
    document.getElementById('stat-row-count').textContent = data.count ?? data.rows.length;
    document.getElementById('stat-exec-time').textContent = data.execution_time_ms ?? 0;

    const pkBadge = document.getElementById('stat-pk-badge');
    const editableBadge = document.getElementById('stat-editable-badge');

    if (data.primary_key && data.primary_key.length > 0) {
        pkBadge.innerHTML = `<i class="fas fa-key text-amber-500 mr-1"></i>PK: ${data.primary_key.join(', ')}`;
    } else {
        pkBadge.innerHTML = '<i class="fas fa-key opacity-40 mr-1"></i>Geen PK';
    }

    if (data.editable) {
        editableBadge.textContent = 'Bewerkbaar';
        editableBadge.className = 'px-2 py-0.5 rounded font-bold text-[11px] bg-emerald-500/20 text-emerald-600 dark:text-emerald-400';
    } else {
        editableBadge.textContent = 'Alleen Lezen';
        editableBadge.className = 'px-2 py-0.5 rounded font-bold text-[11px] bg-black/10 opacity-70';
    }

    if (!data.rows || data.rows.length === 0) {
        renderEmptyState('Deze query levert 0 rijen op.');
        return;
    }

    const wrapperEl = document.getElementById('grid-table-wrapper');
    const writeEl = document.getElementById('grid-write-result');
    const emptyEl = document.getElementById('grid-empty');
    const footerEl = document.getElementById('grid-footer');

    writeEl.classList.add('hidden');
    emptyEl.classList.add('hidden');
    wrapperEl.classList.remove('hidden');
    footerEl.classList.remove('hidden');

    document.getElementById('grid-footer-info').textContent = `${data.rows.length} rijen geladen`;

    // Render Table Header
    const thead = document.getElementById('grid-thead');
    thead.innerHTML = '';
    const headerRow = document.createElement('tr');

    // Sticky Actions Column Header
    if (data.editable) {
        const thAct = document.createElement('th');
        thAct.className = 'sticky-action-col px-4 py-2.5 font-bold uppercase tracking-wider text-[11px] bg-black/10 dark:bg-black/60 border-r text-center';
        thAct.style.borderColor = 'var(--theme-card-border)';
        thAct.textContent = 'Acties';
        headerRow.appendChild(thAct);
    }

    data.columns.forEach((col) => {
        const th = document.createElement('th');
        th.className = 'px-4 py-2.5 font-bold uppercase tracking-wider text-[11px] border-r hover:bg-black/5 dark:hover:bg-white/5 transition';
        th.style.borderColor = 'var(--theme-card-border)';

        const isPk = (data.primary_key || []).includes(col);
        th.innerHTML = `
            <div class="flex items-center gap-1.5 font-mono">
                <span>${escapeHtml(col)}</span>
                ${isPk ? '<i class="fas fa-key text-amber-500 text-[10px]" title="Primaire Sleutel"></i>' : ''}
            </div>
        `;
        headerRow.appendChild(th);
    });
    thead.appendChild(headerRow);

    // Render Table Body
    const tbody = document.getElementById('grid-tbody');
    tbody.innerHTML = '';

    data.rows.forEach((row, rowIdx) => {
        const tr = document.createElement('tr');
        tr.className = 'hover:bg-black/5 dark:hover:bg-white/5 transition group';
        tr.dataset.rowIndex = rowIdx;

        // Sticky Actions Column
        if (data.editable) {
            const tdAct = document.createElement('td');
            tdAct.className = 'sticky-action-col px-3 py-2 bg-inherit backdrop-blur border-r text-center';
            tdAct.style.borderColor = 'var(--theme-card-border)';
            tdAct.innerHTML = `
                <div class="flex items-center justify-center gap-1.5">
                    <button type="button" onclick="openEditRowModal(${rowIdx})" class="p-1 rounded text-blue-500 hover:bg-blue-500/10 transition" title="Rij bewerken">
                        <i class="fas fa-pencil-alt text-xs"></i>
                    </button>
                    <button type="button" onclick="openDeleteRowModal(${rowIdx})" class="p-1 rounded text-red-500 hover:bg-red-500/10 transition" title="Rij verwijderen">
                        <i class="fas fa-trash-alt text-xs"></i>
                    </button>
                </div>
            `;
            tr.appendChild(tdAct);
        }

        // Data Cells
        data.columns.forEach((col) => {
            const td = document.createElement('td');
            const val = row[col];
            const isPk = (data.primary_key || []).includes(col);
            const isEditable = data.editable && !isPk;

            td.className = 'px-4 py-2 border-r font-mono max-w-xs truncate';
            td.style.borderColor = 'var(--theme-card-border)';
            td.dataset.column = col;
            td.dataset.rowIndex = rowIdx;

            if (isEditable) {
                td.classList.add('grid-cell-editable');
                td.title = 'Dubbelklik om te bewerken';
                td.ondblclick = () => startInlineCellEdit(td, rowIdx, col);
            }

            renderCellContent(td, val);
            tr.appendChild(td);
        });

        tbody.appendChild(tr);
    });
}

function renderCellContent(td, val) {
    if (val === null || val === undefined) {
        td.innerHTML = '<span class="italic opacity-40 px-1 py-0.5 rounded bg-black/5 dark:bg-white/5 text-[10px]">NULL</span>';
    } else {
        td.textContent = String(val);
    }
}

// ==========================================
// Inline Cell Editing
// ==========================================

function startInlineCellEdit(td, rowIndex, colName) {
    if (state.editingCell) {
        cancelInlineCellEdit();
    }

    const row = state.data.rows[rowIndex];
    const originalValue = row[colName];
    const isPk = (state.data.primary_key || []).includes(colName);
    if (isPk) {
        showToast('Primaire sleutelkolommen kunnen niet direct bewerkt worden.', 'warning');
        return;
    }

    state.editingCell = {
        td,
        rowIndex,
        colName,
        originalValue
    };

    td.innerHTML = '';
    td.classList.remove('truncate');

    const wrapper = document.createElement('div');
    wrapper.className = 'flex items-center gap-1 min-w-[160px]';

    const colDef = state.schema?.columns ? state.schema.columns[colName] : null;
    let input;

    if (colDef && colDef.data_type === 'enum' && colDef.enum_values && colDef.enum_values.length > 0) {
        input = document.createElement('select');
        input.className = 'theme-input px-2 py-1 text-xs font-mono flex-1';
        if (colDef.is_nullable) {
            const nullOpt = document.createElement('option');
            nullOpt.value = '__NULL__';
            nullOpt.textContent = '(NULL)';
            if (originalValue === null) nullOpt.selected = true;
            input.appendChild(nullOpt);
        }
        colDef.enum_values.forEach((v) => {
            const opt = document.createElement('option');
            opt.value = v;
            opt.textContent = v;
            if (v === originalValue) opt.selected = true;
            input.appendChild(opt);
        });
    } else {
        input = document.createElement('input');
        input.type = 'text';
        input.value = originalValue === null ? '' : originalValue;
        input.placeholder = originalValue === null ? '(NULL)' : '';
        input.className = 'theme-input px-2 py-1 text-xs font-mono flex-1';
    }

    // Save on Enter, cancel on Escape
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            commitInlineCellEdit();
        } else if (e.key === 'Escape') {
            e.preventDefault();
            cancelInlineCellEdit();
        }
    });

    // Save button
    const btnSave = document.createElement('button');
    btnSave.type = 'button';
    btnSave.className = 'p-1 rounded text-emerald-600 hover:bg-emerald-500/10 transition';
    btnSave.title = 'Opslaan (Enter)';
    btnSave.innerHTML = '<i class="fas fa-check"></i>';
    btnSave.onclick = commitInlineCellEdit;

    // Cancel button
    const btnCancel = document.createElement('button');
    btnCancel.type = 'button';
    btnCancel.className = 'p-1 rounded text-gray-400 hover:bg-black/10 transition';
    btnCancel.title = 'Annuleren (Esc)';
    btnCancel.innerHTML = '<i class="fas fa-times"></i>';
    btnCancel.onclick = cancelInlineCellEdit;

    wrapper.appendChild(input);
    wrapper.appendChild(btnSave);
    wrapper.appendChild(btnCancel);

    td.appendChild(wrapper);
    input.focus();
    if (input.select) input.select();
}

async function commitInlineCellEdit() {
    if (!state.editingCell) return;
    const { td, rowIndex, colName, originalValue } = state.editingCell;

    const input = td.querySelector('input, select');
    if (!input) return;

    let newValue = input.value;
    let isNull = false;

    if (input.tagName.toLowerCase() === 'select' && newValue === '__NULL__') {
        isNull = true;
        newValue = null;
    }

    // If unchanged, simply exit
    if (originalValue === newValue || (originalValue === null && isNull)) {
        cancelInlineCellEdit();
        return;
    }

    // Build PK object
    const row = state.data.rows[rowIndex];
    const pk = {};
    (state.data.primary_key || []).forEach((pkCol) => {
        pk[pkCol] = row[pkCol];
    });

    const targetTable = state.data.editable_table || state.currentTable;

    try {
        const formData = new FormData();
        formData.append('action', 'update_cell');
        formData.append('table', targetTable);
        formData.append('column', colName);
        formData.append('pk', JSON.stringify(pk));
        formData.append('value', isNull ? '' : newValue);
        formData.append('is_null', isNull ? '1' : '0');

        const res = await fetchJsonSafely('database_helper.php', {
            method: 'POST',
            body: formData
        });

        if (!res.success) {
            showToast(res.error || 'Fout bij bijwerken van cel.', 'error');
            cancelInlineCellEdit();
            return;
        }

        // Success: update local data and render
        row[colName] = isNull ? null : newValue;
        state.editingCell = null;
        td.classList.add('truncate');
        renderCellContent(td, row[colName]);

        // Subtle green flash
        td.classList.add('bg-emerald-500/20');
        setTimeout(() => td.classList.remove('bg-emerald-500/20'), 1000);

        showToast(`Kolom '${colName}' bijgewerkt.`, 'success', 2000);
    } catch (err) {
        showToast(err.message, 'error');
        cancelInlineCellEdit();
    }
}

function cancelInlineCellEdit() {
    if (!state.editingCell) return;
    const { td, originalValue } = state.editingCell;
    state.editingCell = null;
    td.classList.add('truncate');
    renderCellContent(td, originalValue);
}

// ==========================================
// Row Deletion Modal
// ==========================================

function openDeleteRowModal(rowIndex) {
    if (!state.data || !state.data.rows[rowIndex]) return;
    const row = state.data.rows[rowIndex];
    const table = state.data.editable_table || state.currentTable;

    const pk = {};
    (state.data.primary_key || []).forEach((col) => {
        pk[col] = row[col];
    });

    state.deleteTarget = { table, pk, rowIndex };

    document.getElementById('delete-row-table-name').textContent = table;

    const pkDisplay = Object.entries(pk)
        .map(([k, v]) => `<span class="opacity-70">${escapeHtml(k)}:</span> <span class="text-blue-500">${escapeHtml(v)}</span>`)
        .join(', ');
    document.getElementById('delete-row-pk-display').innerHTML = pkDisplay;

    openModal('modal-delete-row');
}

function closeDeleteRowModal() {
    closeModal('modal-delete-row');
    state.deleteTarget = null;
}

async function submitDeleteRow() {
    if (!state.deleteTarget) return;
    const { table, pk, rowIndex } = state.deleteTarget;

    const btn = document.getElementById('btn-confirm-delete-row');
    if (btn) btn.disabled = true;

    try {
        const formData = new FormData();
        formData.append('action', 'delete_row');
        formData.append('table', table);
        formData.append('pk', JSON.stringify(pk));

        const res = await fetchJsonSafely('database_helper.php', {
            method: 'POST',
            body: formData
        });

        if (btn) btn.disabled = false;

        if (!res.success) {
            showToast(res.error || 'Fout bij verwijderen van rij.', 'error');
            return;
        }

        closeDeleteRowModal();
        showToast('Rij succesvol verwijderd.', 'success');

        // Remove row locally and from DOM or re-execute query
        state.data.rows.splice(rowIndex, 1);
        state.data.count = state.data.rows.length;
        renderGrid(state.data);
    } catch (err) {
        if (btn) btn.disabled = false;
        showToast(err.message, 'error');
    }
}

// ==========================================
// Row Edit Modal
// ==========================================

function openEditRowModal(rowIndex) {
    if (!state.data || !state.data.rows[rowIndex]) return;
    const row = state.data.rows[rowIndex];
    const table = state.data.editable_table || state.currentTable;

    const pk = {};
    (state.data.primary_key || []).forEach((col) => {
        pk[col] = row[col];
    });

    state.editTarget = { table, pk, rowIndex, rowData: row };

    document.getElementById('edit-row-table-title').textContent = table;
    const container = document.getElementById('edit-row-fields-container');
    container.innerHTML = '';

    const columns = state.schema?.columns || {};

    Object.values(columns).forEach((col) => {
        const isPk = (state.data.primary_key || []).includes(col.name);
        const curVal = row[col.name];
        const isNull = (curVal === null || curVal === undefined);

        const fieldDiv = document.createElement('div');
        fieldDiv.className = 'p-3 rounded-xl border bg-black/5 dark:bg-white/5 space-y-2';
        fieldDiv.style.borderColor = 'var(--theme-card-border)';

        const topRow = document.createElement('div');
        topRow.className = 'flex items-center justify-between';

        const label = document.createElement('label');
        label.className = 'text-xs font-bold uppercase tracking-wider opacity-80 flex items-center gap-1.5 font-mono';
        label.innerHTML = `
            <span>${escapeHtml(col.name)}</span>
            ${isPk ? '<span class="text-[10px] text-amber-500 font-bold">(PK)</span>' : ''}
            <span class="text-[10px] opacity-60 font-normal">(${escapeHtml(col.column_type)})</span>
        `;
        topRow.appendChild(label);

        // Null toggle checkbox
        let nullToggle = null;
        if (col.is_nullable && !isPk) {
            const nullDiv = document.createElement('div');
            nullDiv.className = 'flex items-center gap-1.5';
            const check = document.createElement('input');
            check.type = 'checkbox';
            check.id = `edit-null-${col.name}`;
            check.checked = isNull;
            check.className = 'w-3.5 h-3.5 rounded text-blue-600 focus:ring-blue-500 cursor-pointer';

            const checkLabel = document.createElement('label');
            checkLabel.htmlFor = `edit-null-${col.name}`;
            checkLabel.className = 'text-[11px] opacity-70 cursor-pointer select-none';
            checkLabel.textContent = 'Zet op NULL';

            nullDiv.appendChild(check);
            nullDiv.appendChild(checkLabel);
            topRow.appendChild(nullDiv);
            nullToggle = check;
        }

        fieldDiv.appendChild(topRow);

        // Field Input
        let input;
        if (col.data_type === 'enum' && col.enum_values && col.enum_values.length > 0) {
            input = document.createElement('select');
            input.id = `edit-val-${col.name}`;
            input.disabled = isPk;
            input.className = 'w-full theme-input px-3 py-2 text-xs font-mono';
            col.enum_values.forEach((ev) => {
                const opt = document.createElement('option');
                opt.value = ev;
                opt.textContent = ev;
                if (ev === curVal) opt.selected = true;
                input.appendChild(opt);
            });
        } else if (col.data_type === 'text' || col.data_type === 'mediumtext' || col.data_type === 'longtext') {
            input = document.createElement('textarea');
            input.id = `edit-val-${col.name}`;
            input.rows = 3;
            input.disabled = isPk;
            input.value = isNull ? '' : curVal;
            input.className = 'w-full theme-input p-2.5 text-xs font-mono';
        } else {
            input = document.createElement('input');
            input.id = `edit-val-${col.name}`;
            input.disabled = isPk;
            input.value = isNull ? '' : curVal;
            input.className = 'w-full theme-input px-3 py-2 text-xs font-mono';

            if (['int', 'tinyint', 'bigint', 'decimal', 'float'].includes(col.data_type)) {
                input.type = 'number';
            } else if (col.data_type === 'datetime' || col.data_type === 'timestamp') {
                input.type = 'datetime-local';
                if (curVal && typeof curVal === 'string') {
                    input.value = curVal.replace(' ', 'T').substring(0, 16);
                }
            } else if (col.data_type === 'date') {
                input.type = 'date';
            } else {
                input.type = 'text';
            }
        }

        if (isPk) {
            input.classList.add('opacity-60', 'cursor-not-allowed');
        }

        if (nullToggle) {
            if (nullToggle.checked) {
                input.disabled = true;
                input.classList.add('opacity-40');
            }
            nullToggle.onchange = (e) => {
                input.disabled = e.target.checked;
                if (e.target.checked) {
                    input.classList.add('opacity-40');
                } else {
                    input.classList.remove('opacity-40');
                }
            };
        }

        fieldDiv.appendChild(input);
        container.appendChild(fieldDiv);
    });

    openModal('modal-edit-row');
}

function closeEditRowModal() {
    closeModal('modal-edit-row');
    state.editTarget = null;
}

async function submitEditRow() {
    if (!state.editTarget) return;
    const { table, pk } = state.editTarget;
    const columns = state.schema?.columns || {};

    const values = {};
    const nullColumns = [];

    Object.values(columns).forEach((col) => {
        const isPk = (state.data.primary_key || []).includes(col.name);
        if (isPk) return; // PKs are never updated

        const nullCheck = document.getElementById(`edit-null-${col.name}`);
        if (nullCheck && nullCheck.checked) {
            nullColumns.push(col.name);
            return;
        }

        const input = document.getElementById(`edit-val-${col.name}`);
        if (input) {
            values[col.name] = input.value;
        }
    });

    const btn = document.getElementById('btn-submit-edit-row');
    if (btn) btn.disabled = true;

    try {
        const formData = new FormData();
        formData.append('action', 'update_row');
        formData.append('table', table);
        formData.append('pk', JSON.stringify(pk));
        formData.append('values', JSON.stringify(values));
        formData.append('null_columns', JSON.stringify(nullColumns));

        const res = await fetchJsonSafely('database_helper.php', {
            method: 'POST',
            body: formData
        });

        if (btn) btn.disabled = false;

        if (!res.success) {
            showToast(res.error || 'Fout bij bijwerken rij.', 'error');
            return;
        }

        closeEditRowModal();
        showToast('Rij succesvol bijgewerkt.', 'success');
        executeQuery();
    } catch (err) {
        if (btn) btn.disabled = false;
        showToast(err.message, 'error');
    }
}

// ==========================================
// Insert Row Modal
// ==========================================

function openInsertModal() {
    if (!state.schema || !state.schema.columns) {
        showToast('Selecteer eerst een tabel.', 'warning');
        return;
    }

    const table = state.currentTable;
    document.getElementById('insert-row-table-title').textContent = table;
    const container = document.getElementById('insert-row-fields-container');
    container.innerHTML = '';

    Object.values(state.schema.columns).forEach((col) => {
        const fieldDiv = document.createElement('div');
        fieldDiv.className = 'p-3 rounded-xl border bg-black/5 dark:bg-white/5 space-y-2';
        fieldDiv.style.borderColor = 'var(--theme-card-border)';

        const topRow = document.createElement('div');
        topRow.className = 'flex items-center justify-between';

        const label = document.createElement('label');
        label.className = 'text-xs font-bold uppercase tracking-wider opacity-80 flex items-center gap-1.5 font-mono';
        label.innerHTML = `
            <span>${escapeHtml(col.name)}</span>
            ${col.is_auto_increment ? '<span class="text-[10px] text-emerald-500 font-bold">(Auto-increment)</span>' : ''}
            <span class="text-[10px] opacity-60 font-normal">(${escapeHtml(col.column_type)})</span>
        `;
        topRow.appendChild(label);

        // Null toggle checkbox
        let nullToggle = null;
        if (col.is_nullable && !col.is_auto_increment) {
            const nullDiv = document.createElement('div');
            nullDiv.className = 'flex items-center gap-1.5';
            const check = document.createElement('input');
            check.type = 'checkbox';
            check.id = `insert-null-${col.name}`;
            check.checked = (col.default === null);
            check.className = 'w-3.5 h-3.5 rounded text-blue-600 focus:ring-blue-500 cursor-pointer';

            const checkLabel = document.createElement('label');
            checkLabel.htmlFor = `insert-null-${col.name}`;
            checkLabel.className = 'text-[11px] opacity-70 cursor-pointer select-none';
            checkLabel.textContent = 'Zet op NULL';

            nullDiv.appendChild(check);
            nullDiv.appendChild(checkLabel);
            topRow.appendChild(nullDiv);
            nullToggle = check;
        }

        fieldDiv.appendChild(topRow);

        // Field Input
        let input;
        if (col.data_type === 'enum' && col.enum_values && col.enum_values.length > 0) {
            input = document.createElement('select');
            input.id = `insert-val-${col.name}`;
            input.className = 'w-full theme-input px-3 py-2 text-xs font-mono';
            col.enum_values.forEach((ev) => {
                const opt = document.createElement('option');
                opt.value = ev;
                opt.textContent = ev;
                if (ev === col.default) opt.selected = true;
                input.appendChild(opt);
            });
        } else if (col.data_type === 'text' || col.data_type === 'mediumtext' || col.data_type === 'longtext') {
            input = document.createElement('textarea');
            input.id = `insert-val-${col.name}`;
            input.rows = 2;
            input.value = col.default !== null ? col.default : '';
            input.className = 'w-full theme-input p-2.5 text-xs font-mono';
        } else {
            input = document.createElement('input');
            input.id = `insert-val-${col.name}`;
            input.value = col.default !== null ? col.default : '';
            input.className = 'w-full theme-input px-3 py-2 text-xs font-mono';

            if (col.is_auto_increment) {
                input.placeholder = '(Automatisch gegenereerd)';
            } else if (['int', 'tinyint', 'bigint', 'decimal', 'float'].includes(col.data_type)) {
                input.type = 'number';
            } else if (col.data_type === 'datetime' || col.data_type === 'timestamp') {
                input.type = 'datetime-local';
            } else if (col.data_type === 'date') {
                input.type = 'date';
            } else {
                input.type = 'text';
            }
        }

        if (nullToggle) {
            if (nullToggle.checked) {
                input.disabled = true;
                input.classList.add('opacity-40');
            }
            nullToggle.onchange = (e) => {
                input.disabled = e.target.checked;
                if (e.target.checked) {
                    input.classList.add('opacity-40');
                } else {
                    input.classList.remove('opacity-40');
                }
            };
        }

        fieldDiv.appendChild(input);
        container.appendChild(fieldDiv);
    });

    openModal('modal-insert-row');
}

function closeInsertRowModal() {
    closeModal('modal-insert-row');
}

async function submitInsertRow() {
    if (!state.schema || !state.schema.columns) return;
    const table = state.currentTable;
    const columns = state.schema.columns;

    const values = {};
    const nullColumns = [];

    Object.values(columns).forEach((col) => {
        const nullCheck = document.getElementById(`insert-null-${col.name}`);
        if (nullCheck && nullCheck.checked) {
            nullColumns.push(col.name);
            return;
        }

        const input = document.getElementById(`insert-val-${col.name}`);
        if (input) {
            const val = input.value;
            // Ignore empty auto-increment
            if (col.is_auto_increment && (val === '' || val === null)) {
                return;
            }
            values[col.name] = val;
        }
    });

    const btn = document.getElementById('btn-submit-insert-row');
    if (btn) btn.disabled = true;

    try {
        const formData = new FormData();
        formData.append('action', 'insert_row');
        formData.append('table', table);
        formData.append('values', JSON.stringify(values));
        formData.append('null_columns', JSON.stringify(nullColumns));

        const res = await fetchJsonSafely('database_helper.php', {
            method: 'POST',
            body: formData
        });

        if (btn) btn.disabled = false;

        if (!res.success) {
            showToast(res.error || 'Fout bij invoegen rij.', 'error');
            return;
        }

        closeInsertRowModal();
        showToast('Nieuwe rij succesvol toegevoegd.', 'success');
        executeQuery();
    } catch (err) {
        if (btn) btn.disabled = false;
        showToast(err.message, 'error');
    }
}

// ==========================================
// In-Memory Search & CSV Export
// ==========================================

function onFilterTableInput(query) {
    const q = (query || '').toLowerCase().trim();
    const rows = document.querySelectorAll('#grid-tbody tr');
    let visibleCount = 0;

    rows.forEach((tr) => {
        const text = tr.textContent.toLowerCase();
        if (!q || text.includes(q)) {
            tr.style.display = '';
            visibleCount++;
        } else {
            tr.style.display = 'none';
        }
    });

    document.getElementById('stat-row-count').textContent = visibleCount;
    document.getElementById('grid-footer-info').textContent = `${visibleCount} rijen zichtbaar van ${rows.length}`;
}

function exportToCsv() {
    if (!state.data || !state.data.rows || state.data.rows.length === 0) {
        showToast('Geen rijen om te exporteren.', 'warning');
        return;
    }

    const { columns, rows } = state.data;
    const csvLines = [];

    // Header row
    csvLines.push(columns.map(c => `"${String(c).replace(/"/g, '""')}"`).join(','));

    // Data rows
    rows.forEach((r) => {
        const line = columns.map((c) => {
            const v = r[c];
            if (v === null || v === undefined) return '""';
            return `"${String(v).replace(/"/g, '""')}"`;
        });
        csvLines.push(line.join(','));
    });

    const csvContent = 'data:text/csv;charset=utf-8,\uFEFF' + encodeURIComponent(csvLines.join('\r\n'));
    const link = document.createElement('a');
    link.setAttribute('href', csvContent);
    const tableName = state.data.editable_table || state.currentTable || 'database';
    const timestamp = new Date().toISOString().replace(/[:.]/g, '-').slice(0, 19);
    link.setAttribute('download', `${tableName}_export_${timestamp}.csv`);
    document.body.appendChild(link);
    link.click();
    link.remove();

    showToast('CSV export gedownload.', 'success', 2500);
}

// ==========================================
// Modal Helpers
// ==========================================

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('hidden');
    }
}

function closeModal(modalId) {
    const modal = typeof modalId === 'string' ? document.getElementById(modalId) : modalId.closest('[role="dialog"], .fixed');
    if (modal) {
        modal.classList.add('hidden');
    }
}
