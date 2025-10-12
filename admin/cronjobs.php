<?php
define("PAGE_NAME", "a_cronjobs");

session_start();
if (!isset($_SESSION['id'])){
  header("Location: ../index");
}
require("../dblogin.php");

$sql = "SELECT * FROM Gebruikers WHERE id='".$_SESSION['id']."'";
$result = mysqli_query($conn, $sql);

if (mysqli_num_rows($result) > 0) {
    // output data of each row
    while($row = mysqli_fetch_assoc($result)) {
      $vn = $row['voornaam'];
      $priv = $row['priv'];
    }
}
if ($priv < 2){
  header("Location: ../home");
}

// Get global site settings
$sql = "SELECT * FROM Site_Instellingen";
$result = mysqli_query($conn, $sql);

$siteSettings = array();

if (mysqli_num_rows($result) > 0) {
    while($row = mysqli_fetch_assoc($result)) {
      $siteSettings[$row['Instelling']] = $row['Waarde'];
    }
} else {
    echo "0 results";
    exit();
}

if (isset($_POST["user"]) && isset($_POST['priv'])){
  $sql = "UPDATE Gebruikers SET priv='".$_POST['priv']."' WHERE id='".$_POST['user']."'";

  if (mysqli_query($conn, $sql)) {
    $succes = true;
  }
}

?>
<!DOCTYPE html>
<html>
<title>Jotihunt - De Geuzen</title>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="shortcut icon" type="image/png" href="media/geusje.png"/>
<link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">
<link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Raleway">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.3.1/css/all.css" integrity="sha384-mzrmE5qonljUremFsqc01SB46JvROS7bZs3IO2EmfFsd15uHvIt+Y8vEf7N7fWAU" crossorigin="anonymous">
<style>
html,body,h1,h2,h3,h4,h5 {font-family: "Raleway", sans-serif}
@media only screen and (max-width: 600px) {
  .mobile100 {
    width:100%!important;
    flex-basis:100%!important
  }
}

</style>
<body class="w3-light-grey">

<!-- Top container -->
<div class="w3-bar w3-top w3-black w3-large" style="z-index:4">
  <button class="w3-bar-item w3-button w3-hide-large w3-hover-none w3-hover-text-light-grey" onclick="w3_open();"><i class="fa fa-bars"></i>  Menu</button>
  <span class="w3-bar-item w3-right">De Geuzen Arnhem</span>
</div>

<!-- Sidebar -->
<?php include_once('../includes/sidebar.php') ?>

<!-- !PAGE CONTENT! -->
<div class="w3-main" style="margin-left:200px;margin-top:43px;">

  <!-- Header -->
  <header class="w3-container" style="padding-top:22px">
    <h5><b><i class="fas fa-cogs"></i> Admin</b></h5>
  </header>
  <div class="w3-row" style="margin-bottom:100px;">
    <div class="w3-col l12 m12 s12 w3-padding">
      <div class="w3-card-4 w3-white">
        <div class="w3-blue-gray w3-padding" style="width:100%">
          <h5>Cronjobs [WIP]</h5>
        </div>
        <ul class="w3-ul">
        <?php
$sql = "SELECT cj.name, cj.enabled, cj.URL, cj.description, cj.interval, cl.exec_time, cl.exec_length, cl.exec_stat, cl.exec_output
        FROM Cronjobs cj LEFT JOIN Cronlogs cl ON cj.name = cl.name
        WHERE cl.exec_time IS NULL
            OR cl.exec_time = (
                SELECT MAX(cl2.exec_time)
                FROM Cronlogs cl2
                WHERE cl2.name = cj.name
            )";
$result = mysqli_query($conn, $sql);

