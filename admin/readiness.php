<?php
// Pre-Flight Readiness Hub: automated diagnostic matrix, pre-game checklist, and edition archival & reset wizard.
define("PAGE_NAME", "a_readiness");
require_once(__DIR__ . '/../includes/auth.php');

if (!isset($_SESSION['id']) || (int)($_SESSION['priv'] ?? 0) < 2) {
    header("Location: ../home");
    exit();
}

$userPriv = (int)($_SESSION['priv'] ?? 0);
$isSuperAdmin = ($userPriv >= 3);
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<title>Jotify - Pre-Flight Readiness Hub</title>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="shortcut icon" type="image/png" href="../media/geusje.png"/>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://kit.fontawesome.com/870ab34ea3.js" crossorigin="anonymous"></script>
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<?php include_once('../includes/theme.php'); ?>
</head>
<body class="flex h-screen overflow-hidden">

<!-- Sidebar -->
<?php include_once('../includes/sidebar.php') ?>

<!-- Main Content -->
<div class="flex-1 flex flex-col h-screen overflow-y-auto w-full relative">
  <!-- Topbar -->
  <?php include_once('../includes/topbar.php') ?>

  <main class="p-4 md:p-6 max-w-[1400px] mx-auto w-full flex-1">

    <div class="space-y-6 mb-24 max-w-6xl">

      <!-- Dynamic Feedback Alerts -->
      <div id="status-alert" class="hidden px-4 py-3 rounded-lg border relative shadow-sm flex items-center justify-between transition-all">
        <div class="flex items-center gap-3">
          <i id="status-alert-icon" class="fas fa-check-circle text-lg"></i>
          <span id="status-alert-text" class="text-sm font-medium"></span>
        </div>
        <button type="button" onclick="document.getElementById('status-alert').classList.add('hidden')" class="opacity-70 hover:opacity-100 transition">
          <i class="fas fa-times"></i>
        </button>
      </div>

      <!-- Card: Overall Readiness Score Hero Banner -->
      <div id="overall-score-banner" class="theme-card rounded-xl border shadow-sm overflow-hidden mb-6" style="border-color: var(--theme-card-border);">
        <div class="theme-card-header px-6 py-4 border-b flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-white" style="background-color: var(--theme-sidebar-active); border-color: var(--theme-card-border);">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-white/15 flex items-center justify-center text-xl">
              <i class="fas fa-clipboard-check"></i>
            </div>
            <div>
              <h3 class="text-xl font-bold leading-tight">Operatie Pre-Flight</h3>
              <span class="text-xs opacity-80">Jotihunt Voorbereiding & Systeemparaatheid</span>
            </div>
          </div>
          <button type="button" id="btn-refresh-diag" onclick="runAllDiagnostics()" 
                  class="bg-white/20 hover:bg-white/30 text-white text-xs font-semibold px-3.5 py-1.5 rounded-lg transition flex items-center gap-1.5 self-start sm:self-auto shadow-sm">
            <i id="icon-diag-spin" class="fas fa-rotate"></i>
            <span>Heranalyseer Alles</span>
          </button>
        </div>

        <div class="p-6">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4">
            <div>
              <span class="text-xs uppercase tracking-wider opacity-60 font-semibold block mb-1">Status</span>
              <p id="overall-readiness-status" class="text-base sm:text-lg font-bold">
                Systeemanalyse wordt uitgevoerd...
              </p>
            </div>
            <div class="text-left sm:text-right">
              <span class="text-xs uppercase tracking-wider opacity-60 font-semibold block mb-1">Totale Score</span>
              <span id="overall-readiness-score" class="font-mono text-3xl sm:text-4xl font-black theme-primary">--%</span>
            </div>
          </div>

          <!-- Progress Bar -->
          <div class="w-full bg-black/10 dark:bg-white/10 rounded-full h-3 overflow-hidden">
            <div id="overall-score-bar" class="h-3 rounded-full theme-bg-primary transition-all duration-700" style="width: 0%"></div>
          </div>
        </div>
      </div>

      <!-- Navigation Tabs -->
      <div class="border-b flex items-center space-x-2 md:space-x-8 overflow-x-auto text-sm font-semibold mb-6" style="border-color: var(--theme-card-border);">
        <button type="button" data-tab-target="diagnostics" class="pb-3 border-b-2 font-bold theme-primary theme-border-primary flex items-center gap-2 whitespace-nowrap transition">
          <i class="fas fa-heart-pulse"></i>
          <span>1. Systeem- & Integratiediagnose</span>
        </button>
        <button type="button" data-tab-target="checklist" class="pb-3 border-b-2 border-transparent opacity-60 hover:opacity-100 flex items-center gap-2 whitespace-nowrap transition">
          <i class="fas fa-tasks"></i>
          <span>2. Operationele Checklist</span>
        </button>
        <button type="button" data-tab-target="archive" class="pb-3 border-b-2 border-transparent opacity-60 hover:opacity-100 flex items-center gap-2 whitespace-nowrap transition">
          <i class="fas fa-box-archive"></i>
          <span>3. Archivering & Seizoensstart</span>
        </button>
      </div>

      <!-- ==================== TAB 1: DIAGNOSTICS ==================== -->
      <div data-tab-panel="diagnostics" class="space-y-6">
        <div class="flex items-center justify-between">
          <div>
            <h3 class="text-lg font-bold">Live Systeem- & Matrixstatus</h3>
            <p class="text-xs opacity-60">Real-time probes controleren officiële API's, portaal login, crontabs, databases en opslag.</p>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

          <!-- 1. Officiële Jotihunt API -->
          <div id="diag-card-jotihunt_api" class="theme-card border rounded-xl p-5 shadow-sm space-y-3" style="border-color: var(--theme-card-border);">
            <div class="flex items-start justify-between gap-3">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-black/5 flex items-center justify-center text-lg theme-primary">
                  <i class="fas fa-satellite-dish"></i>
                </div>
                <div>
                  <h4 class="font-bold text-sm">Officiële Jotihunt API (2.0)</h4>
                  <span class="text-xs opacity-60">jotihunt.nl/api/2.0/areas</span>
                </div>
              </div>
              <span class="diag-status-badge text-xs px-2.5 py-1 rounded-full font-semibold bg-black/5 opacity-70">
                Wachten...
              </span>
            </div>
            <p class="diag-msg text-sm font-semibold opacity-90">Wordt gecontroleerd...</p>
            <div class="flex items-center justify-between text-xs opacity-60 pt-2 border-t" style="border-color: var(--theme-card-border);">
              <span class="diag-detail">Outbound HTTP status & responstijd</span>
              <span class="diag-latency font-mono font-bold hidden">--ms</span>
            </div>
          </div>

          <!-- 1b. Jotihunt Portaal Inloggegevens (Scraper) -->
          <div id="diag-card-jotihunt_credentials" class="theme-card border rounded-xl p-5 shadow-sm space-y-3" style="border-color: var(--theme-card-border);">
            <div class="flex items-start justify-between gap-3">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-black/5 flex items-center justify-center text-lg theme-primary">
                  <i class="fas fa-key"></i>
                </div>
                <div>
                  <h4 class="font-bold text-sm">Jotihunt.nl Portaal Inloggegevens</h4>
                  <span class="text-xs opacity-60">Automatische sessie & CSRF verificatie</span>
                </div>
              </div>
              <span class="diag-status-badge text-xs px-2.5 py-1 rounded-full font-semibold bg-black/5 opacity-70">
                Wachten...
              </span>
            </div>
            <p class="diag-msg text-sm font-semibold opacity-90">Wordt gecontroleerd...</p>
            <div class="flex items-center justify-between text-xs opacity-60 pt-2 border-t" style="border-color: var(--theme-card-border);">
              <span class="diag-detail">Portaal login & scoutinggroep authenticatie</span>
              <span class="diag-latency font-mono font-bold hidden">--ms</span>
            </div>
          </div>

          <!-- 2. Database & Schema -->
          <div id="diag-card-database" class="theme-card border rounded-xl p-5 shadow-sm space-y-3" style="border-color: var(--theme-card-border);">
            <div class="flex items-start justify-between gap-3">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-black/5 flex items-center justify-center text-lg theme-primary">
                  <i class="fas fa-database"></i>
                </div>
                <div>
                  <h4 class="font-bold text-sm">MariaDB & Tabelschema</h4>
                  <span class="text-xs opacity-60">InnoDB utf8mb4 integriteit</span>
                </div>
              </div>
              <span class="diag-status-badge text-xs px-2.5 py-1 rounded-full font-semibold bg-black/5 opacity-70">
                Wachten...
              </span>
            </div>
            <p class="diag-msg text-sm font-semibold opacity-90">Wordt gecontroleerd...</p>
            <div class="flex items-center justify-between text-xs opacity-60 pt-2 border-t" style="border-color: var(--theme-card-border);">
              <span class="diag-detail">Database latency & tabellen</span>
              <span class="diag-latency font-mono font-bold hidden">--ms</span>
            </div>
          </div>

          <!-- 3. Cronjobs Subsystem -->
          <div id="diag-card-cronjobs" class="theme-card border rounded-xl p-5 shadow-sm space-y-3" style="border-color: var(--theme-card-border);">
            <div class="flex items-start justify-between gap-3">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-black/5 flex items-center justify-center text-lg theme-primary">
                  <i class="fas fa-stopwatch"></i>
                </div>
                <div>
                  <h4 class="font-bold text-sm">Achtergrondtaken (Cronjobs)</h4>
                  <span class="text-xs opacity-60">Vossen, hints, status, logs</span>
                </div>
              </div>
              <span class="diag-status-badge text-xs px-2.5 py-1 rounded-full font-semibold bg-black/5 opacity-70">
                Wachten...
              </span>
            </div>
            <p class="diag-msg text-sm font-semibold opacity-90">Wordt gecontroleerd...</p>
            <div class="flex items-center justify-between text-xs opacity-60 pt-2 border-t" style="border-color: var(--theme-card-border);">
              <span class="diag-detail">Actieve crons & laatste uitvoering</span>
              <span class="diag-latency font-mono font-bold hidden"></span>
            </div>
          </div>

          <!-- 4. Telegram Bot Subsystem -->
          <div id="diag-card-telegram_bot" class="theme-card border rounded-xl p-5 shadow-sm space-y-3" style="border-color: var(--theme-card-border);">
            <div class="flex items-start justify-between gap-3">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-black/5 flex items-center justify-center text-lg theme-primary">
                  <i class="fab fa-telegram-plane"></i>
                </div>
                <div>
                  <h4 class="font-bold text-sm">Telegram Bot Subsystem</h4>
                  <span class="text-xs opacity-60">Bot API & Broadcast kanaal</span>
                </div>
              </div>
              <span class="diag-status-badge text-xs px-2.5 py-1 rounded-full font-semibold bg-black/5 opacity-70">
                Wachten...
              </span>
            </div>
            <p class="diag-msg text-sm font-semibold opacity-90">Wordt gecontroleerd...</p>
            <div class="flex items-center justify-between text-xs opacity-60 pt-2 border-t" style="border-color: var(--theme-card-border);">
              <span class="diag-detail">getMe validatie & meldgroep</span>
              <span class="diag-latency font-mono font-bold hidden">--ms</span>
            </div>
          </div>

          <!-- 5. Mapbox Kaarten API -->
          <div id="diag-card-mapbox" class="theme-card border rounded-xl p-5 shadow-sm space-y-3" style="border-color: var(--theme-card-border);">
            <div class="flex items-start justify-between gap-3">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-black/5 flex items-center justify-center text-lg theme-primary">
                  <i class="fas fa-map-location-dot"></i>
                </div>
                <div>
                  <h4 class="font-bold text-sm">Mapbox Kaarten API</h4>
                  <span class="text-xs opacity-60">Vector tiles & Style API</span>
                </div>
              </div>
              <span class="diag-status-badge text-xs px-2.5 py-1 rounded-full font-semibold bg-black/5 opacity-70">
                Wachten...
              </span>
            </div>
            <p class="diag-msg text-sm font-semibold opacity-90">Wordt gecontroleerd...</p>
            <div class="flex items-center justify-between text-xs opacity-60 pt-2 border-t" style="border-color: var(--theme-card-border);">
              <span class="diag-detail">API key permissies & tiles</span>
              <span class="diag-latency font-mono font-bold hidden">--ms</span>
            </div>
          </div>

          <!-- 6. Web Push Notificaties -->
          <div id="diag-card-web_push" class="theme-card border rounded-xl p-5 shadow-sm space-y-3" style="border-color: var(--theme-card-border);">
            <div class="flex items-start justify-between gap-3">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-black/5 flex items-center justify-center text-lg theme-primary">
                  <i class="fas fa-bell"></i>
                </div>
                <div>
                  <h4 class="font-bold text-sm">Web Push Notificaties (VAPID)</h4>
                  <span class="text-xs opacity-60">Realtime browser push</span>
                </div>
              </div>
              <span class="diag-status-badge text-xs px-2.5 py-1 rounded-full font-semibold bg-black/5 opacity-70">
                Wachten...
              </span>
            </div>
            <p class="diag-msg text-sm font-semibold opacity-90">Wordt gecontroleerd...</p>
            <div class="flex items-center justify-between text-xs opacity-60 pt-2 border-t" style="border-color: var(--theme-card-border);">
              <span class="diag-detail">VAPID sleutels & abonnees</span>
              <span class="diag-latency font-mono font-bold hidden"></span>
            </div>
          </div>

          <!-- 7. Schijfruimte & Rechten -->
          <div id="diag-card-disk_storage" class="theme-card border rounded-xl p-5 shadow-sm space-y-3" style="border-color: var(--theme-card-border);">
            <div class="flex items-start justify-between gap-3">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-black/5 flex items-center justify-center text-lg theme-primary">
                  <i class="fas fa-hard-drive"></i>
                </div>
                <div>
                  <h4 class="font-bold text-sm">Schijfruimte & Rechten</h4>
                  <span class="text-xs opacity-60">media/, DB/backups/</span>
                </div>
              </div>
              <span class="diag-status-badge text-xs px-2.5 py-1 rounded-full font-semibold bg-black/5 opacity-70">
                Wachten...
              </span>
            </div>
            <p class="diag-msg text-sm font-semibold opacity-90">Wordt gecontroleerd...</p>
            <div class="flex items-center justify-between text-xs opacity-60 pt-2 border-t" style="border-color: var(--theme-card-border);">
              <span class="diag-detail">Beschikbare GB's & permissies</span>
              <span class="diag-latency font-mono font-bold hidden"></span>
            </div>
          </div>

          <!-- 8. Jagersvloot & Kiosken -->
          <div id="diag-card-fleet_kiosks" class="theme-card border rounded-xl p-5 shadow-sm space-y-3" style="border-color: var(--theme-card-border);">
            <div class="flex items-start justify-between gap-3">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-black/5 flex items-center justify-center text-lg theme-primary">
                  <i class="fas fa-car-side"></i>
                </div>
                <div>
                  <h4 class="font-bold text-sm">Jagersvloot & Kiosken</h4>
                  <span class="text-xs opacity-60">Voertuigen, jagers & displays</span>
                </div>
              </div>
              <span class="diag-status-badge text-xs px-2.5 py-1 rounded-full font-semibold bg-black/5 opacity-70">
                Wachten...
              </span>
            </div>
            <p class="diag-msg text-sm font-semibold opacity-90">Wordt gecontroleerd...</p>
            <div class="flex items-center justify-between text-xs opacity-60 pt-2 border-t" style="border-color: var(--theme-card-border);">
              <span class="diag-detail">Geregistreerde auto's & rollen</span>
              <span class="diag-latency font-mono font-bold hidden"></span>
            </div>
          </div>

          <!-- 9. Tegenhunt Parameters & HQ -->
          <div id="diag-card-tegenhunt" class="theme-card border rounded-xl p-5 shadow-sm space-y-3" style="border-color: var(--theme-card-border);">
            <div class="flex items-start justify-between gap-3">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-black/5 flex items-center justify-center text-lg theme-primary">
                  <i class="fas fa-bullseye"></i>
                </div>
                <div>
                  <h4 class="font-bold text-sm">Tegenhunt Parameters & HQ</h4>
                  <span class="text-xs opacity-60">Basislocatie & 500m detectie</span>
                </div>
              </div>
              <span class="diag-status-badge text-xs px-2.5 py-1 rounded-full font-semibold bg-black/5 opacity-70">
                Wachten...
              </span>
            </div>
            <p class="diag-msg text-sm font-semibold opacity-90">Wordt gecontroleerd...</p>
            <div class="flex items-center justify-between text-xs opacity-60 pt-2 border-t" style="border-color: var(--theme-card-border);">
              <span class="diag-detail">GROUP_ID & HQ coördinaten</span>
              <span class="diag-latency font-mono font-bold hidden"></span>
            </div>
          </div>

          <!-- 10. Netwerk & Beveiliging -->
          <div id="diag-card-network_security" class="theme-card border rounded-xl p-5 shadow-sm space-y-3" style="border-color: var(--theme-card-border);">
            <div class="flex items-start justify-between gap-3">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-black/5 flex items-center justify-center text-lg theme-primary">
                  <i class="fas fa-shield-halved"></i>
                </div>
                <div>
                  <h4 class="font-bold text-sm">Netwerk & Beveiliging</h4>
                  <span class="text-xs opacity-60">HTTPS, Tunnel & IP forwarding</span>
                </div>
              </div>
              <span class="diag-status-badge text-xs px-2.5 py-1 rounded-full font-semibold bg-black/5 opacity-70">
                Wachten...
              </span>
            </div>
            <p class="diag-msg text-sm font-semibold opacity-90">Wordt gecontroleerd...</p>
            <div class="flex items-center justify-between text-xs opacity-60 pt-2 border-t" style="border-color: var(--theme-card-border);">
              <span class="diag-detail">TLS encryptie & echte client IP</span>
              <span class="diag-latency font-mono font-bold hidden"></span>
            </div>
          </div>

        </div>
      </div>

      <!-- ==================== TAB 2: CHECKLIST ==================== -->
      <div data-tab-panel="checklist" class="space-y-6 hidden">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div>
            <h3 class="text-lg font-bold">Pre-Flight Checklist</h3>
            <p class="text-xs opacity-60">Operationele voorbereidingen voor meldkamer, wagenpark en communicatie.</p>
          </div>
          <div class="flex items-center gap-3">
            <button type="button" onclick="openAddTaskModal()" class="theme-bg-primary hover:opacity-80 text-white text-xs font-semibold px-3 py-1.5 rounded-lg flex items-center gap-1.5 transition shadow-sm">
              <i class="fas fa-plus"></i> Taak Toevoegen
            </button>
            <button type="button" onclick="confirmResetChecklist()" class="bg-black/5 hover:bg-black/10 border text-xs font-semibold px-3 py-1.5 rounded-lg flex items-center gap-1.5 transition" style="border-color: var(--theme-card-border);">
              <i class="fas fa-rotate-left"></i> Resetten
            </button>
          </div>
        </div>

        <!-- Filter bar & Progress -->
        <div class="theme-card border rounded-xl p-4 shadow-sm space-y-4" style="border-color: var(--theme-card-border);">
          <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex flex-wrap items-center gap-2">
              <button type="button" data-checklist-filter="all" class="px-3 py-1.5 rounded-lg text-xs font-bold theme-bg-primary text-white active transition">
                Alle Taken
              </button>
              <button type="button" data-checklist-filter="dispatch" class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-black/5 hover:bg-black/10 opacity-70 transition">
                <i class="fas fa-headset mr-1"></i> HQ & Meldkamer
              </button>
              <button type="button" data-checklist-filter="fleet" class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-black/5 hover:bg-black/10 opacity-70 transition">
                <i class="fas fa-car mr-1"></i> Vloot & Jagers
              </button>
              <button type="button" data-checklist-filter="comms" class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-black/5 hover:bg-black/10 opacity-70 transition">
                <i class="fas fa-walkie-talkie mr-1"></i> Communicatie
              </button>
              <button type="button" data-checklist-filter="general" class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-black/5 hover:bg-black/10 opacity-70 transition">
                <i class="fas fa-clipboard mr-1"></i> Algemeen
              </button>
            </div>
            <div class="flex items-center gap-3">
              <span id="checklist-summary-badge" class="text-xs font-bold px-2.5 py-1 rounded-full bg-black/5 opacity-80">
                Laden...
              </span>
            </div>
          </div>
          <div class="w-full bg-black/10 dark:bg-white/10 rounded-full h-2 overflow-hidden">
            <div id="checklist-progress-bar" class="h-2 rounded-full theme-bg-primary transition-all duration-300" style="width: 0%"></div>
          </div>
        </div>

        <!-- Task list items container -->
        <div id="checklist-items-container" class="space-y-2.5">
          <div class="py-12 text-center opacity-60">
            <i class="fas fa-spinner fa-spin text-2xl mb-2 theme-primary"></i>
            <p class="text-sm">Taken laden...</p>
          </div>
        </div>
      </div>

      <!-- ==================== TAB 3: ARCHIVE & RESET ==================== -->
      <div data-tab-panel="archive" class="space-y-6 hidden">
        <!-- Safety Alert -->
        <div class="p-5 rounded-xl border bg-amber-500/10 border-amber-500/30 text-amber-900 dark:text-amber-200 shadow-sm space-y-2">
          <div class="flex items-center gap-2.5 text-base font-bold text-amber-700 dark:text-amber-300">
            <i class="fas fa-shield-cat text-lg"></i>
            <span>Seizoensarchivering & Schone Start voor het Nieuwe Jachtjaar</span>
          </div>
          <p class="text-sm leading-relaxed opacity-90">
            Voor de start van een nieuwe Jotihunt moeten operationele spelgegevens (hints, vossenlocaties, GPS-sporen, opdrachten en scoutinggroepen-catalogus) veilig worden gearchiveerd. De wizard maakt automatisch een compleet, op zichzelf staand <strong>Editie-Archief (.tar.gz)</strong> aan inclusief alle database-tabellen en ingezonden foto's. Dit archief kan later te allen tijde op een test- of analyseserver worden teruggezet.
          </p>
          <div class="pt-2 flex flex-wrap items-center gap-3 text-xs font-semibold">
            <span><i class="fas fa-check-circle text-emerald-600 mr-1"></i> Gebruikersaccounts blijven intact</span>
            <span><i class="fas fa-check-circle text-emerald-600 mr-1"></i> Wagenpark blijft behouden</span>
            <span><i class="fas fa-check-circle text-emerald-600 mr-1"></i> API-sleutels & instellingen blijven behouden</span>
            <span><i class="fas fa-check-circle text-emerald-600 mr-1"></i> Audit logs & checklists blijven behouden</span>
          </div>
        </div>

        <!-- Current Operational Data Table -->
        <div class="theme-card border rounded-xl p-6 shadow-sm space-y-4" style="border-color: var(--theme-card-border);">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b pb-4" style="border-color: var(--theme-card-border);">
            <div>
              <h3 class="text-lg font-bold">Huidige Operationele Gegevens</h3>
              <p class="text-xs opacity-60">Deze gegevens worden opgenomen in het editie-archief en daarna gereset voor de start.</p>
            </div>
            <div class="flex items-center gap-3">
              <span class="text-xs opacity-60">Totaal records:</span>
              <span id="total-operational-records" class="font-mono text-base font-extrabold theme-primary">--</span>
              <?php if ($isSuperAdmin): ?>
              <button type="button" onclick="openArchiveModal()" class="px-4 py-2 rounded-lg bg-red-600 hover:bg-red-700 text-white font-bold text-xs flex items-center gap-2 transition shadow-sm">
                <i class="fas fa-box-archive"></i> Archiveer Seizoen & Reset Data
              </button>
              <?php else: ?>
              <button type="button" disabled title="Alleen Superadmins (niveau 3) mogen een seizoensreset uitvoeren" class="px-4 py-2 rounded-lg bg-black/5 border opacity-50 font-bold text-xs flex items-center gap-2 cursor-not-allowed" style="border-color: var(--theme-card-border);">
                <i class="fas fa-lock"></i> Superadmin Vereist
              </button>
              <?php endif; ?>
            </div>
          </div>

          <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
              <thead>
                <tr class="border-b text-xs uppercase opacity-60 tracking-wider bg-black/5" style="border-color: var(--theme-card-border);">
                  <th class="py-2.5 px-4">Tabelnaam</th>
                  <th class="py-2.5 px-4">Omschrijving</th>
                  <th class="py-2.5 px-4 text-right">Rijen in Database</th>
                </tr>
              </thead>
              <tbody id="operational-table-body">
                <tr><td colspan="3" class="text-center py-6 opacity-60">Tabelgegevens laden...</td></tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Saved Archived Editions Registry -->
        <div class="theme-card border rounded-xl p-6 shadow-sm space-y-4" style="border-color: var(--theme-card-border);">
          <div class="border-b pb-4" style="border-color: var(--theme-card-border);">
            <h3 class="text-lg font-bold">Gearchiveerde Jotihunt Edities</h3>
            <p class="text-xs opacity-60">Volledige back-up snapshots van eerdere edities. Download een archief om het op een testomgeving te analyseren of te herbeleven.</p>
          </div>

          <div id="archived-editions-list" class="space-y-3">
            <!-- Dynamically populated -->
          </div>

          <div id="archived-editions-empty" class="hidden py-8 text-center opacity-60">
            <i class="fas fa-archive text-3xl mb-2 opacity-40"></i>
            <p class="text-sm">Nog geen gearchiveerde edities in het archiefregister.</p>
          </div>
        </div>
      </div>

    </div>

  </main>
