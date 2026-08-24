<?php
// Vehicle management interface for registering new hunt cars, deleting vehicles, and joining or leaving as passengers.
define("PAGE_NAME", "autos");
require_once('includes/auth.php');
// Auto toevoegen
if (isset($_POST['kenteken'])){
  $stmt_ins = $conn->prepare("INSERT INTO Auto (eigenaar, kenteken) VALUES (?, ?) ON DUPLICATE KEY UPDATE eigenaar = eigenaar");
  $stmt_ins->bind_param("is", $_SESSION['id'], $_POST['kenteken']);
  
  if (!$stmt_ins->execute()) {
    echo "Error: " . $stmt_ins->error;
  }
  $stmt_ins->close();
}

// Gebruikersgegevens ophalen
$stmt = $conn->prepare("SELECT * FROM Gebruikers WHERE id=?");
$stmt->bind_param("i", $_SESSION['id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
      $vn = $row['voornaam'];
      $priv = $row['priv'];
    }
}
$stmt->close();

if (!isset($priv) || ($priv < 1 && !isset($_SESSION['kiosk_id']))) {
  header("Location: home");
  exit();
}

// In of uitstappen als bijrijder
if (isset($_POST['carid'])) {
  if ($_POST['carid'] === "geen") {
    $stmt_bijr = $conn->prepare("DELETE FROM Auto_Bijrijders WHERE gebruiker_id = ?");
    $stmt_bijr->bind_param("i", $_SESSION['id']);
    $stmt_bijr->execute();
    $stmt_bijr->close();
  } else {
    // We geven carid twee keer mee: één keer voor VALUES en één keer voor ON DUPLICATE KEY UPDATE
    $stmt_bijr = $conn->prepare("INSERT INTO Auto_Bijrijders (auto, gebruiker_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE auto = ?");
    $stmt_bijr->bind_param("sis", $_POST['carid'], $_SESSION['id'], $_POST['carid']);
    $stmt_bijr->execute();
    $stmt_bijr->close();
  }
}

?>

