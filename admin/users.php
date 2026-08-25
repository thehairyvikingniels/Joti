<?php
// Administrative interface for viewing registered users, modifying privilege levels, resetting passwords, and deleting accounts.
define("PAGE_NAME", "a_users");
require_once(__DIR__ . '/../includes/auth.php');
if ($privilege < 2) {
    header("Location: ../home");
    exit();
}

$succes = isset($_GET['msg']);
$succes_msg = "";
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'success') $succes_msg = "Gebruiker succesvol bijgewerkt!";
    elseif ($_GET['msg'] === 'password_reset') $succes_msg = "Wachtwoord succesvol gereset!";
    elseif ($_GET['msg'] === 'pic_success') $succes_msg = "Profielfoto succesvol geupload!";
    else $succes_msg = "Actie succesvol uitgevoerd!";
}
$error_msg = $_GET['error'] ?? '';

// Fetch all users into array to avoid duplicate queries
$users_data = [];
$stmt_users = $conn->prepare("SELECT id, voornaam, achternaam, email, priv, first_login, last_login, profile_picture FROM Gebruikers ORDER BY id ASC");
$stmt_users->execute();
$result_users = $stmt_users->get_result();

if ($result_users->num_rows > 0) {
    while($row = $result_users->fetch_assoc()) {
        $users_data[] = $row;
    }
}
$stmt_users->close();

?>

