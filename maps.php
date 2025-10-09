<!DOCTYPE html>

<html>

<head>

<meta charset=utf-8 />

<title>Dit hoor jij niet te zien...</title>

<meta name='viewport' content='initial-scale=1,maximum-scale=1,user-scalable=no' />

<script src='https://api.mapbox.com/mapbox-gl-js/v2.10.0/mapbox-gl.js'></script>

<link href='https://api.mapbox.com/mapbox-gl-js/v2.10.0/mapbox-gl.css' rel='stylesheet' />

<style>

  body { margin:0; padding:0; }

  #map { position:absolute; top:0; bottom:0; width:100%; }

</style>

</head>

<body>

<div id='map'></div>

<script>

<?php

$lat = 52.121581;

$lon = 5.910389;

  

if(isset($_GET['fullscreen'])){

  $zoom = 10;

} else {

  $zoom = 9;

}

if (isset($_GET['punt_lat'])){

  $zoom = 14;

  $lat = $_GET['punt_lat'];

  $lon = $_GET['punt_lon'];

}



require("dblogin.php");

echo "         

mapboxgl.accessToken = 'pk.eyJ1IjoidGhlaGFpcnl2aWtpbmduaWVscyIsImEiOiJjam40YzI2eGEwMjh6M3hscGEweHpxYzg1In0.3obc3XmgMCZ-rY5LLzhW2A';



const map = new mapboxgl.Map({

  container: 'map', // container ID

  style: 'mapbox://styles/mapbox/streets-v11', // style URL

  center: [$lon,$lat], // starting position [lng, lat]

  zoom: $zoom, // starting zoom

});

";



