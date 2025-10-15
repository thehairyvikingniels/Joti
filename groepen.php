<?php
define("PAGE_NAME", "groepen");
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

      if ($row['lat']) {

        $usr_lat = $row['lat'];

        $usr_lon = $row['lon'];

      } else {

        // LAT LON van RB bij geen persoonlijke latlon

        $usr_lat = 51.98769228691746;

        $usr_lon = 5.876286397679744;

      }

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

    <h5><b><i class="fas fa-home fa-fw"></i> Deelnemende Scoutinggroepen</b></h5>

  </header>



  <div class="w3-margin-bottom">

    <div class="w3-container" style="position: sticky; top: 43px">

      <div class="w3-card-4 w3-white w3-padding" style="display: flex; justify-content: space-between; gap:10px">

        <input id="tableSearchInput" oninput="tableSearch()" class="w3-input w3-border w3-round" style="width:50%; flex-grow: 1" type="text" placeholder="Zoek">

        <div id="meta-distance-sorter" class="w3-large" onclick="sortTable('meta-distance')">

          <p><i class="fas fa-ruler fa-fw"></i></p>

        </div>

        <div id="meta-name-sorter" class="w3-large" onclick="sortTable('meta-name')">

          <p><i class="fas fa-font fa-fw"></i></p>

        </div>

        <div id="meta-subarea-sorter" class="w3-large" onclick="sortTable('meta-subarea')">

          <p><i class="fas fa-draw-polygon fa-fw"></i></p>

        </div>

      </div>

    </div>

  <?php

  $sql = "SELECT * FROM Groepen ORDER BY naam DESC";

  $result = mysqli_query($conn, $sql);

  

  if (mysqli_num_rows($result) > 0) {

      echo '<div class="w3-container w3-margin-top">';

      echo '<ul id="tableSearchTable" class="w3-ul w3-card-4 w3-white">';

      while($row = mysqli_fetch_assoc($result)) {
        switch (ucfirst($row['deelgebied'])) {
          case "Alpha":
            $color = "#9829FF";
            break;
          case "Bravo":
            $color = "#2F9CEB";
            break;
          case "Charlie":
            $color = "#2DFF69";
            break;
          case "Delta":
            $color = "#F5F02C";
            break;
          case "Echo":
            $color = "#FFA12E";
            break;
          case "Foxtrot":
            $color = "#F52E2B";
            break;
          case "Golf":
            $color = "#FF6F6F";
            break;
          case "Hotel":
            $color = "#00BFA5";
            break;
          default:
            $color = "#000000";
            break;
        }

        echo '

        <li class="w3-padding-16" meta-name="'.$row['naam'].'" meta-subarea="'.$row['deelgebied'].'" meta-distance="'.latlon_dist($row['lat'], $row['lon'], $usr_lat, $usr_lon).'">

          <div class="w3-bar w3-blue-gray w3-padding w3-round">

            <span class="w3-large w3-tag w3-text-black w3-round" style="background-color:'.$color.'">'.$row['deelgebied'].'</span>

            <span class="w3-large w3-hide-large w3-hide-medium" style="float:right">'.$row['naam'].'</span>

            <span class="w3-xlarge w3-hide-small w3-margin-left">'.$row['naam'].'</span>

          </div>

          <div style="display: flex; justify-content: space-between; gap: 10px 20px; flex-wrap: wrap; align-items: center;">

            <div class="" style="width:75px">

              <img src="'.$row['url'].'" style="width:100%">

            </div>

            <div class="w3-hide-small w3-hide-medium" style="width:100px">



            </div>

            <div style="width: 250px; flex-grow: 2">

              <p>

                <i class="fas fa-map-pin fa-fw"></i> <span>'.$row['lat'].', '.$row['lon'].'</span></br>

                <i class="fas fa-map-marked fa-fw"></i> <span>'.ucfirst($row['straat']).' '.$row['huisnummer'].', '.ucfirst($row['plaats']).'</span></br>

                <i class="fas fa-ruler fa-fw"></i> <span>'.round(latlon_dist($row['lat'], $row['lon'], $usr_lat, $usr_lon)/1000, 1).'km</span>

              </p>

            </div>

            <div style="min-width: 200px; flex-grow: 1; display: flex; justify-content: space-around; align-items: center">

              <a href=""><div class="w3-button w3-blue-gray w3-round">Tegenhunt</div></a>

              <a href="http://www.google.com/maps/dir/?api=1&destination='.urlencode($row['straat'].' '.$row['huisnummer'].', '.$row['plaats']).'&travelmode=driving" target="_blank"><div class="w3-button w3-blue-gray w3-round">Navigeer</div></a>

              <a href="http://www.google.com/maps/search/?api=1&query='.$row['lat'].','.$row['lon'].'" target="_blank"><div class="w3-button w3-blue-gray w3-round">Maps</div></a>

            </div>

          </div>

        </li>

        ';

      }

    echo "</ul>";

      echo "</div>";

  } else {

      echo "Geen resultaten...";

  } 

  ?>

  </div>

  <!-- Footer -->
  <?php require_once('includes/footer.php') ?>



  <!-- End page content -->

</div>



<script>

  // seaarch function for groups

function tableSearch() {

  var input, ul, items, metaName;

  input = document.getElementById("tableSearchInput");

  ul = document.getElementById("tableSearchTable");

  items = ul.getElementsByTagName("li");



  // Loop through all LI's, and hide those who don't match the search query

  for (i = 0; i < items.length; i++) {

    metaName = items[i].getAttribute("meta-name").toUpperCase();



    if (metaName.includes(input.value.toUpperCase())) {

      items[i].style.display = "";

    } else {

      items[i].style.display = "none";

    }

  }

}



function sortTable(metaType) {

  var table, rows, switching, i, x, y, shouldSwitch;

  table = document.getElementById("tableSearchTable");

  icon = document.getElementById(metaType+"-icon");

  switching = true;

  /*Make a loop that will continue until

  no switching has been done:*/

  while (switching) {

    //start by saying: no switching is done:

    switching = false;

    rows = table.getElementsByTagName("li");

    /*Loop through all table rows:*/

    for (i = 0; i < (rows.length - 1); i++) {

      //start by saying there should be no switching:

      shouldSwitch = false;

      /*Get the two elements you want to compare,

      one from current row and one from the next:*/

      x = rows[i].getAttribute(metaType);

      y = rows[i + 1].getAttribute(metaType);

      //check if the two rows should switch place:

      if (metaType == "meta-distance") {

        if (parseFloat(x) > parseFloat(y)) {

          //if so, mark as a switch and break the loop:

          shouldSwitch = true;

          break;

        }

      } else {

        if (x.toLowerCase() > y.toLowerCase()) {

          //if so, mark as a switch and break the loop:

          shouldSwitch = true;

          break;

        }

      }

    }

    if (shouldSwitch) {

      /*If a switch has been marked, make the switch

      and mark that a switch has been done:*/

      rows[i].parentNode.insertBefore(rows[i + 1], rows[i]);

      switching = true;

    }

  }

}0

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