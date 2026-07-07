<?php
define("PAGE_NAME", "a_cronjobs");

session_start();
if (!isset($_SESSION['id'])){
  header("Location: ../index");
  exit();
}
if (!isset($_SESSION['priv']) || $_SESSION['priv'] < 2) {
  header("Location: ../home");
  exit();
}
require("../dblogin.php");

$stmt = $conn->prepare("SELECT voornaam, priv FROM Gebruikers WHERE id=?");
$stmt->bind_param("i", $_SESSION['id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
      $vn = $row['voornaam'];
      $priv = $row['priv'];
    }
} else {
    session_destroy();
    header("Location: ../index");
    exit();
}
$stmt->close();

if ($priv < 2){
  header("Location: ../home");
  exit();
}

// Get global site settings
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

// Dit lijkt een overblijfsel van a_users.php, maar is voor de veiligheid toch omgezet naar een prepared statement.
if (isset($_POST["user"]) && isset($_POST['priv'])){
    $stmt_priv = $conn->prepare("UPDATE Gebruikers SET priv=? WHERE id=?");
    $stmt_priv->bind_param("ii", $_POST['priv'], $_POST['user']);

    if ($stmt_priv->execute()) {
        $succes = true;
    }
    $stmt_priv->close();
}

?>
<!DOCTYPE html>
<html lang="nl">
<head>
<title>Jotihunt - Cronjobs</title>
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
      <div class="theme-card rounded border shadow-sm overflow-hidden w-full max-w-5xl">
        <div class="theme-card-header px-6 py-4 border-b text-white flex justify-between items-center" style="background-color: var(--theme-sidebar-active); border-color: var(--theme-card-border);">
          <h3 class="text-xl font-bold">Cronjobs</h3>
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
                    )";
                    
        $stmt_cron = $conn->prepare($sql);
        $stmt_cron->execute();
        $result_cron = $stmt_cron->get_result();

        if ($result_cron->num_rows > 0) {
            $i = 0;
            while($row = $result_cron->fetch_assoc()) {
              $name = ucfirst(htmlspecialchars($row['name']));
              $interval = number_format($row['interval'] / 60, 1, ',')." min";
              
              // Fallback voor als er nog geen exec_time is
              $exec_time = $row['exec_time'] ? date("d/m H:i:s", strtotime($row['exec_time'])) : "Nooit";
              $exec_length = $row['exec_length'] ? number_format($row['exec_length'] / 1000, 2, ',')." sec" : "0,00 sec";
              $exec_status = $row['exec_stat'];
              
              $exec_next = $row['exec_time'] ? ($row['interval'] + strtotime($row['exec_time']) - time())." sec" : "Onbekend";

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

              echo "<div class='cronTimer p-5 hover:bg-black/5 transition flex flex-col md:flex-row md:items-center justify-between gap-4'>
                      <div class='md:w-1/3 min-w-[250px]'>
                        <h3 class='text-lg font-bold flex items-center gap-2'>
                          <span id='cron_enabled_".$i."' class='cursor-pointer hover:opacity-80 transition' onclick='toggleCron(\"".htmlspecialchars(strtolower($name))."\")'>".$enabled."</span>
                          <span id='cron_status_".$i."' class='".$stat_color." text-sm' title='HTML ".htmlspecialchars($exec_status)." code'><i class='fas fa-circle'></i></span>
                          <span id='cron_name_".$i."'>".$name."</span>
                        </h3>
                      </div>
                      
                      <div class='grid grid-cols-2 sm:grid-cols-4 gap-4 flex-1 w-full text-sm'>
                        <div class='bg-black/5 p-2 rounded'>
                          <div class='opacity-70 mb-1'><i class='fas fa-calendar-alt mr-1'></i> <b>Interval</b></div>
                          <div id='cron_interval_".$i."' class='font-medium'>".$interval."</div>
                        </div>
                        <div class='bg-black/5 p-2 rounded'>
                          <div class='opacity-70 mb-1'><i class='far fa-clock mr-1'></i> <b>Next exec.</b></div>
                          <div id='cron_exec_next_".$i."' class='font-medium text-blue-600 dark:text-blue-400'>".$exec_next."</div>
                        </div>
                        <div class='bg-black/5 p-2 rounded'>
                          <div class='opacity-70 mb-1'><i class='fas fa-history mr-1'></i> <b>Last exec.</b></div>
                          <div id='cron_exec_time_".$i."' class='font-medium opacity-80'>".$exec_time."</div>
                        </div>
                        <div class='bg-black/5 p-2 rounded'>
                          <div class='opacity-70 mb-1'><i class='fas fa-hourglass-half mr-1'></i> <b>Prev. Dur.</b></div>
                          <div id='cron_exec_length_".$i."' class='font-medium opacity-80'>".$exec_length."</div>
                        </div>
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

  <!-- Footer -->
  <?php require_once('../includes/footer.php') ?>
</div>

<script>
if ("<?php echo $_SESSION['gps'] ?? 'false' ?>" == "true"){
  setInterval(function() {
    GPSrefresh();
  }, 5555);
}

setInterval(function() {
  TimerRefresh();
}, 1000);

var countAmont = document.getElementsByClassName('cronTimer').length;

setInterval(function() {
  CronRefresh();
}, 6000);

function toggleCron(name) {
  var xmlhttp;
  if (window.XMLHttpRequest) {
        xmlhttp = new XMLHttpRequest();
  } else {
        xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
  }
  xmlhttp.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
          CronRefresh();
        }
  };
  xmlhttp.open("GET","cronjobs_helper.php?toggleCron="+encodeURIComponent(name),true);
  xmlhttp.send();
}