if (isset($_GET['groepen'])){

  if ($_GET['groepen'] == "true"){

    $sql = "SELECT * FROM Groepen";

    $result = mysqli_query($conn, $sql);

    

    if (mysqli_num_rows($result) > 0) {

      // output data of each row

      $i=0;

      while($row = mysqli_fetch_assoc($result)) {

        $row["deelgebied"] = lcfirst($row["deelgebied"]);

        if ($row["deelgebied"] == null) {$row["deelgebied"] = "unknown";}

          echo "

          const img_".$i." = document.createElement('div');

          img_".$i.".className = 'marker';

          img_".$i.".style.backgroundImage = `url(media/icons/pin_hut_".$row['deelgebied'].".png)`;

          img_".$i.".style.width = `40px`;

          img_".$i.".style.height = `32px`;

          img_".$i.".style.backgroundSize = '100%';



          const marker_".$i." = new mapboxgl.Marker(img_".$i.")

              .setLngLat([".$row['lon'].",".$row['lat']."])

              .setPopup(new mapboxgl.Popup().setHTML(\"".ucfirst(addslashes($row['naam']))."</br>Deelgebied: ".$row['deelgebied']."<br><a href='https://www.google.com/maps/dir/?api=1&origin=&destination=".urlencode($row['straat']." ".$row['huisnummer']." ".$row['plaats'])."&travelmode=driving&dir_action=navigate' target='_blank'>Navigeer</a>\"))

              .addTo(map)

          ";

        $i++;

      }      

    }

  }

}

  

if (isset($_GET['punt_lat'])){

  echo "

  const marker_point = new mapboxgl.Marker()

  .setLngLat([".$_GET['punt_lon'].",".$_GET['punt_lat']."])

  .addTo(map)

  ";

}



if (isset($_GET['autos'])){

  if ($_GET['autos'] == "true"){

    $sql = "SELECT * FROM Autos";

    $result = mysqli_query($conn, $sql);

    

    if (mysqli_num_rows($result) > 0) {

      // output data of each row

        while($row = mysqli_fetch_assoc($result)) {

            $sunrise = date('Y-m-d H:i:s', strtotime('-10 seconds'));

            $sunset = date('Y-m-d H:i:s', strtotime('+10 seconds'));

            $date1 = DateTime::createFromFormat('Y-m-d H:i:s', $row['geotijd']);

            $date2 = DateTime::createFromFormat('Y-m-d H:i:s', $sunrise);

            $date3 = DateTime::createFromFormat('Y-m-d H:i:s', $sunset);

            if ($date1 > $date2 && $date1 < $date3)

            {

              echo "

                L.marker([".$row['lat'].", ".$row['lon']."],{

                  icon: L.mapbox.marker.icon({

                    'marker-size': 'large',

                    'marker-symbol': 'car',

                    'marker-color': '#077c32'

                  })

                })

                  .addTo(map)

                  .bindPopup('Auto ".$row['id']." is hier.');

              ";

              goto aaa;

            }

            $sunrise = date('Y-m-d H:i:s', strtotime('-900 seconds'));

            $sunset = date('Y-m-d H:i:s', strtotime('+7 seconds'));

            $date2 = DateTime::createFromFormat('Y-m-d H:i:s', $sunrise);

            $date3 = DateTime::createFromFormat('Y-m-d H:i:s', $sunset);

          if ($date1 > $date2 && $date1 < $date3) {

              echo "

                L.marker([".$row['lat'].", ".$row['lon']."],{

                  icon: L.mapbox.marker.icon({

                    'marker-size': 'small',

                    'marker-symbol': 'car',

                    'marker-color': '#FFFFFF'

                  })

                })

                  .addTo(map)

                  .bindPopup('Auto ".$row['id']." was hier ".time2str($row['geotijd'])."');

              ";

            }

          aaa:

        }

    }

  }

}

  

 

if (isset($_GET['personen'])){

  if ($_GET['personen'] == "true"){

    $sql = "SELECT * FROM Gebruikers";

    $result = mysqli_query($conn, $sql);

    

    if (mysqli_num_rows($result) > 0) {

      // output data of each row

      $i=0;

      while($row = mysqli_fetch_assoc($result)) {

        $sunrise = date('Y-m-d H:i:s', strtotime('-10 seconds'));

        $sunset = date('Y-m-d H:i:s', strtotime('+10 seconds'));

        $date1 = DateTime::createFromFormat('Y-m-d H:i:s', $row['geotijd']);

        $date2 = DateTime::createFromFormat('Y-m-d H:i:s', $sunrise);

        $date3 = DateTime::createFromFormat('Y-m-d H:i:s', $sunset);

        if ($date1 > $date2 && $date1 < $date3) {

          echo "

          const person_".$i." = document.createElement('div');

          person_".$i.".className = 'marker';

          person_".$i.".style.backgroundImage = `url(media/icons/pin_user.png)`;

          person_".$i.".style.width = `40px`;

          person_".$i.".style.height = `32px`;

          person_".$i.".style.backgroundSize = '100%';



          const marker_".$i." = new mapboxgl.Marker(person_".$i.")

              .setLngLat([".$row['lon'].",".$row['lat']."])

              .setPopup(new mapboxgl.Popup().setHTML(\"".ucfirst(str_replace("'"," ",$row['gebruikersnaam']))." was hier ".time2str($row['geotijd'])."\"))

              .addTo(map)

          ";

          goto next;

        }

        $sunrise = date('Y-m-d H:i:s', strtotime('-900 seconds'));

        $sunset = date('Y-m-d H:i:s', strtotime('+7 seconds'));

        $date2 = DateTime::createFromFormat('Y-m-d H:i:s', $sunrise);

        $date3 = DateTime::createFromFormat('Y-m-d H:i:s', $sunset);

      if ($date1 > $date2 && $date1 < $date3) {

        echo "

        const person_".$i." = document.createElement('div');

        person_".$i.".className = 'marker';

        person_".$i.".style.backgroundImage = `url(media/icons/pin_user.png)`;

        person_".$i.".style.width = `40px`;

        person_".$i.".style.height = `32px`;

        person_".$i.".style.backgroundSize = '100%';



        const marker_p_".$i." = new mapboxgl.Marker(person_".$i.")

            .setLngLat([".$row['lon'].",".$row['lat']."])

            .setPopup(new mapboxgl.Popup().setHTML(\"".ucfirst(str_replace("'"," ",$row['gebruikersnaam']))." was hier ".time2str($row['geotijd'])."\"))

            .addTo(map)

        ";

        }

      next:

      $i++;

      }

    }

  }

}



if (isset($_GET['route'])){

  if ($_GET['route'] == "true"){



    $pk = "pk.eyJ1IjoidGhlaGFpcnl2aWtpbmduaWVscyIsImEiOiJjam40YzI2eGEwMjh6M3hscGEweHpxYzg1In0.3obc3XmgMCZ-rY5LLzhW2A";

    //$pk = "sk.eyJ1IjoidGhlaGFpcnl2aWtpbmduaWVscyIsImEiOiJjbDk3MTBya2Yyb29tM3BwMmtpc2VlODQwIn0.3KsZ5Q0eyYegMg3Ytr6Otw";

    

    

    

    $deelgebieden = array("Alpha","Bravo","Charlie","Delta","Echo","Foxtrot", "Golf", "Hotel");

    



    $route = null;

    foreach ($deelgebieden as $deelgebied) {

      $link = "https://api.mapbox.com/matching/v5/mapbox/cycling/";

      $sql = "SELECT * FROM Voslocaties WHERE deelgebied = '".$deelgebied."'";

      $result = mysqli_query($conn, $sql);

      

      if (mysqli_num_rows($result) > 0) {

        // output data of each row

        $i = 1;

        $radius = null;

        $coords = array();

        while($row = mysqli_fetch_assoc($result)) {

          $link .= substr($row['coordinaat_y'],0,-5).",".substr($row['coordinaat_x'],0,-5);

          $radius .= "25";

      

          if (mysqli_num_rows($result) != $i) {

              $link .= ";";

              $radius .= ";";

          }

      

          $i++;

        }

      

      

        $link .= "?radiuses=".$radius;

        $link .= "&steps=true";

        $link .= "&access_token=".$pk;



        $result = json_decode(file_get_contents($link),true);

        //print_r($result);

        if (isset($result['matchings'][0]['legs'][0])) {

          $i = 0;

          foreach ($result['matchings'][0]['legs'] as $leg) {

            foreach ($leg['steps'] as $step) {

              $coords[$i][0] = $step['maneuver']['location'][0];

              $coords[$i][1] = $step['maneuver']['location'][1];



              $i++;

            }

          }

        }

        switch (ucfirst($deelgebied)) {
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
            $color = "#7E2EFF";
            break;
          default:
            $color = "#000000";
            break;
        }



        $route .=  "

        map.addLayer({

          id: 'route_".$deelgebied."',

          type: 'line',

          source: {

            type: 'geojson',

            data: {

              type: 'Feature',

              properties: {},

              geometry: {

                type: 'LineString',

                coordinates: ".json_encode($coords)."

              }

            }

          },

          layout: {

            'line-join': 'round',

            'line-cap': 'round'

          },

          paint: {

            'line-color': '".$color."',

            'line-width': 7,

            'line-opacity': 0.7

          }

        });";

      }

    }

  }

}





if (isset($_GET['hints'])){

  if ($_GET['hints'] == "true"){

    $sql = "SELECT * FROM Voslocaties";

    $result = mysqli_query($conn, $sql);

    

    if (mysqli_num_rows($result) > 0) {

      // output data of each row

      $i = 0;

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
            $color = "#7E2EFF";
            break;
          default:
            $color = "#000000";
            break;
        }

        

        echo "

        const marker_v_".$i." = new mapboxgl.Marker({

          color: \"".$color."\"

        })

            .setLngLat([".$row['coordinaat_y'].",".$row['coordinaat_x']."])

            .setPopup(new mapboxgl.Popup().setHTML(\"".str_replace("'"," ",$row['deelgebied'])."</br>".date('D H:i', strtotime($row['ingestuurd_op']))."</br><a href='https://www.google.com/maps/dir/?api=1&origin=&destination=".urlencode($row['coordinaat_x'].",".$row['coordinaat_y'])."&travelmode=driving&dir_action=navigate' target='_blank'>Navigeer</a>\"))

            .addTo(map)

        ";

        $i++;

      }

    }

  }

}

if (isset($_GET['fullscreen'])) {

  echo "goAway();  // start the first timer off";

  echo "window.addEventListener('mousemove', goAway, true);";

}



?>



var timer = null;



function goAway() {

    clearTimeout(timer);

    timer = setTimeout(function() {

        window.location = 'https://nielsmaarleveld.nl/joti/maps.php?groepen=true&hints=true&personen=true&route=true&fullscreen';

    }, 30000);

}



map.on('style.load', () => {

  map.setFog({}); // Set the default atmosphere style

  <?php

  if (isset($route)) echo $route;

  ?>

});







</script>

</body>

</html>





