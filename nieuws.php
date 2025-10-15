<?php
define("PAGE_NAME", "nieuws");

session_start();

if (!isset($_SESSION['id'])){

  header("Location: index");

}

require("dblogin.php");
require_once("functies.php");


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

    <h5><b><i class="far fa-newspaper fa-fw"></i> Nieuws</b></h5>

  </header>



  <div class="w3-row-padding w3-margin-bottom">

  <?php

  $sql = "SELECT * FROM Nieuws ORDER BY datum DESC";

  $result = mysqli_query($conn, $sql);

  

  if (mysqli_num_rows($result) > 0) {

      echo '<div class="w3-container">';

      echo '<ul class="w3-ul w3-card-4 w3-white">';

      while($row = mysqli_fetch_assoc($result)) {

        $content = $row['inhoud'];

        $doc=new DOMDocument();

        @$doc->loadHTML($content);

        $imgNodes = $doc->getElementsByTagName('img');

        foreach($imgNodes as $node) {

          $node->setAttribute('width', '100%');

          $node->removeAttribute('height');

        }

        echo '

        <li class="w3-padding-16" id="nieuws-'.$row['id'].'">

          <div class="w3-bar w3-blue-gray w3-padding w3-round-xlarge">

            <span class="w3-xlarge">'.$row['titel'].'</span><span style="float: right;">'.time2str($row['datum']).'</span>

          </div>

          <p>'.$doc->saveHTML().'</p>

        </li>

        ';

      }

    echo "</ul>";

      echo "</div>";

  } else {

      echo "0 results";

  } 

  ?>

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