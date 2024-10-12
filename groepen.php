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
      <img src="media/geusje.png" class="w3-margin-right" style="width:46px">
    </div>
    <div class="w3-col s8 w3-bar">
      <span>Welkom, <strong><?php echo ucfirst($vn); ?></strong></span><br>
      <a href="index" class="w3-bar-item w3-button"><i class="fas fa-sign-out-alt"></i></a>
      <a href="functies?gpstoggle=true&return=groepen" class="w3-bar-item w3-button <?php if ($_SESSION['gps'] == "true"){echo "w3-green";}else{echo "w3-red";} ?>"><i class="fas fa-location-arrow"></i></a>
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
    <?php if ($priv > 0){echo '<a href="vossen" class="w3-bar-item w3-button w3-padding"><i class="fas fa-bullseye fa-fw"></i>  Vossen</a>';}?>    
    <a href="nieuws" class="w3-bar-item w3-button w3-paddings"><i class="far fa-newspaper fa-fw"></i>  Nieuws</a>
    <a href="opdrachten" class="w3-bar-item w3-button w3-padding"><i class="far fa-bell fa-fw"></i>  Opdrachten</a>
    <a href="hints" class="w3-bar-item w3-button w3-padding"><i class="fas fa-question-circle fa-fw"></i>  Hints</a>
    <?php  if ($priv > 0){echo '<a href="punten" class="w3-bar-item w3-button w3-padding"><i class="fas fa-trophy fa-fw"></i>  Punten</a>';}?>
    <a href="groepen" class="w3-bar-item w3-button w3-padding w3-blue"><i class="fas fa-home fa-fw"></i>  Groepen</a>
    <a href="instellingen" class="w3-bar-item w3-button w3-padding"><i class="fas fa-cog fa-fw"></i>  Instellingen</a>
    <?php if ($priv > 0){echo '<a href="autos" class="w3-bar-item w3-button w3-padding"><i class="fas fa-car fa-fw"></i>  Auto\'s</a>';}?>
    <?php if ($priv > 1){echo '<a href="admin/users" class="w3-bar-item w3-button w3-padding"><i class="fas fa-user-cog fa-fw"></i>  [Admin] Users</a>';} ?>
    <?php if ($priv > 1){echo '<a href="admin/cronjobs" class="w3-bar-item w3-button w3-padding"><i class="fas fa-stopwatch fa-fw"></i>  [Admin] Cronjobs</a>';} ?>
    <?php if ($priv > 1){echo '<a href="admin/database" class="w3-bar-item w3-button w3-padding"><i class="fas fa-database fa-fw"></i>  [Admin] Database</a>';} ?><br><br>
  </div>
</nav>
</nav>


<!-- Overlay effect when opening sidebar on small screens -->
<div class="w3-overlay w3-hide-large w3-animate-opacity" onclick="w3_close()" style="cursor:pointer" title="close side menu" id="myOverlay"></div>

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
        if (ucfirst($row['deelgebied']) == "Alpha"){$color = "#9829FF";} elseif (ucfirst($row['deelgebied']) == "Bravo"){$color = "#2F9CEB";} elseif (ucfirst($row['deelgebied']) == "Charlie"){$color = "#2DFF69";} elseif (ucfirst($row['deelgebied']) == "Delta"){$color = "#F5F02C";} elseif (ucfirst($row['deelgebied']) == "Echo"){$color = "#FFA12E";} elseif (ucfirst($row['deelgebied']) == "Foxtrot"){$color = "#F52E2B";} else {$color = "#000000";}
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
  <footer class="w3-container w3-padding-16 w3-dark-grey">
    <center><p><a href="#">Niels Maarleveld</a> - &copy; <?php echo date("Y");?></p>
  </footer>

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