<!DOCTYPE html>
<html lang="nl">
<head>
<title>Jotify - Gebruikers Beheer</title>
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

    <div class="space-y-6 mb-24">
      
      <!-- Users Table Card -->
      <div class="theme-card rounded-xl border shadow-sm overflow-hidden w-full mb-6">
        <div class="theme-card-header px-6 py-4 border-b text-white" style="background-color: var(--theme-sidebar-active); border-color: var(--theme-card-border);">
          <h3 class="text-xl font-bold">Gebruikers</h3>
        </div>
        <div class="p-0">
          <?php if (!empty($succes_msg)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 m-4 rounded relative shadow-sm">
              <span onclick="this.parentElement.style.display='none'" class="absolute top-0 bottom-0 right-0 px-4 py-3 cursor-pointer">
                <i class="fas fa-times opacity-70 hover:opacity-100 transition"></i>
              </span>
              <p class="font-bold"><?= htmlspecialchars($succes_msg) ?></p>
            </div>
          <?php elseif (!empty($error_msg)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 m-4 rounded relative shadow-sm">
              <span onclick="this.parentElement.style.display='none'" class="absolute top-0 bottom-0 right-0 px-4 py-3 cursor-pointer">
                <i class="fas fa-times opacity-70 hover:opacity-100 transition"></i>
              </span>
              <p><?= htmlspecialchars($error_msg) ?></p>
            </div>
          <?php endif; ?>
          
          <!-- Desktop Table -->
          <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-sm text-left whitespace-nowrap">
              <thead class="text-xs uppercase bg-black/5 border-b" style="border-color: var(--theme-card-border);">
                <tr>
                  <th class="px-6 py-3 font-bold">ID</th>
                  <th class="px-6 py-3 font-bold">Naam</th>
                  <th class="px-6 py-3 font-bold">Email</th>
                  <th class="px-6 py-3 font-bold">Laatste login</th>
                  <th class="px-6 py-3 font-bold">Eerste login</th>
                  <th class="px-6 py-3 font-bold text-right">Acties</th>
                </tr>
              </thead>
              <tbody class="divide-y" style="border-color: var(--theme-card-border);">
              <?php
              foreach($users_data as $row) {
                  $priv0 = ($row['priv'] == 0) ? "selected" : "";
                  $priv1 = ($row['priv'] == 1) ? "selected" : "";
                  $priv2 = ($row['priv'] == 2) ? "selected" : "";
                  $priv3 = ($row['priv'] == 3) ? "selected" : "";
                  
                  $can_impersonate = false;
                  if ($_SESSION['priv'] >= 3 && $row['priv'] <= 2) $can_impersonate = true;
                  if ($_SESSION['priv'] == 2 && $row['priv'] <= 1) $can_impersonate = true;

                  echo "<tr class='hover:bg-black/5 transition'>";
                  echo "  <td class='px-6 py-4 font-bold opacity-70'>".htmlspecialchars($row["id"])."</td>";
                  echo "  <td class='px-6 py-4 font-medium flex items-center space-x-3'>";
                  if ($row['profile_picture']) {
                      echo "<img src='../profile_image.php?hash=".urlencode($row['profile_picture'])."&res=low' class='w-8 h-8 rounded-full object-cover shadow-sm'>";
                  } else {
                      echo "<div class='w-8 h-8 rounded-full theme-bg-primary text-white flex items-center justify-center font-bold text-sm shadow-sm'>".strtoupper(substr($row['voornaam'], 0, 1))."</div>";
                  }
                  echo "    <div>".htmlspecialchars($row["voornaam"])."<br><span class='opacity-70 text-xs'>".htmlspecialchars($row["achternaam"])."</span></div>";
                  echo "  </td>";
                  echo "  <td class='px-6 py-4 opacity-80'>".htmlspecialchars($row["email"])."</td>";
                  echo "  <td class='px-6 py-4 opacity-80'>".htmlspecialchars(time2str($row['last_login']))."</td>";
                  echo "  <td class='px-6 py-4 opacity-80'>".htmlspecialchars(time2str($row['first_login']))."</td>";
                  
                  echo "  <td class='px-6 py-4 text-right'>";
                  echo "    <form id='priv_form_desk_".$row['id']."' action='users_helper.php' method='POST' class='flex items-center justify-end gap-2'>";
                  echo "      <input type='hidden' value='".htmlspecialchars($row['id'])."' name='user'>";
                  echo "      <select class='theme-override-bg theme-override-text border rounded-lg px-2.5 py-1 text-xs outline-none focus:ring-2 focus:ring-blue-500 shadow-sm' name='priv'>";
                  echo "        <option value='0' ".$priv0.">Gast</option>";
                  echo "        <option value='1' ".$priv1.">Vossenjager</option>";
                  echo "        <option value='2' ".$priv2.">Admin</option>";
                  echo "        <option value='3' ".$priv3.">Superadmin</option>";
                  echo "        <option value='4' class='text-red-500 font-bold'>Verwijder</option>";
                  echo "      </select>";
                  echo "      <button type='button' class='theme-bg-primary hover:opacity-80 text-white p-2 rounded shadow-sm transition' onclick=\"document.getElementById('priv_modal_desk_".$row['id']."').classList.remove('hidden')\" title='Opslaan'><i class='fas fa-check'></i></button>";
                  
                  if ($can_impersonate) {
                      echo "  <button type='button' onclick=\"document.getElementById('imp_modal_".$row['id']."').classList.remove('hidden')\" class='bg-gray-700 hover:bg-gray-800 text-white p-2 rounded shadow-sm transition' title='Imiteren'><i class='fas fa-user-secret'></i></button>";
                      echo "  <button type='button' onclick=\"document.getElementById('reset_modal_".$row['id']."').classList.remove('hidden')\" class='bg-orange-500 hover:bg-orange-600 text-white p-2 rounded shadow-sm transition' title='Wachtwoord reset'><i class='fas fa-key'></i></button>";
                      echo "  <button type='button' onclick=\"document.getElementById('pic_modal_".$row['id']."').classList.remove('hidden')\" class='bg-blue-500 hover:bg-blue-600 text-white p-2 rounded shadow-sm transition' title='Foto uploaden'><i class='fas fa-image'></i></button>";
                  } else {
                      echo "  <button type='button' class='bg-gray-300 text-gray-500 p-2 rounded cursor-not-allowed opacity-50'><i class='fas fa-user-secret'></i></button>";
                      echo "  <button type='button' class='bg-gray-300 text-gray-500 p-2 rounded cursor-not-allowed opacity-50'><i class='fas fa-key'></i></button>";
                      echo "  <button type='button' class='bg-gray-300 text-gray-500 p-2 rounded cursor-not-allowed opacity-50'><i class='fas fa-image'></i></button>";
                  }
                  echo "    </form>";
                  echo "  </td>";
                  echo "</tr>";
                  
                  // Desktop Role Change Modal
                  echo "
                  <div id='priv_modal_desk_".$row['id']."' class='hidden fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-sm' aria-labelledby='modal-title' role='dialog' aria-modal='true'>
                    <div class='flex items-center justify-center min-h-screen px-4 text-center'>
                      <div class='relative inline-block bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full'>
                        <div class='theme-bg-primary px-4 py-3 sm:px-6 flex justify-between items-center text-white'>
                          <h3 class='text-lg font-bold'><i class='fas fa-question-circle mr-2'></i>Bevestiging</h3>
                          <button type='button' onclick=\"document.getElementById('priv_modal_desk_".$row['id']."').classList.add('hidden')\" class='hover:text-gray-200 transition'><i class='fas fa-times text-xl'></i></button>
                        </div>
                        <div class='bg-white px-4 pt-5 pb-4 sm:p-6 text-gray-800'>
                          <p>Weet je zeker dat je de rol/rechten van <strong>".htmlspecialchars($row['voornaam'])."</strong> wilt wijzigen?</p>
                        </div>
                        <div class='bg-gray-50 px-4 py-3 sm:px-6 flex flex-row-reverse gap-3'>
                          <button type='submit' form='priv_form_desk_".$row['id']."' class='bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded transition shadow-sm'>Ja, wijzig</button>
                          <button type='button' onclick=\"document.getElementById('priv_modal_desk_".$row['id']."').classList.add('hidden')\" class='bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded transition shadow-sm'>Annuleer</button>
                        </div>
                      </div>
                    </div>
                  </div>";
              }
              ?>
              </tbody>
            </table>
          </div>
          
          <!-- Mobile Table -->
          <div class="block md:hidden">
            <ul class="divide-y" style="border-color: var(--theme-card-border);">
            <?php
            foreach($users_data as $row) {
                $priv0 = ($row['priv'] == 0) ? "selected" : "";
                $priv1 = ($row['priv'] == 1) ? "selected" : "";
                $priv2 = ($row['priv'] == 2) ? "selected" : "";
                $priv3 = ($row['priv'] == 3) ? "selected" : "";
                
                $can_impersonate = false;
                if ($_SESSION['priv'] >= 3 && $row['priv'] <= 2) $can_impersonate = true;
                if ($_SESSION['priv'] == 2 && $row['priv'] <= 1) $can_impersonate = true;

                echo "<li class='p-4 hover:bg-black/5 transition'>";
                echo "  <div class='flex justify-between items-start mb-2'>";
                echo "    <div class='flex items-center space-x-3'>";
                if ($row['profile_picture']) {
                    echo "<img src='../profile_image.php?hash=".urlencode($row['profile_picture'])."&res=low' class='w-10 h-10 rounded-full object-cover shadow-sm'>";
                } else {
                    echo "<div class='w-10 h-10 rounded-full theme-bg-primary text-white flex items-center justify-center font-bold shadow-sm'>".strtoupper(substr($row['voornaam'], 0, 1))."</div>";
                }
                echo "      <div>";
                echo "        <p class='font-bold'>".htmlspecialchars($row["voornaam"])." ".htmlspecialchars($row["achternaam"])."</p>";
                echo "        <p class='text-sm opacity-80'>".htmlspecialchars($row["email"])."</p>";
                echo "      </div>";
                echo "    </div>";
                echo "    <div class='text-right text-sm opacity-70'>";
                echo "      <p><b>ID:</b> ".htmlspecialchars($row["id"])."</p>";
                echo "    </div>";
                echo "  </div>";
                
                echo "  <div class='text-xs opacity-70 mb-4 bg-black/5 p-2 rounded'>";
                echo "    <p><b>Laatste:</b> ".htmlspecialchars(time2str($row['last_login']))."</p>";
                echo "    <p><b>Eerste:</b> ".htmlspecialchars(time2str($row['first_login']))."</p>";
                echo "  </div>";
                
                echo "  <form id='priv_form_mob_".$row['id']."' action='users_helper.php' method='POST' class='flex items-center gap-2 w-full'>";
                echo "    <input type='hidden' value='".htmlspecialchars($row['id'])."' name='user'>";
                echo "    <select class='flex-1 border rounded px-2 py-2 outline-none focus:ring-1 focus:ring-blue-500 shadow-sm bg-white text-gray-800' name='priv'>";
                echo "      <option value='0' ".$priv0.">Gast</option>";
                echo "      <option value='1' ".$priv1.">Vossenjager</option>";
                echo "      <option value='2' ".$priv2.">Admin</option>";
                echo "      <option value='3' ".$priv3.">Superadmin</option>";
                echo "      <option value='4' class='text-red-500 font-bold'>Verwijder</option>";
                echo "    </select>";
                echo "    <button type='button' class='theme-bg-primary hover:opacity-80 text-white p-2 rounded shadow-sm transition' onclick=\"document.getElementById('priv_modal_mob_".$row['id']."').classList.remove('hidden')\"><i class='fas fa-check'></i></button>";
                
                if ($can_impersonate) {
                    echo "  <button type='button' onclick=\"document.getElementById('imp_modal_".$row['id']."').classList.remove('hidden')\" class='bg-gray-700 hover:bg-gray-800 text-white p-2 rounded shadow-sm transition'><i class='fas fa-user-secret'></i></button>";
                    echo "  <button type='button' onclick=\"document.getElementById('reset_modal_".$row['id']."').classList.remove('hidden')\" class='bg-orange-500 hover:bg-orange-600 text-white p-2 rounded shadow-sm transition'><i class='fas fa-key'></i></button>";
                    echo "  <button type='button' onclick=\"document.getElementById('pic_modal_".$row['id']."').classList.remove('hidden')\" class='bg-blue-500 hover:bg-blue-600 text-white p-2 rounded shadow-sm transition'><i class='fas fa-image'></i></button>";
                } else {
                    echo "  <button type='button' class='bg-gray-300 text-gray-500 p-2 rounded cursor-not-allowed opacity-50'><i class='fas fa-user-secret'></i></button>";
                    echo "  <button type='button' class='bg-gray-300 text-gray-500 p-2 rounded cursor-not-allowed opacity-50'><i class='fas fa-key'></i></button>";
                    echo "  <button type='button' class='bg-gray-300 text-gray-500 p-2 rounded cursor-not-allowed opacity-50'><i class='fas fa-image'></i></button>";
                }
                echo "  </form>";
                echo "</li>";
                
                // Mobile Role Change Modal
                echo "
                <div id='priv_modal_mob_".$row['id']."' class='hidden fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-sm' aria-labelledby='modal-title' role='dialog' aria-modal='true'>
                  <div class='flex items-center justify-center min-h-screen px-4 text-center'>
                    <div class='relative inline-block bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 w-full max-w-sm'>
                      <div class='theme-bg-primary px-4 py-3 flex justify-between items-center text-white'>
                        <h3 class='text-lg font-bold'><i class='fas fa-question-circle mr-2'></i>Bevestiging</h3>
                        <button type='button' onclick=\"document.getElementById('priv_modal_mob_".$row['id']."').classList.add('hidden')\" class='hover:text-gray-200 transition'><i class='fas fa-times text-xl'></i></button>
                      </div>
                      <div class='bg-white px-4 pt-5 pb-4 text-gray-800'>
                        <p>Weet je zeker dat je de rol/rechten van <strong>".htmlspecialchars($row['voornaam'])."</strong> wilt wijzigen?</p>
                      </div>
                      <div class='bg-gray-50 px-4 py-3 flex flex-row-reverse gap-3'>
                        <button type='submit' form='priv_form_mob_".$row['id']."' class='w-full bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded transition shadow-sm'>Ja, wijzig</button>
                        <button type='button' onclick=\"document.getElementById('priv_modal_mob_".$row['id']."').classList.add('hidden')\" class='w-full bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded transition shadow-sm'>Annuleer</button>
                      </div>
                    </div>
                  </div>
                </div>";
            }
            ?>
            </ul>
          </div>

          <!-- Impersonate & Password Reset Modals -->
          <?php
          foreach($users_data as $row) {
              $can_impersonate = false;
              if ($_SESSION['priv'] >= 3 && $row['priv'] <= 2) $can_impersonate = true;
              if ($_SESSION['priv'] == 2 && $row['priv'] <= 1) $can_impersonate = true;
              
              if ($can_impersonate) {
                  // Impersonate Modal
                  echo "
                  <div id='imp_modal_".$row['id']."' class='hidden fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-sm' aria-labelledby='modal-title' role='dialog' aria-modal='true'>
                    <div class='flex items-center justify-center min-h-screen px-4 text-center'>
                      <div class='relative inline-block bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:max-w-lg w-full'>
                        <div class='bg-gray-800 px-4 py-3 flex justify-between items-center text-white'>
                          <h3 class='text-lg font-bold'><i class='fas fa-user-secret mr-2'></i>Bevestiging</h3>
                          <button type='button' onclick=\"document.getElementById('imp_modal_".$row['id']."').classList.add('hidden')\" class='hover:text-gray-200 transition'><i class='fas fa-times text-xl'></i></button>
                        </div>
                        <div class='bg-white px-4 pt-5 pb-4 text-gray-800'>
                          <p>Weet je zeker dat je wilt inloggen als <strong>".htmlspecialchars($row['voornaam'])." ".htmlspecialchars($row['achternaam'])."</strong>?</p>
                        </div>
                        <div class='bg-gray-50 px-4 py-3 flex flex-row-reverse gap-3'>
                          <form action='users_helper.php' method='POST' class='m-0 p-0 flex-1 sm:flex-none'>
                            <input type='hidden' name='impersonate_user_id' value='".htmlspecialchars($row['id'])."'>
                            <button type='submit' class='w-full bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded transition shadow-sm'>Ja, log in</button>
                          </form>
                          <button type='button' onclick=\"document.getElementById('imp_modal_".$row['id']."').classList.add('hidden')\" class='w-full sm:w-auto bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded transition shadow-sm'>Annuleer</button>
                        </div>
                      </div>
                    </div>
                  </div>";
                  
                  // Password Reset Modal
                  echo "
                  <div id='reset_modal_".$row['id']."' class='hidden fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-sm' aria-labelledby='modal-title' role='dialog' aria-modal='true'>
                    <div class='flex items-center justify-center min-h-screen px-4 text-center'>
                      <div class='relative inline-block bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:max-w-lg w-full'>
                        <div class='bg-orange-500 px-4 py-3 flex justify-between items-center text-white'>
                          <h3 class='text-lg font-bold'><i class='fas fa-key mr-2'></i>Nieuw Wachtwoord</h3>
                          <button type='button' onclick=\"document.getElementById('reset_modal_".$row['id']."').classList.add('hidden')\" class='hover:text-gray-200 transition'><i class='fas fa-times text-xl'></i></button>
                        </div>
                        <form action='users_helper.php' method='POST'>
                          <div class='bg-white px-4 pt-5 pb-4 text-gray-800 space-y-4'>
                            <p>Vul een nieuw wachtwoord in voor <strong>".htmlspecialchars($row['voornaam'])." ".htmlspecialchars($row['achternaam'])."</strong>:</p>
                            <input type='hidden' name='reset_password_user_id' value='".htmlspecialchars($row['id'])."'>
                            <input type='text' name='new_password' class='w-full border rounded-lg px-3 py-2 text-gray-800 outline-none focus:ring-1 focus:ring-blue-500 shadow-sm' required placeholder='Nieuw wachtwoord'>
                          </div>
                          <div class='bg-gray-50 px-4 py-3 flex flex-row-reverse gap-3'>
                            <button type='submit' class='w-full sm:w-auto bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded transition shadow-sm'>Reset Wachtwoord</button>
                            <button type='button' onclick=\"document.getElementById('reset_modal_".$row['id']."').classList.add('hidden')\" class='w-full sm:w-auto bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded transition shadow-sm'>Annuleer</button>
                          </div>
                        </form>
                      </div>
                    </div>
                  </div>";
                  
                  // Profile Picture Upload Modal
                  echo "
                  <div id='pic_modal_".$row['id']."' class='hidden fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-sm' aria-labelledby='modal-title' role='dialog' aria-modal='true'>
                    <div class='flex items-center justify-center min-h-screen px-4 text-center'>
                      <div class='relative inline-block bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:max-w-lg w-full'>
                        <div class='bg-blue-500 px-4 py-3 flex justify-between items-center text-white'>
                          <h3 class='text-lg font-bold'><i class='fas fa-image mr-2'></i>Foto Uploaden</h3>
                          <button type='button' onclick=\"document.getElementById('pic_modal_".$row['id']."').classList.add('hidden')\" class='hover:text-gray-200 transition'><i class='fas fa-times text-xl'></i></button>
                        </div>
                        <form action='users_helper.php' method='POST' enctype='multipart/form-data'>
                          <div class='bg-white px-4 pt-5 pb-4 text-gray-800 space-y-4'>
                            <p>Upload een profielfoto voor <strong>".htmlspecialchars($row['voornaam'])." ".htmlspecialchars($row['achternaam'])."</strong>:</p>
                            <input type='hidden' name='admin_upload_user_id' value='".htmlspecialchars($row['id'])."'>
                            <input type='file' name='admin_profile_picture' accept='image/jpeg, image/png, image/webp' class='w-full border rounded-lg px-3 py-2 text-gray-800 outline-none focus:ring-1 focus:ring-blue-500 shadow-sm' required>
                          </div>
                          <div class='bg-gray-50 px-4 py-3 flex flex-row-reverse gap-3'>
                            <button type='submit' class='w-full sm:w-auto bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded transition shadow-sm'>Uploaden</button>
                            <button type='button' onclick=\"document.getElementById('pic_modal_".$row['id']."').classList.add('hidden')\" class='w-full sm:w-auto bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded transition shadow-sm'>Annuleer</button>
                          </div>
                        </form>
                      </div>
                    </div>
                  </div>";
              }
          }
          ?>
        </div>
      </div>
    </div>
  </main>

  <?php require_once('../includes/footer.php') ?>
</div>

<script src="../js/gps.js"></script>
<script>initGpsTracking('<?php echo $_SESSION['gps'] ?? 'false'; ?>');</script>
</body>
</html>
