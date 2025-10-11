<?php

session_start();

if (!isset($_SESSION['id'])){

  header("Location: index");

}

require("dblogin.php");



if (isset($_GET['refresh'])){

  header("Refresh: 30; URL=home?refresh");

}



$sql = "SELECT * FROM Gebruikers WHERE id='".$_SESSION['id']."'";

$result = mysqli_query($conn, $sql);



if (mysqli_num_rows($result) > 0) {

    // output data of each row

    while($row = mysqli_fetch_assoc($result)) {

      $vn = $row['voornaam'];

      $priv = $row['priv'];

    }

}



$sql = "SELECT id, count(*) as NUM FROM Voslocaties WHERE type='Hint' GROUP BY ingestuurd_op";

$result = mysqli_query($conn, $sql);



if (mysqli_num_rows($result) > 0) {

    // output data of each row

    while($row = mysqli_fetch_assoc($result)) {

      $hintaantal = $row['NUM'];

    }

} else {

    $hintaantal = "0";

}



$sql = "SELECT id, count(*) as NUM FROM Voslocaties WHERE type='Hunt'";

$result = mysqli_query($conn, $sql);



if (mysqli_num_rows($result) > 0) {

    // output data of each row

    while($row = mysqli_fetch_assoc($result)) {

      $huntaantal = $row['NUM'];

    }

} else {

    $huntaantal = "0";

}





$sql = "SELECT * FROM Punten WHERE groep_id = (SELECT id FROM Groepen WHERE naam LIKE '%geuzen%')";

$result = mysqli_query($conn, $sql);



if (mysqli_num_rows($result) > 0) {

    // output data of each row

    while($row = mysqli_fetch_assoc($result)) {

      $puntentotaal = $row['totaal'];

      $plaats = $row['plaats'];

    }

}



$sql = "Select * FROM Voslog ORDER BY datumtijd desc LIMIT 1";

$result = mysqli_query($conn, $sql);



