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

/**
 * Calculates the great-circle distance between two points, with
 * the Haversine formula.
 * @param float $latitudeFrom Latitude of start point in [deg decimal]
 * @param float $longitudeFrom Longitude of start point in [deg decimal]
 * @param float $latitudeTo Latitude of target point in [deg decimal]
 * @param float $longitudeTo Longitude of target point in [deg decimal]
 * @param float $earthRadius Mean earth radius in [km]
 * @return float Distance between points in [km]
 */
function haversineGreatCircleDistance(
  $latitudeFrom, $longitudeFrom, $latitudeTo, $longitudeTo, $earthRadius = 6371)
{
  // convert from degrees to radians
  $latFrom = deg2rad($latitudeFrom);
  $lonFrom = deg2rad($longitudeFrom);
  $latTo = deg2rad($latitudeTo);
  $lonTo = deg2rad($longitudeTo);

  $latDelta = $latTo - $latFrom;
  $lonDelta = $lonTo - $lonFrom;

  $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
    cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
  return $angle * $earthRadius;
}


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

      $i_group=0;

      while($row = mysqli_fetch_assoc($result)) {

        $row["deelgebied"] = lcfirst($row["deelgebied"]);

        if ($row["deelgebied"] == null) {$row["deelgebied"] = "unknown";}

          echo "

          const img_group_".$i_group." = document.createElement('div');

          img_group_".$i_group.".className = 'marker';

          img_group_".$i_group.".style.backgroundImage = `url(media/icons/pin_hut_".$row['deelgebied'].".png)`;

          img_group_".$i_group.".style.width = `40px`;

          img_group_".$i_group.".style.height = `32px`;

          img_group_".$i_group.".style.backgroundSize = '100%';



          const marker_group_".$i_group." = new mapboxgl.Marker(img_group_".$i_group.")

              .setLngLat([".$row['lon'].",".$row['lat']."])

              .setPopup(new mapboxgl.Popup().setHTML(\"".ucfirst(addslashes($row['naam']))."</br>Deelgebied: ".$row['deelgebied']."<br><a href='https://www.google.com/maps/dir/?api=1&origin=&destination=".urlencode($row['straat']." ".$row['huisnummer']." ".$row['plaats'])."&travelmode=driving&dir_action=navigate' target='_blank'>Navigeer</a>\"))

              .addTo(map)

          ";

        $i_group++;

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

      $i_person=0;

      while($row = mysqli_fetch_assoc($result)) {

        $geotijd = $row['geotijd'];
        if (!$geotijd) continue; // Skip users with no location time

        $date1 = DateTime::createFromFormat('Y-m-d H:i:s', $geotijd);
        if ($date1 === false) continue; // Skip users with invalid date format

        $now = new DateTime();
        $interval = $now->diff($date1);
        $minutes_since = ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;

        if ($minutes_since <= 15) { // Show anyone active in the last 15 minutes
          echo "

          const person_".$i_person." = document.createElement('div');

          person_".$i_person.".className = 'marker';

          person_".$i_person.".style.backgroundImage = `url(media/icons/pin_user.png)`;

          person_".$i_person.".style.width = `40px`;

          person_".$i_person.".style.height = `32px`;

          person_".$i_person.".style.backgroundSize = '100%';



          const marker_person_".$i_person." = new mapboxgl.Marker(person_".$i_person.")

              .setLngLat([".$row['lon'].",".$row['lat']."])

              .setPopup(new mapboxgl.Popup().setHTML(\"".ucfirst(str_replace("'"," ",$row['gebruikersnaam']))." was hier ".time2str($row['geotijd'])."\"))

              .addTo(map)

          ";
        }
        $i_person++;
      }
    }
  }
}

// --- START: SEARCH CIRCLE FEATURE ---
$zoekcirkel_layers = "";
$zoekcirkel_js_helper = "";

if (isset($_GET['zoekcirkel']) && $_GET['zoekcirkel'] == 'true') {
    $zoekcirkel_js_helper = "
    function createGeoJSONCircle(center, radiusInKm, points = 64) {
        const coords = {
            latitude: center[1],
            longitude: center[0]
        };
        const km = radiusInKm;
        const ret = [];
        const distanceX = km / (111.320 * Math.cos(coords.latitude * Math.PI / 180));
        const distanceY = km / 110.574;
        let theta, x, y;
        for (let i = 0; i < points; i++) {
            theta = (i / points) * (2 * Math.PI);
            x = distanceX * Math.cos(theta);
            y = distanceY * Math.sin(theta);
            ret.push([coords.longitude + x, coords.latitude + y]);
        }
        ret.push(ret[0]);
        return {
            type: 'geojson',
            data: {
                type: 'FeatureCollection',
                features: [{
                    type: 'Feature',
                    geometry: {
                        type: 'Polygon',
                        coordinates: [ret]
                    }
                }]
            }
        };
    };
    ";

    $deelgebieden = array("Alpha","Bravo","Charlie","Delta","Echo","Foxtrot", "Golf", "Hotel");
    $now = new DateTime();

    foreach ($deelgebieden as $deelgebied) {
        $sql = "SELECT coordinaat_x, coordinaat_y, ingestuurd_op FROM Voslocaties WHERE deelgebied = '".mysqli_real_escape_string($conn, $deelgebied)."' ORDER BY ingestuurd_op ASC";
        $result = mysqli_query($conn, $sql);
        
        $points = [];
        if (mysqli_num_rows($result) > 0) {
            while($row = mysqli_fetch_assoc($result)) {
                $points[] = [
                    'lat' => (float)$row['coordinaat_x'],
                    'lon' => (float)$row['coordinaat_y'],
                    'time' => new DateTime($row['ingestuurd_op'])
                ];
            }
        }

        $radius_km = null;
        $center_lat = null;
        $center_lon = null;

        if (count($points) == 1) {
            $last_point = $points[0];
            $time_diff_seconds = $now->getTimestamp() - $last_point['time']->getTimestamp();
            $time_diff_hours = $time_diff_seconds / 3600;
            $radius_km = $time_diff_hours * 5.5; // Base speed
            $center_lon = $last_point['lon'];
            $center_lat = $last_point['lat'];
        } elseif (count($points) >= 2) {
            $last_point = end($points);
            $second_last_point = $points[count($points) - 2];

            $dist_km = haversineGreatCircleDistance(
                $second_last_point['lat'], $second_last_point['lon'],
                $last_point['lat'], $last_point['lon']
            );
            $time_diff_seconds_between_points = $last_point['time']->getTimestamp() - $second_last_point['time']->getTimestamp();
            $time_diff_hours_between_points = $time_diff_seconds_between_points / 3600;
            $avg_speed = ($time_diff_hours_between_points > 0) ? ($dist_km / $time_diff_hours_between_points) : 5.5;

            $time_diff_seconds_from_now = $now->getTimestamp() - $last_point['time']->getTimestamp();
            $time_diff_hours_from_now = $time_diff_seconds_from_now / 3600;
            $radius_km = $time_diff_hours_from_now * $avg_speed;
            $center_lon = $last_point['lon'];
            $center_lat = $last_point['lat'];
        }

        if (isset($radius_km) && $radius_km > 0) {
            switch (ucfirst($deelgebied)) {
              case "Alpha":   $color = "#9829FF"; break;
              case "Bravo":   $color = "#2F9CEB"; break;
              case "Charlie": $color = "#2DFF69"; break;
              case "Delta":   $color = "#F5F02C"; break;
              case "Echo":    $color = "#FFA12E"; break;
              case "Foxtrot": $color = "#F52E2B"; break;
              case "Golf":    $color = "#FF6F6F"; break;
              case "Hotel":   $color = "#00BFA5"; break;
              default:        $color = "#000000"; break;
            }

            $zoekcirkel_layers .= "
               const center_".$deelgebied." = [".$center_lon.", ".$center_lat."];
               const radius_".$deelgebied." = ".$radius_km.";
               const source_id_".$deelgebied." = 'polygon_".$deelgebied."';

               if (!map.getSource(source_id_".$deelgebied.")) {
                 map.addSource(source_id_".$deelgebied.", createGeoJSONCircle(center_".$deelgebied.", radius_".$deelgebied."));
               }

               map.addLayer({
                   'id': 'circle_fill_".$deelgebied."',
                   'type': 'fill',
                   'source': source_id_".$deelgebied.",
                   'layout': {},
                   'paint': {
                       'fill-color': '".$color."',
                       'fill-opacity': 0.2
                   }
               });
               map.addLayer({
                   'id': 'circle_border_".$deelgebied."',
                   'type': 'line',
                   'source': source_id_".$deelgebied.",
                   'layout': {},
                   'paint': {
                       'line-color': '".$color."',
                       'line-width': 2
                   }
               });
            ";
        }
    }
}
// --- END: SEARCH CIRCLE FEATURE ---


// --- START: PREDICTED ROUTE FEATURE ---
$predicted_route_layers = "";
if (isset($_GET['predicted_route']) && $_GET['predicted_route'] == "true"){
    $deelgebieden = array("Alpha","Bravo","Charlie","Delta","Echo","Foxtrot", "Golf", "Hotel");
    $pk = "pk.eyJ1IjoidGhlaGFpcnl2aWtpbmduaWVscyIsImEiOiJjam40YzI2eGEwMjh6M3hscGEweHpxYzg1In0.3obc3XmgMCZ-rY5LLzhW2A";
    
    foreach ($deelgebieden as $deelgebied) {
        $sql = "SELECT coordinaat_x, coordinaat_y FROM Voslocaties WHERE deelgebied = '".mysqli_real_escape_string($conn, $deelgebied)."' ORDER BY ingestuurd_op ASC";
        $result = mysqli_query($conn, $sql);
        
        $coords_for_api = [];
        if (mysqli_num_rows($result) > 1) {
            while($row = mysqli_fetch_assoc($result)) {
                $coords_for_api[] = $row['coordinaat_y'] . "," . $row['coordinaat_x'];
            }
            
            $api_url = "https://api.mapbox.com/directions/v5/mapbox/walking/" . implode(';', $coords_for_api) . "?steps=true&geometries=geojson&access_token=" . $pk;
            
            // Use cURL for better error handling and performance
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $api_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Adjust for local dev if needed
            $api_response = curl_exec($ch);
            curl_close($ch);

            $route_data = json_decode($api_response, true);
            
            if (isset($route_data['routes'][0])) {
                $route_geometry = json_encode($route_data['routes'][0]['geometry']);
                
                switch (ucfirst($deelgebied)) {
                  case "Alpha":   $color = "#9829FF"; break;
                  case "Bravo":   $color = "#2F9CEB"; break;
                  case "Charlie": $color = "#2DFF69"; break;
                  case "Delta":   $color = "#F5F02C"; break;
                  case "Echo":    $color = "#FFA12E"; break;
                  case "Foxtrot": $color = "#F52E2B"; break;
                  case "Golf":    $color = "#FF6F6F"; break;
                  case "Hotel":   $color = "#00BFA5"; break;
                  default:        $color = "#000000"; break;
                }
                
                $predicted_route_layers .= "
                map.addLayer({
                  id: 'predicted_route_".$deelgebied."',
                  type: 'line',
                  source: {
                    type: 'geojson',
                    data: {
                      type: 'Feature',
                      properties: {},
                      geometry: ".$route_geometry."
                    }
                  },
                  layout: {
                    'line-join': 'round',
                    'line-cap': 'round'
                  },
                  paint: {
                    'line-color': '".$color."',
                    'line-width': 4,
                    'line-opacity': 0.8,
                    'line-dasharray': [2, 2]
                  }
                });";
            }
        }
    }
}
// --- END: PREDICTED ROUTE FEATURE ---


// --- START: VOSSENPAD FEATURE ---
$vossenpad_layers = "";
$vossenpad_stats = []; // PHP array to hold stats

if (isset($_GET['vossenpad']) && $_GET['vossenpad'] == "true"){
    $deelgebieden = array("Alpha","Bravo","Charlie","Delta","Echo","Foxtrot", "Golf", "Hotel");
    
    foreach ($deelgebieden as $deelgebied) {
        $sql = "SELECT coordinaat_x, coordinaat_y, ingestuurd_op FROM Voslocaties WHERE deelgebied = '".mysqli_real_escape_string($conn, $deelgebied)."' ORDER BY ingestuurd_op ASC";
        $result = mysqli_query($conn, $sql);
        
        $points = [];
        if (mysqli_num_rows($result) > 1) { // Need at least 2 points
            while($row = mysqli_fetch_assoc($result)) {
                $points[] = [
                    'lat' => (float)$row['coordinaat_x'],
                    'lon' => (float)$row['coordinaat_y'],
                    'time' => new DateTime($row['ingestuurd_op'])
                ];
            }
        
            // Calculate stats
            $total_distance_km = 0;
            for ($i = 1; $i < count($points); $i++) {
                $total_distance_km += haversineGreatCircleDistance(
                    $points[$i-1]['lat'], $points[$i-1]['lon'],
                    $points[$i]['lat'], $points[$i]['lon']
                );
            }

            $first_point_time = $points[0]['time'];
            $last_point_time = end($points)['time'];
            $time_diff_seconds = $last_point_time->getTimestamp() - $first_point_time->getTimestamp();
            $total_hours = $time_diff_seconds / 3600;

            $avg_speed_kmh = ($total_hours > 0) ? ($total_distance_km / $total_hours) : 0;
            
            $coords = array_map(function($p) { return [$p['lon'], $p['lat']]; }, $points);
        
            switch (ucfirst($deelgebied)) {
              case "Alpha":   $color = "#9829FF"; break;
              case "Bravo":   $color = "#2F9CEB"; break;
              case "Charlie": $color = "#2DFF69"; break;
              case "Delta":   $color = "#F5F02C"; break;
              case "Echo":    $color = "#FFA12E"; break;
              case "Foxtrot": $color = "#F52E2B"; break;
              case "Golf":    $color = "#FF6F6F"; break;
              case "Hotel":   $color = "#00BFA5"; break;
              default:        $color = "#000000"; break;
            }
            
            // Store stats for this team
            $vossenpad_stats[$deelgebied] = [
                'speed' => number_format($avg_speed_kmh, 1) . " km/u",
                'distance' => number_format($total_distance_km, 1) . " km",
                'original_color' => $color
            ];
            
            $vossenpad_layers .=  "
            map.addLayer({
              id: 'vossenpad_".$deelgebied."',
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
                'line-width': 8,
                'line-opacity': 0.75
              }
            });";
        }
    }
}
// --- END: VOSSENPAD FEATURE ---


if (isset($_GET['hints'])){

  if ($_GET['hints'] == "true"){

    $sql = "SELECT * FROM Voslocaties";

    $result = mysqli_query($conn, $sql);

    

    if (mysqli_num_rows($result) > 0) {

      // output data of each row

      $i_hint = 0;

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

        

        echo "

        const marker_v_".$i_hint." = new mapboxgl.Marker({

          color: \"".$color."\"

        })

            .setLngLat([".$row['coordinaat_y'].",".$row['coordinaat_x']."])

            .setPopup(new mapboxgl.Popup().setHTML(\"".str_replace("'"," ",$row['deelgebied'])."</br>".date('D H:i', strtotime($row['ingestuurd_op']))."</br><a href='https://www.google.com/maps/dir/?api=1&origin=&destination=".urlencode($row['coordinaat_x'].",".$row['coordinaat_y'])."' target='_blank'>Navigeer</a>\"))

            .addTo(map)

        ";

        $i_hint++;

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
  if (!empty($zoekcirkel_js_helper)) echo $zoekcirkel_js_helper;
  ?>

  <?php
  if (!empty($predicted_route_layers)) echo $predicted_route_layers;
  if (!empty($zoekcirkel_layers)) echo $zoekcirkel_layers;
  if (!empty($vossenpad_layers)) {
      echo $vossenpad_layers;
      
      // Inject stats and add event listeners
      echo "const vossenpad_stats = ".json_encode($vossenpad_stats).";\n";
      echo "
      let selectedPathId = null;

      Object.keys(vossenpad_stats).forEach(team => {
          const layerId = 'vossenpad_' + team;
          
          map.on('click', layerId, (e) => {
              // Reset previous selection if it exists
              if (selectedPathId && selectedPathId !== layerId) {
                  const oldTeam = selectedPathId.replace('vossenpad_', '');
                  map.setPaintProperty(selectedPathId, 'line-color', vossenpad_stats[oldTeam].original_color);
              }
              
              // Highlight the new selection
              map.setPaintProperty(layerId, 'line-color', '#808080'); // Grey color for selection
              selectedPathId = layerId;

              const stats = vossenpad_stats[team];
              new mapboxgl.Popup()
                  .setLngLat(e.lngLat)
                  .setHTML(`<h3>Team \${team}</h3>
                           <p><strong>Gem. snelheid:</strong> \${stats.speed}</p>
                           <p><strong>Afstand:</strong> \${stats.distance}</p>`)
                  .addTo(map)
                  .on('close', () => {
                      // On popup close, reset the color
                      map.setPaintProperty(layerId, 'line-color', stats.original_color);
                      selectedPathId = null;
                  });
          });

          map.on('mouseenter', layerId, () => { map.getCanvas().style.cursor = 'pointer'; });
          map.on('mouseleave', layerId, () => { map.getCanvas().style.cursor = ''; });
      });
      ";
  }

  ?>

});







</script>

</body>

</html>

