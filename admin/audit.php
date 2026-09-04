<?php
declare(strict_types=1);

// Administrative interface for viewing system audit logs, user assignments, authentication attempts, and security telemetry.
define("PAGE_NAME", "a_audit");
require_once(__DIR__ . '/../includes/auth.php');

if (($privilege ?? 0) < 2) {
    header("Location: ../home");
    exit();
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<title>Jotify - Audit Log</title>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="shortcut icon" type="image/png" href="../media/geusje.png"/>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://kit.fontawesome.com/870ab34ea3.js" crossorigin="anonymous"></script>
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<?php include_once('../includes/theme.php'); ?>
<style>
  .badge-info { background-color: rgba(59, 130, 246, 0.15); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.3); }
  .badge-warning { background-color: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3); }
  .badge-error { background-color: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); }
  .badge-security { background-color: rgba(168, 85, 247, 0.15); color: #a855f7; border: 1px solid rgba(168, 85, 247, 0.3); }
</style>
</head>
<body class="flex h-screen overflow-hidden">

<!-- Sidebar -->
<?php include_once('../includes/sidebar.php') ?>

<!-- Main Content -->
<div class="flex-1 flex flex-col h-screen overflow-y-auto w-full relative">
  <!-- Topbar -->
  <?php include_once('../includes/topbar.php') ?>

  <main class="p-4 md:p-6 max-w-[1400px] mx-auto w-full flex-1">

    <div class="space-y-6 mb-24">

      <!-- Page Header & Global Controls -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h2 class="text-2xl font-bold flex items-center gap-2.5">
            <i class="fas fa-history theme-primary"></i>
            <span>Audit & Activiteitenlogboek</span>
          </h2>
          <p class="text-sm opacity-70 mt-1">Centraal overzicht van toewijzingen, whiteboard mutaties, loginpogingen en systeemwijzigingen.</p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
          <!-- Retention badge -->
          <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border text-xs bg-black/5" style="border-color: var(--theme-card-border);" title="Automatische opschoning schema: Info 3 dagen, Waarschuwing 14 dagen, Beveiliging/Kritiek 30 dagen">
            <i class="fas fa-clock-rotate-left opacity-70"></i>
            <span>Bewaartermijn: <strong>3d</strong> / <strong>14d</strong> / <strong>30d</strong></span>
          </div>

          <!-- Live Toggle -->
          <button id="btn-live-toggle" type="button" class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-lg border text-xs font-semibold bg-emerald-500/10 text-emerald-600 border-emerald-500/30 hover:bg-emerald-500/20 transition">
            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
            <span id="live-status-text">Live: Aan</span>
          </button>

          <!-- Export CSV -->
          <button id="btn-export-csv" type="button" class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-lg border text-xs font-semibold bg-blue-500/10 text-blue-600 border-blue-500/30 hover:bg-blue-500/20 transition">
            <i class="fas fa-file-csv"></i>
            <span>Exporteer CSV</span>
          </button>
        </div>
      </div>

      <!-- Stats Cards (24h Telemetry) -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

        <!-- Stat 1: Total Events 24h -->
        <div class="theme-card rounded-xl border p-4 shadow-sm flex items-center gap-4" style="border-color: var(--theme-card-border);">
          <div class="w-12 h-12 rounded-xl bg-blue-500/15 text-blue-500 flex items-center justify-center text-xl flex-shrink-0">
            <i class="fas fa-bolt"></i>
          </div>
          <div>
            <div id="stat-total-24h" class="text-2xl font-bold">—</div>
            <div class="text-xs opacity-60 uppercase font-semibold tracking-wider">Acties (24u)</div>
          </div>
        </div>

        <!-- Stat 2: Assignments 24h -->
        <div class="theme-card rounded-xl border p-4 shadow-sm flex items-center gap-4" style="border-color: var(--theme-card-border);">
          <div class="w-12 h-12 rounded-xl bg-emerald-500/15 text-emerald-500 flex items-center justify-center text-xl flex-shrink-0">
            <i class="fas fa-users-gear"></i>
          </div>
          <div>
            <div id="stat-assignments-24h" class="text-2xl font-bold">—</div>
            <div class="text-xs opacity-60 uppercase font-semibold tracking-wider">Toewijzingen (24u)</div>
          </div>
        </div>

        <!-- Stat 3: Security & Warnings 24h -->
        <div class="theme-card rounded-xl border p-4 shadow-sm flex items-center gap-4" style="border-color: var(--theme-card-border);">
          <div class="w-12 h-12 rounded-xl bg-amber-500/15 text-amber-500 flex items-center justify-center text-xl flex-shrink-0">
            <i class="fas fa-shield-halved"></i>
          </div>
          <div>
            <div id="stat-security-24h" class="text-2xl font-bold">—</div>
            <div class="text-xs opacity-60 uppercase font-semibold tracking-wider">Beveiliging & Alerts (24u)</div>
          </div>
        </div>

        <!-- Stat 4: Active Users 24h -->
        <div class="theme-card rounded-xl border p-4 shadow-sm flex items-center gap-4" style="border-color: var(--theme-card-border);">
          <div class="w-12 h-12 rounded-xl bg-purple-500/15 text-purple-500 flex items-center justify-center text-xl flex-shrink-0">
            <i class="fas fa-user-check"></i>
          </div>
          <div>
            <div id="stat-active-users-24h" class="text-2xl font-bold">—</div>
            <div class="text-xs opacity-60 uppercase font-semibold tracking-wider">Actieve Gebruikers (24u)</div>
          </div>
        </div>

      </div>

      <!-- Filter Toolbar -->
      <div class="theme-card rounded-xl border p-4 shadow-sm" style="border-color: var(--theme-card-border);">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
          
          <!-- Search input -->
          <div class="lg:col-span-2 relative">
            <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none opacity-50">
              <i class="fas fa-search text-xs"></i>
            </span>
            <input type="text" id="filter-search" placeholder="Zoek op actie, gebruiker, doel, IP..." class="w-full pl-9 pr-4 py-2 text-sm rounded-lg border bg-transparent focus:outline-none focus:ring-2 focus:ring-blue-500/50" style="border-color: var(--theme-card-border);">
          </div>

          <!-- Category filter -->
          <div>
            <select id="filter-category" class="w-full px-3 py-2 text-sm rounded-lg border bg-transparent focus:outline-none focus:ring-2 focus:ring-blue-500/50" style="border-color: var(--theme-card-border);">
              <option value="all">Alle Categorieën</option>
              <option value="assignment">Toewijzingen</option>
              <option value="whiteboard">Whiteboard</option>
              <option value="auth">Authenticatie</option>
              <option value="user">Gebruikersbeheer</option>
              <option value="settings">Instellingen</option>
              <option value="cron">Cronjobs</option>
              <option value="security">Beveiliging / Kiosk</option>
              <option value="system">Systeem</option>
            </select>
          </div>

          <!-- Severity filter -->
          <div>
            <select id="filter-severity" class="w-full px-3 py-2 text-sm rounded-lg border bg-transparent focus:outline-none focus:ring-2 focus:ring-blue-500/50" style="border-color: var(--theme-card-border);">
              <option value="">Alle Niveaus</option>
              <option value="info">Info</option>
              <option value="warning">Waarschuwing</option>
              <option value="error">Fout</option>
              <option value="security">Beveiliging</option>
            </select>
          </div>

          <!-- User filter -->
          <div>
            <select id="filter-user" class="w-full px-3 py-2 text-sm rounded-lg border bg-transparent focus:outline-none focus:ring-2 focus:ring-blue-500/50" style="border-color: var(--theme-card-border);">
              <option value="">Alle Gebruikers</option>
              <!-- Filled via AJAX -->
            </select>
          </div>

        </div>

        <div class="flex items-center justify-between mt-3 pt-3 border-t text-xs opacity-70" style="border-color: var(--theme-card-border);">
          <div id="filter-indicator">Filters actief: 0</div>
          <button id="btn-reset-filters" type="button" class="hover:underline flex items-center gap-1">
            <i class="fas fa-rotate-left"></i> <span>Herstel filters</span>
          </button>
        </div>
      </div>

      <!-- Main Logs Feed Table Card -->
      <div class="theme-card rounded-xl border shadow-sm overflow-hidden" style="border-color: var(--theme-card-border);">
        <div class="theme-card-header px-6 py-4 border-b flex justify-between items-center text-white" style="background-color: var(--theme-sidebar-active); border-color: var(--theme-card-border);">
          <h3 class="text-lg font-bold flex items-center gap-2">
            <i class="fas fa-list-ul"></i>
            <span>Gebeurtenissenlog</span>
            <span id="log-count-badge" class="ml-2 text-xs font-semibold px-2 py-0.5 rounded-full bg-white/20 text-white">0</span>
          </h3>

          <div class="flex items-center gap-3">
            <button id="btn-refresh" class="bg-white/20 hover:bg-white/30 text-white text-xs font-semibold px-3 py-1.5 rounded-lg transition flex items-center gap-1.5">
              <i id="icon-refresh-spin" class="fas fa-rotate"></i>
              <span>Ververs</span>
            </button>
          </div>
        </div>

        <!-- Table Responsive Container -->
        <div class="overflow-x-auto min-h-[300px] relative">
          <!-- Loading overlay -->
          <div id="logs-loading" class="hidden absolute inset-0 bg-white/60 dark:bg-black/60 z-10 flex items-center justify-center backdrop-blur-sm">
            <div class="flex items-center gap-3 px-4 py-2 rounded-lg bg-black/80 text-white text-sm font-semibold shadow-lg">
              <i class="fas fa-spinner fa-spin text-blue-400"></i>
              <span>Logboeken laden...</span>
            </div>
          </div>

          <table class="w-full text-left text-sm whitespace-nowrap">
            <thead class="text-xs uppercase bg-black/5 font-semibold opacity-70 border-b" style="border-color: var(--theme-card-border);">
              <tr>
                <th scope="col" class="px-4 py-3">Tijdstip</th>
                <th scope="col" class="px-4 py-3">Niveau</th>
                <th scope="col" class="px-4 py-3">Categorie</th>
                <th scope="col" class="px-4 py-3">Acteur</th>
                <th scope="col" class="px-4 py-3">Onderwerp / Doel</th>
                <th scope="col" class="px-4 py-3">Details</th>
                <th scope="col" class="px-4 py-3">IP Adres</th>
                <th scope="col" class="px-4 py-3 text-right">Acties</th>
              </tr>
            </thead>
            <tbody id="logs-tbody" class="divide-y" style="border-color: var(--theme-card-border);">
              <!-- Rendered via JS -->
            </tbody>
          </table>

          <!-- Empty state -->
          <div id="logs-empty" class="hidden py-16 text-center">
            <div class="w-16 h-16 rounded-full bg-black/5 flex items-center justify-center mx-auto text-2xl opacity-40 mb-3">
              <i class="fas fa-clipboard-list"></i>
            </div>
            <p class="font-semibold text-base">Geen logberichten gevonden</p>
            <p class="text-xs opacity-60 mt-1">Probeer de zoekterm of filters aan te passen.</p>
          </div>
        </div>

        <!-- Pagination Bar -->
        <div class="px-6 py-4 border-t flex flex-col sm:flex-row items-center justify-between gap-3 text-xs opacity-80" style="border-color: var(--theme-card-border);">
          <div id="pagination-info">Pagina 1 van 1 (0 regels)</div>
          <div class="flex items-center gap-2">
            <button id="btn-page-prev" class="px-3 py-1.5 rounded-lg border hover:bg-black/5 transition disabled:opacity-30 disabled:cursor-not-allowed" style="border-color: var(--theme-card-border);">
              <i class="fas fa-chevron-left mr-1"></i> Vorige
            </button>
            <span id="pagination-current-page" class="font-semibold px-2">1</span>
            <button id="btn-page-next" class="px-3 py-1.5 rounded-lg border hover:bg-black/5 transition disabled:opacity-30 disabled:cursor-not-allowed" style="border-color: var(--theme-card-border);">
              Volgende <i class="fas fa-chevron-right ml-1"></i>
            </button>
          </div>
        </div>
      </div>

    </div>

  </main>
</div>

<!-- Modal: Event Details & JSON Metadata -->
<div id="modal-audit-details" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
  <div class="theme-card rounded-xl border shadow-2xl max-w-2xl w-full overflow-hidden flex flex-col max-h-[90vh]" style="border-color: var(--theme-card-border);">
    <div class="px-6 py-4 border-b flex justify-between items-center" style="border-color: var(--theme-card-border);">
      <h3 class="font-bold text-base flex items-center gap-2">
        <i class="fas fa-circle-info theme-primary"></i>
        <span>Audit Details #<span id="modal-log-id">—</span></span>
      </h3>
      <button type="button" onclick="closeAuditModal()" class="opacity-60 hover:opacity-100 transition text-lg p-1">
        <i class="fas fa-times"></i>
      </button>
    </div>
    
    <div class="p-6 space-y-4 overflow-y-auto flex-1 text-sm">
      <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-xs">
        <div class="p-2.5 rounded-lg border bg-black/5" style="border-color: var(--theme-card-border);">
          <span class="opacity-60 block">Tijdstip</span>
          <strong id="modal-log-time" class="block mt-0.5">—</strong>
        </div>
        <div class="p-2.5 rounded-lg border bg-black/5" style="border-color: var(--theme-card-border);">
          <span class="opacity-60 block">Niveau / Categorie</span>
          <strong id="modal-log-category" class="block mt-0.5">—</strong>
        </div>
        <div class="p-2.5 rounded-lg border bg-black/5" style="border-color: var(--theme-card-border);">
          <span class="opacity-60 block">IP Adres</span>
          <strong id="modal-log-ip" class="block mt-0.5 font-mono">—</strong>
        </div>
        <div class="p-2.5 rounded-lg border bg-black/5" style="border-color: var(--theme-card-border);">
          <span class="opacity-60 block">Acteur</span>
          <strong id="modal-log-actor" class="block mt-0.5">—</strong>
        </div>
        <div class="p-2.5 rounded-lg border bg-black/5" style="border-color: var(--theme-card-border);">
          <span class="opacity-60 block">Betrokkene / Subject</span>
          <strong id="modal-log-subject" class="block mt-0.5">—</strong>
        </div>
        <div class="p-2.5 rounded-lg border bg-black/5" style="border-color: var(--theme-card-border);">
          <span class="opacity-60 block">Doel</span>
          <strong id="modal-log-target" class="block mt-0.5">—</strong>
        </div>
      </div>

      <div>
        <label class="text-xs uppercase font-semibold opacity-60 block mb-1">Details Omschrijving</label>
        <p id="modal-log-details" class="p-3 rounded-lg border bg-black/5 text-sm" style="border-color: var(--theme-card-border);">—</p>
      </div>

      <div>
        <label class="text-xs uppercase font-semibold opacity-60 block mb-1">Ruwe Metadata (JSON)</label>
        <pre id="modal-log-json" class="p-4 rounded-lg bg-gray-900 text-emerald-400 font-mono text-xs overflow-x-auto max-h-56 leading-relaxed select-all">{}</pre>
      </div>
    </div>

    <div class="px-6 py-3 border-t flex justify-end" style="border-color: var(--theme-card-border);">
      <button type="button" onclick="closeAuditModal()" class="px-4 py-2 text-xs font-semibold rounded-lg bg-black/10 hover:bg-black/20 transition">
        Sluiten
      </button>
    </div>
  </div>
</div>

<script src="/js/admin_audit.js"></script>
</body>
</html>
