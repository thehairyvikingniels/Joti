<?php
define("PAGE_NAME", "sa_settings");
session_start();

if (!isset($_SESSION['id'])) {
    header("Location: ../index");
    exit();
}

require("../dblogin.php");
require_once("../functies.php");


// Get userdata
$stmt = $conn->prepare("SELECT voornaam, priv FROM Gebruikers WHERE id=?");
$stmt->bind_param("i", $_SESSION['id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $vn = $row['voornaam'];
    $priv = $row['priv'];
} else {
    // Failsafe als de gebruiker niet (meer) bestaat
    session_destroy();
    header("Location: ../index");
    exit();
}
$stmt->close();

if ($priv < 3) {
    header("Location: ../home");
    exit();
}

// Get global site settings (voor het inladen van eventuele basis-variabelen)
$stmt_settings = $conn->prepare("SELECT Instelling, Waarde FROM Site_Instellingen");
$stmt_settings->execute();
$result_settings = $stmt_settings->get_result();
$siteSettings = array();

if ($result_settings->num_rows > 0) {
    while($row = $result_settings->fetch_assoc()) {
      $siteSettings[$row['Instelling']] = $row['Waarde'];
    }
} else {
    echo "0 results";
    $stmt_settings->close();
    exit();
}
$stmt_settings->close();

$succes_message = '';
$error_message = '';

// Verwerken van formulier om instellingen te UPDATEN
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'update_settings') {
    $all_updates_successful = true;
    
    // Prepared statement één keer voorbereiden buiten de loop (optimaal voor performance)
    $stmt_upd = $conn->prepare("UPDATE Site_Instellingen SET Waarde = ? WHERE Instelling = ?");

    if ($stmt_upd) {
        $uitzonderingen = ['action', 'add_setting_name', 'add_setting_value', 'add_setting_description'];
        
        foreach ($_POST as $instelling => $waarde) {
            // Sla verborgen of toevoeg-velden over
            if (!in_array($instelling, $uitzonderingen)) {
                $inst_clean = trim($instelling);
                $waarde_clean = trim($waarde);
                
                $stmt_upd->bind_param("ss", $waarde_clean, $inst_clean);
                if (!$stmt_upd->execute()) {
                    $all_updates_successful = false;
                    $error_message = "Fout bij het bijwerken van de instelling: " . htmlspecialchars($inst_clean);
                    break; // Stop de loop bij de eerste de beste fout
                }
            }
        }
        $stmt_upd->close();
        
        if ($all_updates_successful) {
            $succes_message = "De instellingen zijn succesvol opgeslagen!";
        }
    } else {
        $error_message = "Er is een fout opgetreden bij het voorbereiden van de database-update: " . $conn->error;
    }
}

// Verwerken van formulier om nieuwe instelling TOE TE VOEGEN
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'add_setting') {
    $newName = trim($_POST['add_setting_name'] ?? '');
    $newValue = trim($_POST['add_setting_value'] ?? '');
    $newDescription = trim($_POST['add_setting_description'] ?? '');

    if (!empty($newName)) {
        // Controleer of de instelling al bestaat
        $check_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM Site_Instellingen WHERE Instelling = ?");
        $check_stmt->bind_param("s", $newName);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $check_row = $check_result->fetch_assoc();
        $check_stmt->close();

        if ($check_row['cnt'] > 0) {
            $error_message = "Instelling met de naam '" . htmlspecialchars($newName) . "' bestaat al.";
        } else {
            // Voeg toe
            $insert_stmt = $conn->prepare("INSERT INTO Site_Instellingen (Instelling, Waarde, Omschrijving) VALUES (?, ?, ?)");
            if ($insert_stmt) {
                $insert_stmt->bind_param("sss", $newName, $newValue, $newDescription);
                if ($insert_stmt->execute()) {
                    $succes_message = "Nieuwe instelling '" . htmlspecialchars($newName) . "' is succesvol toegevoegd!";
                } else {
                    $error_message = "Fout bij het toevoegen van de instelling: " . $insert_stmt->error;
                }
                $insert_stmt->close();
            } else {
                $error_message = "Er is een fout opgetreden bij het voorbereiden van de database-invoeging.";
            }
        }
    } else {
        $error_message = "Naam van de instelling mag niet leeg zijn.";
    }
}

// Verwerken van VERWIJDEREN van een instelling
if (isset($_GET['delete_setting'])) {
    $setting_to_delete = trim($_GET['delete_setting']);
    
    if (!empty($setting_to_delete)) {
        $delete_stmt = $conn->prepare("DELETE FROM Site_Instellingen WHERE Instelling = ?");
        if ($delete_stmt) {
            $delete_stmt->bind_param("s", $setting_to_delete);
            if ($delete_stmt->execute()) {
                $succes_message = "Instelling '" . htmlspecialchars($setting_to_delete) . "' is succesvol verwijderd!";
            } else {
                $error_message = "Fout bij het verwijderen van de instelling: " . $delete_stmt->error;
            }
            $delete_stmt->close();
        } else {
            $error_message = "Er is een fout opgetreden bij het voorbereiden van de database-verwijdering.";
        }
    }
}

