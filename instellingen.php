<?php
// User account settings page for updating profile details, password, API key, avatar, and push notification preferences.
define("PAGE_NAME", "instellingen");
session_start();

if (!isset($_SESSION['id'])){

  header("Location: index");

}

require_once('dblogin.php');
require_once("functies.php");


// get userdata
$stmt = $conn->prepare("SELECT * FROM Gebruikers WHERE id=?");
$stmt->bind_param("i", $_SESSION['id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
      $vn = $row['voornaam'];
      $an = $row['achternaam'];
      $email = $row['email'];
      $api = $row['api'];
      $priv = $row['priv'];
      $username = $row['gebruikersnaam'];
      $profile_picture = $row['profile_picture'] ?? null;
    }
}
$stmt->close();


// Get global site settings
$stmt = $conn->prepare("SELECT * FROM Site_Instellingen");
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
      $siteSettings[$row['Instelling']] = $row['Waarde'];
    }
}
$stmt->close();

// Fetch subscriptions
$stmt = $conn->prepare("SELECT id, endpoint, device_name FROM Notification_Subscriptions WHERE user_id=?");
$stmt->bind_param("i", $_SESSION['id']);
$stmt->execute();
$subscriptions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch preferences
$stmt = $conn->prepare("SELECT notification_prefs FROM Gebruikers WHERE id=?");
$stmt->bind_param("i", $_SESSION['id']);
$stmt->execute();
$res_prefs = $stmt->get_result()->fetch_assoc();
$stmt->close();
$notification_prefs = $res_prefs['notification_prefs'] ? json_decode($res_prefs['notification_prefs'], true) : [
    'welkomsberichten' => true,
    'assignment_changes' => true,
    'vosstatus' => false,
    'locatiestatus' => false,
    'hints' => false,
    'opdrachten' => false,
    'nieuws' => false
];

?>

<!DOCTYPE html>
<html lang="nl">
<head>
<title>Jotify - Instellingen</title>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="shortcut icon" type="image/png" href="media/geusje.png"/>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://kit.fontawesome.com/870ab34ea3.js" crossorigin="anonymous"></script>
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<?php include_once('includes/theme.php'); ?>
</head>
<body class="flex h-screen overflow-hidden">

<!-- Sidebar -->
<?php include_once('includes/sidebar.php') ?>

