<?php
// Defensive session start & redirect if user is logged in
ini_set('session.cookie_httponly', '1');
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
if ($isHttps) {
    ini_set('session.cookie_secure', '1');
}
ini_set('session.cookie_samesite', 'Lax');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once('dblogin.php');
require_once('includes/remember_me.php');

if (!empty($_SESSION['id'])) {
    header("Location: home");
    exit();
}

// Check persistent remember-me cookie
$rememberUserId = validateRememberToken($conn);
if ($rememberUserId !== null) {
    $_SESSION['id'] = $rememberUserId;
    session_regenerate_id(true);
    header("Location: home");
    exit();
}

$groupName = 'Jotify';
$stmt = $conn->prepare("SELECT Waarde FROM Site_Instellingen WHERE Instelling = 'GROUP_ID'");
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows > 0) {
    $row = $res->fetch_assoc();
    $groupId = $row['Waarde'];
    if (!empty($groupId)) {
        $stmt2 = $conn->prepare("SELECT naam FROM Groepen WHERE id = ?");
        $stmt2->bind_param("i", $groupId);
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        if ($res2->num_rows > 0) {
            $row2 = $res2->fetch_assoc();
            $groupName = $row2['naam'];
        }
        $stmt2->close();
    }
}

// Fetch admins for the "Forgot Password" modal
$admins = [];
$stmt3 = $conn->prepare("SELECT voornaam FROM Gebruikers WHERE priv >= 3 ORDER BY voornaam ASC");
$stmt3->execute();
$res3 = $stmt3->get_result();
if ($res3->num_rows > 0) {
    while($row3 = $res3->fetch_assoc()) {
        $admins[] = $row3['voornaam'];
    }
}
$stmt3->close();

$stmt->close();
?>

