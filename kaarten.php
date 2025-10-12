<?php
define("PAGE_NAME", "kaarten");
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

    <h5><b><i class="fas fa-map-marked-alt"></i> Kaarten</b></h5>

  </header>

  <div class="w3-row">

    <div class="w3-container" style="width:100%;">

      <div>

        <form>

          <input type="checkbox" onclick="kaartveranderen()" id="groepen" name="groepen" value="true"><label for="groepen">Groepen</label>

          <input type="checkbox" onclick="kaartveranderen()" id="personen" name="personen" value="true"> <label for="personen">Personen</label>

          <input type="checkbox" onclick="kaartveranderen()" id="hints" name="hints" value="true"> <label for="hints">Vossen (locaties)</label>

          <input type="checkbox" onclick="kaartveranderen()" id="vossenpad" name="vossenpad" value="true"> <label for="vossenpad">Vossenpad</label>
          
          <input type="checkbox" onclick="kaartveranderen()" id="predicted_route" name="predicted_route" value="true"> <label for="predicted_route">Voorspelde Route</label><br>

        </form>

      </div>

      <iframe id="iframe01" src="maps.php?groepen=false&personen=false&hints=false&vossenpad=false&predicted_route=false" style="width:100%; height:73vh"></iframe>

    </div>

  </div>

  <!-- Footer -->
  <?php require_once('includes/footer.php') ?>



  <!-- End page content -->

</div>



<script>

function kaartveranderen(a) {

  // Get the checkbox

  var groepen = document.getElementById("groepen");

  var personen = document.getElementById("personen");

  var hints = document.getElementById("hints");

  var vossenpad = document.getElementById("vossenpad");

  var predicted_route = document.getElementById("predicted_route");

  

  var url = "maps.php?";

  // If the checkbox is checked, display the output text

  url += "groepen=" + (groepen.checked ? "true" : "false");
  url += "&personen=" + (personen.checked ? "true" : "false");
  url += "&hints=" + (hints.checked ? "true" : "false");
  url += "&vossenpad=" + (vossenpad.checked ? "true" : "false");
  url += "&predicted_route=" + (predicted_route.checked ? "true" : "false");

  

  document.getElementById('iframe01').src = url;

}

  
  </script>

  <script>
    

// elke 5 sec locatie opvragen (als gebruiker het aan heeft staan)

if ("<?php echo $_SESSION['gps']?>" == "true"){

  setInterval(function() {

    GPSrefresh();

  }, 5555);

}

  

 // Locatie opvragen 

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