function TimerRefresh() {
  for (let i = 0; i < countAmont; i++) {
    var timer = document.getElementById("cron_exec_next_" + i);
    var cron_enabled = document.getElementById("cron_enabled_" + i);

    if (cron_enabled.innerHTML.includes("off")) {
      timer.innerHTML = " - disabled - ";
      timer.className = "font-medium opacity-50";
    } else {
      timer.className = "font-medium text-blue-600 dark:text-blue-400";
      if (timer.innerHTML !== "executing..." && timer.innerHTML !== "Onbekend" && timer.innerHTML !== " - disabled - ") {
        let currentSecs = parseInt(timer.innerHTML);
        if(!isNaN(currentSecs)) {
            currentSecs--;
            if (currentSecs <= 0) {
              timer.innerHTML = "executing...";
              timer.className = "font-bold text-orange-500 animate-pulse";
            } else {
              timer.innerHTML = currentSecs + " sec";
            }        
        }
      }
    }
  }
}

function CronRefresh() {
  var xmlhttp;
  if (window.XMLHttpRequest) {
      xmlhttp = new XMLHttpRequest();
  } else {
      xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
  }
  xmlhttp.onreadystatechange = function() {
      if (this.readyState == 4 && this.status == 200) {
        try {
            var json = JSON.parse(this.responseText);
            countAmont = json.length;
            
            for(var i = 0; i < json.length; i++){
              var cron_enabled = document.getElementById("cron_enabled_" + i);
              var cron_status = document.getElementById("cron_status_" + i);
              var cron_name = document.getElementById("cron_name_" + i);
              var cron_interval = document.getElementById("cron_interval_" + i);
              var cron_exec_time = document.getElementById("cron_exec_time_" + i);
              var cron_exec_length = document.getElementById("cron_exec_length_" + i);
              var cron_exec_next = document.getElementById("cron_exec_next_" + i);

              if (json[i]['enabled'].includes('toggle-on')) {
                  cron_enabled.innerHTML = '<i class="fas fa-toggle-on fa-fw text-green-500 text-xl align-middle"></i>';
              } else {
                  cron_enabled.innerHTML = '<i class="fas fa-toggle-off fa-fw text-gray-400 text-xl align-middle"></i>';
              }
              
              cron_status.className = json[i]['stat_color'].replace('w3-text-green', 'text-green-500').replace('w3-text-yellow', 'text-yellow-500').replace('w3-text-red', 'text-red-500').replace('w3-text-grey', 'text-gray-400') + ' text-sm';
              cron_status.title = "HTML " + json[i]['exec_status'] + " code.";
              cron_name.innerHTML = json[i]['name'];
              cron_name.title = json[i]['description'];
              cron_interval.innerHTML = json[i]['interval'];
              cron_exec_time.innerHTML = json[i]['exec_time'];
              cron_exec_length.innerHTML = json[i]['exec_length'];
              cron_exec_next.innerHTML = json[i]['exec_next'];
            }
        } catch (e) {
            console.error("Ongeldige JSON ontvangen van cronjobs_helper.php");
        }
      }
  };
  xmlhttp.open("GET","cronjobs_helper.php?cronjobs",true);
  xmlhttp.send();
}

function GPSrefresh() {
  if (navigator.geolocation) {
      navigator.geolocation.getCurrentPosition(showPosition);
  } else {
      console.log("Geolocation is not supported by this browser.");
  }
  
  function showPosition(position) {
    var xmlhttp;
    if (window.XMLHttpRequest) {
        xmlhttp = new XMLHttpRequest();
    } else {
        xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
    }
    xmlhttp.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
            // Gelukt
        }
    };
    xmlhttp.open("GET","../functies.php?lat="+position.coords.latitude+"&lon="+position.coords.longitude,true);
    xmlhttp.send();
  }
} 
</script>

</body>
</html>