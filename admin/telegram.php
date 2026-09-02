<?php
// admin/telegram.php — Administrative dashboard for Telegram message ingestion, subscriber management, and event simulation.
define("PAGE_NAME", "admin_telegram");
require_once(__DIR__ . '/../includes/auth.php');

if ($privilege < 2) {
    header("Location: ../home");
    exit();
}

$notInAdminfolder = false;

// 1. Fetch current telegram settings
$stmt_settings = $conn->prepare("SELECT Instelling, Waarde FROM Site_Instellingen WHERE Instelling IN ('TELEGRAM_REGISTRATION_CODE', 'TELEGRAM_INGEST_SECRET', 'TELEGRAM_GROUP_CHAT_ID', 'TELEGRAM_FORWARD_MODE')");
$stmt_settings->execute();
$res_settings = $stmt_settings->get_result();
$telegram_settings = [];
while ($row = $res_settings->fetch_assoc()) {
    $telegram_settings[$row['Instelling']] = $row['Waarde'];
}
$stmt_settings->close();

$regCode = $telegram_settings['TELEGRAM_REGISTRATION_CODE'] ?? '';
$ingestSecret = $telegram_settings['TELEGRAM_INGEST_SECRET'] ?? '';
$groupChat = $telegram_settings['TELEGRAM_GROUP_CHAT_ID'] ?? '';
$forwardMode = $telegram_settings['TELEGRAM_FORWARD_MODE'] ?? 'forward';

// 2. Fetch active subscribers
$stmt_subs = $conn->prepare("SELECT id, voornaam, achternaam, gebruikersnaam, priv, telegram_chat_id FROM Gebruikers WHERE priv >= 1 AND telegram_enabled = 1 AND telegram_chat_id IS NOT NULL AND telegram_chat_id != '' ORDER BY voornaam ASC");
$stmt_subs->execute();
$subscribers = $stmt_subs->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_subs->close();

// 3. Fetch initial 25 messages
$stmt_msgs = $conn->prepare("SELECT * FROM Telegram_Messages ORDER BY id DESC LIMIT 25");
$stmt_msgs->execute();
$messages = $stmt_msgs->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_msgs->close();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="utf-8">
  <title>Jotify - Telegram Beheer</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="shortcut icon" type="image/png" href="../media/geusje.png"/>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../includes/switch.css">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <?php include_once(__DIR__ . '/../includes/theme.php'); ?>
</head>
<body class="flex h-screen overflow-hidden bg-gray-50 text-gray-900">

<!-- Sidebar -->
<?php include_once(__DIR__ . '/../includes/sidebar.php'); ?>

