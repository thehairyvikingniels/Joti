<?php

session_start();

if (!isset($_SESSION['id'])){

  header("Location: index");

}

require("dblogin.php");



$sql = "SELECT * FROM Gebruikers WHERE id='".$_SESSION['id']."'";

$result = mysqli_query($conn, $sql);



if (mysqli_num_rows($result) > 0) {

    // output data of each row

    while($row = mysqli_fetch_assoc($result)) {

      $vn = $row['voornaam'];

      $priv = $row['priv'];

    }

} else {

    echo "0 results";

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

<script src="https://kit.fontawesome.com/870ab34ea3.js" crossorigin="anonymous"></script>

<style>

html,body,h1,h2,h3,h4,h5 {font-family: "Raleway", sans-serif}

</style>

<body class="w3-light-grey">



<!-- Top container -->

<div class="w3-bar w3-top w3-black w3-large" style="z-index:4">

  <button class="w3-bar-item w3-button w3-hide-large w3-hover-none w3-hover-text-light-grey" onclick="w3_open();"><i class="fa fa-bars"></i>  Menu</button>

  <span class="w3-bar-item w3-right">De Geuzen Arnhem</span>

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

      <a href="functies?gpstoggle=true&return=vossen" class="w3-bar-item w3-button <?php if ($_SESSION['gps'] == "true"){echo "w3-green";}else{echo "w3-red";} ?>"><i class="fas fa-location-arrow"></i></a>

    </div>

  </div>

  <hr>

  <div class="w3-container">

    <h5>Dashboard</h5>

  </div>

  <div class="w3-bar-block">

    <a href="#" class="w3-bar-item w3-button w3-padding-16 w3-hide-large w3-dark-grey w3-hover-black" onclick="w3_close()" title="close menu"><i class="fa fa-remove fa-fw"></i>  Sluit Menu</a>

    <a href="home" class="w3-bar-item w3-button w3-padding"><i class="fa fa-users fa-fw"></i>  Overzicht</a>

    <?php if ($priv > 0){echo '<a href="kaarten" class="w3-bar-item w3-button w3-padding"><i class="fas fa-map-marked-alt fa-fw"></i>  Kaarten</a>';}?>

    <?php if ($priv > 0){echo '<a href="hunts" class="w3-bar-item w3-button w3-padding"><i class="fas fa-map-marker-alt fa-fw"></i>  Hunt!</a>';}?>

    <?php if ($priv > 0){echo '<a href="vossen" class="w3-bar-item w3-button w3-padding w3-blue"><i class="fas fa-bullseye fa-fw"></i>  Vossen</a>';}?>

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
    
    <?php if ($priv > 2){echo '<a href="admin/settings" class="w3-bar-item w3-button w3-padding"><i class="fas fa-toolbox fa-fw"></i>  [Admin] Settings</a>';} ?><br><br>

  </div>

</nav>

</nav>





<!-- Overlay effect when opening sidebar on small screens -->

<div class="w3-overlay w3-hide-large w3-animate-opacity" onclick="w3_close()" style="cursor:pointer" title="close side menu" id="myOverlay"></div>



<!-- !PAGE CONTENT! -->

<div class="w3-main" style="margin-left:200px;margin-top:43px;">



  <!-- Header -->

  <header class="w3-container" style="padding-top:22px">

    <h5><b><i class="fas fa-bullseye"></i> Vossen</b></h5>

  </header>

  <div>

    <table class="w3-table w3-blue" style="width:100%">



  <?php

  $sql = "SELECT count(*) as sum, max(datumtijd) as max, min(datumtijd) as min FROM Voslog ORDER BY datumtijd desc";

  $result = mysqli_query($conn, $sql);

  $row = mysqli_fetch_assoc($result);

  echo "

  <tr style=\"height:35px;padding:5px\">

    <td></td>

    <td colspan=\"".round($row['sum']/2,0,PHP_ROUND_HALF_DOWN)."\" class=\"w3-display-container\"><span class=\"w3-display-bottomleft\"><i class=\"fas fa-arrow-turn-up fa-rotate-180\"></i> ".time2str($row['max'])."</span></td>

    <td colspan=\"".round($row['sum']/2,0,PHP_ROUND_HALF_UP)."\" class=\"w3-display-container\"><span class=\"w3-display-bottomright\">".time2str($row['min'])." <i class=\"fas fa-arrow-turn-down\"></i></span></td>

  </tr>

  ";







  $allevossen = array("alpha","bravo","charlie","delta","echo","foxtrot","golf","hotel");      

  foreach($allevossen as $vosnaam){

    echo "<tr>";

    echo "<td style='width:10%'>".ucfirst($vosnaam)."</td>";

    $sql = "SELECT * FROM Voslog ORDER BY datumtijd desc";

    $result = mysqli_query($conn, $sql);



    if (mysqli_num_rows($result) > 0) {

      // output data of each row

      $a = 0;

      while($row = mysqli_fetch_assoc($result)) {

        if (!isset($largenum)) {

          $largenum = strtotime($row["datumtijd"]);

        }

        $vossen[$a] = ($largenum - strtotime($row["datumtijd"]));

        ${"status_".$vosnaam}[$a] = $row[$vosnaam];

        $a++;

      }

      $widthtotal = 0;

      $a = 0;

      foreach($vossen as $vos) {

        if (${"status_".$vosnaam}[$a] == 0){

          $kleur = "red";

        } elseif (${"status_".$vosnaam}[$a] == 1){

          $kleur = "orange";

        } elseif (${"status_".$vosnaam}[$a] == 2){

          $kleur = "green";

        }

        $width = $vos / $vossen[count($vossen)-1]*100;

        echo "<td style='padding:0px;width:".(($width - $widthtotal)/100*90)."%' class='w3-".$kleur."'></td>";

        $widthtotal += $width;

        $a++;

      }

    } else {

      echo "Geen resultaten";

    }

    echo "</tr>";

  }

  ?>

      </tr>

    </table>

  </div>



  <!-- Footer -->

  <footer class="w3-container w3-padding-16 w3-dark-grey">

    <center><p><a href="#">Niels Maarleveld</a> - &copy; <?php echo date("Y");?></p>

  </footer>



  <!-- End page content -->

</div>



<script>

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