<html>
  <head>
    <title>Jotify - Login</title>
    <meta name="author" content="Niels Maarleveld">
    <link rel="icon" href="media/geusje.png">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">    
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://kit.fontawesome.com/870ab34ea3.js" crossorigin="anonymous"></script>
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <?php include_once('includes/theme.php'); ?>
  </head>
  <body class="min-h-screen flex items-center justify-center bg-cover bg-center relative" style="background-image: url('media/bg1.jpg');">
      <div class="absolute inset-0 bg-black/40 backdrop-blur-sm z-0"></div>

      <div class="z-10 w-full max-w-md p-4">
        <div class="theme-card theme-text rounded-2xl border shadow-2xl overflow-hidden backdrop-blur-md" style="border-color: var(--theme-card-border);">
            <header class="p-6 text-center border-b" style="background-color: var(--theme-sidebar-active); border-color: var(--theme-card-border);">
              <h1 class="text-3xl font-bold text-white tracking-wider">Jotify</h1>
              <h5 class="text-sm text-white/80 mt-1 uppercase tracking-widest font-semibold">Login &mdash; <?= htmlspecialchars($groupName) ?></h5>
            </header>
            
            <div class="p-6">
              <div class="flex justify-center mb-6">
                <div class="w-32 h-32 bg-white rounded-full border-4 border-white shadow-lg flex items-center justify-center overflow-hidden">
                  <img src="media/geusje_bevosd.png" class="w-full h-full object-contain p-2">
                </div>
              </div>
              
              <?php if (isset($_GET['error'])): ?>
                  <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded shadow-sm relative flex items-start">
                    <p class="flex-1"><?= htmlspecialchars($_GET['error']) ?></p>
                    <button onclick="this.parentElement.style.display='none'" class="text-red-500 hover:text-red-700 ml-3"><i class="fas fa-times text-lg"></i></button>
                  </div>
              <?php endif; ?>
              
              <form action="login.php" method="post" class="space-y-4">
                <div>
                  <label class="block text-sm font-semibold mb-1 opacity-80">Gebruikersnaam</label>
                  <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none opacity-50">
                      <i class="fas fa-user text-gray-800"></i>
                    </div>
                    <input class="w-full border rounded-lg pl-10 pr-3 py-2.5 text-gray-800 outline-none focus:ring-2 focus:ring-blue-500 shadow-sm" id="user" type="text" name="username" placeholder="Gebruikersnaam" autofocus>
                  </div>
                </div>
                
                <div>
                  <label class="block text-sm font-semibold mb-1 opacity-80">Wachtwoord</label>
                  <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none opacity-50">
                      <i class="fas fa-lock text-gray-800"></i>
                    </div>
                    <input class="w-full border rounded-lg pl-10 pr-3 py-2.5 text-gray-800 outline-none focus:ring-2 focus:ring-blue-500 shadow-sm" id="pswd" type="password" name="pswd" placeholder="Wachtwoord">
                  </div>
                </div>

                <div class="flex items-center justify-between text-sm pt-1">
                  <label class="flex items-center space-x-2 cursor-pointer select-none opacity-85 hover:opacity-100 transition">
                    <input type="checkbox" name="remember_me" value="1" class="w-4 h-4 rounded text-blue-600 focus:ring-blue-500 border-gray-300">
                    <span class="text-xs sm:text-sm font-semibold">Ingelogd blijven</span>
                  </label>
                </div>
                
                <div class="pt-2">
                  <button class="w-full theme-bg-primary text-white font-bold py-3 rounded-lg shadow-md hover:opacity-90 transition transform hover:-translate-y-0.5" type="submit">Log In</button>
                </div>
                
                <div class="flex justify-between items-center text-sm pt-4 border-t" style="border-color: var(--theme-card-border);">
                  <button type="button" class="font-semibold theme-primary hover:opacity-80 transition" onclick="document.getElementById('modal01').classList.remove('hidden')">Wordt lid</button>
                  <button type="button" class="font-semibold opacity-70 hover:opacity-100 transition" onclick="document.getElementById('adminModal').classList.remove('hidden')">Wachtwoord vergeten?</button>
                </div>
              </form>
            </div>
        </div>
      </div>

      <!-- Registration Modal -->
      <div id="modal01" class="<?= isset($_GET['m_error']) ? '' : 'hidden' ?> fixed inset-0 z-50 overflow-y-auto bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
          <div class="relative w-full max-w-md bg-white dark:bg-gray-800 rounded-2xl shadow-2xl overflow-hidden transform transition-all">
            <header class="p-4 theme-bg-primary text-white flex justify-between items-center"> 
              <h2 class="text-xl font-bold">Wordt lid</h2>
              <button onclick="document.getElementById('modal01').classList.add('hidden')" class="text-white/80 hover:text-white transition"><i class="fas fa-times text-xl"></i></button>
            </header>
            
            <div class="p-6 max-h-[80vh] overflow-y-auto theme-text theme-card">
              <?php if (isset($_GET['m_error'])): ?>
                  <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-800 p-4 mb-6 rounded shadow-sm relative flex items-start">
                    <p class="flex-1"><?= htmlspecialchars($_GET['m_error']) ?></p>
                    <button onclick="this.parentElement.style.display='none'" class="text-yellow-600 hover:text-yellow-800 ml-3"><i class="fas fa-times text-lg"></i></button>
                  </div>
              <?php endif; ?>
              
              <form method="post" action="login.php" onkeydown="return event.key != 'Enter';" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-semibold mb-1 opacity-80">Voornaam</label>
                    <input class="w-full border rounded-lg px-3 py-2 text-gray-800 outline-none focus:ring-2 focus:ring-blue-500 shadow-sm" type="text" name="voornaam" required minlength="1" maxlength="128">
                  </div>
                  <div>
                    <label class="block text-sm font-semibold mb-1 opacity-80">Achternaam</label>
                    <input class="w-full border rounded-lg px-3 py-2 text-gray-800 outline-none focus:ring-2 focus:ring-blue-500 shadow-sm" type="text" name="achternaam" required minlength="1" maxlength="128">
                  </div>
                </div>
                
                <div>
                  <label class="block text-sm font-semibold mb-1 opacity-80">Email</label>
                  <input class="w-full border rounded-lg px-3 py-2 text-gray-800 outline-none focus:ring-2 focus:ring-blue-500 shadow-sm" type="email" name="email" required minlength="4" maxlength="320">
                </div>
                
                <div>
                  <label class="block text-sm font-semibold mb-1 opacity-80">Gebruikersnaam</label>
                  <input class="w-full border rounded-lg px-3 py-2 text-gray-800 outline-none focus:ring-2 focus:ring-blue-500 shadow-sm" type="text" name="gebruikersnaam" required minlength="5" maxlength="32">
                </div>
                
                <div>
                  <label class="block text-sm font-semibold mb-1 opacity-80">Telefoon <span class="font-normal text-xs opacity-70 ml-1">(Moet met 316 beginnen!)</span></label>
                  <input class="w-full border rounded-lg px-3 py-2 text-gray-800 outline-none focus:ring-2 focus:ring-blue-500 shadow-sm" type="tel" name="telefoon" placeholder="+316 12345678" required minlength="11" maxlength="15">
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-semibold mb-1 opacity-80">Wachtwoord</label>
                    <input class="w-full border rounded-lg px-3 py-2 text-gray-800 outline-none focus:ring-2 focus:ring-blue-500 shadow-sm" type="password" name="pswd0" required minlength="8">
                  </div>
                  <div>
                    <label class="block text-sm font-semibold mb-1 opacity-80">Herhaal</label>
                    <input class="w-full border rounded-lg px-3 py-2 text-gray-800 outline-none focus:ring-2 focus:ring-blue-500 shadow-sm" type="password" name="pswd1" required minlength="8">
                  </div>
                </div>
                
                <div class="pt-4">
                  <button class="w-full theme-bg-primary text-white font-bold py-3 rounded-lg shadow-md hover:opacity-90 transition" type="submit">Maak account aan</button>
                </div>
              </form>
            </div>
          </div>
        </div>

      <!-- Forgot Password Modal -->
      <div id="adminModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="relative w-full max-w-sm bg-white dark:bg-gray-800 rounded-2xl shadow-2xl overflow-hidden transform transition-all">
          <header class="p-4 theme-bg-primary text-white flex justify-between items-center"> 
            <h2 class="text-xl font-bold">Wachtwoord vergeten?</h2>
            <button onclick="document.getElementById('adminModal').classList.add('hidden')" class="text-white/80 hover:text-white transition"><i class="fas fa-times text-xl"></i></button>
          </header>
          <div class="p-6 theme-text theme-card">
            <p class="mb-4">Neem contact op met een van de volgende admins om je wachtwoord te resetten:</p>
            <ul class="list-disc pl-5 space-y-1 font-semibold opacity-80">
              <?php foreach($admins as $admin): ?>
                <li><?= htmlspecialchars($admin) ?></li>
              <?php endforeach; ?>
              <?php if (empty($admins)): ?>
                <li>Geen admins gevonden.</li>
              <?php endif; ?>
            </ul>
            
            <div class="mt-6 text-center">
                <button type="button" onclick="document.getElementById('adminModal').classList.add('hidden')" class="w-full bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 rounded-lg transition">Sluiten</button>
            </div>
          </div>
        </div>
      </div>
      
      <div class="absolute bottom-0 w-full">
        <?php require_once('includes/footer.php') ?>
      </div>
  </body>
</html>