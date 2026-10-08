<?php
// admin/database.php
// Full database explorer for Superadmins: visual query builder, manual SQL runner, inline cell/row editing, and record creation.
define("PAGE_NAME", "a_database");
require_once(__DIR__ . '/../includes/auth.php');

if ($privilege < 3) {
    header("Location: ../home");
    exit();
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<title>Jotify - Database Explorer</title>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="shortcut icon" type="image/png" href="../media/geusje.png"/>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://kit.fontawesome.com/870ab34ea3.js" crossorigin="anonymous"></script>
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<?php include_once('../includes/theme.php'); ?>
<style>
    /* Custom styles for Database Explorer */
    .sql-code-box {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    }
    .grid-cell-editable {
        cursor: cell;
        transition: background-color 0.15s ease;
    }
    .grid-cell-editable:hover {
        outline: 1px dashed rgba(59, 130, 246, 0.5);
        outline-offset: -2px;
    }
    .sticky-action-col {
        position: sticky;
        left: 0;
        z-index: 5;
    }
    thead .sticky-action-col {
        z-index: 20;
    }
    /* Toast slide-in */
    @keyframes toastSlideIn {
        from { transform: translateY(1rem); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }
    .toast-animate {
        animation: toastSlideIn 0.2s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }
</style>
</head>
<body class="flex h-screen overflow-hidden">

<!-- Sidebar -->
<?php include_once('../includes/sidebar.php') ?>

<!-- Main Content -->
<div class="flex-1 flex flex-col h-screen overflow-y-auto w-full relative">
  <!-- Topbar -->
  <?php include_once('../includes/topbar.php') ?>

  <main class="p-4 md:p-6 max-w-[1600px] mx-auto w-full flex-1 flex flex-col gap-6 mb-24">

    <!-- Page Title & Mode Switcher -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold flex items-center gap-2.5">
          <i class="fas fa-database text-blue-500"></i>
          <span>Database Explorer</span>
        </h1>
        <p class="text-sm opacity-70 mt-1">
          Inspecteer tabellen, stel dynamische filters samen of voer handmatige SQL-query's uit.
        </p>
      </div>

      <div class="flex flex-wrap items-center gap-3">
        <!-- Mode Toggle Segmented Control -->
        <div class="inline-flex rounded-xl p-1 bg-black/5 border" style="border-color: var(--theme-card-border);">
          <button type="button" id="btn-mode-builder" onclick="switchMode('builder')" class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5 theme-bg-primary text-white shadow-sm">
            <i class="fas fa-filter"></i>
            <span>Visuele Bouwer</span>
          </button>
          <button type="button" id="btn-mode-manual" onclick="switchMode('manual')" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold opacity-70 hover:opacity-100 transition flex items-center gap-1.5">
            <i class="fas fa-terminal"></i>
            <span>Handmatige SQL</span>
          </button>
        </div>

        <!-- Action: New Row Button (Visible when table selected) -->
        <button type="button" id="btn-open-insert-modal" onclick="openInsertModal()" class="hidden bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-4 py-2 text-xs rounded-xl shadow transition flex items-center gap-2">
          <i class="fas fa-plus"></i>
          <span>Nieuwe Rij</span>
        </button>

        <!-- Action: Execute Query Button -->
        <button type="button" id="btn-run-query" onclick="executeQuery()" class="theme-bg-primary hover:opacity-90 text-white font-bold px-5 py-2 text-xs rounded-xl shadow transition flex items-center gap-2">
          <i class="fas fa-play"></i>
          <span>Query Uitvoeren</span>
        </button>
      </div>
    </div>

    <!-- Panel 1: Visual Query Builder -->
    <div id="panel-builder" class="theme-card rounded-xl border shadow-sm overflow-hidden p-5 space-y-5" style="border-color: var(--theme-card-border);">
      
      <!-- Top Row: Table Selector, Sort Column, Sort Direction, Limit -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        
        <!-- Table Selector -->
        <div>
          <label for="builder-table" class="block text-xs font-bold uppercase tracking-wider mb-1.5 opacity-80 flex items-center gap-1.5">
            <i class="fas fa-table opacity-70"></i> Tabel
          </label>
          <select id="builder-table" onchange="onTableSelected()" class="w-full theme-input px-3.5 py-2 text-sm font-mono cursor-pointer">
            <option value="" disabled selected>Selecteer een tabel...</option>
          </select>
        </div>

        <!-- Sort Column -->
        <div>
          <label for="builder-sort-col" class="block text-xs font-bold uppercase tracking-wider mb-1.5 opacity-80 flex items-center gap-1.5">
            <i class="fas fa-arrow-down-a-z opacity-70"></i> Sorteren op kolom
          </label>
          <select id="builder-sort-col" onchange="updateQueryPreview()" class="w-full theme-input px-3.5 py-2 text-sm cursor-pointer">
            <option value="">(Geen sortering)</option>
          </select>
        </div>

        <!-- Sort Direction -->
        <div>
          <label for="builder-sort-dir" class="block text-xs font-bold uppercase tracking-wider mb-1.5 opacity-80 flex items-center gap-1.5">
            <i class="fas fa-arrow-down-short-wide opacity-70"></i> Volgorde
          </label>
          <select id="builder-sort-dir" onchange="updateQueryPreview()" class="w-full theme-input px-3.5 py-2 text-sm cursor-pointer">
            <option value="ASC">Oplopend (ASC)</option>
            <option value="DESC">Aflopend (DESC)</option>
          </select>
        </div>

        <!-- Limit / Top X -->
        <div>
          <label for="builder-limit" class="block text-xs font-bold uppercase tracking-wider mb-1.5 opacity-80 flex items-center gap-1.5">
            <i class="fas fa-list-ol opacity-70"></i> Limiet (Top X)
          </label>
          <div class="flex items-center gap-2">
            <input type="number" id="builder-limit" min="1" max="5000" value="100" oninput="updateQueryPreview()" class="w-full theme-input px-3.5 py-2 text-sm">
            <div class="flex gap-1">
              <button type="button" onclick="setLimit(50)" class="px-2 py-1.5 text-xs rounded-lg border hover:bg-black/5 opacity-80" style="border-color: var(--theme-card-border);">50</button>
              <button type="button" onclick="setLimit(100)" class="px-2 py-1.5 text-xs rounded-lg border hover:bg-black/5 opacity-80" style="border-color: var(--theme-card-border);">100</button>
              <button type="button" onclick="setLimit(500)" class="px-2 py-1.5 text-xs rounded-lg border hover:bg-black/5 opacity-80" style="border-color: var(--theme-card-border);">500</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Filters Section -->
      <div class="pt-2 border-t" style="border-color: var(--theme-card-border);">
        <div class="flex items-center justify-between mb-3">
          <div class="flex items-center gap-2">
            <h3 class="text-xs font-bold uppercase tracking-wider opacity-80 flex items-center gap-1.5">
              <i class="fas fa-filter opacity-70"></i> Filters (AND-combinaties)
            </h3>
            <span id="filters-count-badge" class="px-2 py-0.5 rounded-full text-xs bg-black/10 font-bold">0</span>
          </div>

          <button type="button" id="btn-add-filter" onclick="addFilterRow()" class="px-3 py-1.5 text-xs font-bold rounded-lg border hover:bg-black/5 transition flex items-center gap-1.5" style="border-color: var(--theme-card-border);">
            <i class="fas fa-plus text-blue-500"></i>
            <span>Filter Toevoegen</span>
          </button>
        </div>

        <!-- Filter Rows Container -->
        <div id="builder-filters-container" class="space-y-2">
          <!-- Dynamic Filter Rows appended here -->
          <div id="empty-filters-placeholder" class="p-4 rounded-xl border border-dashed text-center opacity-60 text-xs" style="border-color: var(--theme-card-border);">
            Geen filters actief. Klik op <strong>+ Filter Toevoegen</strong> om rijen te filteren op kolomwaarden.
          </div>
        </div>
      </div>

      <!-- Query Preview & Switch to Manual -->
      <div class="pt-2 border-t" style="border-color: var(--theme-card-border);">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-bold uppercase tracking-wider opacity-60">Gegenereerde SQL</span>
          <div class="flex items-center gap-2">
            <button type="button" onclick="copySqlPreview()" class="text-xs opacity-70 hover:opacity-100 transition flex items-center gap-1">
              <i class="fas fa-copy"></i>
              <span>Kopiëren</span>
            </button>
            <span class="opacity-40">•</span>
            <button type="button" onclick="transferToManualMode()" class="text-xs font-semibold theme-primary hover:underline flex items-center gap-1">
              <i class="fas fa-code-branch"></i>
              <span>Bewerken in Handmatige Modus</span>
            </button>
          </div>
        </div>
        <pre id="builder-sql-preview" class="sql-code-box p-3 rounded-lg text-xs overflow-x-auto bg-black/5 border text-blue-600 dark:text-blue-400 font-mono" style="border-color: var(--theme-card-border);">SELECT * FROM `...` LIMIT 100;</pre>
      </div>
    </div>

    <!-- Panel 2: Manual SQL Editor (Hidden by default) -->
    <div id="panel-manual" class="hidden theme-card rounded-xl border shadow-sm overflow-hidden p-5 space-y-4" style="border-color: var(--theme-card-border);">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
        <div class="flex items-center gap-3">
          <h2 class="text-sm font-bold flex items-center gap-2">
            <i class="fas fa-terminal opacity-70"></i> Handmatige SQL Opdracht
          </h2>
          <!-- Query Status Badge -->
          <span id="manual-query-type-badge" class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30 flex items-center gap-1.5">
            <i class="fas fa-circle-check"></i>
            <span>Alleen Lezen (SELECT)</span>
          </span>
        </div>

        <div class="text-xs opacity-60 flex items-center gap-1.5">
          <kbd class="px-1.5 py-0.5 rounded bg-black/10 font-mono text-[10px]">Ctrl</kbd> + 
          <kbd class="px-1.5 py-0.5 rounded bg-black/10 font-mono text-[10px]">Enter</kbd> 
          <span>om uit te voeren</span>
        </div>
      </div>

      <!-- SQL Textarea -->
      <textarea id="manual-sql-input" rows="6" oninput="onManualSqlInput()" placeholder="Typ hier je SQL-opdracht, bijvoorbeeld:&#10;SELECT * FROM Gebruikers WHERE priv = 3 LIMIT 50;&#10;UPDATE Site_Instellingen SET Waarde = '1' WHERE Instelling = 'Onderhoud';" class="w-full theme-input sql-code-box p-3 text-sm rounded-xl outline-none resize-y"></textarea>

      <!-- Action buttons for manual mode -->
      <div class="flex flex-wrap items-center justify-between gap-3 pt-2">
        <div class="flex items-center gap-2">
          <button type="button" id="btn-manual-dry-run" onclick="executeDryRun()" class="px-3.5 py-2 text-xs font-bold rounded-xl border border-amber-500/40 text-amber-600 dark:text-amber-400 hover:bg-amber-500/10 transition flex items-center gap-1.5">
            <i class="fas fa-flask"></i>
            <span>Dry-run Testen</span>
          </button>
          <button type="button" onclick="resetManualToBuilder()" class="px-3.5 py-2 text-xs font-semibold rounded-xl border hover:bg-black/5 transition flex items-center gap-1.5 opacity-80" style="border-color: var(--theme-card-border);">
            <i class="fas fa-rotate-left"></i>
            <span>Herstel naar Bouwer Query</span>
          </button>
        </div>

        <div class="flex items-center gap-2">
          <span id="manual-dry-run-status" class="text-xs opacity-70 italic hidden"></span>
          <button type="button" onclick="executeQuery()" class="theme-bg-primary hover:opacity-90 text-white font-bold px-5 py-2 text-xs rounded-xl shadow transition flex items-center gap-2">
            <i class="fas fa-play"></i>
            <span>Uitvoeren</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Status & Results Bar -->
    <div id="results-status-bar" class="theme-card rounded-xl border shadow-sm px-4 py-3 flex flex-col md:flex-row md:items-center justify-between gap-3" style="border-color: var(--theme-card-border);">
      <!-- Left side: Stats -->
      <div class="flex flex-wrap items-center gap-3 text-xs">
        <span class="flex items-center gap-1.5 font-bold">
          <i class="fas fa-table-cells opacity-70"></i>
          <span id="stat-row-count" class="font-mono">0</span> rijen
        </span>
        <span class="opacity-40">•</span>
        <span class="flex items-center gap-1.5 opacity-80">
          <i class="fas fa-stopwatch opacity-70"></i>
          <span id="stat-exec-time" class="font-mono">0</span> ms
        </span>
        <span class="opacity-40">•</span>
        <span id="stat-pk-badge" class="px-2 py-0.5 rounded font-mono bg-black/5 border text-[11px]" style="border-color: var(--theme-card-border);">
          <i class="fas fa-key opacity-60 mr-1"></i>PK: -
        </span>
        <span id="stat-editable-badge" class="px-2 py-0.5 rounded font-bold text-[11px] bg-black/10">
          Alleen Lezen
        </span>
      </div>

      <!-- Right side: Quick In-Memory Filter & CSV Export -->
      <div class="flex items-center gap-2">
        <div class="relative">
          <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-xs opacity-50"></i>
          <input type="text" id="filter-table-input" oninput="onFilterTableInput(this.value)" placeholder="Zoek in tabel..." class="theme-input pl-7 pr-3 py-1.5 text-xs rounded-lg w-40 sm:w-56">
        </div>
        <button type="button" id="btn-export-csv" onclick="exportToCsv()" class="px-3 py-1.5 text-xs font-semibold rounded-lg border hover:bg-black/5 transition flex items-center gap-1.5 whitespace-nowrap" style="border-color: var(--theme-card-border);">
          <i class="fas fa-file-csv text-emerald-500"></i>
          <span>CSV Export</span>
        </button>
      </div>
    </div>

    <!-- Data Grid Container -->
    <div class="theme-card rounded-xl border shadow-sm overflow-hidden flex flex-col" style="border-color: var(--theme-card-border);">
      
      <!-- Loading State -->
      <div id="grid-loading" class="hidden p-12 text-center space-y-3">
        <i class="fas fa-circle-notch fa-spin text-3xl theme-primary"></i>
        <p class="text-sm font-semibold opacity-70">Gegevens ophalen uit de database...</p>
      </div>

      <!-- Manual Write Result Card (Shown after manual UPDATE/DELETE/INSERT) -->
      <div id="grid-write-result" class="hidden p-8 text-center space-y-3">
        <div class="w-12 h-12 rounded-full bg-emerald-500/20 text-emerald-500 flex items-center justify-center mx-auto text-xl">
          <i class="fas fa-check"></i>
        </div>
        <h3 class="text-lg font-bold" id="grid-write-title">Schrijfopdracht Voltooid</h3>
        <p class="text-sm opacity-80" id="grid-write-details">Er zijn 0 rijen beïnvloed.</p>
        <div class="pt-2">
          <button type="button" onclick="switchMode('builder')" class="px-4 py-2 text-xs font-bold rounded-xl theme-bg-primary text-white shadow hover:opacity-90 transition">
            Terug naar Visuele Bouwer
          </button>
        </div>
      </div>

      <!-- Empty State -->
      <div id="grid-empty" class="p-12 text-center opacity-60 text-sm space-y-2">
        <i class="fas fa-database text-3xl opacity-40"></i>
        <p id="grid-empty-text">Selecteer een tabel en klik op "Query Uitvoeren" om de inhoud te bekijken.</p>
      </div>

      <!-- Scrollable Data Grid Wrapper -->
      <div id="grid-table-wrapper" class="hidden overflow-x-auto max-h-[600px] w-full">
        <table id="grid-table" class="w-full text-xs text-left whitespace-nowrap border-collapse">
          <thead id="grid-thead" class="sticky top-0 bg-black/10 dark:bg-black/40 backdrop-blur z-10 border-b" style="border-color: var(--theme-card-border);">
            <!-- Headers generated dynamically -->
          </thead>
          <tbody id="grid-tbody" class="divide-y divide-black/5 dark:divide-white/5">
            <!-- Rows generated dynamically -->
          </tbody>
        </table>
      </div>

      <!-- Footer Info in Data Grid -->
      <div id="grid-footer" class="hidden px-4 py-2.5 border-t text-[11px] opacity-60 flex items-center justify-between" style="border-color: var(--theme-card-border);">
        <span id="grid-footer-info">0 rijen getoond</span>
        <span class="italic">Dubbelklik op een cel om direct te bewerken</span>
      </div>
    </div>

  </main>

  <!-- Modal: Write Confirmation / Dry-run -->
  <div id="modal-confirm-write" class="fixed inset-0 bg-black/70 z-50 hidden flex items-center justify-center p-4">
    <div class="theme-card rounded-2xl border shadow-2xl w-full max-w-lg overflow-hidden animate-fadeIn" style="border-color: var(--theme-card-border);">
      <div class="px-6 py-4 border-b text-white flex items-center justify-between bg-amber-600">
        <h3 class="text-lg font-bold flex items-center gap-2">
          <i class="fas fa-triangle-exclamation"></i>
          <span>Schrijfopdracht Bevestigen</span>
        </h3>
        <button type="button" onclick="closeConfirmWriteModal()" class="text-white/80 hover:text-white"><i class="fas fa-times"></i></button>
      </div>
      <div class="p-6 space-y-4">
        <p class="text-sm">
          Je staat op het punt een schrijfopdracht uit te voeren die gegevens in de live database wijzigt of verwijdert:
        </p>

        <!-- Query Code Box -->
        <pre id="confirm-write-query" class="sql-code-box p-3 rounded-lg text-xs overflow-x-auto bg-black/10 border font-mono break-all whitespace-pre-wrap max-h-36" style="border-color: var(--theme-card-border);"></pre>

        <!-- Dry-run Impact Notice -->
        <div class="p-3.5 rounded-lg border bg-black/5 text-xs space-y-1.5" style="border-color: var(--theme-card-border);">
          <div class="flex items-center gap-2 text-amber-500 font-semibold">
            <i class="fas fa-info-circle"></i>
            <span>Verwachte impact (dry-run):</span>
          </div>
          <p id="confirm-write-impact" class="opacity-90">Deze opdracht beïnvloedt vermoedelijk <strong>0</strong> rij(en).</p>
        </div>

        <!-- Checkbox confirmation -->
        <div class="flex items-start gap-3 pt-2">
          <input type="checkbox" id="check-confirm-write" onchange="toggleConfirmWriteButton(this.checked)" class="mt-0.5 w-4 h-4 rounded text-blue-600 focus:ring-blue-500 cursor-pointer">
          <label for="check-confirm-write" class="text-xs cursor-pointer select-none leading-relaxed">
            <strong>Ik begrijp dat deze wijziging direct en definitief wordt toegepast op de actieve database.</strong>
          </label>
        </div>

        <div class="pt-4 border-t flex items-center justify-end gap-3" style="border-color: var(--theme-card-border);">
          <button type="button" onclick="closeConfirmWriteModal()" class="px-4 py-2 text-xs font-semibold rounded-lg border hover:bg-black/5 transition" style="border-color: var(--theme-card-border);">
            Annuleren
          </button>
          <button type="button" id="btn-execute-confirmed-write" onclick="submitConfirmedWrite()" disabled class="bg-amber-600 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-amber-700 text-white font-bold px-5 py-2 text-xs rounded-lg shadow transition flex items-center gap-2">
            <i class="fas fa-play"></i>
            <span>Definitief Uitvoeren</span>
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal: Delete Row -->
  <div id="modal-delete-row" class="fixed inset-0 bg-black/70 z-50 hidden flex items-center justify-center p-4">
    <div class="theme-card rounded-2xl border shadow-2xl w-full max-w-md overflow-hidden animate-fadeIn" style="border-color: var(--theme-card-border);">
      <div class="px-6 py-4 border-b text-white flex items-center justify-between bg-red-600">
        <h3 class="text-lg font-bold flex items-center gap-2">
          <i class="fas fa-trash-alt"></i>
          <span>Rij Verwijderen</span>
        </h3>
        <button type="button" onclick="closeDeleteRowModal()" class="text-white/80 hover:text-white"><i class="fas fa-times"></i></button>
      </div>
      <div class="p-6 space-y-4">
        <p class="text-sm">
          Weet je zeker dat je deze rij uit tabel <strong id="delete-row-table-name" class="font-mono theme-primary"></strong> wilt verwijderen?
        </p>

        <div class="p-3 rounded-lg border bg-black/5 text-xs font-mono space-y-1" style="border-color: var(--theme-card-border);">
          <span class="text-[10px] uppercase font-bold opacity-60 block">Primaire Sleutel:</span>
          <div id="delete-row-pk-display" class="break-all font-bold"></div>
        </div>

        <p class="text-xs text-red-500 font-semibold">
          <i class="fas fa-exclamation-triangle mr-1"></i> Deze actie kan niet ongedaan worden gemaakt.
        </p>

        <div class="pt-3 border-t flex items-center justify-end gap-3" style="border-color: var(--theme-card-border);">
          <button type="button" onclick="closeDeleteRowModal()" class="px-4 py-2 text-xs font-semibold rounded-lg border hover:bg-black/5 transition" style="border-color: var(--theme-card-border);">
            Annuleren
          </button>
          <button type="button" id="btn-confirm-delete-row" onclick="submitDeleteRow()" class="bg-red-600 hover:bg-red-700 text-white font-bold px-5 py-2 text-xs rounded-lg shadow transition flex items-center gap-2">
            <i class="fas fa-trash-alt"></i>
            <span>Verwijderen</span>
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal: Edit Row -->
  <div id="modal-edit-row" class="fixed inset-0 bg-black/70 z-50 hidden flex items-center justify-center p-4">
    <div class="theme-card rounded-2xl border shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col overflow-hidden animate-fadeIn" style="border-color: var(--theme-card-border);">
      <div class="px-6 py-4 border-b text-white flex items-center justify-between theme-bg-primary">
        <h3 class="text-lg font-bold flex items-center gap-2">
          <i class="fas fa-pencil-alt"></i>
          <span>Rij Bewerken in <span id="edit-row-table-title" class="font-mono"></span></span>
        </h3>
        <button type="button" onclick="closeEditRowModal()" class="text-white/80 hover:text-white"><i class="fas fa-times"></i></button>
      </div>

      <div class="p-6 overflow-y-auto flex-1 space-y-4" id="edit-row-fields-container">
        <!-- Dynamic input fields generated here -->
      </div>

      <div class="px-6 py-4 border-t flex items-center justify-end gap-3 bg-black/5" style="border-color: var(--theme-card-border);">
        <button type="button" onclick="closeEditRowModal()" class="px-4 py-2 text-xs font-semibold rounded-lg border hover:bg-black/5 transition" style="border-color: var(--theme-card-border);">
          Annuleren
        </button>
        <button type="button" id="btn-submit-edit-row" onclick="submitEditRow()" class="theme-bg-primary hover:opacity-90 text-white font-bold px-5 py-2 text-xs rounded-lg shadow transition flex items-center gap-2">
          <i class="fas fa-save"></i>
          <span>Wijzigingen Opslaan</span>
        </button>
      </div>
    </div>
  </div>

  <!-- Modal: Insert Row -->
  <div id="modal-insert-row" class="fixed inset-0 bg-black/70 z-50 hidden flex items-center justify-center p-4">
    <div class="theme-card rounded-2xl border shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col overflow-hidden animate-fadeIn" style="border-color: var(--theme-card-border);">
      <div class="px-6 py-4 border-b text-white flex items-center justify-between bg-emerald-600">
        <h3 class="text-lg font-bold flex items-center gap-2">
          <i class="fas fa-plus"></i>
          <span>Nieuwe Rij Invoegen in <span id="insert-row-table-title" class="font-mono"></span></span>
        </h3>
        <button type="button" onclick="closeInsertRowModal()" class="text-white/80 hover:text-white"><i class="fas fa-times"></i></button>
      </div>

      <div class="p-6 overflow-y-auto flex-1 space-y-4" id="insert-row-fields-container">
        <!-- Dynamic input fields generated here -->
      </div>

      <div class="px-6 py-4 border-t flex items-center justify-end gap-3 bg-black/5" style="border-color: var(--theme-card-border);">
        <button type="button" onclick="closeInsertRowModal()" class="px-4 py-2 text-xs font-semibold rounded-lg border hover:bg-black/5 transition" style="border-color: var(--theme-card-border);">
          Annuleren
        </button>
        <button type="button" id="btn-submit-insert-row" onclick="submitInsertRow()" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-5 py-2 text-xs rounded-lg shadow transition flex items-center gap-2">
          <i class="fas fa-plus"></i>
          <span>Rij Toevoegen</span>
        </button>
      </div>
    </div>
  </div>

  <!-- Floating Toast Notifications Container -->
  <div id="toast-container" class="fixed bottom-5 right-5 z-50 flex flex-col gap-2 max-w-sm pointer-events-none"></div>

  <?php require_once('../includes/footer.php') ?>
</div>

<script src="../js/admin_database.js"></script>
<script src="../js/gps.js"></script>
<script>initGpsTracking('<?php echo $_SESSION['gps'] ?? 'false'; ?>');</script>

</body>
</html>