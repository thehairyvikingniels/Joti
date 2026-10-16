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
    activeTab: 'audit', // 'audit' | 'sessions'
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

  const sessionsState = {
    currentPage: 1,
    totalPages: 1,
    limit: 25,
    searchDebounceTimer: null,
    sessions: new Map(), // id -> session object
    openTimelineId: null, // currently expanded session id
    filters: {
      search: '',
      auth_method: 'all',
      status: 'all',
      user_id: '',
      session_id: ''
    }
  };

  // DOM Elements
  const elements = {
    // Tabs
    tabBtnAudit: document.getElementById('tab-btn-audit'),
    tabBtnSessions: document.getElementById('tab-btn-sessions'),
    viewAuditLogs: document.getElementById('view-audit-logs'),
    viewSessions: document.getElementById('view-sessions'),
    activeSessionsBadge: document.getElementById('active-sessions-badge'),

    // Audit Logs Tab
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

    // Sessions Tab
    sessionsTbody: document.getElementById('sessions-tbody'),
    sessionsLoading: document.getElementById('sessions-loading'),
    sessionsEmpty: document.getElementById('sessions-empty'),
    sessionsCountBadge: document.getElementById('sessions-count-badge'),
    btnRefreshSessions: document.getElementById('btn-refresh-sessions'),
    iconRefreshSessionsSpin: document.getElementById('icon-refresh-sessions-spin'),
    filterSessionsSearch: document.getElementById('filter-sessions-search'),
    filterSessionsAuth: document.getElementById('filter-sessions-auth'),
    filterSessionsStatus: document.getElementById('filter-sessions-status'),
    filterSessionsUser: document.getElementById('filter-sessions-user'),
    filterSessionsIndicator: document.getElementById('filter-sessions-indicator'),
    btnResetSessionsFilters: document.getElementById('btn-reset-sessions-filters'),
    sessionsPaginationInfo: document.getElementById('sessions-pagination-info'),
    sessionsPaginationCurrentPage: document.getElementById('sessions-pagination-current-page'),
    btnSessionsPagePrev: document.getElementById('btn-sessions-page-prev'),
    btnSessionsPageNext: document.getElementById('btn-sessions-page-next'),

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
   * Load Users for dropdown filters
   */
  async function loadUsers() {
    const data = await fetchJson('/admin/audit_helper?action=get_users');
    if (data && data.success && Array.isArray(data.users)) {
      if (elements.filterUser) {
        elements.filterUser.innerHTML = '<option value="">Alle Gebruikers</option>';
        data.users.forEach(u => {
          const opt = document.createElement('option');
          opt.value = u.id;
          opt.textContent = `${u.display_name} (${u.username})`;
          elements.filterUser.appendChild(opt);
        });
      }
      if (elements.filterSessionsUser) {
        elements.filterSessionsUser.innerHTML = '<option value="">Alle Gebruikers</option>';
        data.users.forEach(u => {
          const opt = document.createElement('option');
          opt.value = u.id;
          opt.textContent = `${u.display_name} (${u.username})`;
          elements.filterSessionsUser.appendChild(opt);
        });
      }
    }
  }

  /**
   * Switch between Audit Logs tab and Sessions Tracker tab
   */
  function switchMainTab(tab) {
    state.activeTab = tab;
    if (tab === 'sessions') {
      if (elements.tabBtnSessions) {
        elements.tabBtnSessions.className = 'tab-btn px-4 py-3 text-sm font-semibold border-b-2 border-blue-500 text-blue-500 flex items-center gap-2 transition';
      }
      if (elements.tabBtnAudit) {
        elements.tabBtnAudit.className = 'tab-btn px-4 py-3 text-sm font-semibold border-b-2 border-transparent opacity-70 hover:opacity-100 flex items-center gap-2 transition';
      }
      if (elements.viewAuditLogs) elements.viewAuditLogs.classList.add('hidden');
      if (elements.viewSessions) elements.viewSessions.classList.remove('hidden');

      loadSessions(sessionsState.currentPage);
    } else {
      if (elements.tabBtnAudit) {
        elements.tabBtnAudit.className = 'tab-btn px-4 py-3 text-sm font-semibold border-b-2 border-blue-500 text-blue-500 flex items-center gap-2 transition';
      }
      if (elements.tabBtnSessions) {
        elements.tabBtnSessions.className = 'tab-btn px-4 py-3 text-sm font-semibold border-b-2 border-transparent opacity-70 hover:opacity-100 flex items-center gap-2 transition';
      }
      if (elements.viewSessions) elements.viewSessions.classList.add('hidden');
      if (elements.viewAuditLogs) elements.viewAuditLogs.classList.remove('hidden');

      loadLogs(state.currentPage);
    }
  }

  /**
   * Build query parameters for Audit Logs
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
   * Build query parameters for Sessions
   */
  function buildSessionsQueryParams(extra = {}) {
    const p = new URLSearchParams();
    if (sessionsState.filters.search) p.append('search', sessionsState.filters.search);
    if (sessionsState.filters.auth_method && sessionsState.filters.auth_method !== 'all') p.append('auth_method', sessionsState.filters.auth_method);
    if (sessionsState.filters.status && sessionsState.filters.status !== 'all') p.append('status', sessionsState.filters.status);
    if (sessionsState.filters.user_id) p.append('user_id', sessionsState.filters.user_id);
    if (sessionsState.filters.session_id) p.append('session_id', sessionsState.filters.session_id);
    p.append('limit', sessionsState.limit.toString());

    Object.entries(extra).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== '') {
        p.append(k, v.toString());
      }
    });
    return p.toString();
  }

  /**
   * Update active filters indicator for Audit Logs
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
   * Update active filters indicator for Sessions
   */
  function updateSessionsFilterIndicator() {
    let count = 0;
    if (sessionsState.filters.search) count++;
    if (sessionsState.filters.auth_method && sessionsState.filters.auth_method !== 'all') count++;
    if (sessionsState.filters.status && sessionsState.filters.status !== 'all') count++;
    if (sessionsState.filters.user_id) count++;
    if (sessionsState.filters.session_id) count++;
    if (elements.filterSessionsIndicator) {
      elements.filterSessionsIndicator.textContent = `Filters actief: ${count}`;
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

    const sessionBadge = (log.session_id && log.session_id > 0)
      ? `<button type="button" onclick="window.AuditHub.openSessionInTracker(${log.session_id})" class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-mono bg-blue-500/10 text-blue-600 hover:bg-blue-500/20 border border-blue-500/20 transition cursor-pointer" title="Bekijk sessie #${log.session_id} in Sessie Tracker"><i class="fas fa-desktop text-[9px]"></i> #${log.session_id}</button>`
      : '';

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
        <td class="px-4 py-3">
          <div class="flex items-center flex-wrap gap-1.5">
            ${actorHtml}
            ${sessionBadge}
          </div>
        </td>
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
    if (!state.isLive || state.activeTab !== 'audit' || state.currentPage !== 1 || state.lastMaxId === 0) {
      return;
    }

    const qs = buildQueryParams({ action: 'get_logs', since_id: state.lastMaxId });
    const data = await fetchJson(`/admin/audit_helper?${qs}`);

    if (data && data.success && Array.isArray(data.logs) && data.logs.length > 0) {
      data.logs.forEach(log => {
        state.logs.set(log.id, log);
        if (log.id > state.lastMaxId) {
          state.lastMaxId = log.id;
        }
      });

      let newRowsHtml = '';
      const sortedNew = [...data.logs].sort((a, b) => b.id - a.id);
      sortedNew.forEach(log => {
        newRowsHtml += createLogRowHtml(log, true);
      });

      if (elements.tbody) {
        elements.tbody.insertAdjacentHTML('afterbegin', newRowsHtml);
        if (elements.emptyState) elements.emptyState.classList.add('hidden');
      }

      const currentCount = parseInt(elements.countBadge?.textContent?.replace(/\D/g, '') || '0', 10);
      if (elements.countBadge) {
        elements.countBadge.textContent = (currentCount + data.logs.length).toLocaleString('nl-NL');
      }

      loadStats();
    }
  }

  // =========================================================================
  // SESSIONS TRACKER IMPLEMENTATION
  // =========================================================================

  /**
   * Render Session Status Badge
   */
  function renderSessionStatusBadge(status) {
    const s = (status || 'active').toLowerCase();
    if (s === 'active') {
      return `<span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-600 border border-emerald-500/20">
        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> Actief
      </span>`;
    } else if (s === 'expired') {
      return `<span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-500/10 text-amber-600 border border-amber-500/20">
        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Verlopen
      </span>`;
    }
    return `<span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-500/10 text-gray-600 border border-gray-500/20">
      <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span> Uitgelogd
    </span>`;
  }

  /**
   * Render Auth Method Badge
   */
  function renderAuthMethodBadge(method) {
    const m = (method || 'password').toLowerCase();
    if (m === 'remember_me') {
      return `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-medium border bg-purple-500/10 text-purple-600 border-purple-500/20" title="Automatisch ingelogd via Remember-Me cookie">
        <i class="fas fa-cookie-bite text-[9px]"></i> Remember-Me
      </span>`;
    } else if (m === 'php_session') {
      return `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-medium border bg-slate-500/10 text-slate-600 border-slate-500/20" title="Bestaande actieve PHP sessie">
        <i class="fas fa-rotate-left text-[9px]"></i> Hersteld
      </span>`;
    }
    return `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-medium border bg-blue-500/10 text-blue-600 border-blue-500/20" title="Regulier ingelogd met gebruikersnaam en wachtwoord">
      <i class="fas fa-key text-[9px]"></i> Wachtwoord
    </span>`;
  }

  /**
   * Render Network Badge with intelligent platform heuristics and real ping
   */
  function renderNetworkBadge(s) {
    const isMobile = s.platform && /android|iphone|ipad|ipod|mobile/i.test(s.platform);
    const type = (s.network_type || '').toLowerCase();
    let icon = 'fa-wifi';
    let label = 'Wi-Fi / LAN';

    if (type === 'cellular' || type === '4g' || type === '5g') {
      if (isMobile) {
        icon = 'fa-tower-cell';
        label = 'Mobiel (4G/5G)';
      } else {
        icon = 'fa-network-wired';
        label = 'Vast / Wi-Fi';
      }
    } else if (type === 'wifi') {
      icon = 'fa-wifi';
      label = 'Wi-Fi';
    } else if (type === 'ethernet') {
      icon = 'fa-network-wired';
      label = 'Ethernet / LAN';
    } else if (type === 'mobiel' || type === 'mobiel_wifi') {
      icon = 'fa-mobile-screen';
      label = 'Mobiel / Wi-Fi';
    } else if (type === 'lan_wifi' || !type || type === 'unknown') {
      icon = isMobile ? 'fa-mobile-screen' : 'fa-network-wired';
      label = isMobile ? 'Mobiel / Wi-Fi' : 'Vast / Wi-Fi';
    } else {
      label = escapeHtml(type.toUpperCase());
    }

    const parts = [];
    if (s.network_downlink !== null && s.network_downlink > 0) {
      parts.push(s.network_downlink >= 10 ? '≥10 Mbps' : `${s.network_downlink} Mbps`);
    }
    if (s.network_rtt !== null && s.network_rtt > 0) {
      parts.push(`${s.network_rtt}ms`);
    }

    const detailsStr = parts.length > 0 ? ` · ${parts.join(' · ')}` : '';

    return `<div class="text-[11px] opacity-75 flex items-center gap-1.5 mt-0.5 font-medium">
      <i class="fas ${icon} text-[10px] text-blue-500 opacity-80"></i>
      <span>${label}${detailsStr}</span>
    </div>`;
  }

  /**
   * Create table row HTML for a single session
   */
  function createSessionRowHtml(s) {
    const isExpanded = sessionsState.openTimelineId === s.id;
    const chevronClass = isExpanded ? 'fa-chevron-up' : 'fa-chevron-down';

    // Device / screen info
    let screenHtml = '';
    if (s.viewport_width && s.screen_width) {
      screenHtml = `<div class="text-xs font-mono opacity-80">${s.viewport_width}×${s.viewport_height} <span class="opacity-50 text-[10px]">(${s.screen_width}×${s.screen_height})</span></div>`;
    } else if (s.viewport_width) {
      screenHtml = `<div class="text-xs font-mono opacity-80">${s.viewport_width}×${s.viewport_height}</div>`;
    } else {
      screenHtml = `<div class="text-xs opacity-40 italic">Geen schermdata</div>`;
    }

    const platformIcon = (s.platform && s.platform.toLowerCase().includes('mac')) ? 'fa-apple' :
      (s.platform && s.platform.toLowerCase().includes('win')) ? 'fa-windows' :
      (s.platform && s.platform.toLowerCase().includes('linux')) ? 'fa-linux' :
      (s.platform && s.platform.toLowerCase().includes('iphone')) ? 'fa-mobile-screen-button' :
      (s.platform && s.platform.toLowerCase().includes('android')) ? 'fa-android' : 'fa-laptop';

    const inputIcon = (s.touch_points && s.touch_points > 0)
      ? '<i class="fas fa-fingerprint text-[10px] opacity-60 ml-1" title="Touchscreen"></i>'
      : '<i class="fas fa-mouse text-[10px] opacity-60 ml-1" title="Muis/Toetsenbord"></i>';

    const networkHtml = renderNetworkBadge(s);

    return `
      <tr id="session-row-${s.id}" class="hover:bg-black/5 transition-colors">
        <td class="px-4 py-3">
          <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-full bg-blue-500/10 text-blue-600 flex items-center justify-center font-bold text-xs flex-shrink-0">
              ${escapeHtml(s.username.substring(0, 2).toUpperCase())}
            </div>
            <div>
              <div class="font-semibold text-sm flex items-center gap-1.5">
                <span>${escapeHtml(s.user_display_name)}</span>
                <span class="text-xs font-mono opacity-50">#${s.id}</span>
              </div>
              <div class="text-xs opacity-60">@${escapeHtml(s.username)}</div>
            </div>
          </div>
        </td>
        <td class="px-4 py-3">
          <div class="flex flex-col gap-1 items-start">
            ${renderSessionStatusBadge(s.status)}
            ${renderAuthMethodBadge(s.auth_method)}
          </div>
        </td>
        <td class="px-4 py-3">
          <div class="font-mono text-xs opacity-90">${escapeHtml(s.ip_address || '—')}</div>
          ${networkHtml}
        </td>
        <td class="px-4 py-3">
          <div class="flex items-center gap-1.5 text-xs font-medium">
            <i class="fab ${platformIcon} opacity-70"></i>
            <span>${escapeHtml(s.platform || 'Onbekend')}</span>
            ${inputIcon}
          </div>
          ${screenHtml}
        </td>
        <td class="px-4 py-3">
          <div class="font-mono text-xs opacity-80" title="${escapeHtml(s.started_at)}">${escapeHtml(s.started_at_formatted)}</div>
          <div class="text-xs opacity-60 mt-0.5 flex items-center gap-1">
            <i class="fas fa-hourglass-half text-[10px]"></i>
            <span>Duur: <strong>${escapeHtml(s.duration_formatted)}</strong></span>
          </div>
        </td>
        <td class="px-4 py-3">
          <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs border bg-blue-500/10 text-blue-600 border-blue-500/20" title="${s.event_count} telemetry events gelogd">
              <i class="fas fa-bolt text-[9px]"></i> ${s.event_count}
            </span>
            ${s.audit_count > 0 ? `
              <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs border bg-amber-500/10 text-amber-600 border-amber-500/20 font-semibold" title="${s.audit_count} gekoppelde audit logs">
                <i class="fas fa-shield text-[9px]"></i> ${s.audit_count}
              </span>` : `
              <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs border bg-black/5 opacity-50" title="0 gekoppelde audit logs">
                0 audits
              </span>`}
          </div>
          <div class="text-[11px] opacity-60 mt-1">Laatst: ${escapeHtml(s.last_activity_ago)}</div>
        </td>
        <td class="px-4 py-3 text-right">
          <button type="button" onclick="window.AuditHub.toggleSessionTimeline(${s.id})" id="btn-toggle-timeline-${s.id}" class="px-3 py-1.5 rounded-lg border text-xs font-semibold text-blue-600 border-blue-500/30 hover:bg-blue-500/10 transition flex items-center gap-1.5 ml-auto">
            <i id="chevron-timeline-${s.id}" class="fas ${chevronClass} text-[10px] transition-transform"></i>
            <span>Tijdlijn</span>
          </button>
        </td>
      </tr>
    `;
  }

  /**
   * Load Sessions with current filters and pagination
   */
  async function loadSessions(page = 1, showSpinner = true) {
    if (showSpinner && elements.sessionsLoading) {
      elements.sessionsLoading.classList.remove('hidden');
    }
    if (elements.iconRefreshSessionsSpin) {
      elements.iconRefreshSessionsSpin.classList.add('fa-spin');
    }

    sessionsState.currentPage = page;
    const qs = buildSessionsQueryParams({ action: 'get_sessions', page: page });
    const data = await fetchJson(`/admin/audit_helper?${qs}`);

    if (showSpinner && elements.sessionsLoading) {
      elements.sessionsLoading.classList.add('hidden');
    }
    if (elements.iconRefreshSessionsSpin) {
      elements.iconRefreshSessionsSpin.classList.remove('fa-spin');
    }

    if (!data || !data.success) {
      console.error('Failed to load sessions', data);
      return;
    }

    sessionsState.totalPages = data.pages || 1;

    // Update active sessions badge in tab header
    if (elements.activeSessionsBadge) {
      if (data.active_count && data.active_count > 0) {
        elements.activeSessionsBadge.textContent = `${data.active_count} actief`;
        elements.activeSessionsBadge.classList.remove('hidden');
      } else {
        elements.activeSessionsBadge.classList.add('hidden');
      }
    }

    if (elements.sessionsCountBadge) {
      elements.sessionsCountBadge.textContent = data.total.toLocaleString('nl-NL');
    }

    if (elements.sessionsPaginationInfo) {
      elements.sessionsPaginationInfo.textContent = `Pagina ${data.page} van ${sessionsState.totalPages} (${data.total.toLocaleString('nl-NL')} sessies)`;
    }
    if (elements.sessionsPaginationCurrentPage) {
      elements.sessionsPaginationCurrentPage.textContent = data.page;
    }
    if (elements.btnSessionsPagePrev) {
      elements.btnSessionsPagePrev.disabled = data.page <= 1;
    }
    if (elements.btnSessionsPageNext) {
      elements.btnSessionsPageNext.disabled = data.page >= sessionsState.totalPages;
    }

    sessionsState.sessions.clear();

    if (!data.sessions || data.sessions.length === 0) {
      if (elements.sessionsTbody) elements.sessionsTbody.innerHTML = '';
      if (elements.sessionsEmpty) elements.sessionsEmpty.classList.remove('hidden');
      return;
    }

    if (elements.sessionsEmpty) elements.sessionsEmpty.classList.add('hidden');

    let html = '';
    data.sessions.forEach(s => {
      sessionsState.sessions.set(s.id, s);
      html += createSessionRowHtml(s);
    });

    if (elements.sessionsTbody) {
      elements.sessionsTbody.innerHTML = html;
    }

    // If an accordion was open and its row exists on this page, restore it
    if (sessionsState.openTimelineId && sessionsState.sessions.has(sessionsState.openTimelineId)) {
      toggleSessionTimeline(sessionsState.openTimelineId, true);
    }
  }

  /**
   * Render Event Timeline Item
   */
  function renderTimelineEventHtml(item) {
    let iconClass = 'fa-circle-dot';
    let iconColor = 'bg-blue-500 text-white';
    let typeTitle = 'Interactie';
    let detailHtml = '';

    switch (item.event_type) {
      case 'page_view':
        iconClass = 'fa-file-lines';
        iconColor = 'bg-blue-500 text-white';
        typeTitle = 'Pagina Geopend';
        detailHtml = `<span class="font-mono text-xs font-semibold text-blue-600">${escapeHtml(item.page_url || '/')}</span>`;
        if (item.referrer) {
          detailHtml += `<div class="text-[11px] opacity-60 mt-0.5"><i class="fas fa-arrow-turn-up fa-rotate-90 text-[9px] mr-1"></i>Referrer: ${escapeHtml(item.referrer)}</div>`;
        }
        break;
      case 'click':
        iconClass = 'fa-arrow-pointer';
        iconColor = 'bg-purple-500 text-white';
        typeTitle = 'Klik Interactie';
        detailHtml = `<span>Geklikt op <strong>${escapeHtml(item.target_label || item.target_id || item.target_tag || 'element')}</strong></span>`;
        if (item.page_url) {
          detailHtml += `<span class="opacity-50 text-xs ml-2">op ${escapeHtml(item.page_url)}</span>`;
        }
        break;
      case 'tab_switch':
        iconClass = 'fa-table-columns';
        iconColor = 'bg-indigo-500 text-white';
        typeTitle = 'Tab Gewisseld';
        detailHtml = `<span>Gewisseld naar tabblad <strong>${escapeHtml(item.target_label || 'Tab')}</strong></span>`;
        break;
      case 'modal_open':
        iconClass = 'fa-window-maximize';
        iconColor = 'bg-cyan-500 text-white';
        typeTitle = 'Dialoogvenster Geopend';
        detailHtml = `<span>Modal geopend: <strong>${escapeHtml(item.target_label || item.target_id || 'Modal')}</strong></span>`;
        break;
      case 'form_submit':
        iconClass = 'fa-paper-plane';
        iconColor = 'bg-emerald-500 text-white';
        typeTitle = 'Formulier Verzonden';
        detailHtml = `<span>Formulier verzonden: <strong>${escapeHtml(item.target_label || item.target_id || 'Form')}</strong></span>`;
        break;
      case 'visibility_hidden':
        iconClass = 'fa-eye-slash';
        iconColor = 'bg-slate-500 text-white';
        typeTitle = 'Pagina Geminimaliseerd';
        detailHtml = `<span class="opacity-70 text-xs">Gebruiker wisselde naar andere app of tabblad</span>`;
        break;
      case 'visibility_visible':
        iconClass = 'fa-eye';
        iconColor = 'bg-emerald-600 text-white';
        typeTitle = 'Pagina Actief';
        detailHtml = `<span class="opacity-70 text-xs">Gebruiker keerde terug naar tabblad</span>`;
        break;
      case 'auth_login':
        iconClass = 'fa-right-to-bracket';
        iconColor = 'bg-emerald-500 text-white';
        typeTitle = 'Inloggen Voltooid';
        detailHtml = `<span class="font-medium text-emerald-600">Sessie geactiveerd</span>`;
        break;
      case 'auth_logout':
        iconClass = 'fa-right-from-bracket';
        iconColor = 'bg-rose-500 text-white';
        typeTitle = 'Uitgelogd';
        detailHtml = `<span class="font-medium text-rose-600">Sessie beëindigd door gebruiker</span>`;
        break;
      default:
        iconClass = 'fa-circle-dot';
        iconColor = 'bg-gray-500 text-white';
        typeTitle = escapeHtml(item.event_type);
        detailHtml = `<span>${escapeHtml(item.target_label || item.page_url || '')}</span>`;
    }

    return `
      <div class="relative pl-8 pb-5 group">
        <!-- Bullet -->
        <div class="absolute left-0 top-1 w-6 h-6 rounded-full ${iconColor} flex items-center justify-center text-[10px] shadow-sm ring-4 ring-white dark:ring-slate-900 z-10">
          <i class="fas ${iconClass}"></i>
        </div>
        
        <!-- Content: Crisp white background with clean contrast -->
        <div class="bg-white dark:bg-slate-800 p-3.5 rounded-xl border shadow-2xs flex flex-col sm:flex-row sm:items-center justify-between gap-3" style="border-color: var(--theme-card-border);">
          <div>
            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-0.5">${typeTitle}</div>
            <div class="text-sm font-medium text-slate-800 dark:text-slate-200">${detailHtml}</div>
          </div>
          <div class="text-xs font-mono font-medium text-slate-600 dark:text-slate-300 sm:text-right flex-shrink-0" title="${escapeHtml(item.date_formatted)}">
            <div><i class="fas fa-clock text-[10px] mr-1 opacity-70"></i>${escapeHtml(item.time_formatted)}</div>
            <div class="text-[10px] opacity-60 font-normal">${escapeHtml(item.time_ago)}</div>
          </div>
        </div>
      </div>
    `;
  }

  /**
   * Render Audit Log Timeline Item (linked system audit record)
   */
  function renderTimelineAuditHtml(item) {
    const sevBadge = renderSeverityBadge(item.severity);
    const catBadge = renderCategoryBadge(item.category);

    return `
      <div class="relative pl-8 pb-5 group">
        <!-- Bullet -->
        <div class="absolute left-0 top-1 w-6 h-6 rounded-full bg-amber-500 text-white flex items-center justify-center text-[10px] shadow-sm ring-4 ring-amber-100 dark:ring-amber-950/60 z-10 animate-bounce-once">
          <i class="fas fa-shield-halved"></i>
        </div>
        
        <!-- Content Card: High-contrast white background with left accent border -->
        <div class="p-4 rounded-xl border border-l-4 bg-white dark:bg-slate-800 shadow-sm flex flex-col gap-2.5" style="border-color: var(--theme-card-border); border-left-color: #f59e0b;">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center flex-wrap gap-2">
              <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-500/15 text-amber-800 dark:text-amber-300 border border-amber-500/30">
                <i class="fas fa-file-shield text-xs"></i> Audit Record #${item.id}
              </span>
              ${sevBadge}
              ${catBadge}
            </div>
            <div class="text-xs font-mono font-medium text-slate-700 dark:text-slate-300 flex items-center gap-1.5" title="${escapeHtml(item.date_formatted)}">
              <i class="fas fa-clock text-[10px] opacity-70"></i>
              <span>${escapeHtml(item.time_formatted)}</span>
              <span class="text-[10px] opacity-60 font-normal">(${escapeHtml(item.time_ago)})</span>
            </div>
          </div>

          <div class="font-semibold text-sm text-slate-900 dark:text-slate-50 leading-relaxed">
            ${escapeHtml(item.details)}
          </div>

          ${item.target_label ? `
            <div class="text-xs text-slate-600 dark:text-slate-300 flex items-center gap-1.5">
              <i class="fas fa-crosshairs text-[10px] opacity-70"></i>
              <span>Doel: <strong>${escapeHtml(item.target_label)}</strong></span>
            </div>` : ''}

          <div class="flex items-center justify-between pt-2.5 border-t text-xs" style="border-color: var(--theme-card-border);">
            <span class="font-mono text-[11px] text-slate-500 dark:text-slate-400">IP: ${escapeHtml(item.ip_address || '—')}</span>
            <button type="button" onclick="window.AuditHub.openModal(${item.id})" class="px-3 py-1.5 rounded-lg bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-800 dark:text-slate-100 transition font-semibold text-xs flex items-center gap-1.5 shadow-2xs">
              <i class="fas fa-eye text-[10px]"></i>
              <span>Bekijk Details</span>
            </button>
          </div>
        </div>
      </div>
    `;
  }

  /**
   * Accordion: Toggle Session Timeline
   */
  async function toggleSessionTimeline(sessionId, forceOpen = false) {
    const existingRow = document.getElementById(`session-timeline-row-${sessionId}`);
    const chevron = document.getElementById(`chevron-timeline-${sessionId}`);

    // If already open and not forced, collapse it
    if (existingRow && !forceOpen) {
      existingRow.remove();
      if (chevron) {
        chevron.className = 'fas fa-chevron-down text-[10px] transition-transform';
      }
      sessionsState.openTimelineId = null;
      return;
    }

    // If another timeline is currently open, remove it
    if (sessionsState.openTimelineId && sessionsState.openTimelineId !== sessionId) {
      const prevRow = document.getElementById(`session-timeline-row-${sessionsState.openTimelineId}`);
      const prevChevron = document.getElementById(`chevron-timeline-${sessionsState.openTimelineId}`);
      if (prevRow) prevRow.remove();
      if (prevChevron) prevChevron.className = 'fas fa-chevron-down text-[10px] transition-transform';
    }

    const parentRow = document.getElementById(`session-row-${sessionId}`);
    if (!parentRow) return;

    if (chevron) {
      chevron.className = 'fas fa-chevron-up text-[10px] transition-transform';
    }

    // Insert loading container
    if (existingRow) existingRow.remove();
    const timelineRow = document.createElement('tr');
    timelineRow.id = `session-timeline-row-${sessionId}`;
    timelineRow.className = 'bg-slate-50/60 dark:bg-slate-900/60';
    timelineRow.innerHTML = `
      <td colspan="7" class="p-4 sm:p-6 border-b" style="border-color: var(--theme-card-border);">
        <div id="timeline-box-${sessionId}" class="space-y-4">
          <div class="flex items-center justify-center py-8 text-sm opacity-70">
            <i class="fas fa-spinner fa-spin text-blue-500 text-lg mr-2.5"></i>
            <span>Sessie-tijdlijn ophalen...</span>
          </div>
        </div>
      </td>
    `;
    parentRow.after(timelineRow);
    sessionsState.openTimelineId = sessionId;

    // Fetch timeline data
    const data = await fetchJson(`/admin/audit_helper?action=get_session_timeline&session_id=${sessionId}`);
    const timelineBox = document.getElementById(`timeline-box-${sessionId}`);
    if (!timelineBox) return;

    if (!data || !data.success) {
      timelineBox.innerHTML = `
        <div class="p-4 rounded-lg bg-red-500/10 border border-red-500/20 text-red-600 text-sm">
          <i class="fas fa-triangle-exclamation mr-1.5"></i>
          Fout bij laden van tijdlijn: ${escapeHtml(data?.error || 'Onbekende fout')}
        </div>
      `;
      return;
    }

    const sess = data.session || {};
    const timeline = data.timeline || [];

    // Hardware & Device summary bar
    let deviceDetails = [];
    if (sess.user_agent) deviceDetails.push(`<span class="truncate max-w-sm inline-block text-slate-800 dark:text-white font-medium" title="${escapeHtml(sess.user_agent)}"><i class="fas fa-compass mr-1 text-slate-500 dark:text-white/70"></i>${escapeHtml(sess.user_agent)}</span>`);
    if (sess.platform) deviceDetails.push(`<span class="text-slate-700 dark:text-white"><i class="fas fa-laptop mr-1 text-slate-500 dark:text-white/70"></i>${escapeHtml(sess.platform)}</span>`);
    if (sess.language) deviceDetails.push(`<span class="text-slate-700 dark:text-white"><i class="fas fa-language mr-1 text-slate-500 dark:text-white/70"></i>${escapeHtml(sess.language)}</span>`);
    if (sess.viewport_width && sess.viewport_height) deviceDetails.push(`<span class="text-slate-700 dark:text-white"><i class="fas fa-display mr-1 text-slate-500 dark:text-white/70"></i>Viewport: ${sess.viewport_width}×${sess.viewport_height}</span>`);

    let timelineItemsHtml = '';
    if (timeline.length === 0) {
      timelineItemsHtml = `
        <div class="py-8 text-center text-sm text-slate-500 dark:text-slate-400">
          <i class="fas fa-clock-rotate-left text-2xl mb-2 opacity-40 block"></i>
          Nog geen interactie-events of audit logs geregistreerd voor deze sessie.
        </div>
      `;
    } else {
      timelineItemsHtml = `
        <div class="relative pl-3 mt-4 border-l-2 ml-3 space-y-3" style="border-color: var(--theme-card-border);">
          ${timeline.map(item => item.type === 'audit' ? renderTimelineAuditHtml(item) : renderTimelineEventHtml(item)).join('')}
        </div>
      `;
    }

    timelineBox.innerHTML = `
      <div class="p-5 sm:p-6 rounded-2xl border bg-white dark:bg-slate-900 shadow-sm space-y-5" style="border-color: var(--theme-card-border);">
        <!-- Timeline Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b" style="border-color: var(--theme-card-border);">
          <div>
            <h4 class="font-bold text-base flex items-center gap-2 text-slate-900 dark:text-white">
              <i class="fas fa-desktop theme-primary"></i>
              <span>Interactie & Audit Tijdlijn — Sessie #${sess.id}</span>
            </h4>
            <div class="text-xs text-slate-600 dark:text-white/80 mt-0.5">
              Gebruiker: <strong class="text-slate-900 dark:text-white">${escapeHtml(sess.user_display_name)} (@${escapeHtml(sess.username)})</strong> · 
              Gestart om <span class="dark:text-white">${escapeHtml(sess.started_at_formatted)}</span> (${escapeHtml(sess.started_ago)}) · 
              Duur: <strong class="text-slate-900 dark:text-white">${escapeHtml(sess.duration_formatted)}</strong>
            </div>
          </div>
          <div class="flex items-center gap-2">
            ${renderSessionStatusBadge(sess.status)}
            ${renderAuthMethodBadge(sess.auth_method)}
          </div>
        </div>

        <!-- Metadata Sub-bar -->
        <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs font-mono p-3 rounded-xl bg-slate-50 dark:bg-slate-800/80 border text-slate-700 dark:text-white" style="border-color: var(--theme-card-border);">
          ${deviceDetails.join('<span class="opacity-30 dark:opacity-60">•</span>')}
        </div>

        <!-- Chronological Timeline Stream -->
        <div>
          <div class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2 flex items-center justify-between">
            <span class="dark:text-white/90">Chronologische Gebeurtenissen (${timeline.length})</span>
            <span class="text-[11px] font-normal normal-case opacity-70 dark:text-white/70">Sessie Telemetrie + Systeem Audit Logs</span>
          </div>
          ${timelineItemsHtml}
        </div>
      </div>
    `;
  }

  /**
   * Cross-link: Open a session from an audit log button
   */
  async function openSessionInTracker(sessionId) {
    switchMainTab('sessions');
    // Set session_id filter and load
    sessionsState.filters.search = '';
    sessionsState.filters.auth_method = 'all';
    sessionsState.filters.status = 'all';
    sessionsState.filters.user_id = '';
    sessionsState.filters.session_id = sessionId.toString();

    if (elements.filterSessionsSearch) elements.filterSessionsSearch.value = '';
    if (elements.filterSessionsAuth) elements.filterSessionsAuth.value = 'all';
    if (elements.filterSessionsStatus) elements.filterSessionsStatus.value = 'all';
    if (elements.filterSessionsUser) elements.filterSessionsUser.value = '';
    updateSessionsFilterIndicator();

    await loadSessions(1);
    // Automatically unfold timeline
    setTimeout(() => {
      toggleSessionTimeline(sessionId, true);
    }, 200);
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
      let actorStr = log.actor_username ? `${log.actor_username} (#${log.actor_user_id || '?'})` : 'Systeem';
      if (log.session_id) {
        actorStr += ` [Sessie #${log.session_id}]`;
      }
      elements.modalLogActor.textContent = actorStr;
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
        session_id: log.session_id || null,
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

    // Manual Refresh (Audit)
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

    // --- Sessions Listeners ---
    if (elements.filterSessionsSearch) {
      elements.filterSessionsSearch.addEventListener('input', (e) => {
        clearTimeout(sessionsState.searchDebounceTimer);
        sessionsState.searchDebounceTimer = setTimeout(() => {
          sessionsState.filters.search = e.target.value.trim();
          sessionsState.filters.session_id = ''; // clear explicit session_id on free search
          updateSessionsFilterIndicator();
          loadSessions(1);
        }, 300);
      });
    }

    if (elements.filterSessionsAuth) {
      elements.filterSessionsAuth.addEventListener('change', (e) => {
        sessionsState.filters.auth_method = e.target.value;
        updateSessionsFilterIndicator();
        loadSessions(1);
      });
    }

    if (elements.filterSessionsStatus) {
      elements.filterSessionsStatus.addEventListener('change', (e) => {
        sessionsState.filters.status = e.target.value;
        updateSessionsFilterIndicator();
        loadSessions(1);
      });
    }

    if (elements.filterSessionsUser) {
      elements.filterSessionsUser.addEventListener('change', (e) => {
        sessionsState.filters.user_id = e.target.value;
        updateSessionsFilterIndicator();
        loadSessions(1);
      });
    }

    if (elements.btnResetSessionsFilters) {
      elements.btnResetSessionsFilters.addEventListener('click', () => {
        sessionsState.filters.search = '';
        sessionsState.filters.auth_method = 'all';
        sessionsState.filters.status = 'all';
        sessionsState.filters.user_id = '';
        sessionsState.filters.session_id = '';
        if (elements.filterSessionsSearch) elements.filterSessionsSearch.value = '';
        if (elements.filterSessionsAuth) elements.filterSessionsAuth.value = 'all';
        if (elements.filterSessionsStatus) elements.filterSessionsStatus.value = 'all';
        if (elements.filterSessionsUser) elements.filterSessionsUser.value = '';
        updateSessionsFilterIndicator();
        loadSessions(1);
      });
    }

    if (elements.btnRefreshSessions) {
      elements.btnRefreshSessions.addEventListener('click', () => {
        loadSessions(sessionsState.currentPage);
      });
    }

    if (elements.btnSessionsPagePrev) {
      elements.btnSessionsPagePrev.addEventListener('click', () => {
        if (sessionsState.currentPage > 1) {
          loadSessions(sessionsState.currentPage - 1);
        }
      });
    }

    if (elements.btnSessionsPageNext) {
      elements.btnSessionsPageNext.addEventListener('click', () => {
        if (sessionsState.currentPage < sessionsState.totalPages) {
          loadSessions(sessionsState.currentPage + 1);
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
    closeModal: closeAuditModal,
    switchMainTab: switchMainTab,
    toggleSessionTimeline: toggleSessionTimeline,
    openSessionInTracker: openSessionInTracker
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
