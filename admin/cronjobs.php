<?php
// admin/cronjobs.php
// Displays master cron runner heartbeat, cron statuses, real-time countdowns, and logs modal.
define("PAGE_NAME", "a_cronjobs");

require_once(__DIR__ . '/../includes/auth.php');
if ($privilege < 2) {
    header("Location: ../home");
    exit();
}

// Fetch initial master cron runner heartbeat from Site_Instellingen
$stmt_mb = $conn->prepare("SELECT Instelling, Waarde FROM Site_Instellingen WHERE Instelling IN ('CRON_MASTER_LAST_RUN', 'CRON_MASTER_INFO')");
$stmt_mb->execute();
$res_mb = $stmt_mb->get_result();
$mb_settings = [];
while ($r = $res_mb->fetch_assoc()) {
    $mb_settings[$r['Instelling']] = $r['Waarde'];
}
$stmt_mb->close();

$master_last_run = $mb_settings['CRON_MASTER_LAST_RUN'] ?? null;
$master_raw_info = $mb_settings['CRON_MASTER_INFO'] ?? null;
$master_info = $master_raw_info ? json_decode($master_raw_info, true) : null;
$master_seconds_ago = $master_last_run ? (time() - strtotime($master_last_run)) : null;

if ($master_last_run) {
    if ($master_seconds_ago <= 90) {
        $master_badge_class = 'bg-emerald-500/15 border-emerald-500/40 text-emerald-600 dark:text-emerald-400';
        $master_badge_text = 'Actief (Gezond)';
        $master_badge_icon = 'fa-circle-check';
    } elseif ($master_seconds_ago <= 300) {
        $master_badge_class = 'bg-amber-500/15 border-amber-500/40 text-amber-600 dark:text-amber-400';
        $master_badge_text = 'Vertraagd (Let op)';
        $master_badge_icon = 'fa-triangle-exclamation';
    } else {
        $master_badge_class = 'bg-red-500/15 border-red-500/40 text-red-600 dark:text-red-400';
        $master_badge_text = 'Inactief / Gestopt';
        $master_badge_icon = 'fa-circle-xmark';
    }
} else {
    $master_badge_class = 'bg-red-500/15 border-red-500/40 text-red-600 dark:text-red-400';
    $master_badge_text = 'Nooit uitgevoerd';
    $master_badge_icon = 'fa-circle-xmark';
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<title>Jotify - Cronjobs</title>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="shortcut icon" type="image/png" href="../media/geusje.png"/>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://kit.fontawesome.com/870ab34ea3.js" crossorigin="anonymous"></script>
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<?php include_once('../includes/theme.php'); ?>
<style>
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
<div class="flex-1 flex flex-col h-screen overflow-y-auto w-full relative" style="background-color: var(--theme-bg); color: var(--theme-text);">
  <!-- Topbar -->
  <?php include_once('../includes/topbar.php') ?>

  <main class="p-4 md:p-6 max-w-[1400px] mx-auto w-full flex-1">

    <div class="space-y-6 mb-24">

      <!-- Hero Card: Master Cron Checker (cron/index.php) -->
      <div id="master-cron-checker" class="theme-card rounded-xl border shadow-sm overflow-hidden w-full mb-6" style="border-color: var(--theme-card-border);">
        <div class="px-6 py-4 border-b flex flex-col sm:flex-row sm:items-center justify-between gap-3" style="background-color: var(--theme-card-bg); border-color: var(--theme-card-border); color: var(--theme-text);">
          <div>
            <h3 class="text-xl font-bold flex items-center gap-2.5">
              <i class="fas fa-heartbeat text-red-500"></i>
              <span>Master Cron Checker</span>
            </h3>
            <p class="text-xs opacity-75 mt-0.5">
              Controleert of de centrale cron runner (<code class="font-mono bg-black/10 dark:bg-white/10 px-1.5 py-0.5 rounded">cron/index.php</code>) periodiek wordt aangeroepen door het besturingssysteem.
            </p>
          </div>

          <div class="flex items-center gap-2">
            <button type="button" id="btn-run-master-cron" onclick="triggerMasterCron()" class="theme-bg-primary hover:opacity-90 text-white text-xs font-bold px-3.5 py-1.5 rounded-lg transition flex items-center gap-1.5 shadow-sm">
              <i id="icon-run-master" class="fas fa-play text-[10px]"></i>
              <span>Nu Uitvoeren</span>
            </button>
            <button type="button" onclick="CronRefresh()" class="p-1.5 rounded-lg border hover:bg-black/5 dark:hover:bg-white/5 transition flex items-center justify-center text-xs font-semibold" style="border-color: var(--theme-card-border); color: var(--theme-text);" title="Verversen">
              <i class="fas fa-rotate"></i>
            </button>
          </div>
        </div>

        <div class="p-6">
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            
            <!-- Status Badge -->
            <div class="p-4 rounded-xl border bg-black/5 dark:bg-white/5 flex flex-col justify-between gap-2" style="border-color: var(--theme-card-border);">
              <span class="text-xs uppercase tracking-wider opacity-60 font-semibold flex items-center gap-1.5">
                <i class="fas fa-signal opacity-70"></i> Status Runner
              </span>
              <div class="flex items-center gap-2">
                <span id="master-cron-badge" class="px-2.5 py-1 rounded-full text-xs font-bold border flex items-center gap-1.5 <?= $master_badge_class ?>">
                  <i id="master-cron-badge-icon" class="fas <?= $master_badge_icon ?>"></i>
                  <span id="master-cron-badge-text"><?= $master_badge_text ?></span>
                </span>
              </div>
              <span id="master-cron-relative" class="text-xs opacity-70 font-mono">
                <?= $master_last_run ? ($master_seconds_ago !== null ? ($master_seconds_ago . 's geleden') : '') : 'Geen data' ?>
              </span>
            </div>

            <!-- Last Execution Time -->
            <div class="p-4 rounded-xl border bg-black/5 dark:bg-white/5 flex flex-col justify-between gap-2" style="border-color: var(--theme-card-border);">
              <span class="text-xs uppercase tracking-wider opacity-60 font-semibold flex items-center gap-1.5">
                <i class="fas fa-clock opacity-70"></i> Laatste Uitvoering
              </span>
              <span id="master-cron-timestamp" class="text-base font-bold font-mono theme-primary">
                <?= $master_last_run ? date('d-m-Y H:i:s', strtotime($master_last_run)) : 'Nooit' ?>
              </span>
              <span class="text-xs opacity-60">Systeemklok (Amsterdam)</span>
            </div>

            <!-- Duration & SAPI -->
            <div class="p-4 rounded-xl border bg-black/5 dark:bg-white/5 flex flex-col justify-between gap-2" style="border-color: var(--theme-card-border);">
              <span class="text-xs uppercase tracking-wider opacity-60 font-semibold flex items-center gap-1.5">
                <i class="fas fa-stopwatch opacity-70"></i> Looptijd & SAPI
              </span>
              <span id="master-cron-duration" class="text-base font-bold font-mono">
                <?= isset($master_info['duration_ms']) ? ($master_info['duration_ms'] . ' ms') : '—' ?>
                <span id="master-cron-sapi" class="text-xs opacity-60 font-normal"><?= isset($master_info['sapi']) ? ('(' . strtoupper($master_info['sapi']) . ')') : '' ?></span>
              </span>
              <span class="text-xs opacity-60">Executie overhead</span>
            </div>

            <!-- Dispatched Tasks in Last Run -->
            <div class="p-4 rounded-xl border bg-black/5 dark:bg-white/5 flex flex-col justify-between gap-2" style="border-color: var(--theme-card-border);">
              <span class="text-xs uppercase tracking-wider opacity-60 font-semibold flex items-center gap-1.5">
                <i class="fas fa-layer-group opacity-70"></i> Taken Aangeroepen
              </span>
              <span id="master-cron-tasks" class="text-base font-bold font-mono">
                <?= isset($master_info['tasks_dispatched']) ? ($master_info['tasks_dispatched'] . ' taken') : '—' ?>
              </span>
              <span class="text-xs opacity-60">Tijdens laatste cyclus</span>
            </div>
          </div>

          <!-- Collapsible Crontab Server Helper -->
          <div class="mt-4 pt-4 border-t flex flex-col gap-2" style="border-color: var(--theme-card-border);">
            <button type="button" onclick="document.getElementById('crontab-help-box').classList.toggle('hidden')" class="text-xs font-semibold px-3 py-1.5 rounded-lg border hover:bg-black/5 dark:hover:bg-white/5 transition flex items-center gap-2 self-start" style="border-color: var(--theme-card-border); color: var(--theme-text);">
              <i class="fas fa-terminal opacity-70"></i>
              <span>Crontab serverconfiguratie instructies bekijken</span>
              <i class="fas fa-chevron-down text-[10px] opacity-70"></i>
            </button>
            <div id="crontab-help-box" class="hidden p-4 rounded-xl border text-xs space-y-2.5 mt-2 bg-black/5 dark:bg-white/5" style="border-color: var(--theme-card-border); color: var(--theme-text);">
              <p class="opacity-80">Voor een 20-seconden cyclus dient de crontab voor <code class="px-1.5 py-0.5 rounded bg-black/10 dark:bg-white/10 font-mono font-semibold">www-data</code> of <code class="px-1.5 py-0.5 rounded bg-black/10 dark:bg-white/10 font-mono font-semibold">root</code> als volgt te zijn geconfigureerd:</p>
              <pre class="bg-slate-900 text-emerald-400 p-3 rounded-lg overflow-x-auto text-xs font-mono leading-relaxed shadow-inner border border-slate-700/50 select-all">* * * * * php /var/www/Joti/cron/index.php > /dev/null 2>&1
* * * * * sleep 20; php /var/www/Joti/cron/index.php > /dev/null 2>&1
* * * * * sleep 40; php /var/www/Joti/cron/index.php > /dev/null 2>&1</pre>
            </div>
          </div>
        </div>
      </div>

      <!-- Card 2: Individual Scheduled Cronjobs -->
      <div class="theme-card rounded-xl border shadow-sm overflow-hidden w-full mb-6" style="border-color: var(--theme-card-border);">
        <div class="px-6 py-4 border-b flex justify-between items-center" style="border-color: var(--theme-card-border); background-color: var(--theme-card-bg); color: var(--theme-text);">
          <h3 class="text-xl font-bold flex items-center gap-2">
            <i class="fas fa-clock theme-primary"></i>
            <span>Geplande Taken (Cronjobs)</span>
          </h3>
        </div>
        
        <div class="divide-y" style="border-color: var(--theme-card-border);">
        <?php
        $sql = "SELECT cj.name, cj.enabled, cj.URL, cj.description, cj.interval, cl.exec_time, cl.exec_length, cl.exec_stat, cl.exec_output
                FROM Cronjobs cj LEFT JOIN Cronlogs cl ON cj.name = cl.name
                WHERE cl.exec_time IS NULL
                    OR cl.exec_time = (
                        SELECT MAX(cl2.exec_time)
                        FROM Cronlogs cl2
                        WHERE cl2.name = cj.name
                    )
                ORDER BY cj.name ASC";
                    
        $stmt_cron = $conn->prepare($sql);
        $stmt_cron->execute();
        $result_cron = $stmt_cron->get_result();

        if ($result_cron->num_rows > 0) {
            $i = 0;
            while($row = $result_cron->fetch_assoc()) {
              $name = htmlspecialchars($row['name']);
              $interval = number_format($row['interval'] / 60, 1, ',')." min";
              
              // Fallback voor als er nog geen exec_time is
              $exec_time = $row['exec_time'] ? date("d/m H:i:s", strtotime($row['exec_time'])) : "Nooit";
              $exec_length = $row['exec_length'] ? number_format($row['exec_length'] / 1000, 2, ',')." sec" : "0,00 sec";
              $exec_status = $row['exec_stat'];
              
              $exec_next_val = $row['exec_time'] ? ($row['interval'] + strtotime($row['exec_time']) - time()) : 0;
              if ($row['enabled'] == 1) {
                  if ($exec_next_val <= 0) {
                      $exec_next = "executing...";
                      $next_class = "font-bold text-orange-500 animate-pulse";
                  } else {
                      $exec_next = $exec_next_val . " sec";
                      $next_class = "theme-primary font-medium";
                  }
              } else {
                  $exec_next = " - disabled - ";
                  $next_class = "opacity-50";
              }

              if ($row['enabled'] == 1) {
                $enabled = '<i class="fas fa-toggle-on fa-fw text-green-500 text-xl align-middle"></i>';
              } else {
                $enabled = '<i class="fas fa-toggle-off fa-fw text-gray-400 text-xl align-middle"></i>';
              }

              switch ($exec_status) {
                case 200: // succes
                  $stat_color = "text-green-500";
                  break;
                case 429: // too many requests
                  $stat_color = "text-yellow-500";
                  break;
                case 500: // script error
                  $stat_color = "text-red-500";
                  break;
                default:
                  $stat_color = ($exec_status === null) ? "text-gray-400" : "text-red-500";
                  break;
              }

              echo "<div class='cronTimer p-5 hover:bg-black/5 dark:hover:bg-white/5 transition flex flex-col md:flex-row md:items-center justify-between gap-4'>
                      <div class='md:w-1/3 min-w-[260px]'>
                        <h3 class='text-base font-bold flex items-center gap-2 flex-wrap'>
                          <span id='cron_enabled_".$i."' class='cursor-pointer hover:opacity-80 transition' onclick='toggleCron(\"".htmlspecialchars($row['name'])."\")'>".$enabled."</span>
                          <span id='cron_status_".$i."' class='".$stat_color." text-sm' title='HTML ".htmlspecialchars($exec_status)." code'><i class='fas fa-circle'></i></span>
                          <span id='cron_name_".$i."' class='font-mono'>".$name."</span>
                        </h3>
                        <p id='cron_desc_".$i."' class='text-xs opacity-70 mt-1.5 leading-relaxed'>".htmlspecialchars($row['description'] ?? '')."</p>
                      </div>
                      
                      <div class='grid grid-cols-2 sm:grid-cols-4 gap-3 flex-1 w-full text-xs'>
                        <div class='bg-black/5 dark:bg-white/5 p-2.5 rounded-lg border' style='border-color: var(--theme-card-border);'>
                          <div class='opacity-70 mb-1'><i class='fas fa-calendar-alt mr-1 opacity-70'></i> <b>Interval</b></div>
                          <div id='cron_interval_".$i."' class='font-medium'>".$interval."</div>
                        </div>
                        <div class='bg-black/5 dark:bg-white/5 p-2.5 rounded-lg border' style='border-color: var(--theme-card-border);'>
                          <div class='opacity-70 mb-1'><i class='far fa-clock mr-1 opacity-70'></i> <b>Next exec.</b></div>
                          <div id='cron_exec_next_".$i."' data-seconds='".$exec_next_val."' data-enabled='".$row['enabled']."' class='".$next_class."'>".$exec_next."</div>
                        </div>
                        <div class='bg-black/5 dark:bg-white/5 p-2.5 rounded-lg border' style='border-color: var(--theme-card-border);'>
                          <div class='opacity-70 mb-1'><i class='fas fa-history mr-1 opacity-70'></i> <b>Last exec.</b></div>
                          <div id='cron_exec_time_".$i."' class='font-medium opacity-80'>".$exec_time."</div>
                        </div>
                        <div class='bg-black/5 dark:bg-white/5 p-2.5 rounded-lg border' style='border-color: var(--theme-card-border);'>
                          <div class='opacity-70 mb-1'><i class='fas fa-hourglass-half mr-1 opacity-70'></i> <b>Prev. Dur.</b></div>
                          <div id='cron_exec_length_".$i."' class='font-medium opacity-80'>".$exec_length."</div>
                        </div>
                      </div>

                      <div class='flex items-center justify-end md:pl-2'>
                        <button type='button' onclick='openCronLogsModal(\"".htmlspecialchars($row['name'])."\")' class='px-3 py-1.5 rounded-lg border text-xs font-semibold hover:bg-black/5 dark:hover:bg-white/5 transition flex items-center gap-1.5 opacity-90 shadow-sm' style='border-color: var(--theme-card-border); color: var(--theme-text);' title='Bekijk recente logs'>
                          <i class='fas fa-file-lines theme-primary'></i>
                          <span>Logs</span>
                        </button>
                      </div>
                    </div>";
              $i++;
            }
        } else {
            echo "<div class='p-6 text-center opacity-60'>Geen cronjobs gevonden.</div>";
        }
        $stmt_cron->close();
        ?>
        </div>
      </div>
    </div>

  </main>

  <!-- Modal: Cron Execution Logs Viewer -->
  <div id="modal-cron-logs" class="fixed inset-0 bg-black/70 z-50 hidden flex items-center justify-center p-4">
    <div class="theme-card rounded-2xl border shadow-2xl w-full max-w-3xl max-h-[90vh] flex flex-col overflow-hidden animate-fadeIn" style="border-color: var(--theme-card-border);">
      <div class="px-6 py-4 border-b flex items-center justify-between" style="border-color: var(--theme-card-border); background-color: var(--theme-card-bg); color: var(--theme-text);">
        <h3 class="text-lg font-bold flex items-center gap-2">
          <i class="fas fa-file-lines theme-primary"></i>
          <span>Uitvoeringslogs: <span id="modal-logs-cron-name" class="font-mono underline theme-primary"></span></span>
        </h3>
        <button type="button" onclick="closeCronLogsModal()" class="opacity-70 hover:opacity-100 transition p-1" style="color: var(--theme-text);"><i class="fas fa-times text-lg"></i></button>
      </div>

      <!-- Modal Body -->
      <div class="p-6 overflow-y-auto flex-1 space-y-4">
        
        <!-- Loading State -->
        <div id="modal-logs-loading" class="hidden p-12 text-center space-y-3">
          <i class="fas fa-circle-notch fa-spin text-2xl theme-primary"></i>
          <p class="text-xs font-medium opacity-70">Logs ophalen uit de database...</p>
        </div>

        <!-- Empty State -->
        <div id="modal-logs-empty" class="hidden p-8 text-center opacity-60 text-xs space-y-2">
          <i class="fas fa-folder-open text-2xl opacity-40"></i>
          <p>Er zijn nog geen uitvoeringslogs geregistreerd voor deze taak.</p>
        </div>

        <!-- Logs Container -->
        <div id="modal-logs-container" class="space-y-4">
          <!-- Dynamic logs rendered here -->
        </div>

      </div>

      <!-- Modal Footer -->
      <div class="px-6 py-3.5 border-t flex items-center justify-between bg-black/5 dark:bg-white/5" style="border-color: var(--theme-card-border); color: var(--theme-text);">
        <span id="modal-logs-count-info" class="text-xs opacity-60">0 logs getoond</span>
        <button type="button" onclick="closeCronLogsModal()" class="px-4 py-2 text-xs font-semibold rounded-lg border hover:bg-black/5 dark:hover:bg-white/5 transition" style="border-color: var(--theme-card-border); color: var(--theme-text);">
          Sluiten
        </button>
      </div>
    </div>
  </div>

  <!-- Toast Container -->
  <div id="toast-container" class="fixed bottom-5 right-5 z-50 flex flex-col gap-2 max-w-sm pointer-events-none"></div>

  <?php require_once('../includes/footer.php') ?>
</div>

<script src="../js/admin_cronjobs.js"></script>
<script src="../js/gps.js"></script>
<script>initGpsTracking('<?php echo $_SESSION['gps'] ?? 'false'; ?>');</script>
</body>
</html>