<?php
// api/telegram_webhook.php — Webhook endpoint for Telegram Bot API updates (commands & live location streaming).

header('Content-Type: application/json; charset=utf-8');

require_once(__DIR__ . '/../dblogin.php');
require_once(__DIR__ . '/../includes/helpers.php');
require_once(__DIR__ . '/../includes/telegram_bot.php');
require_once(__DIR__ . '/../includes/telegram_parser.php');

// 1. Fetch Telegram security secret & settings
$stmt_settings = $conn->prepare("SELECT Instelling, Waarde FROM Site_Instellingen WHERE Instelling IN ('TELEGRAM_INGEST_SECRET', 'TELEGRAM_BOT_TOKEN', 'TELEGRAM_GROUP_CHAT_ID', 'GROUP_ID', 'FOX_NAMES')");
$stmt_settings->execute();
$res_settings = $stmt_settings->get_result();
$settings = [];
while ($row = $res_settings->fetch_assoc()) {
    $settings[$row['Instelling']] = $row['Waarde'];
}
$stmt_settings->close();

$configuredSecret = $settings['TELEGRAM_INGEST_SECRET'] ?? '';

// 2. Validate Telegram Webhook Secret Token (if configured)
if (!empty($configuredSecret) && !str_starts_with($configuredSecret, 'placeholder')) {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $secretHeader = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] 
        ?? $headers['X-Telegram-Bot-Api-Secret-Token'] 
        ?? $headers['x-telegram-bot-api-secret-token'] 
        ?? '';

    if (!empty($secretHeader) && !hash_equals($configuredSecret, $secretHeader)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Forbidden: Invalid secret token.']);
        exit();
    }
}

// 3. Parse incoming update
$rawInput = file_get_contents('php://input');
$update = json_decode($rawInput, true);

if (!is_array($update)) {
    echo json_encode(['ok' => true, 'notice' => 'No JSON update body received.']);
    exit();
}

$bot = new TelegramBot($conn, $settings['TELEGRAM_BOT_TOKEN'] ?? null);

// Helper function to update user and car coordinates
function updateLocation(mysqli $conn, int $userId, float $lat, float $lon): void {
    // Update user's personal location using MySQL NOW() for UTC consistency
    $stmt_user = $conn->prepare("UPDATE Gebruikers SET lat = ?, lon = ?, geotijd = NOW() WHERE id = ?");
    if ($stmt_user) {
        $stmt_user->bind_param("ddi", $lat, $lon, $userId);
        $stmt_user->execute();
        $stmt_user->close();
    }

    // Check if user is in a car
    $stmt_car = $conn->prepare("SELECT auto FROM Auto_Bijrijders WHERE gebruiker_id = ? LIMIT 1");
    if ($stmt_car) {
        $stmt_car->bind_param("i", $userId);
        $stmt_car->execute();
        $res_car = $stmt_car->get_result();
        if ($row_car = $res_car->fetch_assoc()) {
            $kenteken = $row_car['auto'];
            $stmt_pos = $conn->prepare("INSERT INTO Auto_Positie (auto, gebruiker_id, datumtijd, lat, lon) VALUES (?, ?, NOW(), ?, ?)");
            if ($stmt_pos) {
                $stmt_pos->bind_param("sidd", $kenteken, $userId, $lat, $lon);
                $stmt_pos->execute();
                $stmt_pos->close();
            }
        }
        $stmt_car->close();
    }

    // Check if active Tegenhunt session exists
    $activeTegenhunt = function_exists('getActiveTegenhunt') ? getActiveTegenhunt($conn) : null;
    if ($activeTegenhunt && function_exists('recordTegenhuntBreadcrumb')) {
        recordTegenhuntBreadcrumb($conn, (int)$activeTegenhunt['id'], $userId, $lat, $lon, 10.0);
    }
}

// ==========================================
// 4. HANDLE LIVE LOCATION UPDATES (Streamed)
// ==========================================
if (isset($update['edited_message']['location'])) {
    $edited = $update['edited_message'];
    $chatId = (string)($edited['chat']['id'] ?? '');
    $lat = (float)($edited['location']['latitude'] ?? 0);
    $lon = (float)($edited['location']['longitude'] ?? 0);

    if (!empty($chatId) && $lat != 0 && $lon != 0) {
        $stmt = $conn->prepare("SELECT id, priv FROM Gebruikers WHERE telegram_chat_id = ? AND priv >= 1 LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $chatId);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($user) {
                updateLocation($conn, (int)$user['id'], $lat, $lon);
            }
        }
    }

    echo json_encode(['ok' => true]);
    exit();
}