if (mysqli_num_rows($result) > 0) {
  $i = 0;
    // output data of each row
    while($row = mysqli_fetch_assoc($result)) {
      $name = ucfirst($row['name']);
      $interval = number_format($row['interval'] / 60, 1, ',')." min";
      $exec_time = date("d/m H:i:s",strtotime($row['exec_time']));
      $exec_length = number_format($row['exec_length'] / 1000, 2, ',')." sec";
      $exec_status = $row['exec_stat'];
      $exec_output = $row['exec_output'];
      $exec_next = ($row['interval'] + strtotime($row['exec_time']) - time())." sec";

      if ($row['enabled'] === "1") {
        $enabled = '<i class="fas fa-toggle-on fa-fw"></i>';
      } else {
        $enabled = '<i class="fas fa-toggle-off fa-fw"></i>';
      }


      switch ($exec_status) {
        case 200: // succes
          $stat_color = "w3-text-green";
          break;
        case 429: // too many requests
          $stat_color = "w3-text-yellow";
          break;
        case 500: // script error
          $stat_color = "w3-text-red";
          break;
        default:
          $stat_color = "w3-text-red";
          break;
      }



      echo "<li class='cronTimer' style='display: flex; flex-direction: row; flex-wrap: wrap; justify-content: space-between'>
              <div class='mobile100' style='flex-basis: 250px'>
                <h3>
                  <span id='cron_enabled_".$i."' onclick='toggleCron(\"".strtolower($name)."\")'>".$enabled."</span>
                  <span id='cron_status_".$i."' class='".$stat_color."' title='HTML ".$exec_status." code'><i class='fas fa-circle'></i></span>
                  <span id='cron_name_".$i."'>".$name."</span>
                </h3>
              </div>
              <div><i class='fas fa-calendar-alt'></i> <b>Interval:</b><br><span id='cron_interval_".$i."'>".$interval."</span></div>
              <div><i class='far fa-clock'></i> <b>Next exec.:</b><br><span id='cron_exec_next_".$i."'>".$exec_next."</span></div>
              <div><i class='fas fa-history'></i> <b>Last exec.:</b><br><span id='cron_exec_time_".$i."'>".$exec_time."</span></div>
              <div><i class='fas fa-hourglass-half'></i> <b>Prev. Dur.:</b><br><span id='cron_exec_length_".$i."'>".$exec_length."</span></div>
              <div><h4><i id='cron_start_".$i."' class='fas fa-play'></i></h4><div>
            </li>";
      $i++;
    }
}       
        ?>
        </ul>
      </div>
    </div>

  </div>
  <!-- Footer -->
  <?php require_once('../includes/footer.php') ?>

  <!-- End page content -->
</div>

  <script>

if ("<?php echo $_SESSION['gps']?>" == "true"){
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
    if (window.XMLHttpRequest) {
          // code for IE7+, Firefox, Chrome, Opera, Safari
          xmlhttp = new XMLHttpRequest();
      } else {
          // code for IE6, IE5
          xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
      }
      xmlhttp.onreadystatechange = function() {
          if (this.readyState == 4 && this.status == 200) {
            CronRefresh();
          }
      };
      xmlhttp.open("GET","cronjobs_helper.php?toggleCron="+name,true);
      xmlhttp.send();
  }

  function TimerRefresh() {
    for (let i = 0; i < countAmont; i++) {
      var timer = document.getElementById("cron_exec_next_" + i);
      var cron_start = document.getElementById("cron_start_" + i);
      var cron_enabled = document.getElementById("cron_enabled_" + i);

      if (cron_enabled.innerHTML.includes("off")) {
        timer.innerHTML = " - disabled - ";
      } else {
        timer.innerHTML.replace(" sec", "");
        if (timer.innerHTML != "executing...") {
          timer.innerHTML = (parseInt(timer.innerHTML) - 1);
          cron_start.className = "fas fa-play";
          if (timer.innerHTML <= 0) {
            timer.innerHTML = "executing...";
            cron_start.className = "fas fa-sync-alt fa-spin";
          } else {
            timer.innerHTML += " sec";
          }        
        }
      }
    }
  }

  function CronRefresh() {
    if (window.XMLHttpRequest) {
        // code for IE7+, Firefox, Chrome, Opera, Safari
        xmlhttp = new XMLHttpRequest();
    } else {
        // code for IE6, IE5
        xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
    }
    xmlhttp.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
          console.log(this.responseText);
          json = JSON.parse(this.responseText);
          countAmont = json.length;
          for(var i = 0; i < json.length; i++){
            var cron_enabled = document.getElementById("cron_enabled_" + i);
            var cron_status = document.getElementById("cron_status_" + i);
            var cron_name = document.getElementById("cron_name_" + i);
            var cron_interval = document.getElementById("cron_interval_" + i);
            var cron_exec_time = document.getElementById("cron_exec_time_" + i);
            var cron_exec_length = document.getElementById("cron_exec_length_" + i);
            var cron_exec_next = document.getElementById("cron_exec_next_" + i);
            var cron_start = document.getElementById("cron_start_" + i);

            cron_enabled.innerHTML = json[i]['enabled'];
            cron_status.className = json[i]['stat_color'];
            cron_status.title = "HTML " + json[i]['exec_status'] + " code.";
            cron_name.innerHTML = json[i]['name'];
            cron_name.title = json[i]['description'];
            cron_interval.innerHTML = json[i]['interval'];
            cron_exec_time.innerHTML = json[i]['exec_time'];
            cron_exec_length.innerHTML = json[i]['exec_length'];
            cron_exec_next.innerHTML = json[i]['exec_next'];
            if (json[i]['exec_next'] <= 0) {
              cron_start.classname = 'fas fa-sync-alt fa-spin';
            } else {
              cron_start.classname = 'fas fa-play';
            }
            
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
