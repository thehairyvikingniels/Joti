/**
 * js/admin_audit.js
 *
 * Frontend controller for Admin Audit & Telemetry Hub.
 * Features: live polling, debounced filtering, in-DOM JSON metadata modal,
 * 24h summary telemetry, and CSV streaming export.
 */

(() => {
  'use strict';

  // State Management
  const state = {
    currentPage: 1,
    totalPages: 1,
    limit: 50,
    isLive: true,
    pollTimer: null,
    statsTimer: null,
    lastMaxId: 0,
    searchDebounceTimer: null,
    logs: new Map(), // id -> log object
    filters: {
      search: '',
      category: 'all',
      severity: '',
      user_id: ''
    }
  };

  // DOM Elements
  const elements = {
    tbody: document.getElementById('logs-tbody'),
    loadingOverlay: document.getElementById('logs-loading'),
    emptyState: document.getElementById('logs-empty'),
    countBadge: document.getElementById('log-count-badge'),
    btnRefresh: document.getElementById('btn-refresh'),
    iconRefreshSpin: document.getElementById('icon-refresh-spin'),
    btnLiveToggle: document.getElementById('btn-live-toggle'),
    liveStatusText: document.getElementById('live-status-text'),
    btnExportCsv: document.getElementById('btn-export-csv'),
    btnResetFilters: document.getElementById('btn-reset-filters'),
    filterIndicator: document.getElementById('filter-indicator'),
    filterSearch: document.getElementById('filter-search'),
    filterCategory: document.getElementById('filter-category'),
    filterSeverity: document.getElementById('filter-severity'),
    filterUser: document.getElementById('filter-user'),
    paginationInfo: document.getElementById('pagination-info'),
    paginationCurrentPage: document.getElementById('pagination-current-page'),
    btnPagePrev: document.getElementById('btn-page-prev'),
    btnPageNext: document.getElementById('btn-page-next'),
    // Stats
    statTotal: document.getElementById('stat-total-24h'),
    statAssignments: document.getElementById('stat-assignments-24h'),
    statSecurity: document.getElementById('stat-security-24h'),
    statActiveUsers: document.getElementById('stat-active-users-24h'),
    // Modal
    modal: document.getElementById('modal-audit-details'),
    modalLogId: document.getElementById('modal-log-id'),
    modalLogTime: document.getElementById('modal-log-time'),
    modalLogCategory: document.getElementById('modal-log-category'),
    modalLogIp: document.getElementById('modal-log-ip'),
    modalLogActor: document.getElementById('modal-log-actor'),
    modalLogSubject: document.getElementById('modal-log-subject'),
    modalLogTarget: document.getElementById('modal-log-target'),
    modalLogDetails: document.getElementById('modal-log-details'),
    modalLogJson: document.getElementById('modal-log-json')
  };

  /**
   * Safe fetch with error handling and JSON parsing
   */
  async function fetchJson(url, options = {}) {
    try {
      const res = await fetch(url, {
        headers: { 'Accept': 'application/json', ...options.headers },
        ...options
      });
      if (!res.ok) {
        throw new Error(`HTTP ${res.status}`);
      }
      return await res.json();
    } catch (err) {
      console.error('Fetch error:', err);
      return null;
    }
  }

  /**
   * Load 24h Telemetry Stats
   */
  async function loadStats() {
    const data = await fetchJson('/admin/audit_helper?action=get_stats');
    if (data && data.success && data.stats) {
      if (elements.statTotal) elements.statTotal.textContent = data.stats.total_24h.toLocaleString('nl-NL');
      if (elements.statAssignments) elements.statAssignments.textContent = data.stats.assignments_24h.toLocaleString('nl-NL');
      if (elements.statSecurity) elements.statSecurity.textContent = data.stats.security_24h.toLocaleString('nl-NL');
      if (elements.statActiveUsers) elements.statActiveUsers.textContent = data.stats.active_users_24h.toLocaleString('nl-NL');
    }
  }

  /**
   * Load Users for dropdown filter
   */
  async function loadUsers() {
    const data = await fetchJson('/admin/audit_helper?action=get_users');
    if (data && data.success && Array.isArray(data.users) && elements.filterUser) {
      elements.filterUser.innerHTML = '<option value="">Alle Gebruikers</option>';
      data.users.forEach(u => {
        const opt = document.createElement('option');
        opt.value = u.id;
        opt.textContent = `${u.display_name} (${u.username})`;
        elements.filterUser.appendChild(opt);
      });
    }
  }

  /**
   * Build query parameters based on state and filters
   */
  function buildQueryParams(extra = {}) {
    const p = new URLSearchParams();
    if (state.filters.search) p.append('search', state.filters.search);
    if (state.filters.category && state.filters.category !== 'all') p.append('category', state.filters.category);
    if (state.filters.severity) p.append('severity', state.filters.severity);
    if (state.filters.user_id) p.append('actor_id', state.filters.user_id);
    p.append('limit', state.limit.toString());

    Object.entries(extra).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== '') {
        p.append(k, v.toString());
      }
    });
    return p.toString();
  }

  /**
   * Update active filters indicator
   */
  function updateFilterIndicator() {
    let count = 0;
    if (state.filters.search) count++;
    if (state.filters.category && state.filters.category !== 'all') count++;
    if (state.filters.severity) count++;
    if (state.filters.user_id) count++;
    if (elements.filterIndicator) {
      elements.filterIndicator.textContent = `Filters actief: ${count}`;
    }
  }

  /**
   * Render severity badge
   */
  function renderSeverityBadge(severity) {
    const sev = (severity || 'info').toLowerCase();
    let badgeClass = 'badge-info';
    let icon = 'fa-info-circle';
    let label = 'Info';

    if (sev === 'warning') {
      badgeClass = 'badge-warning';
      icon = 'fa-triangle-exclamation';
      label = 'Waarschuwing';
    } else if (sev === 'error') {
      badgeClass = 'badge-error';
      icon = 'fa-circle-xmark';
      label = 'Fout';
    } else if (sev === 'security') {
      badgeClass = 'badge-security';
      icon = 'fa-shield-halved';
      label = 'Beveiliging';
    }

    return `<span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold ${badgeClass}">
      <i class="fas ${icon} text-[10px]"></i> ${label}
    </span>`;
  }

  /**
   * Render category badge
   */
  function renderCategoryBadge(category) {
    const cat = (category || 'system').toLowerCase();
    const map = {
      assignment: { label: 'Toewijzing', icon: 'fa-user-check', color: 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20' },
      whiteboard: { label: 'Whiteboard', icon: 'fa-chalkboard', color: 'bg-teal-500/10 text-teal-600 border-teal-500/20' },
      auth: { label: 'Auth', icon: 'fa-key', color: 'bg-indigo-500/10 text-indigo-600 border-indigo-500/20' },
      user: { label: 'Gebruiker', icon: 'fa-user-gear', color: 'bg-sky-500/10 text-sky-600 border-sky-500/20' },
      settings: { label: 'Instelling', icon: 'fa-toolbox', color: 'bg-amber-500/10 text-amber-600 border-amber-500/20' },
      cron: { label: 'Cronjob', icon: 'fa-stopwatch', color: 'bg-violet-500/10 text-violet-600 border-violet-500/20' },
      security: { label: 'Kiosk / Token', icon: 'fa-user-shield', color: 'bg-purple-500/10 text-purple-600 border-purple-500/20' },
      system: { label: 'Systeem', icon: 'fa-server', color: 'bg-gray-500/10 text-gray-600 border-gray-500/20' }
    };
    const c = map[cat] || { label: cat, icon: 'fa-tag', color: 'bg-black/5 text-gray-600 border-black/10' };
    return `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs border ${c.color}">
      <i class="fas ${c.icon} text-[9px] opacity-70"></i> ${c.label}
    </span>`;
  }

  /**
   * Escape HTML to prevent XSS
   */
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
   * Create table row HTML for a single log record
   */
  function createLogRowHtml(log, isNew = false) {
    const actorHtml = log.actor_username
      ? `<span class="font-medium flex items-center gap-1.5"><i class="fas fa-user-circle opacity-60"></i> ${escapeHtml(log.actor_username)}</span>`
      : `<span class="opacity-40 italic">Systeem / Anoniem</span>`;

    let subjectHtml = '';
    if (log.subject_username) {
      subjectHtml += `<span class="inline-flex items-center gap-1 font-medium"><i class="fas fa-user text-xs opacity-60"></i> ${escapeHtml(log.subject_username)}</span>`;
    }
    if (log.target_label) {
      if (subjectHtml) subjectHtml += ' <span class="opacity-40">→</span> ';
      subjectHtml += `<span class="inline-flex items-center gap-1 opacity-90"><i class="fas fa-crosshairs text-xs opacity-60"></i> ${escapeHtml(log.target_label)}</span>`;
    }
    if (!subjectHtml) {
      subjectHtml = `<span class="opacity-40">—</span>`;
    }

    const highlightClass = isNew ? 'bg-emerald-500/10 transition-colors duration-1000' : 'hover:bg-black/5 transition-colors';

    return `
      <tr id="log-row-${log.id}" class="${highlightClass}">
        <td class="px-4 py-3 font-mono text-xs opacity-80">${escapeHtml(log.time_formatted || log.created_at)}</td>
        <td class="px-4 py-3">${renderSeverityBadge(log.severity)}</td>
        <td class="px-4 py-3">${renderCategoryBadge(log.category)}</td>
        <td class="px-4 py-3">${actorHtml}</td>
        <td class="px-4 py-3">${subjectHtml}</td>
        <td class="px-4 py-3 max-w-xs truncate" title="${escapeHtml(log.details)}">${escapeHtml(log.details)}</td>
        <td class="px-4 py-3 font-mono text-xs opacity-70">${escapeHtml(log.ip_address || '—')}</td>
        <td class="px-4 py-3 text-right">
          <button type="button" onclick="window.AuditHub.openModal(${log.id})" class="px-2 py-1 rounded hover:bg-black/10 text-xs text-blue-600 transition" title="Bekijk JSON metadata">
            <i class="fas fa-eye"></i>
          </button>
        </td>
      </tr>
    `;
  }

  /**
   * Load Logs with current filters and pagination
   */
  async function loadLogs(page = 1, showSpinner = true) {
    if (showSpinner && elements.loadingOverlay) {
      elements.loadingOverlay.classList.remove('hidden');
    }
    if (elements.iconRefreshSpin) {
      elements.iconRefreshSpin.classList.add('fa-spin');
    }

    state.currentPage = page;
    const qs = buildQueryParams({ action: 'get_logs', page: page });
    const data = await fetchJson(`/admin/audit_helper?${qs}`);

    if (showSpinner && elements.loadingOverlay) {
      elements.loadingOverlay.classList.add('hidden');
    }
    if (elements.iconRefreshSpin) {
      elements.iconRefreshSpin.classList.remove('fa-spin');
    }

    if (!data || !data.success) {
      console.error('Failed to load logs', data);
      return;
    }

    state.totalPages = data.pages || 1;
    if (data.max_id && data.max_id > state.lastMaxId) {
      state.lastMaxId = data.max_id;
    }

    if (elements.countBadge) {
      elements.countBadge.textContent = data.total.toLocaleString('nl-NL');
    }

    if (elements.paginationInfo) {
      elements.paginationInfo.textContent = `Pagina ${data.page} van ${state.totalPages} (${data.total.toLocaleString('nl-NL')} regels)`;
    }
    if (elements.paginationCurrentPage) {
      elements.paginationCurrentPage.textContent = data.page;
    }
    if (elements.btnPagePrev) {
      elements.btnPagePrev.disabled = data.page <= 1;
    }
    if (elements.btnPageNext) {
      elements.btnPageNext.disabled = data.page >= state.totalPages;
    }

    state.logs.clear();

    if (!data.logs || data.logs.length === 0) {
      if (elements.tbody) elements.tbody.innerHTML = '';
      if (elements.emptyState) elements.emptyState.classList.remove('hidden');
      return;
    }

    if (elements.emptyState) elements.emptyState.classList.add('hidden');

    let html = '';
    data.logs.forEach(log => {
      state.logs.set(log.id, log);
      html += createLogRowHtml(log);
    });

    if (elements.tbody) {
      elements.tbody.innerHTML = html;
    }
  }

  /**
   * Live Polling: check for new logs since lastMaxId
   */
  async function pollNewLogs() {
    if (!state.isLive || state.currentPage !== 1 || state.lastMaxId === 0) {
      return;
    }

    const qs = buildQueryParams({ action: 'get_logs', since_id: state.lastMaxId });
    const data = await fetchJson(`/admin/audit_helper?${qs}`);

    if (data && data.success && Array.isArray(data.logs) && data.logs.length > 0) {
      // New logs arrived!
      data.logs.forEach(log => {
        state.logs.set(log.id, log);
        if (log.id > state.lastMaxId) {
          state.lastMaxId = log.id;
        }
      });

      // Render new logs at the top
      let newRowsHtml = '';
      // Reverse so newest is first
      const sortedNew = [...data.logs].sort((a, b) => b.id - a.id);
      sortedNew.forEach(log => {
        newRowsHtml += createLogRowHtml(log, true);
      });

      if (elements.tbody) {
        elements.tbody.insertAdjacentHTML('afterbegin', newRowsHtml);
        if (elements.emptyState) elements.emptyState.classList.add('hidden');
      }

      // Update count badge & stats
      const currentCount = parseInt(elements.countBadge?.textContent?.replace(/\D/g, '') || '0', 10);
      if (elements.countBadge) {
        elements.countBadge.textContent = (currentCount + data.logs.length).toLocaleString('nl-NL');
      }

      loadStats();
    }
  }

  /**
   * Modal: Open and display event details
   */
  function openAuditModal(logId) {
    const log = state.logs.get(logId);
    if (!log || !elements.modal) return;

    if (elements.modalLogId) elements.modalLogId.textContent = log.id;
    if (elements.modalLogTime) elements.modalLogTime.textContent = log.time_formatted || log.created_at;
    if (elements.modalLogCategory) {
      elements.modalLogCategory.innerHTML = `${renderSeverityBadge(log.severity)} ${renderCategoryBadge(log.category)}`;
    }
    if (elements.modalLogIp) elements.modalLogIp.textContent = log.ip_address || '—';
    if (elements.modalLogActor) {
      elements.modalLogActor.textContent = log.actor_username ? `${log.actor_username} (#${log.actor_user_id || '?'})` : 'Systeem';
    }
    if (elements.modalLogSubject) {
      elements.modalLogSubject.textContent = log.subject_username ? `${log.subject_username} (#${log.subject_user_id || '?'})` : '—';
    }
    if (elements.modalLogTarget) {
      elements.modalLogTarget.textContent = log.target_label ? `${log.target_label} (${log.target_type || 'object'} #${log.target_id || ''})` : '—';
    }
    if (elements.modalLogDetails) elements.modalLogDetails.textContent = log.details || 'Geen toelichting';

    if (elements.modalLogJson) {
      const fullObj = {
        id: log.id,
        created_at: log.created_at,
        severity: log.severity,
        category: log.category,
        action: log.action,
        actor: { id: log.actor_user_id, username: log.actor_username },
        subject: { id: log.subject_user_id, username: log.subject_username },
        target: { type: log.target_type, id: log.target_id, label: log.target_label },
        ip_address: log.ip_address,
        metadata: log.metadata || {}
      };
      elements.modalLogJson.textContent = JSON.stringify(fullObj, null, 2);
    }

    elements.modal.classList.remove('hidden');
  }

  /**
   * Modal: Close
   */
  function closeAuditModal() {
    if (elements.modal) {
      elements.modal.classList.add('hidden');
    }
  }

  /**
   * Setup Event Listeners
   */
  function setupListeners() {
    // Search input (debounced 300ms)
    if (elements.filterSearch) {
      elements.filterSearch.addEventListener('input', (e) => {
        clearTimeout(state.searchDebounceTimer);
        state.searchDebounceTimer = setTimeout(() => {
          state.filters.search = e.target.value.trim();
          updateFilterIndicator();
          loadLogs(1);
        }, 300);
      });
    }

    // Category select
    if (elements.filterCategory) {
      elements.filterCategory.addEventListener('change', (e) => {
        state.filters.category = e.target.value;
        updateFilterIndicator();
        loadLogs(1);
      });
    }

    // Severity select
    if (elements.filterSeverity) {
      elements.filterSeverity.addEventListener('change', (e) => {
        state.filters.severity = e.target.value;
        updateFilterIndicator();
        loadLogs(1);
      });
    }

    // User select
    if (elements.filterUser) {
      elements.filterUser.addEventListener('change', (e) => {
        state.filters.user_id = e.target.value;
        updateFilterIndicator();
        loadLogs(1);
      });
    }

    // Reset filters
    if (elements.btnResetFilters) {
      elements.btnResetFilters.addEventListener('click', () => {
        state.filters.search = '';
        state.filters.category = 'all';
        state.filters.severity = '';
        state.filters.user_id = '';
        if (elements.filterSearch) elements.filterSearch.value = '';
        if (elements.filterCategory) elements.filterCategory.value = 'all';
        if (elements.filterSeverity) elements.filterSeverity.value = '';
        if (elements.filterUser) elements.filterUser.value = '';
        updateFilterIndicator();
        loadLogs(1);
      });
    }

    // Manual Refresh
    if (elements.btnRefresh) {
      elements.btnRefresh.addEventListener('click', () => {
        loadLogs(state.currentPage);
        loadStats();
      });
    }

    // Live Toggle
    if (elements.btnLiveToggle) {
      elements.btnLiveToggle.addEventListener('click', () => {
        state.isLive = !state.isLive;
        if (state.isLive) {
          elements.btnLiveToggle.className = 'inline-flex items-center gap-2 px-3.5 py-1.5 rounded-lg border text-xs font-semibold bg-emerald-500/10 text-emerald-600 border-emerald-500/30 hover:bg-emerald-500/20 transition';
          elements.btnLiveToggle.innerHTML = '<span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span><span>Live: Aan</span>';
        } else {
          elements.btnLiveToggle.className = 'inline-flex items-center gap-2 px-3.5 py-1.5 rounded-lg border text-xs font-semibold bg-black/5 text-gray-500 border-black/10 hover:bg-black/10 transition';
          elements.btnLiveToggle.innerHTML = '<span class="w-2 h-2 rounded-full bg-gray-400"></span><span>Live: Gepauzeerd</span>';
        }
      });
    }

    // Export CSV
    if (elements.btnExportCsv) {
      elements.btnExportCsv.addEventListener('click', () => {
        const qs = buildQueryParams({ action: 'export_csv' });
        window.location.href = `/admin/audit_helper?${qs}`;
      });
    }

    // Pagination: Prev
    if (elements.btnPagePrev) {
      elements.btnPagePrev.addEventListener('click', () => {
        if (state.currentPage > 1) {
          loadLogs(state.currentPage - 1);
        }
      });
    }

    // Pagination: Next
    if (elements.btnPageNext) {
      elements.btnPageNext.addEventListener('click', () => {
        if (state.currentPage < state.totalPages) {
          loadLogs(state.currentPage + 1);
        }
      });
    }

    // Close modal on Escape key
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && elements.modal && !elements.modal.classList.contains('hidden')) {
        closeAuditModal();
      }
    });

    // Close modal on backdrop click
    if (elements.modal) {
      elements.modal.addEventListener('click', (e) => {
        if (e.target === elements.modal) {
          closeAuditModal();
        }
      });
    }
  }

  // Global exposure for in-HTML onclick callbacks
  window.AuditHub = {
    openModal: openAuditModal,
    closeModal: closeAuditModal
  };
  window.closeAuditModal = closeAuditModal;

  // Initialize
  document.addEventListener('DOMContentLoaded', () => {
    setupListeners();
    loadUsers();
    loadStats();
    loadLogs(1);

    // Live polling every 3 seconds
    state.pollTimer = setInterval(pollNewLogs, 3000);
    // Refresh stats every 30 seconds
    state.statsTimer = setInterval(loadStats, 30000);
  });
})();