// ==========================================
// 5. HANDLE NORMAL MESSAGES
// ==========================================
if (isset($update['message'])) {
    $msg = $update['message'];
    $chatId = (string)($msg['chat']['id'] ?? '');
    $text = trim($msg['text'] ?? '');

    // 5.1 Direct Location Message (Pin or Initial Live Share)
    if (isset($msg['location'])) {
        $lat = (float)($msg['location']['latitude'] ?? 0);
        $lon = (float)($msg['location']['longitude'] ?? 0);

        if (!empty($chatId) && $lat != 0 && $lon != 0) {
            $stmt = $conn->prepare("SELECT id, voornaam, priv FROM Gebruikers WHERE telegram_chat_id = ? AND priv >= 1 LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("s", $chatId);
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($user) {
                    updateLocation($conn, (int)$user['id'], $lat, $lon);
                    $reply = "📍 *Locatie ontvangen!*\n"
                           . "Je positie (`" . number_format($lat, 5) . ", " . number_format($lon, 5) . "`) is direct bijgewerkt op de Jotify kaart.\n\n"
                           . "💡 *Live locatie delen:* Als je hebt gekozen voor *'Deel mijn live locatie...'* blijft je locatie continu automatisch live zolang de deelsessie actief is, zelfs als je scherm uit staat!";
                    $bot->sendMessage($chatId, $reply);
                } else {
                    $bot->sendMessage($chatId, "⚠️ Je account is nog niet gekoppeld aan Jotify. Gebruik `/start <koppelcode>` om te koppelen.");
                }
            }
        }
        echo json_encode(['ok' => true]);
        exit();
    }

    // 5.2 Text Commands
    if (!empty($text)) {
        $parts = preg_split('/\s+/', $text, 2);
        $cmd = strtolower($parts[0]);
        $param = trim($parts[1] ?? '');

        // Remove bot mention from command (e.g. /vossen@JotifyScoutBot -> /vossen)
        if (str_contains($cmd, '@')) {
            $cmd = explode('@', $cmd)[0];
        }

        // COMMAND: /start or /register
        if ($cmd === '/start' || $cmd === '/register' || $cmd === '/koppel') {
            if (!empty($param)) {
                $code = strtoupper($param);
                $stmt = $conn->prepare("SELECT id, voornaam, achternaam, priv FROM Gebruikers WHERE telegram_link_code = ? AND telegram_link_code IS NOT NULL AND priv >= 1 LIMIT 1");
                $stmt->bind_param("s", $code);
                $stmt->execute();
                $matchedUser = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($matchedUser) {
                    // Update user record with telegram_chat_id and enable telegram
                    $stmt_up = $conn->prepare("UPDATE Gebruikers SET telegram_chat_id = ?, telegram_enabled = 1 WHERE id = ?");
                    $stmt_up->bind_param("si", $chatId, $matchedUser['id']);
                    $stmt_up->execute();
                    $stmt_up->close();

                    $name = htmlspecialchars($matchedUser['voornaam'] . ' ' . $matchedUser['achternaam']);
                    $welcomeMsg = "✅ *Gekoppeld met Jotify!*\n\n"
                                . "Welkom, *{$name}*! Je Telegram account is succesvol gekoppeld aan Jotify.\n\n"
                                . "Je ontvangt vanaf nu automatisch spelmeldingen en kunt:\n"
                                . "• 📍 *Live locatie delen:* Druk op de paperclip 📎 &rarr; *Locatie* &rarr; *Deel live locatie...*\n"
                                . "• /status — Bekijk je status, auto & toewijzing\n"
                                . "• /vossen — Actuele vossenstatussen & hunt-immuniteit\n"
                                . "• /score — Huidige teamscore\n"
                                . "• /help — Volledig overzicht van mogelijkheden";
                    $bot->sendMessage($chatId, $welcomeMsg);
                } else {
                    $bot->sendMessage($chatId, "❌ *Koppelcode ongeldig of verlopen.*\n\nControleer je persoonlijke koppelcode op je [Jotify Instellingen](https://joti.maarleveld.app/instellingen#telegram) pagina en probeer het opnieuw.");
                }
            } else {
                // No parameter provided: check if already linked
                $stmt = $conn->prepare("SELECT voornaam, achternaam FROM Gebruikers WHERE telegram_chat_id = ? AND priv >= 1 LIMIT 1");
                $stmt->bind_param("s", $chatId);
                $stmt->execute();
                $existing = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($existing) {
                    $name = htmlspecialchars($existing['voornaam']);
                    $bot->sendMessage($chatId, "👋 Hallo *{$name}*! Je account is al gekoppeld aan Jotify.\n\nGebruik /status, /vossen, /score of /help voor beschikbare acties.");
                } else {
                    $infoMsg = "👋 *Welkom bij de Jotify Bot!*\n\n"
                             . "Om deze bot te koppelen aan je Jotify jagersaccount:\n"
                             . "1. Open je [Jotify Instellingen](https://joti.maarleveld.app/instellingen#telegram)\n"
                             . "2. Klik op *Koppel met Telegram* of kopieer je persoonlijke 6-tekens code\n"
                             . "3. Stuur hier het bericht: `/start JOUWCODE`";
                    $bot->sendMessage($chatId, $infoMsg);
                }
            }
            echo json_encode(['ok' => true]);
            exit();
        }

        // Authenticate sender for game commands (must be linked & priv >= 1)
        $stmt_auth = $conn->prepare("SELECT id, voornaam, achternaam, gebruikersnaam, priv, lat, lon, geotijd FROM Gebruikers WHERE telegram_chat_id = ? AND priv >= 1 LIMIT 1");
        $stmt_auth->bind_param("s", $chatId);
        $stmt_auth->execute();
        $currentUser = $stmt_auth->get_result()->fetch_assoc();
        $stmt_auth->close();

        if (!$currentUser && in_array($cmd, ['/status', '/vossen', '/score'])) {
            $bot->sendMessage($chatId, "⚠️ Je account is nog niet gekoppeld aan Jotify of heeft onvoldoende rechten (minimaal Vossenjager).\n\nKoppel je account via `/start <koppelcode>` vanaf je [Instellingenpagina](https://joti.maarleveld.app/instellingen#telegram).");
            echo json_encode(['ok' => true]);
            exit();
        }

        // COMMAND: /status
        if ($cmd === '/status') {
            $userId = (int)$currentUser['id'];
            $fullName = htmlspecialchars($currentUser['voornaam'] . ' ' . $currentUser['achternaam']);
            $privLabels = [0 => 'Gast / Kiosk', 1 => 'Vossenjager', 2 => 'Admin', 3 => 'Superadmin'];
            $role = $privLabels[$currentUser['priv']] ?? 'Gebruiker';

            // 1. Location Status
            $locText = "⚪ *Nog geen locatie bekend*";
            if (!empty($currentUser['geotijd'])) {
                $geoTs = strtotime($currentUser['geotijd']);
                $diffSec = time() - $geoTs;
                $relTime = time2str($currentUser['geotijd']);
                $coords = number_format((float)$currentUser['lat'], 4) . ', ' . number_format((float)$currentUser['lon'], 4);

                if ($diffSec < 900) {
                    $locText = "🟢 *Live actief* (bijgewerkt: {$relTime})\n   📍 Coördinaten: `{$coords}`";
                } else {
                    $locText = "⚪ *Laatst gezien:* {$relTime}\n   📍 Coördinaten: `{$coords}`";
                }
            }

            // 2. Car Occupancy
            $carText = "🚗 *Auto:* Niet ingedeeld in een auto";
            $stmt_c = $conn->prepare("SELECT a.kenteken, ab.is_driver FROM Auto_Bijrijders ab JOIN Auto a ON ab.auto = a.kenteken WHERE ab.gebruiker_id = ? LIMIT 1");
            if ($stmt_c) {
                $stmt_c->bind_param("i", $userId);
                $stmt_c->execute();
                $carRow = $stmt_c->get_result()->fetch_assoc();
                $stmt_c->close();

                if ($carRow) {
                    $kt = htmlspecialchars($carRow['kenteken']);
                    $driverLabel = $carRow['is_driver'] ? 'Bestuurder' : 'Bijrijder';
                    
                    // Fetch fellow passengers
                    $passengers = [];
                    $stmt_p = $conn->prepare("SELECT g.voornaam, g.achternaam, ab2.is_driver FROM Auto_Bijrijders ab2 JOIN Gebruikers g ON ab2.gebruiker_id = g.id WHERE ab2.auto = ? ORDER BY ab2.is_driver DESC, g.voornaam ASC");
                    if ($stmt_p) {
                        $stmt_p->bind_param("s", $kt);
                        $stmt_p->execute();
                        $res_p = $stmt_p->get_result();
                        while ($p = $res_p->fetch_assoc()) {
                            $pName = htmlspecialchars($p['voornaam'] . ' ' . $p['achternaam']);
                            $passengers[] = $p['is_driver'] ? "{$pName} 🚘" : $pName;
                        }
                        $stmt_p->close();
                    }
                    $passList = !empty($passengers) ? implode(', ', $passengers) : 'Alleen';
                    $carText = "🚗 *Auto:* `{$kt}` ({$driverLabel})\n   👥 *Inzittenden:* {$passList}";
                }
            }

            // 3. Current Assignment
            $assignText = "📋 *Taak:* Geen actieve toewijzing";
            $stmt_a = $conn->prepare("SELECT type, referentie_id FROM Toewijzingen WHERE gebruiker_id = ? ORDER BY timestamp DESC LIMIT 1");
            if ($stmt_a) {
                $stmt_a->bind_param("i", $userId);
                $stmt_a->execute();
                $assignRow = $stmt_a->get_result()->fetch_assoc();
                $stmt_a->close();
                if ($assignRow) {
                    $assignText = "📋 *Taak:* " . htmlspecialchars($assignRow['type']) . " (#" . (int)$assignRow['referentie_id'] . ")";
                }
            }

            $reply = "👤 *JOUW STATUS*\n\n"
                   . "• *Naam:* {$fullName} ({$role})\n"
                   . "• {$locText}\n"
                   . "• {$carText}\n"
                   . "• {$assignText}\n\n"
                   . "💡 *Locatie bijwerken:* Deel je live locatie via de paperclip 📎 om real-time tracking te activeren.";
            $bot->sendMessage($chatId, $reply);
            echo json_encode(['ok' => true]);
            exit();
        }

        // COMMAND: /vossen
        if ($cmd === '/vossen') {
            $foxNamesStr = $settings['FOX_NAMES'] ?? 'Alpha,Bravo,Charlie,Delta,Echo,Foxtrot,Golf,Hotel,Oscar';
            $foxes = array_map('trim', explode(',', $foxNamesStr));

            $lines = [];
            $now = time();

            foreach ($foxes as $fox) {
                // 1. Current status from Voslog
                $stmt_v = $conn->prepare("SELECT status, datumtijd FROM Voslog WHERE vos = ? ORDER BY datumtijd DESC LIMIT 1");
                $statusNum = 0;
                $statusTime = '';
                if ($stmt_v) {
                    $stmt_v->bind_param("s", $fox);
                    $stmt_v->execute();
                    $vRow = $stmt_v->get_result()->fetch_assoc();
                    $stmt_v->close();
                    if ($vRow) {
                        $statusNum = (int)$vRow['status'];
                        $statusTime = $vRow['datumtijd'];
                    }
                }

                $badge = match ($statusNum) {
                    2 => "🟢 *Actief*",
                    1 => "🟠 *Onderweg*",
                    default => "🔴 *Inactief*"
                };

                // 2. Check 60-minute immunity from latest approved/pending hunt
                $stmt_h = $conn->prepare("SELECT ingestuurd_op, code FROM Voslocaties WHERE type = 'Hunt' AND deelgebied = ? AND status != 'Afgekeurd' ORDER BY ingestuurd_op DESC LIMIT 1");
                $immunityText = "";
                if ($stmt_h) {
                    $stmt_h->bind_param("s", $fox);
                    $stmt_h->execute();
                    $huntRow = $stmt_h->get_result()->fetch_assoc();
                    $stmt_h->close();

                    if ($huntRow && !empty($huntRow['ingestuurd_op'])) {
                        $huntTs = strtotime($huntRow['ingestuurd_op']);
                        $elapsedSec = $now - $huntTs;

                        if ($elapsedSec < 3600 && $elapsedSec >= 0) {
                            $remMin = (int)ceil((3600 - $elapsedSec) / 60);
                            $freeTime = date('H:i', $huntTs + 3600);
                            $immunityText = " — ⏳ *Immuun tot {$freeTime}* (nog {$remMin}m)";
                        }
                    }
                }

                if (empty($immunityText)) {
                    if ($statusNum === 2 || $statusNum === 1) {
                        $immunityText = " — 🎯 *Huntbaar!*";
                    } else {
                        $immunityText = " — 💤 *Rust*";
                    }
                }

                $lines[] = "• *{$fox}:* {$badge}{$immunityText}";
            }

            $reply = "🦊 *VOSSENSTATUSSEN*\n\n" . implode("\n", $lines) . "\n\n_Regel: 60 minuten immuniteit na een geregistreerde hunt._";
            $bot->sendMessage($chatId, $reply);
            echo json_encode(['ok' => true]);
            exit();
        }

        // COMMAND: /score
        if ($cmd === '/score') {
            $groupId = (int)($settings['GROUP_ID'] ?? 0);
            $stmt_s = $conn->prepare("SELECT * FROM Punten WHERE groep_id = ? LIMIT 1");
            $pts = null;
            if ($stmt_s) {
                $stmt_s->bind_param("i", $groupId);
                $stmt_s->execute();
                $pts = $stmt_s->get_result()->fetch_assoc();
                $stmt_s->close();
            }

            $hunts = (int)($pts['hunts'] ?? 0);
            $opdrachten = (int)($pts['opdrachten'] ?? 0);
            $hints = (int)($pts['hints'] ?? 0);
            $tegenhunts = (int)($pts['tegenhunts'] ?? 0);
            $foto = (int)($pts['foto_opdrachten'] ?? 0);
            $straf = (int)($pts['strafpunten'] ?? 0);
            $totaal = $hunts + $opdrachten + $hints + $tegenhunts + $foto - $straf;

            $reply = "🏆 *JOTIHUNT SCORES*\n\n"
                   . "🎯 Hunts: *{$hunts}* pt\n"
                   . "📝 Opdrachten: *{$opdrachten}* pt\n"
                   . "💡 Hints: *{$hints}* pt\n"
                   . "🛡️ Tegenhunts: *{$tegenhunts}* pt\n"
                   . "📸 Foto-opdrachten: *{$foto}* pt\n"
                   . ($straf > 0 ? "⚠️ Strafpunten: *-{$straf}* pt\n" : "")
                   . "━━━━━━━━━━━━━━━\n"
                   . "⭐ *TOTAAL: {$totaal} PUNTEN*";
            $bot->sendMessage($chatId, $reply);
            echo json_encode(['ok' => true]);
            exit();
        }

        // COMMAND: /help
        if ($cmd === '/help') {
            $reply = "📖 *JOTIFY TELEGRAM BOT HULP*\n\n"
                   . "*Beschikbare commando's:*\n"
                   . "• /status — Bekijk je persoonlijke status, auto en toewijzing\n"
                   . "• /vossen — Bekijk actuele vossenstatussen & hunt-immuniteit\n"
                   . "• /score — Bekijk de huidige score van het team\n"
                   . "• /help — Dit hulpoverzicht weergeven\n\n"
                   . "📍 *Permanente Live Locatie:*\n"
                   . "Je kunt via Telegram je live locatie continu doorgeven aan het basisteam, zelfs als je telefoon vergrendeld is en de browser gesloten!\n"
                   . "1. Tik op het paperclip-icoon 📎 in deze chat\n"
                   . "2. Kies *Locatie*\n"
                   . "3. Kies *Deel mijn live locatie voor...* (bijv. 8 uur)\n"
                   . "Je coördinaten worden nu real-time weergegeven op de Jotify kaart!";
            $bot->sendMessage($chatId, $reply);
            echo json_encode(['ok' => true]);
            exit();
        }

        // 5.3 Forwarded or direct Jotihunt game message parser
        // Allows hunters to forward messages from @Jotihunt_bot or paste game alerts directly
        if ($currentUser) {
            $forwardSender = '@Jotihunt_bot';
            if (isset($msg['forward_from'])) {
                $forwardSender = '@' . ($msg['forward_from']['username'] ?? $msg['forward_from']['first_name'] ?? 'Jotihunt_bot');
            } elseif (isset($msg['forward_sender_name'])) {
                $forwardSender = $msg['forward_sender_name'];
            }

            try {
                $parseResult = parseAndDispatchTelegramMessage($conn, $text, $forwardSender, (int)($msg['message_id'] ?? 0));
                if (($parseResult['type'] ?? 'unknown') !== 'unknown') {
                    $reply = "✅ *Jotihunt Bericht Verwerkt!*\n\n"
                           . "📋 *Event:* " . htmlspecialchars($parseResult['summary']) . "\n"
                           . "🏷️ *Type:* `" . htmlspecialchars($parseResult['type']) . "`\n\n"
                           . "De wijziging is direct doorgevoerd in Jotify en doorgestuurd naar de actieve jagers.";
                    $bot->sendMessage($chatId, $reply);
                    echo json_encode(['ok' => true]);
                    exit();
                }
            } catch (Throwable $e) {
                error_log("Telegram game parser error: " . $e->getMessage());
            }
        }

        // Default unknown command
        $bot->sendMessage($chatId, "❓ Onbekend commando of niet herkend als Jotihunt spelbericht.\n\nTyp /help voor een overzicht van alle commando's.");
    }
}

echo json_encode(['ok' => true]);