<!DOCTYPE html>
<html lang="nl">
<head>
<title>Jotify - Auto's</title>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="shortcut icon" type="image/png" href="media/geusje.png"/>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://kit.fontawesome.com/870ab34ea3.js" crossorigin="anonymous"></script>
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<link rel="stylesheet" href="includes/numberPlate.css">
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

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
      
      <!-- Auto Aanmaken & Tabel -->
      <div class="theme-card rounded border shadow-sm p-5 overflow-hidden">
        <h3 class="text-lg font-bold mb-4">Auto aanmaken</h3>
        <form method="POST" class="mb-6">    
          <div class="flex flex-col items-center justify-center">
            <div class="form-control mb-4">
              <div class="car-license shadow-sm">
                <abbr title="Netherlands" class="car-license__country-code">
                  <svg class="svg" viewBox="0 0 300 300" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="20" height="20" aria-labelledby="euSymbol" role="img">
                    <title id="euSymbol">EU symbol</title>
                    <g id="s" transform="translate(150,30)" fill="#fc0">
                      <g id="c">
                        <path id="t" d="M 0,-20 V 0 H 10" transform="rotate(18 0,-20)"/>
                        <use xlink:href="#t" transform="scale(-1,1)"/>
                      </g>
                      <use xlink:href="#c" transform="rotate(72)"/>
                      <use xlink:href="#c" transform="rotate(144)"/>
                      <use xlink:href="#c" transform="rotate(216)"/>
                      <use xlink:href="#c" transform="rotate(288)"/>
                    </g>
                    <use xlink:href="#s" transform="rotate(30 150,150) rotate(330 150,30)"/>
                    <use xlink:href="#s" transform="rotate(60 150,150) rotate(300 150,30)"/>
                    <use xlink:href="#s" transform="rotate(90 150,150) rotate(270 150,30)"/>
                    <use xlink:href="#s" transform="rotate(120 150,150) rotate(240 150,30)"/>
                    <use xlink:href="#s" transform="rotate(150 150,150) rotate(210 150,30)"/>
                    <use xlink:href="#s" transform="rotate(180 150,150) rotate(180 150,30)"/>
                    <use xlink:href="#s" transform="rotate(210 150,150) rotate(150 150,30)"/>
                    <use xlink:href="#s" transform="rotate(240 150,150) rotate(120 150,30)"/>
                    <use xlink:href="#s" transform="rotate(270 150,150) rotate(90 150,30)"/>
                    <use xlink:href="#s" transform="rotate(300 150,150) rotate(60 150,30)"/>
                    <use xlink:href="#s" transform="rotate(330 150,150) rotate(30 150,30)"/>
                  </svg>
                  <span>NL</span>
                </abbr>
                <div class="car-license__form-control">
                  <input type="text" class="car-license__input" id="input-kenteken" maxlength="8" autocomplete="off" name="kenteken" default="GE-LU-KT">  
                  <span class="valid-message"></span>
                </div>
              </div>
            </div>
            <button id="kentekenKnop" class="bg-green-600 text-white font-bold py-2 px-6 rounded hover:bg-green-700 transition shadow-sm disabled:opacity-50 disabled:cursor-not-allowed" disabled>Aanmaken</button>
          </div>
        </form>

        <hr class="my-6 border-gray-200" style="border-color: var(--theme-card-border);">

        <div class="overflow-x-auto">
          <table class="w-full text-sm text-left">
            <thead class="text-xs uppercase theme-card-header opacity-80">
              <tr>
                <th class="px-4 py-3">Kenteken</th>
                <th class="px-4 py-3 hidden md:table-cell">Inzittenden</th>
                <th class="px-4 py-3">Eigenaar</th>
                <th class="px-4 py-3"></th>
              </tr>
            </thead>
            <tbody class="divide-y" style="border-color: var(--theme-card-border);">
              <?php
              $sql = "
              SELECT 
                CONCAT(UPPER(SUBSTRING(ge.voornaam,1,1)),LOWER(SUBSTRING(ge.voornaam,2))) as eigenaar,
                ge.id as id,
                a.kenteken as kenteken,
                GROUP_CONCAT(CONCAT(UPPER(SUBSTRING(gb.voornaam,1,1)),LOWER(SUBSTRING(gb.voornaam,2))) SEPARATOR ', ') as inzittenden
              FROM Auto a
              LEFT JOIN Auto_Bijrijders ab
                on a.kenteken = ab.auto
              LEFT JOIN Gebruikers gb
                on gb.id = ab.gebruiker_id
              LEFT JOIN Gebruikers ge
                on ge.id = a.eigenaar
              GROUP BY a.kenteken
              ";
              
              $stmt_table = $conn->prepare($sql);
              $stmt_table->execute();
              $result_table = $stmt_table->get_result();

              if ($result_table->num_rows > 0) {
                while($row = $result_table->fetch_assoc()) {
                  echo "<tr class='hover:opacity-80 transition-opacity'>";
                  echo "  <td class='px-4 py-3 font-semibold'>".strtoupper(htmlspecialchars($row['kenteken']))."</td>";
                  echo "  <td class='px-4 py-3 hidden md:table-cell text-sm'>".htmlspecialchars($row['inzittenden'])."</td>";
                  echo "  <td class='px-4 py-3'>".htmlspecialchars($row['eigenaar'])."</td>";
                  echo "  <td class='px-4 py-3 text-right'>";
                  if ($_SESSION['id'] == $row['id']) {
                    echo "    <a href='autos?delauto=".urlencode($row['kenteken'])."' class='text-red-500 hover:text-red-700 transition'><i class=\"fas fa-trash\"></i></a>";
                  }
                  echo "  </td>";
                  echo "</tr>";
                  echo "<tr class='md:hidden bg-black/5'>";
                  echo "  <td colspan='4' class='px-4 py-2 text-xs italic opacity-80'>".htmlspecialchars($row['inzittenden'])."</td>";
                  echo "</tr>";
                }
              } else {
                  echo "<tr><td colspan='4' class='px-4 py-4 text-center opacity-70'>Geen auto's gevonden.</td></tr>";
              }
              $stmt_table->close();
              ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Stap in / Uit -->
      <div class="theme-card rounded border shadow-sm p-5">
        <h3 class="text-lg font-bold mb-4">Stap in / uit</h3>
        <form method="POST">
          <select class="w-full border rounded px-3 py-2 text-gray-800 outline-none focus:ring-1 focus:ring-blue-500 shadow-sm cursor-pointer mb-4" name="carid">
            <option value="geen" selected>Geen (Uitstappen)</option>
            <?php
            $sql_drop = "SELECT a.kenteken, a.eigenaar, b.voornaam FROM Auto as a INNER JOIN Gebruikers as b ON a.eigenaar = b.id ORDER BY b.voornaam ASC";
            $stmt_drop = $conn->prepare($sql_drop);
            $stmt_drop->execute();
            $result_drop = $stmt_drop->get_result();
            
            if ($result_drop->num_rows > 0) {
              while($row = $result_drop->fetch_assoc()) {
                echo "<option value=\"".htmlspecialchars($row['kenteken'])."\">Auto ".htmlspecialchars($row['kenteken'])." (".ucfirst(htmlspecialchars($row['voornaam'])).")</option>";
              }
            }
            $stmt_drop->close();
          ?>
          </select>  
          <div class="text-center">
            <button type="submit" class="bg-blue-600 text-white font-bold py-2 px-6 rounded hover:bg-blue-700 transition shadow-sm"><i class="fas fa-car-side mr-2"></i>Vroem!</button>
          </div>
        </form>
      </div>

    </div>
  </main>

  <?php require_once('includes/footer.php') ?>
</div>

<script type="text/javascript" src="includes/numberPlate.js"></script>
<script src="js/gps.js"></script>
<script>initGpsTracking('<?php echo $_SESSION['gps'] ?? 'false'; ?>');</script>

</body>
</html>