// Haal alle huidige instellingen op voor weergave in het formulier
$stmt_all = $conn->prepare("SELECT Instelling, Waarde, Omschrijving FROM Site_Instellingen ORDER BY Instelling ASC");
$stmt_all->execute();
$result_all = $stmt_all->get_result();
$settings = [];

if ($result_all->num_rows > 0) {
    while ($row = $result_all->fetch_assoc()) {
        $settings[] = $row;
    }
}
$stmt_all->close();

// Fetch groups for the GROUP_ID dropdown
$stmt_groups = $conn->prepare("SELECT id, naam FROM Groepen ORDER BY naam ASC");
$stmt_groups->execute();
$result_groups = $stmt_groups->get_result();
$groepen_options = [];
if ($result_groups->num_rows > 0) {
    while ($r = $result_groups->fetch_assoc()) {
        $groepen_options[] = $r;
    }
}
$stmt_groups->close();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<title>Jotihunt - Instellingen</title>
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


    <div class="space-y-6 mb-24 max-w-5xl">
      <?php if (!empty($succes_message)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative shadow-sm">
          <span onclick="this.parentElement.style.display='none'" class="absolute top-0 bottom-0 right-0 px-4 py-3 cursor-pointer">
            <i class="fas fa-times opacity-70 hover:opacity-100 transition"></i>
          </span>
          <p><?php echo htmlspecialchars($succes_message); ?></p>
        </div>
      <?php endif; ?>
      
      <?php if (!empty($error_message)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative shadow-sm">
          <span onclick="this.parentElement.style.display='none'" class="absolute top-0 bottom-0 right-0 px-4 py-3 cursor-pointer">
            <i class="fas fa-times opacity-70 hover:opacity-100 transition"></i>
          </span>
          <p><?php echo htmlspecialchars($error_message); ?></p>
        </div>
      <?php endif; ?>

      <!-- Bewerk Bestaande Instellingen -->
      <div class="theme-card rounded border shadow-sm overflow-hidden">
        <div class="theme-card-header px-6 py-4 border-b text-white" style="background-color: var(--theme-sidebar-active); border-color: var(--theme-card-border);">
          <h3 class="text-xl font-bold">Bewerk Bestaande Instellingen</h3>
        </div>
        <div class="p-6">
          <form method="POST" action="settings" class="space-y-8">
            <input type="hidden" name="action" value="update_settings">
            
            <div class="space-y-6">
            <?php foreach ($settings as $setting): ?>
              <div class="p-4 rounded-lg bg-black/5 border" style="border-color: var(--theme-card-border);">
                <div class="mb-3">
                  <label class="block font-bold opacity-80"><?php echo htmlspecialchars($setting['Instelling']); ?></label>
                  <p class="text-sm opacity-60"><?php echo htmlspecialchars($setting['Omschrijving']); ?></p>
                </div>
                
                <div class="flex flex-row gap-2 sm:gap-4 items-center">
                    <?php if ($setting['Instelling'] === 'GROUP_ID'): ?>
                        <div class="flex-1 min-w-0">
                            <?php if (empty($groepen_options)): ?>
                                <select class="w-full border rounded-lg px-3 py-2 outline-none focus:ring-1 focus:ring-blue-500 shadow-sm bg-white text-gray-800" name="<?php echo htmlspecialchars($setting['Instelling']); ?>" required>
                                    <option value="0">Placeholder (No Groups Loaded)</option>
                                </select>
                                <a href="../cron/subscriptions.php" target="_blank" class="text-blue-500 hover:text-blue-600 text-sm mt-1 inline-block transition"><i class="fas fa-sync mr-1"></i>Haal groepen op</a>
                            <?php else: ?>
                                <select class="w-full border rounded-lg px-3 py-2 outline-none focus:ring-1 focus:ring-blue-500 shadow-sm bg-white text-gray-800" name="<?php echo htmlspecialchars($setting['Instelling']); ?>" required>
                                    <option value="0" <?php echo ($setting['Waarde'] == '0') ? 'selected' : ''; ?>>Placeholder (No Groups Loaded)</option>
                                    <?php foreach ($groepen_options as $groep): ?>
                                        <option value="<?php echo htmlspecialchars($groep['id']); ?>" <?php echo ($setting['Waarde'] == $groep['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($groep['naam']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="flex-1 min-w-0">
                            <input class="w-full border rounded-lg px-3 py-2 outline-none focus:ring-1 focus:ring-blue-500 shadow-sm bg-white text-gray-800" type="text" name="<?php echo htmlspecialchars($setting['Instelling']); ?>" value="<?php echo htmlspecialchars($setting['Waarde']); ?>" required>
                        </div>
                    <?php endif; ?>
                    
                    <button type="button" onclick="confirmDelete('<?php echo htmlspecialchars($setting['Instelling']); ?>')" class="bg-red-500 hover:bg-red-600 text-white p-2 rounded-lg transition shadow-sm flex-shrink-0 w-10 h-10 flex justify-center items-center" title="Verwijder instelling">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
              </div>
            <?php endforeach; ?>
            </div>
            
            <div class="pt-4 border-t" style="border-color: var(--theme-card-border);">
              <button type="submit" class="theme-bg-primary text-white font-bold py-3 px-8 rounded shadow-sm hover:opacity-90 transition"><i class="fas fa-save mr-2"></i>Instellingen Opslaan</button>
            </div>
          </form>
        </div>
      </div>

      <!-- Voeg Nieuwe Instelling Toe -->
      <div class="theme-card rounded border shadow-sm overflow-hidden mt-8">
        <div class="theme-card-header px-6 py-4 border-b text-white" style="background-color: var(--theme-sidebar-active); border-color: var(--theme-card-border);">
          <h3 class="text-xl font-bold">Voeg Nieuwe Instelling Toe</h3>
        </div>
        <div class="p-6">
          <form method="POST" action="settings" class="space-y-6">
            <input type="hidden" name="action" value="add_setting">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label class="block text-sm font-bold opacity-70 mb-1 uppercase tracking-wide">Instelling Naam (Uniek)</label>
                <input class="w-full border rounded-lg px-3 py-2 text-gray-800 outline-none focus:ring-1 focus:ring-blue-500 shadow-sm" type="text" name="add_setting_name" placeholder="Bijv. joti_startdatum" required>
              </div>
              
              <div>
                <label class="block text-sm font-bold opacity-70 mb-1 uppercase tracking-wide">Waarde</label>
                <input class="w-full border rounded-lg px-3 py-2 text-gray-800 outline-none focus:ring-1 focus:ring-blue-500 shadow-sm" type="text" name="add_setting_value" placeholder="Bijv. 2023-10-13 18:00:00" required>
              </div>
            </div>
            
            <div>
              <label class="block text-sm font-bold opacity-70 mb-1 uppercase tracking-wide">Omschrijving</label>
              <input class="w-full border rounded-lg px-3 py-2 text-gray-800 outline-none focus:ring-1 focus:ring-blue-500 shadow-sm" type="text" name="add_setting_description" placeholder="Korte omschrijving van deze instelling">
            </div>
            
            <div class="pt-4 border-t" style="border-color: var(--theme-card-border);">
              <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-8 rounded shadow-sm transition"><i class="fas fa-plus mr-2"></i>Nieuwe Instelling Toevoegen</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </main>

  <!-- Delete Modal -->
  <div id="deleteModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-sm" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
      
      <div class="relative inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full">
        
        <div class="bg-red-600 px-4 py-3 sm:px-6 flex justify-between items-center">
          <h3 class="text-lg leading-6 font-medium text-white" id="modal-title">
            <i class="fas fa-exclamation-triangle mr-2"></i>Bevestig Verwijdering
          </h3>
          <button onclick="document.getElementById('deleteModal').classList.add('hidden')" class="text-white hover:text-gray-200 focus:outline-none">
            <i class="fas fa-times text-xl"></i>
          </button>
        </div>
        
        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4 text-gray-800">
          <div class="sm:flex sm:items-start">
            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
              <div class="mt-2">
                <p id="deleteModalText" class="text-sm mb-4"></p>
                <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded text-sm text-red-700">
                    <p class="font-bold mb-1">LET OP:</p>
                    <p>Het verwijderen van essentiële instellingen kan de werking van de website permanent verstoren!</p>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
          <a id="confirmDeleteButton" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm transition">
            Verwijderen
          </a>
          <button type="button" onclick="document.getElementById('deleteModal').classList.add('hidden')" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm transition">
            Annuleren
          </button>
        </div>
        
      </div>
    </div>
  </div>

  <!-- Footer -->
  <?php require_once('../includes/footer.php') ?>
</div>

<script>
if ("<?php echo $_SESSION['gps'] ?? 'false' ?>" == "true"){
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
        console.log("Latitude: " + position.coords.latitude + "\nLongitude: " + position.coords.longitude);
        
        var xmlhttp;
        if (window.XMLHttpRequest) {
            xmlhttp = new XMLHttpRequest();
        } else {
            xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
        }
        xmlhttp.onreadystatechange = function() {
            if (this.readyState == 4 && this.status == 200) {
            }
        };
        xmlhttp.open("GET", "../functies.php?lat=" + position.coords.latitude + "&lon=" + position.coords.longitude, true);
        xmlhttp.send();
    }
}

// Show modal for deleting a setting
function confirmDelete(settingName) {
    // Set the dynamic text in the modal
    document.getElementById('deleteModalText').innerHTML = "Weet je zeker dat je de instelling '<strong>" + settingName + "</strong>' wilt verwijderen?";
    
    // Set the href for the final delete button
    var deleteUrl = "settings?delete_setting=" + encodeURIComponent(settingName);
    document.getElementById('confirmDeleteButton').href = deleteUrl;

    // Show the modal
    document.getElementById('deleteModal').classList.remove('hidden');
}
</script>

</body>
</html>