if (mysqli_num_rows($result) > 0) {

    // output data of each row

    $a = 0;

  $vossen = array("Alpha", "Bravo", "Charlie", "Delta", "Echo", "Foxtrot", "Golf", "Hotel");

    while($row = mysqli_fetch_assoc($result)) {

      //////////

      foreach ($vossen as $vosnaam){

        $voslc = lcfirst($vosnaam);

          $sql_2 = "SELECT MAX(datumtijd) as datumtijd FROM Voslog orig WHERE $vosnaam <> (SELECT $vosnaam FROM Voslog WHERE id = orig.id - 1);";

          $result_2 = mysqli_query($conn, $sql_2);

          if (mysqli_num_rows($result_2) > 0) {

            while($row_2 = mysqli_fetch_assoc($result_2)) {

              $vos[$vosnaam]["verandering"] = $row_2['datumtijd'];

              // dynamic duration: seconds, minutes, hours, or "> 24 uur"
              $diff = time() - strtotime($row_2['datumtijd']);
              if ($diff < 60) {
                $vos[$vosnaam]["duratie"] = $diff . " sec";
              } elseif ($diff < 3600) {
                $vos[$vosnaam]["duratie"] = round($diff / 60) . " min";
              } elseif ($diff < 86400) {
                $vos[$vosnaam]["duratie"] = round($diff / 3600, 1) . " uur";
              } else {
                $vos[$vosnaam]["duratie"] = "24 uur +";
              }

            }

          }

        $vos[$vosnaam]["Status"] = $row[$voslc];

        if ($row[$voslc] == 0){

          $vos[$vosnaam]["Kleur"] = "red";

        } elseif ($row[$voslc] == 1){

          $vos[$vosnaam]["Kleur"] = "orange";

        } elseif ($row[$voslc] == 2){

          $vos[$vosnaam]["Kleur"] = "green";

        }

      }

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

  

<meta name="mobile-web-app-capable" content="yes">

<meta name="apple-mobile-web-app-capable" content="yes">

<style>

html,body,h1,h2,h3,h4,h5 {font-family: "Raleway", sans-serif}

</style>

<body class="w3-light-grey">



<!-- Top container -->

<div class="w3-bar w3-top w3-black w3-large" style="z-index:4;">

  <button class="w3-bar-item w3-button w3-hide-large w3-hover-none w3-hover-text-light-grey" onclick="w3_open();"><i class="fa fa-bars"></i>  Menu</button>

  <!-- Desktop / medium+ : show on medium and large (hide only on small) -->
  <div class="w3-hide-small w3-hide-medium w3-bar-item" style="display:flex; gap:6px; align-items:center; flex:1; min-width:0;">
    <?php
      $names = array("Alpha","Bravo","Charlie","Delta","Echo","Foxtrot","Golf","Hotel");
      foreach ($names as $n) {
        // each item is a bar-item, uses w3-center + color class from PHP
        // fixed height and centered content so thickness is consistent
        echo '<div class="w3-center w3-padding-small w3-round w3-'.$vos[$n]["Kleur"].'" style="flex:1; min-width:90px; box-sizing:border-box; height:34px; display:flex; align-items:center; justify-content:center; font-size:0.95rem;">';
        echo '<span style="font-weight:700; margin-right:6px;">'.substr($n,0,1).'</span><span>'.$vos[$n]["duratie"].'</span>';
        echo '</div>';
      }
    ?>
  </div>

  <!-- Title (fixed) -->
  <div class="w3-bar-item w3-right" style="flex:none; width:200px; text-align:right;">De Geuzen Arnhem</div>

</div>



<!-- Sidebar/menu -->

<nav class="w3-sidebar w3-collapse w3-white w3-animate-left" style="z-index:3;width:200px;" id="mySidebar"><br>

  <div class="w3-container w3-row">

    <div class="w3-col s4">

      <img src="media/geusje.png" class="w3-margin-right" style="width:46px">

    </div>

    <div class="w3-col s8 w3-bar">

      <span>Welkom, <strong><?php echo ucfirst($vn); ?></strong></span><br>

      <a href="index" class="w3-bar-item w3-button"><i class="fas fa-sign-out-alt"></i></a>

      <a href="functies?gpstoggle=true&return=home" class="w3-bar-item w3-button <?php if ($_SESSION['gps'] == "true"){echo "w3-green";}else{echo "w3-red";} ?>"><i class="fas fa-location-arrow"></i></a>

    </div>

  </div>

  <hr>

  <div class="w3-container">

    <h5>Dashboard</h5>

  </div>

  <div class="w3-bar-block">

    <a href="#" class="w3-bar-item w3-button w3-padding-16 w3-hide-large w3-dark-grey w3-hover-black" onclick="w3_close()" title="close menu"><i class="fa fa-remove fa-fw"></i>  Sluit Menu</a>

    <a href="home" class="w3-bar-item w3-button w3-padding w3-blue"><i class="fa fa-users fa-fw"></i>  Overzicht</a>

    <?php if ($priv > 0){echo '<a href="kaarten" class="w3-bar-item w3-button w3-padding"><i class="fas fa-map-marked-alt fa-fw"></i>  Kaarten</a>';}?>

    <?php if ($priv > 0){echo '<a href="hunts" class="w3-bar-item w3-button w3-padding"><i class="fas fa-map-marker-alt fa-fw"></i>  Hunt!</a>';}?>

    <?php if ($priv > 0){echo '<a href="vossen" class="w3-bar-item w3-button w3-padding"><i class="fas fa-bullseye fa-fw"></i>  Vossen</a>';}?>

    <a href="nieuws" class="w3-bar-item w3-button w3-padding"><i class="far fa-newspaper fa-fw"></i>  Nieuws</a>

    <a href="opdrachten" class="w3-bar-item w3-button w3-padding"><i class="far fa-bell fa-fw"></i>  Opdrachten</a>

    <a href="hints" class="w3-bar-item w3-button w3-padding"><i class="fas fa-question-circle fa-fw"></i>  Hints</a>

    <?php if ($priv > 0){echo '<a href="punten" class="w3-bar-item w3-button w3-padding"><i class="fas fa-trophy fa-fw"></i>  Punten</a>';}?>

    <a href="groepen" class="w3-bar-item w3-button w3-padding"><i class="fas fa-home fa-fw"></i>  Groepen</a>

    <a href="instellingen" class="w3-bar-item w3-button w3-padding"><i class="fas fa-cog fa-fw"></i>  Instellingen</a>

    <?php if ($priv > 0){echo '<a href="autos" class="w3-bar-item w3-button w3-padding"><i class="fas fa-car fa-fw"></i>  Auto\'s</a>';}?>

    <?php if ($priv > 1){echo '<a href="admin/users" class="w3-bar-item w3-button w3-padding"><i class="fas fa-user-cog fa-fw"></i>  [Admin] Users</a>';} ?>

    <?php if ($priv > 1){echo '<a href="admin/cronjobs" class="w3-bar-item w3-button w3-padding"><i class="fas fa-stopwatch fa-fw"></i>  [Admin] Cronjobs</a>';} ?>

    <?php if ($priv > 1){echo '<a href="admin/database" class="w3-bar-item w3-button w3-padding"><i class="fas fa-database fa-fw"></i>  [Admin] Database</a>';} ?>
    
    <?php if ($priv > 1){echo '<a href="admin/settings" class="w3-bar-item w3-button w3-padding"><i class="fas fa-database fa-fw"></i>  [Admin] Settings</a>';} ?><br><br>

  </div>

</nav>

</nav>





<!-- Overlay effect when opening sidebar on small screens -->

<div class="w3-overlay w3-hide-large w3-animate-opacity" onclick="w3_close()" style="cursor:pointer" title="close side menu" id="myOverlay"></div>



<!-- !PAGE CONTENT! -->

<div class="w3-main" style="margin-left:200px;margin-top:43px;">

  <div class="w3-bar">

    <!-- Mobile / small: show only on small screens; items wrap (two per row) -->
    <div class="w3-hide-large w3-row w3-padding-small">
      <?php
        foreach ($names as $n) {
          echo '<div class="w3-col s6" style="box-sizing:border-box; padding:4px;">';
          // match the desktop height/centering so thickness is the same visually
          echo '<div class="w3-card-2 w3-'.$vos[$n]["Kleur"].' w3-padding-small w3-center" style="height:42px; display:flex; align-items:center; justify-content:center; gap:6px;">';
          echo '<span style="font-weight:700;">'.substr($n,0,1).'</span><span style="font-size:0.95rem;">'.$vos[$n]["duratie"].'</span>';
          echo '</div></div>';
        }
      ?>
    </div>

  </div>

  <!-- Header -->

  <header class="w3-container" style="padding-top:22px">

    <h5><b><i class="fas fa-tachometer-alt"></i> Overzicht</b></h5>

  </header>



  <div class="w3-row-padding w3-margin-bottom">

    <div class="w3-quarter" style="margin-bottom:11px">

      <div class="w3-container w3-red w3-padding-16 w3-round-xlarge">

        <div class="w3-left"><i class="fas fa-trophy w3-xxxlarge"></i></div>

        <div class="w3-right">

          <h3><?php if (isset($puntentotaal)){echo $puntentotaal;} else {echo 0;} ?></h3>

        </div>

        <div class="w3-clear"></div>

        <h4>Punten</h4>

      </div>

    </div>

    <div class="w3-quarter" style="margin-bottom:11px">

      <div class="w3-container w3-orange w3-text-white w3-padding-16 w3-round-xlarge">

        <div class="w3-left"><i class="fas fa-star w3-xxxlarge"></i></div>

        <div class="w3-right">

          <h3><?php if (isset($plaats)){echo $plaats;} else {echo 0;}?>e</h3>

        </div>

        <div class="w3-clear"></div>

        <h4>Plaats</h4>

      </div>

    </div>

    <div class="w3-quarter" style="margin-bottom:11px">

      <div class="w3-container w3-blue w3-padding-16 w3-round-xlarge">

        <div class="w3-left"><i class="fas fa-bullseye w3-xxxlarge"></i></div>

        <div class="w3-right">

          <h3><?php echo $huntaantal;?></h3>

        </div>

        <div class="w3-clear"></div>

        <h4>Hunts</h4>

      </div>

    </div>

    <div class="w3-quarter" style="margin-bottom:11px">

      <div class="w3-container w3-teal w3-padding-16 w3-round-xlarge">

        <div class="w3-left"><i class="fas fa-question-circle w3-xxxlarge"></i></div>

        <div class="w3-right">

          <h3><?php echo $hintaantal ?></h3>

        </div>

        <div class="w3-clear"></div>

        <h4>Hints opgestuurd</h4>

      </div>

    </div>

  </div>



  <div class="w3-panel" style="margin-bottom:11px">

    <div class="w3-row-padding" style="margin:0 -16px">

      <div class="w3-third">

        <div>

          <h5>Invulgegevens voor homebase<i id="invulgegevens_icon" class="fas fa-redo-alt w3-right" onclick="invulgegevens()"></i></h5>

          <div id="invulgegevens">



          </div>

        </div>

        <div>

          <h5>Auto's onderweg<i id="autosonderweg_icon" class="fas fa-redo-alt w3-right" onclick="autosonderweg()"></i></h5>

          <div id="autosonderweg">



          </div>

        </div>

      </div>

      <div class="w3-twothird">

        <h5>Gebeurtenissen<i id="gebeurtenissen_icon" class="fas fa-redo-alt w3-right" onclick="gebeurtenissen()"></i></h5>

          <div id="gebeurtenissen">

            

          </div>

      </div>

    </div>

  </div>

</div>

  <!-- Footer -->

  <footer class="w3-container w3-padding-16 w3-dark-grey">

    <center><p><a href="#">Niels Maarleveld</a> - &copy; <?php echo date("Y");?></p>

  </footer>

<div id="demo">

  

</div>

<div id="modal01" class="w3-modal">

  <div class="w3-modal-content">

    <header class="w3-container w3-red"> 

      <span onclick="document.getElementById('modal01').style.display='none'" 

      class="w3-button w3-display-topright"><i class="fas fa-times"></i></span>

      <h2>Zeker dat je deze wilt markeren als opgestuurd?</h2>

    </header>

    <div class="w3-container w3-padding">

      <p><b>Door dit te markeren als klaar betekend het dat iemand dit in de officiele jotihuntwebsite heeft opgestuurd.</b></p>

      <div class="w3-bar">

        <a href="#" id="opgestuurdurl" class="w3-button w3-red" onclick="">Ja</a>

        <span class="w3-button w3-green" onclick="document.getElementById('modal01').style.display='none'">Nee</span>

      </div>

    </div>



  <footer class="w3-container w3-padding-16 w3-dark-grey w3-bottom">

    <center><p><a href="#">Niels Maarleveld</a> - &copy; <?php echo date("Y");?></p>

  </footer>



  </div>

</div>

  <!-- End page content -->

</div>

<!-- Welcome modal (lorem ipsum title/content) -->
<?php
// show welcome modal once after login for priv 0
if (isset($_SESSION['show_welcome_modal']) && $_SESSION['show_welcome_modal'] === true && isset($_SESSION['priv']) && $_SESSION['priv'] == 0) {
  // unset immediately so it won't show on refresh
  unset($_SESSION['show_welcome_modal']);
  ?>
  <div id="welcomeModal" class="w3-modal">
    <div class="w3-modal-content w3-animate-top w3-card-4" style="max-width:600px">
      <header class="w3-container w3-theme-d1"> 
        <h2>Welkom bij de Jotihunt van de Geuzen Arnhem!</h2>
      </header>
      <div class="w3-container w3-padding">
        <p>
          Hoi <?php echo ucfirst($vn); ?>, als nieuwe gebruiker van dit platform willen we je graag welkom heten!
        </p>
        <p>
          Dit platform is speciaal ontwikkeld voor de Jotihunt en biedt verschillende functies om je ervaring te verbeteren. Hier zijn enkele belangrijke punten om te weten:
        </p>
        <ul>
          <li>Je kunt eenvoudig hints opvragen en opdrachten bekijken via het menu.</li>
          <li>Je locatie kan worden gedeeld met de homebase. Dit kun je aan- of uitzetten met de GPS-knop linksbovenin.</li>
          <li>Voor vragen of problemen kun je altijd contact opnemen met de organisatie.</li>
        </ul>
        <p>
          <strong>Jij hebt op dit moment nog geen extra rechten, maar deze kan je aanvragen bij de volgende gebruikers:</strong>
        </p>
        <ul>
          <?php
          $sql = "SELECT voornaam, achternaam FROM Gebruikers WHERE priv > 1 ORDER BY voornaam ASC";
          $result = mysqli_query($conn, $sql);
          if (mysqli_num_rows($result) > 0) {
            while($row = mysqli_fetch_assoc($result)) {
              echo '<li>'.ucfirst($row['voornaam']).' '.ucfirst($row['achternaam']).'</li>';
            }
          } else {
            echo '<li>Geen gebruikers met extra rechten gevonden...</br>Als je beheerder bent dien je dat handmatig te doen via de database door de kolomn priv van 0 of 1 naar 2 aan te passen.</li>';
          }
          ?>
        </ul>
      </div>
      <footer class="w3-container w3-theme-d1 w3-padding">
        <div style="display:flex; justify-content:flex-end; align-items:center; gap:8px;">
          <div id="closeWrap" style="position:relative; display:inline-block; min-width:160px;">
            <!-- disabled button with countdown -->
            <button id="welcomeClose" class="w3-button w3-green w3-round w3-disabled" disabled
                    style="position:relative; z-index:2; opacity:0.65; min-width:160px; display:flex; align-items:center; justify-content:center; gap:8px;">
              <span id="closeLabel">Sluiten</span>
              <span id="closeCountdown" style="opacity:0.9;">(7s)</span>
            </button>
            <!-- green progress overlay that fills the button -->
            <div id="welcomeProgress" style="position:absolute; left:0; top:0; height:100%; background:rgba(34,177,76,0.18); width:0%; border-radius:8px; z-index:1; pointer-events:none;"></div>
          </div>
        </div>
      </footer>
    </div>
  </div>

  <script>
    // auto-open welcome modal on page load and force 7s read-delay with countdown
    (function() {
      window.addEventListener('load', function() {
        var modal = document.getElementById('welcomeModal');
        if (!modal) return;
        modal.style.display = 'block';

        var closeBtn = document.getElementById('welcomeClose');
        var progress = document.getElementById('welcomeProgress');
        var countdownEl = document.getElementById('closeCountdown');
        var duration = 7000; // milliseconds
        var start = Date.now();

        // update loop using requestAnimationFrame for smooth progress + 1s steps for countdown
        var raf;
        function tick() {
          var elapsed = Date.now() - start;
          var pct = Math.min(100, (elapsed / duration) * 100);
          progress.style.width = pct + '%';

          var remaining = Math.max(0, Math.ceil((duration - elapsed) / 1000));
          // show "(Ns)" until finished
          if (remaining > 0) {
            countdownEl.textContent = '(' + remaining + 's)';
          } else {
            countdownEl.style.display = 'none';
          }

          if (elapsed < duration) {
            raf = requestAnimationFrame(tick);
          } else {
            // enable button after duration
            closeBtn.disabled = false;
            closeBtn.classList.remove('w3-disabled');
            closeBtn.style.opacity = '1';
            progress.style.display = 'none';
            cancelAnimationFrame(raf);
          }
        }
        // start anim
        tick();

        // close handler (only effective after enabled)
        closeBtn.addEventListener('click', function() {
          if (!closeBtn.disabled) {
            modal.style.display = 'none';
          }
        });
      });
    })();
  </script>
  <?php
}
?>

<input type="hidden" id="hgtyhgty">

    <!-- The core Firebase JS SDK is always required and must be listed first -->

<script src="https://www.gstatic.com/firebasejs/7.0.0/firebase-app.js"></script>



<!-- TODO: Add SDKs for Firebase products that you want to use

     https://firebase.google.com/docs/web/setup#available-libraries -->

<script src="https://www.gstatic.com/firebasejs/7.0.0/firebase-analytics.js"></script>



<script>

  // Your web app's Firebase configuration

  var firebaseConfig = {

    apiKey: "AIzaSyA6Jz4tgPa_Trsw87UOVj-ut_ojdzhX5ps",

    authDomain: "jotihunt-1539122761269.firebaseapp.com",

    databaseURL: "https://jotihunt-1539122761269.firebaseio.com",

    projectId: "jotihunt-1539122761269",

    storageBucket: "",

    messagingSenderId: "376439098940",

    appId: "1:376439098940:web:3bf5ab91c34efafd3e3d39",

    measurementId: "G-1ZGJEPTP5T"

  };

  // Initialize Firebase

  firebase.initializeApp(firebaseConfig);

  firebase.analytics();

</script>

    </body>

</html>

<script>

setInterval(function() {

  invulgegevens();

  gebeurtenissen();

  autosonderweg();

}, 11111);

  

  

// Overzicht - Gebeurtenissen ophalen

window.onload = gebeurtenissen();

function gebeurtenissen(str = "6") {

  var icon = document.getElementById("gebeurtenissen_icon");

  icon.classList.add("w3-spin");

  var xhttp;

  xhttp = new XMLHttpRequest();

  xhttp.onreadystatechange = function() {

    if (this.readyState == 4 && this.status == 200) {

      document.getElementById("gebeurtenissen").innerHTML = this.responseText;

      setTimeout(function() {

        

      icon.classList.remove("w3-spin");

      }, 1000);

      

    }

  };

  xhttp.open("GET", "functies.php?gebeurtenissen="+str, true);

  xhttp.send();

}

 

  

// Overzicht - Auto's ophalen

window.onload = autosonderweg();

function autosonderweg(str = "6") {

  var icon = document.getElementById("autosonderweg_icon");

  icon.classList.add("w3-spin");

  var xhttp;

  xhttp = new XMLHttpRequest();

  xhttp.onreadystatechange = function() {

    if (this.readyState == 4 && this.status == 200) {

      document.getElementById("autosonderweg").innerHTML = this.responseText;

      setTimeout(function() {

        

      icon.classList.remove("w3-spin");

      }, 1000);

      

    }

  };

  xhttp.open("GET", "functies.php?autos="+str, true);

  xhttp.send();

}

  

<?php if ($priv > 0){ echo '

// Overzicht -Invulgegevens ophalen

window.onload = invulgegevens();

function invulgegevens(str = "6") {

  var icon = document.getElementById("invulgegevens_icon");

  icon.classList.add("w3-spin");

  var xhttp;

  xhttp = new XMLHttpRequest();

  xhttp.onreadystatechange = function() {

    if (this.readyState == 4 && this.status == 200) {

      document.getElementById("invulgegevens").innerHTML = this.responseText;

      setTimeout(function() {

        

      icon.classList.remove("w3-spin");

      }, 1000);

    }

  };

  xhttp.open("GET", "functies.php?invulgegevens="+str, true);

  xhttp.send();

}

';}?>  

// Get the Sidebar

var mySidebar = document.getElementById("mySidebar");



// Get the DIV with overlay effect

var overlayBg = document.getElementById("myOverlay");



// Toggle between showing and hiding the sidebar, and add overlay effect

function w3_open() {

    if (mySidebar.style.display === 'block') {

        mySidebar.style.display = 'none';

        overlayBg.style.display = "none";

    } else {

        mySidebar.style.display = 'block';

        overlayBg.style.display = "block";

    }

}



// Close the sidebar with the close button

function w3_close() {

    mySidebar.style.display = "none";

    overlayBg.style.display = "none";

}

  </script>

  <script>



if ("<?php echo $_SESSION['gps']?>" == "true"){

  GPSrefresh();

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