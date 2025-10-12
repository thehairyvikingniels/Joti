<?php
define("PAGE_NAME", "autos");
session_start();

if (!isset($_SESSION['id'])){

  header("Location: index");

}

require("dblogin.php");



if (isset($_GET['delauto'])){

  $sql = "DELETE FROM Auto WHERE kenteken='".addslashes($_GET['delauto'])."'";

  if (mysqli_query($conn, $sql)) {

    header("Location: autos");

  } else {

    echo "Error updating record: " . mysqli_error($conn);

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


if (isset($_POST['kenteken'])){

  $sql = "INSERT INTO Auto (eigenaar, kenteken) VALUES ('".$_SESSION['id']."', '".addslashes($_POST['kenteken'])."') ON DUPLICATE KEY UPDATE eigenaar = eigenaar";

  if (mysqli_query($conn, $sql)) {

  } else {

    echo "Error: " . $sql . "<br>" . mysqli_error($conn);

  }

}



$sql = "SELECT * FROM Gebruikers WHERE id='".$_SESSION['id']."'";

$result = mysqli_query($conn, $sql);



if (mysqli_num_rows($result) > 0) {

    // output data of each row

    while($row = mysqli_fetch_assoc($result)) {

      $vn = $row['voornaam'];

      $priv = $row['priv'];

    }

}



if (isset($_POST['carid'])) {

  if ($_POST['carid'] == "geen") {

    $sql = "DELETE FROM Auto_Bijrijders WHERE gebruiker_id = '".$_SESSION['id']."'";

  } else {

    $sql = "INSERT INTO Auto_Bijrijders (auto, gebruiker_id) VALUES ('".addslashes($_POST['carid'])."','".$_SESSION['id']."') ON DUPLICATE KEY UPDATE auto = '".addslashes($_POST['carid'])."'";

  }

  $result = mysqli_query($conn, $sql);

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



<link rel="stylesheet" href="includes/numberPlate.css">

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

    <h5><b><i class="fas fa-car fa-fw"></i>Auto's</b></h5>

    <div class="w3-row">

      <div class="w3-col l6 m12 s12 w3-padding">

        <div class="w3-card-4 w3-white w3-padding">

          <h5>Auto aanmaken</h5>

          <form method="POST">    

            <center>

              <div class="form-control">

                <div class="car-license">

                  <abbr title="Netherlands" class="car-license__country-code">

                    <svg class="svg" viewBox="0 0 300 300" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="20" height="20"

                          aria-labelledby="euSymbol" role="img">

                            <title id="euSymbol">EU symbol</title>

                      <g id="s" transform="translate(150,30)" fill="#fc0">

                        <g id="c">

                          <path id="t" d="M 0,-20 V 0 H 10" transform="rotate(18 0,-20)"/>

                          <use xlink:href="#t" transform="scale(-1,1)"/>

                        </g>

                        <use xlink:href="#c" transform="rotate(72)"/>

                        <use xlink:href="#c" transform="rotate(144)"/>

                        <use xlink:href="#c" transform="rotate(216)"/>

                        <use xlink:href="#c" transform="rotate(288)"/>a

                      </g>

                      <use xlink:href="#s" transform="rotate(30 150,150) rotate(330 150,30)"/>

                      <use xlink:href="#s" transform="rotate(60 150,150) rotate(300 150,30)"/>

                      <use xlink:href="#s" transform="rotate(90 150,150) rotate(270 150,30)"/>

                      <use xlink:href="#s" transform="rotate(120 150,150) rotate(240 150,30)"/>

                      <use xlink:href="#s" transform="rotate(150 150,150) rotate(210 150,30)"/>

                      <use xlink:href="#s" transform="rotate(180 150,150) rotate(180 150,30)"/>

                      <use xlink:href="#s" transform="rotate(210 150,150) rotate(150 150,30)"/>

                      <use xlink:href="#s" transform="rotate(240 150,150) rotate(120 150,30)"/>

                      <use xlink:href="#s" transform="rotate(270 150,150) rotate(90 150,30)"/>

                      <use xlink:href="#s" transform="rotate(300 150,150) rotate(60 150,30)"/>

                      <use xlink:href="#s" transform="rotate(330 150,150) rotate(30 150,30)"/>

                    </svg>

                    <span>NL</span>

                  </abbr>

                    <div class="car-license__form-control">

                      <input type="text" class="car-license__input" id="input-kenteken" maxlength="8" autocomplete="off" name="kenteken" default="GE-LU-KT">  

                      <span class="valid-message"></span>

                    </div>

                </div>

              </div>

            </center>

            <center><button id="kentekenKnop" class="w3-button w3-disabled w3-ripple w3-margin w3-green" disabled>Aanmaken</button></center>

          </form>

          <hr>

          <table class="w3-table-all" style="width:100%">

            <tr class="w3-hide-small w3-hide-medium">

              <th>Kenteken</th>

              <th>Inzittenden</th>

              <th>Eigenaar</th>

              <th></th>

            </tr>

            <?php

            $sql = "

            SELECT 

              CONCAT(UPPER(SUBSTRING(ge.voornaam,1,1)),LOWER(SUBSTRING(ge.voornaam,2))) as eigenaar,

              ge.id as id,

              a.kenteken as kenteken,

              GROUP_CONCAT(CONCAT(UPPER(SUBSTRING(gb.voornaam,1,1)),LOWER(SUBSTRING(gb.voornaam,2))) SEPARATOR ', ') as inzittenden

            FROM Auto a

            LEFT JOIN Auto_Bijrijders ab

              on a.kenteken = ab.auto

            LEFT JOIN Gebruikers gb

              on gb.id = ab.gebruiker_id

            LEFT JOIN Gebruikers ge

              on ge.id = a.eigenaar

            GROUP BY a.kenteken

            ";

            $result = mysqli_query($conn, $sql);



            if (mysqli_num_rows($result) > 0) {

              // output data of each row

              while($row = mysqli_fetch_assoc($result)) {

                echo "<tr>";

                echo "  <td>".strtoupper($row['kenteken'])."</td>";

                echo "  <td class='w3-hide-small w3-hide-medium'>".$row['inzittenden']."</td>";

                echo "  <td>".$row['eigenaar']."</td>";

                if ($_SESSION['id'] == $row['id']){

                  echo " <td class='w3-right'><a href='autos?delauto=".$row['kenteken']."'><i class=\"fas fa-trash\"></i></a></td>";

                } else {

                  echo "<td></td>";

                }

                echo "</tr>";

                echo "<tr class='w3-hide-large'>";

                echo "  <td colspan='4'>".$row['inzittenden']."</td>";

                echo "</tr>";

              }

            }

            ?>

          </table>

        </div>

      </div>

      <div class="w3-col l6 m6 s12 w3-padding">

        <div class="w3-card-4 w3-white w3-padding">

          <h5>Stap in / uit</h5>

          <form method="POST">

            <select class="w3-select" name="carid">

              <option value="geen"  selected>Geen</option>

              <?php

              $sql = "SELECT a.kenteken, a.eigenaar, b.voornaam FROM Auto as a INNER JOIN Gebruikers as b ON a.eigenaar = b.id;";

              $result = mysqli_query($conn, $sql);

              if (mysqli_num_rows($result) > 0) {

                while($row = mysqli_fetch_assoc($result)) {

                  $bijrijders = json_decode($row['bijrijders'],true);

                  echo "<option value=\"".$row['kenteken']."\">Auto ".$row['kenteken']." ".ucfirst($row['voornaam'])."</option>";

                }

              }

            ?>

            </select>  

            <center><button type="submit" class="w3-button w3-green w3-margin">Vroem!</button></center>

          </form>

        </div>

      </div>

    </div>

  </header>



  <!-- Footer -->
  <?php require_once('includes/footer.php') ?>



  <!-- End page content -->

</div>



<!-- Number plate validation -->

<script type="text/javascript" src="includes/numberPlate.js"></script>

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