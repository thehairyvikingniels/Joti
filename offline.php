<?php
// Offline fallback screen that periodically checks server connectivity and redirects once the connection is restored.
require_once('dblogin.php');
$stmt = $conn->prepare("SELECT Waarde FROM Site_Instellingen WHERE Instelling = 'GROUP_LOGO_SMALL_URL'");
$stmt->execute();
$res = $stmt->get_result();
$logoUrl = "media/geusje.png"; // fallback
if ($res->num_rows > 0) {
    $row = $res->fetch_assoc();
    $logoUrl = $row['Waarde'];
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jotihunt - Geen Verbinding</title>
    <link rel="shortcut icon" type="image/png" href="media/geusje.png"/>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://kit.fontawesome.com/870ab34ea3.js" crossorigin="anonymous"></script>
    <style>
        @keyframes progress {
            0% { width: 0%; }
            100% { width: 100%; }
        }
        .progress-bar-fill {
            animation: progress 10s linear infinite;
        }
    </style>
</head>
<body class="bg-gray-900 text-gray-100 flex items-center justify-center h-screen p-4">
    <div class="bg-gray-800 border border-gray-700 rounded-xl p-8 max-w-md w-full text-center shadow-2xl space-y-6">
        <div class="w-20 h-20 bg-gray-700 rounded-full flex items-center justify-center mx-auto overflow-hidden shadow-inner p-2 border border-gray-600">
            <img src="/<?= htmlspecialchars($logoUrl) ?>" class="w-full h-full object-contain filter grayscale opacity-80" alt="Offline Logo">
        </div>

        <div>
            <h1 class="text-2xl font-bold text-white mb-2">Geen verbinding</h1>
            <p class="text-gray-400 text-sm">
                Check je internetverbinding, of de server is tijdelijk offline.
            </p>
        </div>

        <!-- Progress Bar Container -->
        <div class="space-y-2">
            <div class="flex justify-between text-xs text-gray-400 font-medium">
                <span>Automatisch opnieuw verbinden...</span>
                <span id="countdown-text">10s</span>
            </div>
            <div class="w-full bg-gray-700 h-2 rounded-full overflow-hidden">
                <div id="progress-fill" class="bg-blue-500 h-full w-0 progress-bar-fill"></div>
            </div>
        </div>

        <button onclick="checkConnection()" class="w-full py-3 bg-blue-600 hover:bg-blue-500 text-white font-semibold rounded-lg transition duration-200 flex items-center justify-center gap-2 shadow-lg">
            <i class="fas fa-sync-alt"></i> Nu opnieuw proberen
        </button>
    </div>

    <script>
        let remainingSeconds = 10;
        let countdownElement = document.getElementById('countdown-text');

        // Countdown timer for display
        setInterval(() => {
            remainingSeconds--;
            if (remainingSeconds < 0) {
                remainingSeconds = 10;
            }
            countdownElement.textContent = remainingSeconds + 's';
        }, 1000);

        function checkConnection() {
            fetch('/functies.php?onlinecheck=1', { cache: 'no-store' })
                .then(response => {
                    if (response.ok || response.status === 401) {
                        // Connection restored! Redirect back
                        if (document.referrer && new URL(document.referrer).origin === window.location.origin) {
                            window.location.href = document.referrer;
                        } else {
                            window.location.href = '/';
                        }
                    }
                })
                .catch(() => {
                    // Still offline, progress bar will loop
                    console.log("[Offline] Server nog niet bereikbaar...");
                });
        }

        // Retry connection every 10 seconds
        setInterval(checkConnection, 10000);
    </script>
</body>
</html>