<!-- Main Content -->
<div class="flex-1 flex flex-col h-screen overflow-y-auto w-full relative">
  <!-- Topbar -->
  <?php include_once('includes/topbar.php') ?>

  <main class="p-4 md:p-6 max-w-[1400px] mx-auto w-full flex-1">


    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      
      <!-- Gegevens Wijzigen -->
      <div class="theme-card rounded border shadow-sm p-5">
        <?php if (isset($_GET['t']) && $_GET['t'] == "gegevens"): ?>
          <div class="bg-blue-100 text-blue-800 p-3 rounded mb-4 flex justify-between items-start">
            <p class="text-sm font-medium"><?= htmlspecialchars($_GET['e']) ?></p>
            <button onclick="this.parentElement.style.display='none'" class="text-blue-500 hover:text-blue-800"><i class="fas fa-times"></i></button>
          </div>
        <?php endif; ?>
        <form method="POST" action="instellingen_helper.php">
          <h3 class="text-lg font-bold mb-4">Gegevens wijzigen</h3>
          
          <div class="space-y-3">
            <div>
              <label class="block text-sm font-semibold mb-1 opacity-80">Gebruikersnaam</label>
              <input name="username" type="text" value="<?= htmlspecialchars($username) ?>" required minlength="5" maxlength="32" class="w-full border rounded px-3 py-2 text-gray-800 outline-none focus:ring-1 focus:ring-blue-500">
            </div>
            <div>
              <label class="block text-sm font-semibold mb-1 opacity-80">Voornaam</label>
              <input name="firstname" type="text" value="<?= htmlspecialchars(ucfirst($vn)) ?>" required class="w-full border rounded px-3 py-2 text-gray-800 outline-none focus:ring-1 focus:ring-blue-500">
            </div>
            <div>
              <label class="block text-sm font-semibold mb-1 opacity-80">Achternaam</label>
              <input name="lastname" type="text" value="<?= htmlspecialchars(ucfirst($an)) ?>" required class="w-full border rounded px-3 py-2 text-gray-800 outline-none focus:ring-1 focus:ring-blue-500">
            </div>
            <div>
              <label class="block text-sm font-semibold mb-1 opacity-80">Email</label>
              <input name="email" type="email" value="<?= htmlspecialchars($email) ?>" required class="w-full border rounded px-3 py-2 text-gray-800 outline-none focus:ring-1 focus:ring-blue-500">
            </div>
          </div>
          
          <div class="mt-5 text-center">
            <button type="submit" class="theme-bg-primary text-white font-bold py-2 px-6 rounded hover:opacity-90 transition shadow-sm">Verander</button>
          </div>
        </form>
      </div>

      <!-- Wachtwoord Wijzigen -->
      <div class="theme-card rounded border shadow-sm p-5">
        <?php if (isset($_GET['t']) && $_GET['t'] == "wachtwoord"): ?>
          <div class="bg-blue-100 text-blue-800 p-3 rounded mb-4 flex justify-between items-start">
            <p class="text-sm font-medium"><?= htmlspecialchars($_GET['e']) ?></p>
            <button onclick="this.parentElement.style.display='none'" class="text-blue-500 hover:text-blue-800"><i class="fas fa-times"></i></button>
          </div>
        <?php endif; ?>
        <form method="POST" action="instellingen_helper.php">
          <h3 class="text-lg font-bold mb-4">Wachtwoord wijzigen</h3>
          
          <div class="space-y-3">
            <div>
              <label class="block text-sm font-semibold mb-1 opacity-80">Wachtwoord</label>
              <input name="pswd0" type="password" placeholder="Nieuw Wachtwoord" required minlength="8" class="w-full border rounded px-3 py-2 text-gray-800 outline-none focus:ring-1 focus:ring-blue-500">
            </div>
            <div>
              <label class="block text-sm font-semibold mb-1 opacity-80">Herhaal Wachtwoord</label>
              <input name="pswd1" type="password" placeholder="Herhaal Wachtwoord" required minlength="8" class="w-full border rounded px-3 py-2 text-gray-800 outline-none focus:ring-1 focus:ring-blue-500">
            </div>
          </div>
          
          <div class="mt-5 text-center">
            <button type="submit" class="theme-bg-primary text-white font-bold py-2 px-6 rounded hover:opacity-90 transition shadow-sm">Verander</button>
          </div>
        </form>
      </div>

      <!-- Profielfoto Wijzigen -->
      <div class="theme-card rounded border shadow-sm p-5">
        <?php if (isset($_GET['t']) && $_GET['t'] == "profielfoto"): ?>
          <div class="bg-blue-100 text-blue-800 p-3 rounded mb-4 flex justify-between items-start">
            <p class="text-sm font-medium"><?= htmlspecialchars($_GET['e']) ?></p>
            <button onclick="this.parentElement.style.display='none'" class="text-blue-500 hover:text-blue-800"><i class="fas fa-times"></i></button>
          </div>
        <?php endif; ?>
        <form method="POST" action="instellingen_helper.php" enctype="multipart/form-data">
          <h3 class="text-lg font-bold mb-4">Profielfoto</h3>
          
          <div class="flex flex-col items-center mb-4">
            <?php if ($profile_picture): ?>
              <img src="<?= $notInAdminfolder ?? '' ?>profile_image.php?hash=<?= urlencode($profile_picture) ?>&res=high" alt="Profielfoto" class="w-32 h-32 rounded-full object-cover shadow-md mb-2 border-2 border-gray-200">
            <?php else: ?>
              <div class="w-32 h-32 rounded-full theme-bg-primary text-white flex items-center justify-center font-bold text-4xl shadow-md mb-2">
                 <?php echo strtoupper(substr($vn ?? 'U', 0, 1)); ?>
              </div>
            <?php endif; ?>
          </div>

          <div class="space-y-3">
            <div>
              <label class="block text-sm font-semibold mb-1 opacity-80">Nieuwe foto uploaden</label>
              <input name="profile_picture" type="file" accept="image/jpeg, image/png, image/webp" class="w-full border rounded px-3 py-2 text-gray-800 outline-none focus:ring-1 focus:ring-blue-500 cursor-pointer bg-white">
            </div>
          </div>
          
          <div class="mt-5 text-center flex gap-2 justify-center">
            <button type="submit" class="theme-bg-primary text-white font-bold py-2 px-6 rounded hover:opacity-90 transition shadow-sm">Uploaden</button>
            <?php if ($profile_picture): ?>
            <a href="instellingen_helper.php?delete_profile_picture=1" class="bg-red-500 text-white font-bold py-2 px-4 rounded hover:bg-red-600 transition shadow-sm"><i class="fas fa-trash-alt"></i></a>
            <?php endif; ?>
          </div>
        </form>
      </div>

      <!-- Theme Switcher -->
      <div class="theme-card rounded border shadow-sm p-5">
        <h3 class="text-lg font-bold mb-4">Thema Voorkeur</h3>
        <p class="text-sm opacity-80 mb-4">Kies je favoriete kleurenschema. Deze wordt opgeslagen in je profiel en geladen op alle apparaten.</p>
        
        <div class="space-y-3">
          <select onchange="changeTheme(this.value)" class="w-full border rounded px-3 py-2 text-gray-800 outline-none focus:ring-1 focus:ring-blue-500 shadow-sm cursor-pointer">
            <option value="light" <?= $theme == 'light' ? 'selected' : '' ?>>Light</option>
            <option value="dark" <?= $theme == 'dark' ? 'selected' : '' ?>>Dark</option>
            <option value="rose-gold" <?= $theme == 'rose-gold' ? 'selected' : '' ?>>Rose Gold</option>
            <option value="cyber" <?= $theme == 'cyber' ? 'selected' : '' ?>>Cyber</option>
            <option value="nature" <?= $theme == 'nature' ? 'selected' : '' ?>>Nature</option>
            <option value="coral" <?= $theme == 'coral' ? 'selected' : '' ?>>Coral</option>
          </select>
        </div>
        
        <script>
        function changeTheme(newTheme) {
            var xhttp = new XMLHttpRequest();
            xhttp.onreadystatechange = function() {
                if (this.readyState == 4 && this.status == 200) {
                    window.location.href = window.location.pathname + window.location.search;
                }
            };
            xhttp.open("GET", "<?= $notInAdminfolder ?>functies.php?set_theme=" + newTheme, true);
            xhttp.send();
        }
        </script>
      </div>

      <!-- Notificaties -->
      <div class="theme-card rounded border shadow-sm p-5 lg:col-span-2 xl:col-span-3" id="notificaties">
        <?php if (isset($_GET['t']) && $_GET['t'] == "notificaties"): ?>
          <div class="bg-blue-100 text-blue-800 p-3 rounded mb-4 flex justify-between items-start">
            <p class="text-sm font-medium"><?= htmlspecialchars($_GET['e']) ?></p>
            <button onclick="this.parentElement.style.display='none'" class="text-blue-500 hover:text-blue-800"><i class="fas fa-times"></i></button>
          </div>
        <?php endif; ?>
        <h3 class="text-lg font-bold mb-4">Notificaties</h3>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
          <!-- Gekoppelde apparaten -->
          <div>
            <h4 class="font-semibold mb-3 border-b pb-2">Gekoppelde Apparaten</h4>
            <?php if (empty($subscriptions)): ?>
              <p class="text-sm opacity-70 mb-4">Je hebt nog geen apparaten gekoppeld voor push notificaties.</p>
            <?php else: ?>
              <ul class="space-y-2 mb-4">
                <?php foreach ($subscriptions as $sub): ?>
                  <li class="flex justify-between items-center theme-override-bg bg-opacity-50 p-2 rounded border theme-card-border text-sm">
                    <span class="truncate max-w-[150px] sm:max-w-[200px]" title="<?= htmlspecialchars($sub['device_name']) ?>">
                      <i class="fas fa-mobile-alt mr-2 opacity-50"></i><?= htmlspecialchars($sub['device_name']) ?>
                    </span>
                    <div>
                      <button onclick="renameDevice(<?= $sub['id'] ?>, '<?= addslashes(htmlspecialchars($sub['device_name'])) ?>')" class="text-blue-500 hover:text-blue-700 p-1 mr-1" title="Hernoem apparaat">
                        <i class="fas fa-edit"></i>
                      </button>
                      <button onclick="unsubscribeDevice('<?= htmlspecialchars($sub['endpoint']) ?>')" class="text-red-500 hover:text-red-700 p-1" title="Verwijder apparaat">
                        <i class="fas fa-trash-alt"></i>
                      </button>
                    </div>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
            <button onclick="requestAndSubscribeToPush()" class="theme-bg-primary text-white text-sm font-bold py-2 px-4 rounded hover:opacity-90 transition shadow-sm w-full sm:w-auto">
              <i class="fas fa-bell mr-2"></i> Zet meldingen aan voor dit apparaat
            </button>
            <script>
            function renameDevice(id, currentName) {
                document.getElementById('rename_device_id').value = id;
                document.getElementById('new_device_name').value = currentName;
                document.getElementById('renameModal').classList.remove('hidden');
            }
            function unsubscribeDevice(endpoint) {
                document.getElementById('deleteModal').classList.remove('hidden');
                document.getElementById('confirmDeleteBtn').onclick = function() {
                    document.getElementById('deleteModal').classList.add('hidden');
                    // Force removing it from the browser push manager as well if it's the current device
                    if ('serviceWorker' in navigator) {
                        navigator.serviceWorker.ready.then(function(reg) {
                            reg.pushManager.getSubscription().then(function(sub) {
                                if (sub && sub.endpoint === endpoint) {
                                    sub.unsubscribe();
                                }
                            });
                        }).finally(function() {
                            doDeleteEndpoint(endpoint);
                        });
                    } else {
                        doDeleteEndpoint(endpoint);
                    }
                };
            }
            function doDeleteEndpoint(endpoint) {
                fetch('/api/subscribe.php?endpoint=' + encodeURIComponent(endpoint), {
                    method: 'DELETE'
                }).then(() => window.location.reload());
            }
            </script>
          </div>

          <!-- Voorkeuren -->
          <div>
            <h4 class="font-semibold mb-3 border-b pb-2">Melding Voorkeuren</h4>
            <form method="POST" action="instellingen_helper.php">
              <div class="space-y-3 mb-4">
                <?php
                $channels = [
                    'welkomsberichten' => 'Welkomsberichten',
                    'assignment_changes' => 'Wijzigingen in je opdracht',
                    'vosstatus' => 'Vosstatussen',
                    'locatiestatus' => 'Voslocatie status',
                    'hints' => 'Elke nieuwe Hint',
                    'opdrachten' => 'Elke nieuwe Opdracht',
                    'nieuws' => 'Elk nieuw Nieuws artikel'
                ];
                foreach ($channels as $key => $label):
                  $default_val = in_array($key, ['welkomsberichten', 'assignment_changes']);
                  $is_enabled = isset($notification_prefs[$key]) ? $notification_prefs[$key] : $default_val;
                  $checked = $is_enabled ? 'checked' : '';
                ?>
                <label class="flex items-center space-x-3 cursor-pointer">
                  <input type="checkbox" name="notif_<?= $key ?>" value="1" <?= $checked ?> class="form-checkbox h-4 w-4 text-blue-600 transition duration-150 ease-in-out cursor-pointer">
                  <span class="text-sm font-medium opacity-90"><?= htmlspecialchars($label) ?></span>
                </label>
                <?php endforeach; ?>
              </div>
              <button type="submit" name="save_notif_prefs" class="bg-green-500 text-white text-sm font-bold py-2 px-6 rounded hover:bg-green-600 transition shadow-sm">
                Voorkeuren Opslaan
              </button>
            </form>
          </div>
        </div>
      </div>

    </div>
  </main>

  <!-- Delete Modal -->
  <div id="deleteModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-sm" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen px-4 text-center">
        <div class="relative inline-block theme-card rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md w-full">
            <div class="bg-red-600 px-4 py-3 sm:px-6 flex justify-between items-center text-white">
                <h4 class="text-lg font-bold"><i class="fas fa-exclamation-triangle mr-2"></i>Apparaat Verwijderen</h4>
                <button type="button" onclick="document.getElementById('deleteModal').classList.add('hidden')" class="hover:text-gray-200 transition"><i class="fas fa-times text-xl"></i></button>
            </div>
            <div class="p-6">
                <p class="mb-2">Weet je zeker dat je dit apparaat wilt ontkoppelen?</p>
            </div>
            <div class="bg-black/5 px-4 py-3 sm:px-6 flex flex-row-reverse gap-3 border-t theme-card-border">
                <button id="confirmDeleteBtn" class="bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded transition shadow-sm w-full sm:w-auto">Verwijderen</button>
                <button type="button" onclick="document.getElementById('deleteModal').classList.add('hidden')" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded transition shadow-sm w-full sm:w-auto">Annuleren</button>
            </div>
        </div>
    </div>
  </div>

  <!-- Rename Modal -->
  <div id="renameModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-sm" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen px-4 text-center">
        <div class="relative inline-block theme-card rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md w-full">
            <div class="theme-bg-primary px-4 py-3 sm:px-6 flex justify-between items-center text-white">
                <h4 class="text-lg font-bold"><i class="fas fa-edit mr-2"></i>Apparaat Hernoemen</h4>
                <button type="button" onclick="document.getElementById('renameModal').classList.add('hidden')" class="hover:text-gray-200 transition"><i class="fas fa-times text-xl"></i></button>
            </div>
            <form id="renameForm" action="instellingen_helper.php" method="POST">
                <div class="p-6">
                    <input type="hidden" id="rename_device_id" name="rename_device_id">
                    <label class="block text-sm font-bold opacity-70 mb-1">Nieuwe naam voor dit apparaat</label>
                    <input class="w-full border rounded-lg px-3 py-2 text-gray-800 outline-none focus:ring-1 focus:ring-blue-500 shadow-sm" type="text" id="new_device_name" name="new_device_name" required>
                </div>
                <div class="bg-black/5 px-4 py-3 sm:px-6 flex flex-row-reverse gap-3 border-t theme-card-border">
                    <button type="submit" class="theme-bg-primary hover:opacity-80 text-white font-bold py-2 px-4 rounded transition shadow-sm w-full sm:w-auto">Opslaan</button>
                    <button type="button" onclick="document.getElementById('renameModal').classList.add('hidden')" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded transition shadow-sm w-full sm:w-auto">Annuleren</button>
                </div>
            </form>
        </div>
    </div>
  </div>

  <!-- Footer -->
  <?php require_once('includes/footer.php') ?>
</div>

<script>



if ("<?php echo $_SESSION['gps']?>" == "true"){

  setInterval(function() {

    GPSrefresh();

  }, 5555);

}

  

 

 function GPSrefresh() {

    if (navigator.geolocation) {

        navigator.geolocation.getCurrentPosition(showPosition);

    } else {

        console.log("Geolocation is not supported by this browser.");

    }

    function showPosition(position) {

     console.log("Latitude: " + position.coords.latitude + 

      "<br>Longitude: " + position.coords.longitude);

      

      

      if (window.XMLHttpRequest) {

            // code for IE7+, Firefox, Chrome, Opera, Safari

            xmlhttp = new XMLHttpRequest();

        } else {

            // code for IE6, IE5

            xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");

        }

        xmlhttp.onreadystatechange = function() {

            if (this.readyState == 4 && this.status == 200) {

            }

        };

        xmlhttp.open("GET","functies.php?lat="+position.coords.latitude+"&lon="+position.coords.longitude,true);

        xmlhttp.send();

    }

   

   



 } 

  

  

</script>



</body>

</html>