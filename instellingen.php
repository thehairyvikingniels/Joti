<?php
define("PAGE_NAME", "instellingen");
session_start();

if (!isset($_SESSION['id'])){

  header("Location: index");

}

require("dblogin.php");
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


<!-- Topbar -->
<?php include_once('includes/topbar.php') ?>


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