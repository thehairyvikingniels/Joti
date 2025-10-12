<?php
define("PAGE_NAME", "a_database");

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



<!-- Sidebar -->
<?php include_once('../includes/sidebar.php') ?>

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

