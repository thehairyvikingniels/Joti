<?php

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

      <img src="../media/geusje.png" class="w3-margin-right" style="width:46px">

    </div>

    <div class="w3-col s8 w3-bar">

      <span>Welkom, <strong><?php echo ucfirst($vn); ?></strong></span><br>

      <a href="index" class="w3-bar-item w3-button"><i class="fas fa-sign-out-alt"></i></a>

      <a href="functies?gpstoggle=true&return=instellingen" class="w3-bar-item w3-button <?php if ($_SESSION['gps'] == "true"){echo "w3-green";}else{echo "w3-red";} ?>"><i class="fas fa-location-arrow"></i></a>

    </div>

  </div>

  <hr>

  <div class="w3-container">

    <h5>Dashboard</h5>

  </div>

  <div class="w3-bar-block">

    <a href="#" class="w3-bar-item w3-button w3-padding-16 w3-hide-large w3-dark-grey w3-hover-black" onclick="w3_close()" title="close menu"><i class="fa fa-remove fa-fw"></i>  Sluit Menu</a>

    <a href="../home" class="w3-bar-item w3-button w3-padding"><i class="fa fa-users fa-fw"></i>  Overzicht</a>

    <a href="../kaarten" class="w3-bar-item w3-button w3-padding"><i class="fas fa-map-marked-alt fa-fw"></i>  Kaarten</a>

    <a href="../hunts" class="w3-bar-item w3-button w3-padding"><i class="fas fa-map-marker-alt fa-fw"></i>  Hunt!</a>

    <a href="../vossen" class="w3-bar-item w3-button w3-padding"><i class="fas fa-bullseye fa-fw"></i>  Vossen</a>

    <a href="../nieuws" class="w3-bar-item w3-button w3-padding"><i class="far fa-newspaper fa-fw"></i>  Nieuws</a>

    <a href="../opdrachten" class="w3-bar-item w3-button w3-padding"><i class="far fa-bell fa-fw"></i>  Opdrachten</a>

    <a href="../hints" class="w3-bar-item w3-button w3-padding"><i class="fas fa-question-circle fa-fw"></i>  Hints</a>

    <a href="../punten" class="w3-bar-item w3-button w3-padding"><i class="fas fa-trophy fa-fw"></i>  Punten</a>

    <a href="../groepen" class="w3-bar-item w3-button w3-padding"><i class="fas fa-home fa-fw"></i>  Groepen</a>

    <a href="../instellingen" class="w3-bar-item w3-button w3-padding"><i class="fas fa-cog fa-fw"></i>  Instellingen</a>

    <?php if ($priv > 0){echo '<a href="../autos" class="w3-bar-item w3-button w3-padding"><i class="fas fa-car fa-fw"></i>  Auto\'s</a>';}?>

    <?php if ($priv > 1){echo '<a href="users" class="w3-bar-item w3-button w3-padding"><i class="fas fa-user-cog fa-fw"></i>  [Admin] Users</a>';} ?>

    <?php if ($priv > 1){echo '<a href="cronjobs" class="w3-bar-item w3-button w3-padding"><i class="fas fa-stopwatch fa-fw"></i>  [Admin] Cronjobs</a>';} ?>

    <?php if ($priv > 1){echo '<a href="database" class="w3-bar-item w3-button w3-padding w3-blue"><i class="fas fa-database fa-fw"></i>  [Admin] Database</a>';} ?>

    <?php if ($priv > 2){echo '<a href="settings" class="w3-bar-item w3-button w3-padding"><i class="fas fa-toolbox fa-fw"></i>  [Admin] Settings</a>';} ?>

    <br><br>

  </div>

</nav>

</nav>





<!-- Overlay effect when opening sidebar on small screens -->

<div class="w3-overlay w3-hide-large w3-animate-opacity" onclick="w3_close()" style="cursor:pointer" title="close side menu" id="myOverlay"></div>



<!-- !PAGE CONTENT! -->

<div class="w3-main" style="margin-left:200px;margin-top:43px;">



  <!-- Header -->

  <header class="w3-container" style="padding-top:22px">

    <h5><b><i class="fas fa-cogs"></i> Admin</b></h5>

  </header>

  <div class="w3-row" style="margin-bottom:100px;">

    <div class="w3-col l7 m12 s12 w3-padding">

      <div class="w3-card-4 w3-white">

        <div class="w3-blue-gray w3-padding" style="width:100%">

          <h5>Database [WIP]</h5>

        </div>

        

      </div>

    </div>



  </div>

  <!-- Footer -->
  <?php require_once('../includes/footer.php') ?>



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

