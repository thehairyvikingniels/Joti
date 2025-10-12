<?php
define("PAGE_NAME", "instellingen");
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

      $an = $row['achternaam'];

      $email = $row['email'];

      $api = $row['api'];

      $priv = $row['priv'];

      $username = $row['gebruikersnaam'];

    }

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


<!-- Sidebar -->
<?php include_once('includes/sidebar.php') ?>


<!-- !PAGE CONTENT! -->

<div class="w3-main" style="margin-left:200px;margin-top:43px;">



  <!-- Header -->

  <header class="w3-container" style="padding-top:22px">

    <h5><b><i class="fas fa-cog"></i> Instellingen</b></h5>

  </header>

  <div class="w3-row w3-margin">

    <div class="w3-third w3-padding">

      <div id="gegevens" class="w3-card w3-white w3-padding">

        <?php if (isset($_GET['t'])){if($_GET['t'] == "gegevens"){

        echo '<div class="w3-panel w3-blue w3-display-container">

              <span onclick="this.parentElement.style.display=\'none\'" class="w3-button w3-large w3-display-topright">&times;</span>

              <p>'.$_GET['e'].'</p>

              </div>'; }} ?>

        <form method="POST" action="instellingen_helper.php">

          <h3>Gegevens wijzigen</h3>

          <h5>Gebruikersnaam:</h5>

          <input name="username" type="text" value="<?php echo $username;?>" required minlength="5" maxlength="32" style="width:100%">

          <h5>Voornaam:</h5>

          <input name="firstname" type="text" value="<?php echo ucfirst($vn);?>" required style="width:100%">

          <h5>Achternaam:</h5>

          <input name="lastname" type="text" value="<?php echo ucfirst($an);?>" required style="width:100%">

          <h5>Email:</h5>

          <input name="email" type="email" value="<?php echo $email;?>" required style="width:100%"><br><br>

          <center><button type="submit" class="w3-button w3-blue-gray">Verander</button></center>

        </form>

      </div>

    </div>

    <div class="w3-third w3-padding">

      <div id="wachtwoord" class="w3-card w3-white w3-padding">

        <?php if (isset($_GET['t'])){if($_GET['t'] == "wachtwoord"){

        echo '<div class="w3-panel w3-blue w3-display-container">

              <span onclick="this.parentElement.style.display=\'none\'" class="w3-button w3-large w3-display-topright">&times;</span>

              <p>'.$_GET['e'].'</p>

              </div>'; }} ?>

        <form method="POST" action="instellingen_helper.php">

          <h3>Wachtwoord wijzigen</h3>

          <h5>Wachtwoord:</h5>

          <input name="pswd0" type="password" placeholder="Wachtwoord" required minlength="8" style="width:100%">

          <h5>Herhaal Wachtwoord:</h5>

          <input name="pswd1" type="password" placeholder="Wachtwoord" required minlength="8" style="width:100%"><br><br>

          <center><button type="submit" class="w3-button w3-blue-gray">Verander</button></center>

        </form>

      </div>

    </div>

    <div class="w3-third w3-padding">

      <div id="api" class="w3-card w3-white w3-padding">

        <?php if (isset($_GET['t'])){if($_GET['t'] == "api"){

        echo '<div class="w3-panel w3-blue w3-display-container">

              <span onclick="this.parentElement.style.display=\'none\'" class="w3-button w3-large w3-display-topright">&times;</span>

              <p>'.$_GET['e'].'</p>

              </div>'; }} ?>

        <form method="POST" action="instellingen_helper.php">

          <h3><a href="https://docs.google.com/document/d/1XO9K8cVwgysytti1LSQa2i3dpYmeWNQ8BS5sSwqNjgY/edit?usp=sharing">Api</a> key voor telegram</h3>

          <h5>Key:</h5>

          <input name="api" type="text" value="<?php echo $api;?>" readonly style="width:100%"><br><br>

          <center><button type="submit" class="w3-button w3-blue-gray">Hergenereer</button></center>

        </form>

      </div>

    </div>

  </div>

  <!-- Footer -->
  <?php require_once('includes/footer.php') ?>



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