<!-- Main Content -->
<div class="flex-1 flex flex-col h-screen overflow-y-auto w-full relative">
  <!-- Topbar -->
  <?php include_once(__DIR__ . '/../includes/topbar.php'); ?>

  <main class="p-4 md:p-6 max-w-[1400px] mx-auto w-full flex-1">
    <div class="max-w-6xl mx-auto space-y-6">
  
      <!-- Page Header -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h2 class="text-3xl font-bold theme-primary flex items-center gap-3">
            <i class="fab fa-telegram text-blue-500"></i>
            <span>Telegram Ingest & Beheer</span>
          </h2>
          <p class="text-sm opacity-70 mt-1">Real-time verwerking van spelberichten van @Jotihunt_bot, automatische triggers en instant doorsturen naar jagers.</p>
        </div>
        <div class="flex items-center gap-2">
          <button onclick="pollFeed()" class="theme-card border px-4 py-2.5 rounded-xl text-sm font-semibold hover:bg-black/5 transition shadow-sm flex items-center gap-2">
            <i class="fas fa-sync-alt" id="refreshIcon"></i> Vernieuwen
          </button>
        </div>
      </div>

      <!-- Grid: Status & Koppeling + Forwarding Targets -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- Card 1: Bot Koppeling & Webhook Info -->
        <div class="theme-card rounded-xl shadow-sm border overflow-hidden flex flex-col justify-between">
          <div>
            <div class="theme-card-header px-6 py-4 border-b text-white flex justify-between items-center" style="background-color: var(--theme-sidebar-active); border-color: var(--theme-card-border);">
              <h3 class="text-lg font-semibold flex items-center gap-2">
                <i class="fas fa-link mr-1"></i> Jotihunt Bot Koppeling
              </h3>
              <span class="text-xs px-2.5 py-1 rounded-full font-bold bg-white/20 text-white">
                <?= !empty($regCode) && $regCode !== 'placeholder_code' ? 'Code Beschikbaar' : 'Wachten op Scraper' ?>
              </span>
            </div>

            <div class="p-6 space-y-4 text-sm">
              <div>
                <label class="block text-xs font-semibold opacity-70 mb-1 uppercase tracking-wide">Registratiecode (Automatisch opgehaald via scraper)</label>
                <div class="flex gap-2">
                  <input type="text" readonly value="<?= !empty($regCode) && $regCode !== 'placeholder_code' ? '/register ' . htmlspecialchars($regCode) : 'Geen code bekend (draai portal scraper)' ?>" id="regCodeInput" class="flex-1 theme-override-bg theme-override-text border rounded-xl px-3.5 py-2.5 font-mono text-sm shadow-sm outline-none">
                  <?php if (!empty($regCode) && $regCode !== 'placeholder_code'): ?>
                  <button type="button" onclick="copyToClipboard('regCodeInput', this)" class="theme-bg-primary text-white px-4 py-2.5 rounded-xl text-sm font-bold hover:opacity-90 transition shadow-sm">
                    <i class="fas fa-copy"></i>
                  </button>
                  <a href="https://t.me/Jotihunt_bot?start=register_<?= urlencode($regCode) ?>" target="_blank" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2.5 rounded-xl text-sm font-bold transition shadow-sm flex items-center">
                    <i class="fab fa-telegram-plane"></i>
                  </a>
                  <?php endif; ?>
                </div>
                <p class="text-xs opacity-60 mt-1.5">Stuur dit bericht naar <a href="https://t.me/Jotihunt_bot" target="_blank" class="text-blue-500 hover:underline">@Jotihunt_bot</a> vanuit het gekoppelde teamaccount.</p>
              </div>

              <div>
                <label class="block text-xs font-semibold opacity-70 mb-1 uppercase tracking-wide">Webhook Ingest Endpoint</label>
                <input type="text" readonly value="https://joti.maarleveld.app/api/telegram_ingest.php" id="ingestUrlInput" class="w-full theme-override-bg theme-override-text border rounded-xl px-3.5 py-2.5 font-mono text-xs shadow-sm outline-none">
              </div>

              <div>
                <label class="block text-xs font-semibold opacity-70 mb-1 uppercase tracking-wide">Ingest Secret Key</label>
                <div class="flex gap-2">
                  <input type="password" readonly value="<?= htmlspecialchars($ingestSecret) ?>" id="secretInput" class="flex-1 theme-override-bg theme-override-text border rounded-xl px-3.5 py-2.5 font-mono text-sm shadow-sm outline-none">
                  <button type="button" onclick="togglePasswordVisibility('secretInput', this)" class="theme-card border px-4 py-2.5 rounded-xl text-sm hover:bg-black/5 transition shadow-sm">
                    <i class="fas fa-eye"></i>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Card 2: Forwarding & Abonnees -->
        <div class="theme-card rounded-xl shadow-sm border overflow-hidden flex flex-col justify-between">
          <div>
            <div class="theme-card-header px-6 py-4 border-b text-white flex justify-between items-center" style="background-color: var(--theme-sidebar-active); border-color: var(--theme-card-border);">
              <h3 class="text-lg font-semibold flex items-center gap-2">
                <i class="fas fa-share-nodes mr-1"></i> Doorgestuurde Ontvangers
              </h3>
              <span class="text-xs px-2.5 py-1 rounded-full font-bold bg-white/20 text-white">
                <?= count($subscribers) ?> Jagers actief
              </span>
            </div>

            <form id="configForm" onsubmit="saveConfig(event)" class="p-6 space-y-4 text-sm">
              <div>
                <label class="block text-xs font-semibold opacity-70 mb-1 uppercase tracking-wide">Centrale Groep / Kanaal Chat ID (optioneel)</label>
                <input type="text" name="telegram_group_chat_id" value="<?= htmlspecialchars($groupChat !== 'placeholder_group_id' && $groupChat !== '-1001234567890' ? $groupChat : '') ?>" placeholder="Bijv. -1001234567890 of @mijn_kanaal" class="w-full theme-override-bg theme-override-text border rounded-xl px-3.5 py-2.5 text-sm shadow-sm outline-none focus:ring-2 focus:ring-blue-500">
                <p class="text-xs opacity-60 mt-1.5">Berichten worden instant geforward naar deze groep én alle individueel geregistreerde jagers.</p>
              </div>

              <div>
                <label class="block text-xs font-semibold opacity-70 mb-1 uppercase tracking-wide">Forward Modus</label>
                <select name="telegram_forward_mode" class="w-full theme-override-bg theme-override-text border rounded-xl px-3.5 py-2.5 text-sm shadow-sm outline-none">
                  <option value="forward" <?= $forwardMode === 'forward' ? 'selected' : '' ?>>Doorsturen (met officiële @Jotihunt_bot header)</option>
                  <option value="relay" <?= $forwardMode === 'relay' ? 'selected' : '' ?>>Relay (als nieuw bericht zonder header)</option>
                </select>
              </div>

              <div>
                <label class="block text-xs font-semibold opacity-70 mb-2 uppercase tracking-wide">Geregistreerde Jagers (via Persoonlijke Instellingen)</label>
                <?php if (empty($subscribers)): ?>
                  <p class="text-xs opacity-60 italic">Nog geen jagers geregistreerd. Jagers kunnen hun Telegram koppelen via <a href="../instellingen#telegram" class="text-blue-500 hover:underline">Instellingen</a>.</p>
                <?php else: ?>
                  <div class="flex flex-wrap gap-2 max-h-32 overflow-y-auto p-1">
                    <?php foreach ($subscribers as $sub): ?>
                      <?php
                        $subName = trim($sub['voornaam'] . ' ' . $sub['achternaam']);
                        $isUsername = str_starts_with($sub['telegram_chat_id'], '@');
                        $badgeStyle = $isUsername ? 'bg-amber-50 text-amber-900 border-amber-300' : 'bg-blue-50 text-blue-900 border-blue-200';
                      ?>
                      <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium <?= $badgeStyle ?> border shadow-sm" title="<?= $isUsername ? 'Let op: @gebruikersnaam vereist MTProto listener daemon. Voor Bot API is een numeriek Chat ID nodig.' : 'Numeriek Chat ID' ?>">
                        <i class="fab fa-telegram <?= $isUsername ? 'text-amber-600' : 'text-blue-500' ?>"></i>
                        <span><?= htmlspecialchars($subName ?: $sub['gebruikersnaam']) ?></span>
                        <span class="opacity-60 text-[10px] font-mono">(<?= htmlspecialchars($sub['telegram_chat_id']) ?>)</span>
                        <?php if ($isUsername): ?>
                          <span class="text-[9px] bg-amber-200 text-amber-900 px-1.5 py-0.5 rounded font-bold ml-0.5" title="Voor bots is een numeriek ID via @userinfobot nodig">Let op: username</span>
                        <?php endif; ?>
                      </span>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>

              <div class="pt-2 text-right">
                <button type="submit" class="w-full md:w-auto px-8 py-2.5 theme-bg-primary text-white font-bold rounded-xl shadow-sm hover:opacity-90 transition">
                  Configuratie Opslaan
                </button>
              </div>
            </form>
          </div>
        </div>

      </div>

      <!-- Card: Telegram Bot Webhook & API Beheer -->
      <div class="theme-card rounded-xl shadow-sm border overflow-hidden mb-6">
        <div class="theme-card-header px-6 py-4 border-b text-white flex justify-between items-center" style="background-color: var(--theme-sidebar-active); border-color: var(--theme-card-border);">
          <h3 class="text-xl font-bold flex items-center gap-2">
            <i class="fas fa-robot mr-1"></i> Telegram Bot & Webhook Status
          </h3>
          <span class="text-xs opacity-80 font-mono">@JotifyScoutBot</span>
        </div>

        <div class="p-6">
          <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 p-4 rounded-xl border theme-override-bg mb-4">
            <div class="flex-1 space-y-1" id="webhookStatusText">
              <span class="opacity-60 text-xs"><i class="fas fa-spinner fa-spin"></i> Webhook status laden van Telegram...</span>
            </div>
            <div class="flex flex-wrap gap-2">
              <button type="button" onclick="testBotToken()" id="btnTestBot" class="px-4 py-2 rounded-xl text-xs font-bold border hover:bg-black/5 transition shadow-sm flex items-center gap-1.5">
                <i class="fas fa-key"></i> Token Testen
              </button>
              <button type="button" onclick="registerWebhook()" class="px-4 py-2 rounded-xl text-xs font-bold bg-blue-600 hover:bg-blue-700 text-white transition shadow-sm flex items-center gap-1.5">
                <i class="fas fa-plug"></i> Webhook Registreren
              </button>
              <button type="button" onclick="removeWebhook()" class="px-4 py-2 rounded-xl text-xs font-bold bg-red-600 hover:bg-red-700 text-white transition shadow-sm flex items-center gap-1.5">
                <i class="fas fa-trash"></i> Webhook Verwijderen
              </button>
            </div>
          </div>

          <p class="text-xs opacity-70">
            Het webhook endpoint ontvangt live locatie streaming van gebruikers en commando's (<code>/status</code>, <code>/vossen</code>, <code>/score</code>). Notificaties worden via de 40-sec push_queue cronjob verzonden.
          </p>
        </div>
      </div>

      <!-- Card 3: Interactieve Simulator & Handmatige Test -->
      <div class="theme-card rounded-xl shadow-sm border overflow-hidden mb-6">
        <div class="theme-card-header px-6 py-4 border-b text-white flex justify-between items-center" style="background-color: var(--theme-sidebar-active); border-color: var(--theme-card-border);">
          <h3 class="text-xl font-bold flex items-center gap-2">
            <i class="fas fa-flask mr-2"></i> Bericht Simulator & Handmatige Invoer
          </h3>
        </div>

        <div class="p-6">
          <p class="text-xs opacity-70 mb-4">Test de parser tegen officiële berichtformaten of voer handmatig een bericht in als het internet tijdelijk hapert.</p>

          <!-- Quick Example Buttons -->
          <div class="flex flex-wrap gap-2 mb-4">
            <span class="text-xs font-semibold opacity-60 self-center mr-1">Voorbeelden:</span>
            <button type="button" onclick="setSimExample('Status van Charlie is gewijzigd in orange')" class="text-xs px-3 py-1.5 rounded-lg border hover:bg-black/5 transition shadow-sm">Vos Oranje</button>
            <button type="button" onclick="setSimExample('Status van Alpha is gewijzigd in green')" class="text-xs px-3 py-1.5 rounded-lg border hover:bg-black/5 transition shadow-sm">Vos Groen</button>
            <button type="button" onclick="setSimExample('De hunt met code BnsVqJy is voorlopig goedgekeurd en levert 3 punt(en) op.')" class="text-xs px-3 py-1.5 rounded-lg border hover:bg-black/5 transition shadow-sm">Hunt Goedgekeurd</button>
            <button type="button" onclick="setSimExample('Jullie inzending voor de opdracht \'Light of Eärendil ✨\' is beoordeeld, jullie hebben daarvoor 5 punt(en) gekregen\n\nOpmerkingen: Prachtige uitwerking van het thema!')" class="text-xs px-3 py-1.5 rounded-lg border hover:bg-black/5 transition shadow-sm">Opdracht Beoordeeld</button>
            <button type="button" onclick="setSimExample('correct HAPPY HOUR')" class="text-xs px-3 py-1.5 rounded-lg border hover:bg-black/5 transition shadow-sm">Happy Hour</button>
            <button type="button" onclick="setSimExample('⚠️ TEGENHUNT! Er is een tegenhunt geplaatst tegen jullie groep. Richting: Zuid (180°). Zoek binnen een straal van 500m.')" class="text-xs px-3 py-1.5 rounded-lg border border-red-300 text-red-700 bg-red-50 hover:bg-red-100 transition shadow-sm">Tegenhunt Alarm</button>
          </div>

          <form id="simForm" onsubmit="runSimulation(event)" class="space-y-4">
            <div>
              <textarea id="simMessage" rows="3" placeholder="Plak of typ hier het Telegram-bericht..." required class="w-full theme-override-bg theme-override-text border rounded-xl p-3 text-sm shadow-sm outline-none focus:ring-2 focus:ring-blue-500 font-mono"></textarea>
            </div>
            <div class="flex items-center justify-between">
              <div id="simResultBadge" class="hidden text-xs font-semibold px-3 py-1.5 rounded-xl shadow-sm"></div>
              <button type="submit" id="simSubmitBtn" class="ml-auto px-8 py-2.5 theme-bg-primary text-white font-bold rounded-xl shadow-sm hover:opacity-90 transition flex items-center gap-2">
                <i class="fas fa-play"></i> Parseren & Verwerken
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Card 4: Live Berichtenfeed -->
      <div class="theme-card rounded-xl shadow-sm border overflow-hidden mb-12">
        <div class="theme-card-header px-6 py-4 border-b text-white flex justify-between items-center" style="background-color: var(--theme-sidebar-active); border-color: var(--theme-card-border);">
          <h3 class="text-lg font-semibold flex items-center gap-2">
            <i class="fas fa-list mr-2"></i> Recente Telegram Berichten
          </h3>
          <span class="text-xs opacity-75">Auto-refresh elke 5s</span>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left text-sm table-fixed">
            <thead class="uppercase tracking-wider border-b border-gray-200 bg-gray-50/50">
              <tr>
                <th scope="col" class="w-28 px-4 py-3 font-medium opacity-80 text-xs">Tijd</th>
                <th scope="col" class="w-32 px-4 py-3 font-medium opacity-80 text-xs">Type</th>
                <th scope="col" class="w-32 px-4 py-3 font-medium opacity-80 text-xs">Afzender</th>
                <th scope="col" class="px-4 py-3 font-medium opacity-80 text-xs">Bericht</th>
                <th scope="col" class="w-56 px-4 py-3 font-medium opacity-80 text-xs">Payload Details</th>
              </tr>
            </thead>
            <tbody id="feedTableBody" class="divide-y divide-gray-200">
              <?php if (empty($messages)): ?>
                <tr id="emptyFeedRow">
                  <td colspan="5" class="px-6 py-6 text-center opacity-60 italic">Nog geen berichten ontvangen in de database.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($messages as $msg): ?>
                  <?php
                    $badgeColor = 'bg-gray-100 text-gray-800';
                    if ($msg['parsed_type'] === 'fox_status') $badgeColor = 'bg-orange-100 text-orange-800';
                    elseif ($msg['parsed_type'] === 'hunt_status') $badgeColor = 'bg-green-100 text-green-800';
                    elseif ($msg['parsed_type'] === 'assignment_graded') $badgeColor = 'bg-blue-100 text-blue-800';
                    elseif ($msg['parsed_type'] === 'happy_hour') $badgeColor = 'bg-yellow-100 text-yellow-800';
                    elseif ($msg['parsed_type'] === 'tegenhunt') $badgeColor = 'bg-red-100 text-red-800 font-bold';
                    elseif ($msg['parsed_type'] === 'admin_broadcast') $badgeColor = 'bg-purple-100 text-purple-800';
                  ?>
                  <tr class="hover:bg-gray-50/50 transition-colors">
                    <td class="px-4 py-3 whitespace-nowrap text-xs opacity-80" title="<?= htmlspecialchars(formatAmsterdamDateTime($msg['created_at'])) ?>">
                      <?= htmlspecialchars(time2str($msg['created_at'])) ?>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap">
                      <span class="px-2.5 py-1 rounded-full text-xs font-semibold <?= $badgeColor ?>">
                        <?= htmlspecialchars($msg['parsed_type']) ?>
                      </span>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-xs font-mono opacity-80 truncate" title="<?= htmlspecialchars($msg['sender']) ?>">
                      <?= htmlspecialchars($msg['sender']) ?>
                    </td>
                    <td class="px-4 py-3 text-xs font-sans overflow-hidden">
                      <div class="truncate max-w-full" title="<?= htmlspecialchars($msg['message_text']) ?>">
                        <?= htmlspecialchars(str_replace(["\r\n", "\r", "\n"], " ", $msg['message_text'])) ?>
                      </div>
                    </td>
                    <td class="px-4 py-3 text-[11px] font-mono opacity-70 overflow-hidden">
                      <div class="truncate max-w-full" title="<?= htmlspecialchars($msg['parsed_payload'] ?? '') ?>">
                        <?= htmlspecialchars($msg['parsed_payload'] ?? '') ?>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </main>
  <?php require_once(__DIR__ . '/../includes/footer.php'); ?>
</div>

<script src="../js/admin_telegram.js"></script>
</body>
</html>
