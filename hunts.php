<?php
define("PAGE_NAME", "hunts");

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


if (isset($_POST['deelgebied']) && isset($_POST['code']) && isset($_POST['lat']) && isset($_POST['lon'])) {

  if(empty($_POST["lat"])){

    $lat = $lon = "0.0";

  } else {

    $lat = $_POST['lat'];

    $lon = $_POST['lon'];

  }

  echo date("d-m-Y H:i:s",strtotime($_POST['tijd']));

  $sql = "INSERT INTO Voslocaties (deelgebied, coordinaat_x, coordinaat_y, type, code, ingestuurd_op)

  VALUES ('".ucfirst($_POST['deelgebied'])."', '".$lat."', '".$lon."', 'Hunt', '".$_POST['code']."', '".date('Y-m-d H:i:s',strtotime($_POST['tijd']))."')";

  

  if (mysqli_query($conn, $sql)) {

  

  } else {

      echo "Error: " . $sql . "<br>" . mysqli_error($conn);

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

<!-- Sidebar -->
<?php include_once('includes/sidebar.php') ?>


<!-- !PAGE CONTENT! -->

<div class="w3-main" style="margin-left:200px;margin-top:43px;">



  <!-- Header -->

  <header class="w3-container" style="padding-top:22px">

    <h5><b><i class="fas fa-map-marker-alt"></i> Hunt melden</b></h5>

    <div class="w3-card-4 w3-margin">

    

      <div class="w3-container w3-blue-grey">

        <h2>Meld hier de hunt van een vossenteam</h2>

        <h5>De code wordt op de homebase door iemand voor je ingevuld!</h5>

      </div>

      

      <form class="w3-container w3-padding" method="post">

        <br>

        <label>Deelgebied</label>

        <select class="w3-select w3-round-xlarge" name="deelgebied" required>

          <option value="" disabled selected>Kies deelgebeid van hunt</option>

          <option value="Alpha">Alpha</option>

          <option value="Bravo">Bravo</option>

          <option value="Charlie">Charlie</option>

          <option value="Delta">Delta</option>

          <option value="Echo">Echo</option>

          <option value="Foxtrot">Foxtrot</option>

          <option value="Golf">Golf</option>

          <option value="Hotel">Hotel</option>

        </select>

        <label>Tijd</label>

        <input class="w3-input w3-round-xlarge" type="time" name="tijd" required>

        <label>Code</label>

        <input class="w3-input w3-round-xlarge" name="code" type="text" size="11" maxlength="11" style="text-transform:uppercase" required>

        <br>

        <label>Gebruik huidige locatie</label>

        <span class="w3-button w3-green w3-round-xlarge" onclick="GPSlocate('ja')">Ja</span>

        <span class="w3-button w3-red w3-round-xlarge"   onclick="GPSlocate('nee')">Nee</span>

        <div id="map" class="w3-margin-top w3-round-xlarge" style="display:none">

        

        </div>

        <br>

        <input type="hidden" id="lat" name="lat" value="0.0">

        <input type="hidden" id="lon" name="lon" value="0.0">

        <div id="knoppie">

          

        </div>

      </form>

    

    </div>

  </header>



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

  

    

  </script><script>  

    function GPSlocate(str) {

      if (str == "nee"){

        document.getElementById('map').innerHTML = '<h4>Nu komt deze hunt niet op de huntenkaart te staan...</h4>';

        document.getElementById('map').style.display = 'block';

        document.getElementById('lat').value = '0.0';

        document.getElementById('lon').value = '0.0';

        document.getElementById('knoppie').innerHTML = '<button class="w3-button w3-dark-grey w3-margin-bottom w3-center w3-round-xlarge" type="submit">Verstuur</button>';

      } else {

        document.getElementById('map').innerHTML = '<iframe id="iframe01" src="" style="width:100%; height:40vh"></iframe>';

        if (navigator.geolocation) {

            navigator.geolocation.getCurrentPosition(showPosition);

        } else {

            console.log("Geolocation is not supported by this browser.");

        }

        function showPosition(position) {

         console.log("Latitude: " + position.coords.latitude + 

          "<br>Longitude: " + position.coords.longitude);

            var url = "maps.php?punt_lat="+position.coords.latitude+"&punt_lon="+position.coords.longitude;

            document.getElementById('iframe01').src = url;

            document.getElementById('map').style.display = 'block';

            document.getElementById('lat').value = position.coords.latitude;

            document.getElementById('lon').value = position.coords.longitude;

            document.getElementById('knoppie').innerHTML = '<button class="w3-button w3-dark-grey w3-margin-bottom w3-center w3-round-xlarge" type="submit">Verstuur</button>';

        }

      }

      

      



   

   



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