</div>

<!-- ==================== MODAL: ADD CUSTOM TASK ==================== -->
<div id="modal-add-task" class="fixed inset-0 bg-black/70 z-50 flex items-center justify-center p-4 hidden">
  <div class="theme-card rounded-2xl border shadow-2xl max-w-lg w-full overflow-hidden animate-fadeIn" style="border-color: var(--theme-card-border);">
    <div class="theme-card-header px-6 py-4 border-b flex items-center justify-between text-white" style="background-color: var(--theme-sidebar-active); border-color: var(--theme-card-border);">
      <h4 class="text-lg font-bold flex items-center gap-2">
        <i class="fas fa-plus"></i>
        <span>Nieuwe Checklist Taak</span>
      </h4>
      <button type="button" onclick="closeAddTaskModal()" class="text-white/80 hover:text-white transition">
        <i class="fas fa-times text-lg"></i>
      </button>
    </div>

    <form id="add-task-form" onsubmit="submitAddTask(event)" class="p-6 space-y-4">
      <div>
        <label class="block text-xs font-semibold opacity-70 mb-1 uppercase tracking-wide">Taaktitel *</label>
        <input type="text" id="new-task-title" required placeholder="Bijv. Reservecamera's opladen voor opdrachten" 
               class="theme-input px-3.5 py-2.5 text-sm w-full outline-none">
      </div>

      <div>
        <label class="block text-xs font-semibold opacity-70 mb-1 uppercase tracking-wide">Categorie</label>
        <select id="new-task-category" class="theme-input px-3.5 py-2.5 text-sm w-full outline-none">
          <option value="dispatch">HQ & Meldkamer</option>
          <option value="fleet">Vloot & Jagers</option>
          <option value="comms">Communicatie & Radio</option>
          <option value="general" selected>Algemeen & Facilitair</option>
        </select>
      </div>

      <div>
        <label class="block text-xs font-semibold opacity-70 mb-1 uppercase tracking-wide">Toelichting (optioneel)</label>
        <textarea id="new-task-desc" rows="3" placeholder="Extra instructies of bijzonderheden voor de dienstdoende dispatcher..." 
                  class="theme-input px-3.5 py-2.5 text-sm w-full outline-none"></textarea>
      </div>

      <div class="flex items-center justify-end gap-3 pt-3 border-t" style="border-color: var(--theme-card-border);">
        <button type="button" onclick="closeAddTaskModal()" class="px-4 py-2 text-sm font-semibold rounded-lg border hover:bg-black/5 transition" style="border-color: var(--theme-card-border);">
          Annuleren
        </button>
        <button type="submit" id="btn-submit-add-task" class="theme-bg-primary hover:opacity-80 text-white font-bold px-5 py-2 text-sm rounded-lg shadow transition">
          Taak Opslaan
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ==================== MODAL: DELETE CUSTOM TASK ==================== -->
<div id="modal-delete-task" class="fixed inset-0 bg-black/70 z-50 flex items-center justify-center p-4 hidden">
  <div class="theme-card rounded-2xl border shadow-2xl max-w-md w-full overflow-hidden animate-fadeIn" style="border-color: var(--theme-card-border);">
    <div class="px-6 py-4 border-b text-white flex items-center justify-between bg-red-600">
      <h4 class="text-lg font-bold flex items-center gap-2">
        <i class="fas fa-trash-alt"></i>
        <span>Taak Verwijderen</span>
      </h4>
      <button type="button" onclick="closeDeleteTaskModal()" class="text-white/80 hover:text-white transition">
        <i class="fas fa-times"></i>
      </button>
    </div>
    <div class="p-6 space-y-4">
      <p class="text-sm">Weet je zeker dat je deze zelfgemaakte taak wilt verwijderen uit de checklist?</p>
      <div class="flex items-center justify-end gap-3 pt-3 border-t" style="border-color: var(--theme-card-border);">
        <button type="button" onclick="closeDeleteTaskModal()" class="px-4 py-2 text-sm font-semibold rounded-lg border hover:bg-black/5 transition" style="border-color: var(--theme-card-border);">
          Annuleren
        </button>
        <button type="button" onclick="executeDeleteTask()" class="bg-red-600 hover:bg-red-700 text-white font-bold px-5 py-2 text-sm rounded-lg shadow transition">
          Verwijderen
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ==================== MODAL: RESET CHECKLIST ==================== -->
<div id="modal-reset-checklist" class="fixed inset-0 bg-black/70 z-50 flex items-center justify-center p-4 hidden">
  <div class="theme-card rounded-2xl border shadow-2xl max-w-md w-full overflow-hidden animate-fadeIn" style="border-color: var(--theme-card-border);">
    <div class="px-6 py-4 border-b text-white flex items-center justify-between bg-amber-600">
      <h4 class="text-lg font-bold flex items-center gap-2">
        <i class="fas fa-rotate-left"></i>
        <span>Checklist Resetten</span>
      </h4>
      <button type="button" onclick="closeResetChecklistModal()" class="text-white/80 hover:text-white transition">
        <i class="fas fa-times"></i>
      </button>
    </div>
    <div class="p-6 space-y-4">
      <p class="text-sm">Alle afgevinkte taken worden weer op 'open' gezet voor een schone start. De taken zelf blijven behouden.</p>
      <div class="flex items-center justify-end gap-3 pt-3 border-t" style="border-color: var(--theme-card-border);">
        <button type="button" onclick="closeResetChecklistModal()" class="px-4 py-2 text-sm font-semibold rounded-lg border hover:bg-black/5 transition" style="border-color: var(--theme-card-border);">
          Annuleren
        </button>
        <button type="button" onclick="executeResetChecklist()" class="bg-amber-600 hover:bg-amber-700 text-white font-bold px-5 py-2 text-sm rounded-lg shadow transition">
          Reset Bevestigen
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ==================== MODAL: ARCHIVE & RESET SEASON ==================== -->
<div id="modal-archive-season" class="fixed inset-0 bg-black/70 z-50 flex items-center justify-center p-4 hidden">
  <div class="theme-card rounded-2xl border shadow-2xl max-w-lg w-full overflow-hidden animate-fadeIn" style="border-color: var(--theme-card-border);">
    <div class="px-6 py-4 border-b text-white flex items-center justify-between bg-red-600">
      <h4 class="text-lg font-bold flex items-center gap-2">
        <i class="fas fa-box-archive"></i>
        <span>Seizoensarchivering & Data-Reset</span>
      </h4>
      <button type="button" onclick="closeArchiveModal()" class="text-white/80 hover:text-white transition">
        <i class="fas fa-times"></i>
      </button>
    </div>

    <form onsubmit="executeArchiveAndReset(event)" class="p-6 space-y-4">
      <div class="p-3.5 rounded-xl bg-red-500/10 border border-red-500/30 text-red-700 dark:text-red-300 text-xs space-y-1.5">
        <div class="font-bold flex items-center gap-1.5">
          <i class="fas fa-triangle-exclamation"></i>
          <span>Let op: Deze actie reset alle operationele speldata!</span>
        </div>
        <p>Er wordt eerst een volledige stand-alone back-up (.tar.gz) gemaakt van de database en media. Daarna worden alle jacht- en vossenrecords op nul gezet.</p>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div class="sm:col-span-2">
          <label class="block text-xs font-semibold opacity-70 mb-1 uppercase tracking-wide">Editienaam *</label>
          <input type="text" id="archive-edition-name" required placeholder="Jotihunt 2025" 
                 class="theme-input px-3.5 py-2.5 text-sm w-full outline-none">
        </div>
        <div>
          <label class="block text-xs font-semibold opacity-70 mb-1 uppercase tracking-wide">Jaar *</label>
          <input type="number" id="archive-edition-year" required min="2000" max="2100" 
                 class="theme-input px-3.5 py-2.5 text-sm w-full outline-none">
        </div>
      </div>

      <div>
        <label class="block text-xs font-semibold text-red-600 dark:text-red-400 mb-1 uppercase tracking-wide">
          Typ "RESET" ter bevestiging *
        </label>
        <input type="text" id="archive-confirm-text" required placeholder="RESET" 
               class="theme-input px-3.5 py-2.5 text-sm w-full outline-none font-mono font-bold border-red-500/50">
      </div>

      <!-- Progress spinner during backup creation -->
      <div id="archive-progress-spinner" class="hidden py-3 text-center space-y-2 bg-black/5 rounded-xl border" style="border-color: var(--theme-card-border);">
        <i class="fas fa-spinner fa-spin text-2xl theme-primary"></i>
        <p class="text-xs font-semibold opacity-80">Back-up maken, database comprimeren en tabellen resetten... Een ogenblik geduld.</p>
      </div>

      <div class="flex items-center justify-end gap-3 pt-3 border-t" style="border-color: var(--theme-card-border);">
        <button type="button" onclick="closeArchiveModal()" class="px-4 py-2 text-sm font-semibold rounded-lg border hover:bg-black/5 transition" style="border-color: var(--theme-card-border);">
          Annuleren
        </button>
        <button type="submit" id="btn-execute-archive" class="bg-red-600 hover:bg-red-700 text-white font-bold px-5 py-2 text-sm rounded-lg shadow transition flex items-center gap-2">
          <i class="fas fa-box-archive"></i>
          <span>Archiveren & Resetten</span>
        </button>
      </div>
    </form>
  </div>
</div>

<script src="../js/admin_readiness.js"></script>
</body>
</html>
