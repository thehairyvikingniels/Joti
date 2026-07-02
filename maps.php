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
  .mapboxgl-popup-content {
    font-family: "Raleway", sans-serif;
  }
  .mapboxgl-popup-content h4 {
      margin: 0 0 5px 0;
      font-weight: bold;
  }
   .mapboxgl-popup-content p {
      margin: 0;
  }

</style>

</head>

<body>

<div id='map'></div>

<script>

<?php

// Use position from URL parameters if available, otherwise use defaults
$lat = $_GET['lat'] ?? 52.121581;
$lon = $_GET['lon'] ?? 5.910389;
$zoom = $_GET['zoom'] ?? 9;

if (isset($_GET['punt_lat'])){
  $zoom = 14;
  $lat = $_GET['punt_lat'];
  $lon = $_GET['punt_lon'];
}

require("dblogin.php");

// Get global site settings for game times
$stmt = $conn->prepare("SELECT * FROM Site_Instellingen WHERE Instelling IN ('FOXEXCHANGE_STARTDATE', 'FOXEXCHANGE_ENDDATE', 'API_KEY_MAPBOX')");
$stmt->execute();
$result = $stmt->get_result();

$siteSettings = array();

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $siteSettings[$row['Instelling']] = $row['Waarde'];
    }
}
$stmt->close();

// If halves haven't been set, use these default values
$helft1_end = isset($siteSettings['FOXEXCHANGE_STARTDATE']) ? new DateTime($siteSettings['FOXEXCHANGE_STARTDATE']) : new DateTime('2025-10-11T22:45:00+02:00');
$helft2_start = isset($siteSettings['FOXEXCHANGE_ENDDATE']) ? new DateTime($siteSettings['FOXEXCHANGE_ENDDATE']) : new DateTime('2025-10-12T23:15:00+02:00');



if (!function_exists('time2str')) {
    function time2str($ts)
    {
        if(!ctype_digit($ts))
            $ts = strtotime($ts);

        $diff = time() - $ts;
        if($diff == 0)
            return 'now';
        elseif($diff > 0)
        {
            $day_diff = floor($diff / 86400);
            if($day_diff == 0)
            {
                if($diff < 60) return 'just now';
                if($diff < 120) return '1 minute ago';
                if($diff < 3600) return floor($diff / 60) . ' minutes ago';
                if($diff < 7200) return '1 hour ago';
                if($diff < 86400) return floor($diff / 3600) . ' hours ago';
            }
            if($day_diff == 1) return 'Yesterday';
            if($day_diff < 7) return $day_diff . ' days ago';
            if($day_diff < 31) return ceil($day_diff / 7) . ' weeks ago';
            if($day_diff < 60) return 'last month';
            return date('F Y', $ts);
        }
        else
        {
            $diff = abs($diff);
            $day_diff = floor($diff / 86400);
            if($day_diff == 0)
            {
                if($diff < 120) return 'in a minute';
                if($diff < 3600) return 'in ' . floor($diff / 60) . ' minutes';
                if($diff < 7200) return 'in an hour';
                if($diff < 86400) return 'in ' . floor($diff / 3600) . ' hours';
            }
            if($day_diff == 1) return 'Tomorrow';
            if($day_diff < 4) return date('l', $ts);
            if($day_diff < 7 + (7 - date('w'))) return 'next week';
            if(ceil($day_diff / 7) < 4) return 'in ' . ceil($day_diff / 7) . ' weeks';
            if(date('n', $ts) == date('n') + 1) return 'next month';
            return date('F Y', $ts);
        }
    }
}

function haversineGreatCircleDistance(
  $latitudeFrom, $longitudeFrom, $latitudeTo, $longitudeTo, $earthRadius = 6371)
{
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

// --- Filter Logic ---
$deelgebieden_all = $vossen_names;
$deelgebieden_filter = [];
if (isset($_GET['teams']) && !empty($_GET['teams'])) {
    $teams_from_url = explode(',', $_GET['teams']);
    // Sanitize and capitalize
    $deelgebieden_filter = array_intersect($deelgebieden_all, array_map('ucfirst', $teams_from_url));
}

$time_filter_sql = "";
$time_filter_params = [];
$time_filter_types = "";
$show_helft1 = isset($_GET['helft1']) && $_GET['helft1'] == 'true';
$show_helft2 = isset($_GET['helft2']) && $_GET['helft2'] == 'true';

if ($show_helft1 && !$show_helft2) {
    $time_filter_sql = " AND ingestuurd_op <= ?";
    $time_filter_params[] = $helft1_end->format('Y-m-d H:i:s');
    $time_filter_types .= "s";
} elseif (!$show_helft1 && $show_helft2) {
    $time_filter_sql = " AND ingestuurd_op >= ?";
    $time_filter_params[] = $helft2_start->format('Y-m-d H:i:s');
    $time_filter_types .= "s";
} elseif (!$show_helft1 && !$show_helft2) {
    $time_filter_sql = " AND 1=0"; // Effectively show nothing
}

// Get Mapbox API Key from settings
$mapbox_api_key = $siteSettings['API_KEY_MAPBOX'] ?? '';

echo "mapboxgl.accessToken = '" . addslashes($mapbox_api_key) . "';";

echo "         
const map = new mapboxgl.Map({
  container: 'map',
  style: 'mapbox://styles/mapbox/streets-v11',
  center: [$lon,$lat],
  zoom: $zoom,
});

// Function to send map state to parent
function sendMapState() {
    const center = map.getCenter();
    const zoom = map.getZoom();
    const mapState = {
        lon: center.lng,
        lat: center.lat,
        zoom: zoom
    };
    // Post message to the parent window
    parent.postMessage({ type: 'mapUpdate', state: mapState }, '*');
}

// Add event listeners to send state on change
map.on('moveend', sendMapState);
map.on('zoomend', sendMapState);
map.on('load', sendMapState); // Send initial state on load

";

if (isset($_GET['groepen']) && $_GET['groepen'] == "true"){
    // Get all scout groups
    $sql_groepen = "SELECT * FROM Groepen";
    $params = [];
    $types = "";

    // Dynamically build the IN clause with placeholders if filters exist
    if (!empty($deelgebieden_filter)) {
        $placeholders = implode(',', array_fill(0, count($deelgebieden_filter), '?'));
        $sql_groepen .= " WHERE deelgebied IN ($placeholders)";
        
        $types .= str_repeat('s', count($deelgebieden_filter));
        $params = $deelgebieden_filter;
    }

    $stmt = $conn->prepare($sql_groepen);

    // Only bind parameters if there are any filters applied
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $i_group = 0;
        while($row = $result->fetch_assoc()) {
            $row["deelgebied"] = lcfirst($row["deelgebied"]);
            if ($row["deelgebied"] == null) {
                $row["deelgebied"] = "unknown";
            }
            
            echo "
            const img_group_".$i_group." = document.createElement('div');
            img_group_".$i_group.".className = 'marker';
            img_group_".$i_group.".style.backgroundImage = `url(media/icons/pin_hut_".$row['deelgebied'].".png)`;
            img_group_".$i_group.".style.width = `40px`;
            img_group_".$i_group.".style.height = `32px`;
            img_group_".$i_group.".style.backgroundSize = '100%';
            const marker_group_".$i_group." = new mapboxgl.Marker(img_group_".$i_group.")
                .setLngLat([".$row['lon'].",".$row['lat']."])
                .setPopup(new mapboxgl.Popup().setHTML(\"<h4>".ucfirst(addslashes($row['naam']))."</h4><p>Deelgebied: ".$row['deelgebied']."</p><a href='https://www.google.com/maps/dir/?api=1&origin=&destination=".urlencode($row['straat']." ".$row['huisnummer']." ".$row['plaats'])."' target='_blank'>Navigeer</a>\"))
                .addTo(map);
            ";
            $i_group++;
        }      
    }
    $stmt->close();
}
  
if (isset($_GET['punt_lat'])){
  echo "new mapboxgl.Marker().setLngLat([".$_GET['punt_lon'].",".$_GET['punt_lat']."]).addTo(map);";
}

$car_paths = [];
if (isset($_GET['autos']) && $_GET['autos'] == "true") {
    $sql_autos = "
        SELECT
            ap.auto as kenteken,
            ap.lat,
            ap.lon,
            ap.datumtijd,
            GROUP_CONCAT(g.voornaam SEPARATOR ', ') as bijrijders
        FROM
            Auto_Positie ap
        INNER JOIN (
            SELECT auto, MAX(datumtijd) AS max_datumtijd
            FROM Auto_Positie
            GROUP BY auto
        ) ap_max ON ap.auto = ap_max.auto AND ap.datumtijd = ap_max.max_datumtijd
        LEFT JOIN Auto_Bijrijders ab ON ap.auto = ab.auto
        LEFT JOIN Gebruikers g ON ab.gebruiker_id = g.id
        GROUP BY ap.auto, ap.lat, ap.lon, ap.datumtijd
    ";

    $stmt_autos = $conn->prepare($sql_autos);
    $stmt_autos->execute();
    $result_autos = $stmt_autos->get_result();

    if ($result_autos && $result_autos->num_rows > 0) {
        $i_auto = 0;
        
        // Prepare the path query once outside the loop for better performance
        $stmt_path = $conn->prepare("SELECT lon, lat FROM Auto_Positie WHERE auto = ? AND datumtijd >= ? ORDER BY datumtijd ASC");
        
        while ($row = $result_autos->fetch_assoc()) {
            $date1 = new DateTime($row['datumtijd']);
            $now = new DateTime();
            $interval = $now->diff($date1);
            $minutes_since = ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;

            if ($minutes_since <= 15) {
                // Fetch path for this car for the last 2 hours
                $two_hours_ago = date('Y-m-d H:i:s', strtotime('-2 hours'));
                
                // Bind parameters and execute for the current car
                $stmt_path->bind_param("ss", $row['kenteken'], $two_hours_ago);
                $stmt_path->execute();
                $result_path = $stmt_path->get_result();
                
                $path_coords = [];
                if ($result_path) {
                    while($path_row = $result_path->fetch_assoc()) {
                        $path_coords[] = [(float)$path_row['lon'], (float)$path_row['lat']];
                    }
                }
                $car_paths[$row['kenteken']] = $path_coords;
                
                $popup_html = "<h4>".strtoupper($row['kenteken'])."</h4>";
                $popup_html .= "<p><strong>Inzittenden:</strong> ".($row['bijrijders'] ? htmlspecialchars($row['bijrijders']) : 'Onbekend')."</p>";
                $popup_html .= "<p>".time2str($row['datumtijd'])."</p>";
                $popup_html .= "<a href='https://www.google.com/maps/dir/?api=1&destination=".$row['lat'].",".$row['lon']."' target='_blank'>Navigeer</a>";

                echo "
                  const auto_".$i_auto."_elem = document.createElement('div');
                  auto_".$i_auto."_elem.className = 'marker';
                  auto_".$i_auto."_elem.style.backgroundImage = 'url(media/icons/pin_auto.png)';
                  auto_".$i_auto."_elem.style.width = '40px';
                  auto_".$i_auto."_elem.style.height = '32px';
                  auto_".$i_auto."_elem.style.backgroundSize = '100%';
                  
                  const auto_".$i_auto."_popup = new mapboxgl.Popup().setHTML('".addslashes($popup_html)."');

                  const auto_".$i_auto."_marker = new mapboxgl.Marker(auto_".$i_auto."_elem)
                      .setLngLat([".$row['lon'].", ".$row['lat']."])
                      .setPopup(auto_".$i_auto."_popup)
                      .addTo(map);

                  auto_".$i_auto."_marker.getElement().addEventListener('click', () => {
                      drawCarPath('".str_replace("-", "_", $row['kenteken'])."');
                  });

                  auto_".$i_auto."_popup.on('close', () => {
                      removeCarPath();
                  });
                ";
                $i_auto++;
            }
        }
        $stmt_path->close();
    }
    $stmt_autos->close();
}

if (isset($_GET['personen']) && $_GET['personen'] == "true"){
    // get location of people
    $stmt_personen = $conn->prepare("SELECT * FROM Gebruikers");
    $stmt_personen->execute();
    $result_personen = $stmt_personen->get_result();

    if ($result_personen && $result_personen->num_rows > 0) {
      $i_person = 0;
      while($row = $result_personen->fetch_assoc()) {
        if (empty($row['geotijd'])) continue;
        
        $date1 = new DateTime($row['geotijd']);
        $now = new DateTime();
        $interval = $now->diff($date1);
        $minutes_since = ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;
        
        if ($minutes_since <= 15) {
          echo "
          const person_".$i_person." = document.createElement('div');
          person_".$i_person.".className = 'marker';
          person_".$i_person.".style.backgroundImage = `url(media/icons/pin_user.png)`;
          person_".$i_person.".style.width = `40px`;
          person_".$i_person.".style.height = `32px`;
          person_".$i_person.".style.backgroundSize = '100%';
          const marker_person_".$i_person." = new mapboxgl.Marker(person_".$i_person.")
              .setLngLat([".$row['lon'].",".$row['lat']."])
              .setPopup(new mapboxgl.Popup().setHTML(\"<h4>".ucfirst(addslashes($row['voornaam']))."</h4><p>".time2str($row['geotijd'])."</p>\"))
              .addTo(map);";
        }
        $i_person++;
      }
    }
    $stmt_personen->close();
}

if (!empty($deelgebieden_filter)) {
    if (isset($_GET['hints']) && $_GET['hints'] == "true"){
        
        $sql = "SELECT v.*, g.voornaam FROM Voslocaties v LEFT JOIN Gebruikers g ON v.ingeleverd_door = g.id WHERE 1=1 " . $time_filter_sql;
        
        $params = $time_filter_params;
        $types = $time_filter_types;

        $placeholders = implode(',', array_fill(0, count($deelgebieden_filter), '?'));
        $sql .= " AND v.deelgebied IN ($placeholders)";
        
        $types .= str_repeat('s', count($deelgebieden_filter));
        $params = array_merge($params, $deelgebieden_filter);
        
        $stmt_hints = $conn->prepare($sql);
        
        if (!empty($params)) {
            $stmt_hints->bind_param($types, ...$params);
        }
        
        $stmt_hints->execute();
        $result = $stmt_hints->get_result();

        if ($result && $result->num_rows > 0) {
            $i_hint = 0;
            while($row = $result->fetch_assoc()) {
                $color = getFoxColor(ucfirst($row['deelgebied']));
                $loc_time = new DateTime($row['ingestuurd_op']);
                $helft = ($loc_time <= $helft1_end) ? "Eerste helft" : "Tweede helft";

                $popup_html = "<h4>".htmlspecialchars(ucfirst($row['deelgebied']), ENT_QUOTES)." <small>(".$helft.")</small></h4>";
                $popup_html .= "<p><strong>Tijd:</strong> ".date('d-m H:i', strtotime($row['ingestuurd_op']))."</p>";
                $popup_html .= "<p><strong>Type:</strong> ".htmlspecialchars(ucfirst($row['type']), ENT_QUOTES)."</p>";
                if($row['voornaam']) {
                    $popup_html .= "<p><strong>Door:</strong> ".htmlspecialchars(ucfirst($row['voornaam']), ENT_QUOTES)."</p>";
                }
                if($row['code']) {
                    $popup_html .= "<p><strong>Code:</strong> ".htmlspecialchars($row['code'], ENT_QUOTES)."</p>";
                }
                if($row['opmerking']) {
                    $popup_html .= "<p><strong>Opmerking:</strong> ".htmlspecialchars($row['opmerking'], ENT_QUOTES)."</p>";
                }
                $popup_html .= "<a href=\'https://www.google.com/maps/dir/?api=1&destination=".$row['coordinaat_x'].",".$row['coordinaat_y']."\' target=\'_blank\'>Navigeer</a>";
                
                echo "
                new mapboxgl.Marker({ color: \"".$color."\" })
                    .setLngLat([".$row['coordinaat_y'].",".$row['coordinaat_x']."])
                    .setPopup(new mapboxgl.Popup().setHTML('".addslashes($popup_html)."'))
                    .addTo(map);
                ";
                $i_hint++;
            }
        }
        $stmt_hints->close();
    }

    function buildVosQuery($conn, $time_filter_sql, $time_filter_types, $time_filter_params, $deelgebieden_filter) {
        $results = [];
        foreach ($deelgebieden_filter as $deelgebied) {
            $sql = "SELECT coordinaat_x, coordinaat_y, ingestuurd_op FROM Voslocaties WHERE deelgebied = ? " . $time_filter_sql . " ORDER BY ingestuurd_op ASC";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                // Prepend the deelgebied 's' to the time filter types
                $types = "s" . $time_filter_types;
                // Prepend the specific deelgebied string to the time filter parameters
                $params = array_merge([$deelgebied], $time_filter_params);
                
                // Unpack and bind
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                
                $result = $stmt->get_result();
                $points = [];
                if ($result && $result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $points[] = [
                            'lat' => (float)$row['coordinaat_x'],
                            'lon' => (float)$row['coordinaat_y'],
                            'time' => new DateTime($row['ingestuurd_op'])
                        ];
                    }
                }
                $results[$deelgebied] = $points;
                $stmt->close();
            }
        }
        return $results;
    }

    $all_fox_data = buildVosQuery($conn, $time_filter_sql, $time_filter_types, $time_filter_params, $deelgebieden_filter);

    $zoekcirkel_layers = ""; $zoekcirkel_js_helper = "";
    $vossenpad_layers = ""; $vossenpad_stats = [];
    $predicted_route_layers = "";

    if (isset($_GET['zoekcirkel']) && $_GET['zoekcirkel'] == 'true') {
        $zoekcirkel_js_helper = "
        function createGeoJSONCircle(center, radiusInKm, points = 64) {
            const coords = { latitude: center[1], longitude: center[0] };
            const km = radiusInKm; const ret = [];
            const distanceX = km / (111.320 * Math.cos(coords.latitude * Math.PI / 180));
            const distanceY = km / 110.574;
            let theta, x, y;
            for (let i = 0; i < points; i++) {
                theta = (i / points) * (2 * Math.PI);
                x = distanceX * Math.cos(theta); y = distanceY * Math.sin(theta);
                ret.push([coords.longitude + x, coords.latitude + y]);
            }
            ret.push(ret[0]);
            return { type: 'geojson', data: { type: 'FeatureCollection', features: [{ type: 'Feature', geometry: { type: 'Polygon', coordinates: [ret] } }] } };
        };";

        $now = new DateTime();
        foreach ($all_fox_data as $deelgebied => $points) {
            if (empty($points)) continue;
            $radius_km = null;
            if (count($points) == 1) {
                $last_point = $points[0];
                $time_diff_hours = ($now->getTimestamp() - $last_point['time']->getTimestamp()) / 3600;
                $radius_km = $time_diff_hours * 5.5;
            } elseif (count($points) >= 2) {
                $last_point = end($points); $second_last_point = $points[count($points) - 2];
                $dist_km = haversineGreatCircleDistance($second_last_point['lat'], $second_last_point['lon'], $last_point['lat'], $last_point['lon']);
                $time_diff_hours_between = ($last_point['time']->getTimestamp() - $second_last_point['time']->getTimestamp()) / 3600;
                $avg_speed = ($time_diff_hours_between > 0) ? ($dist_km / $time_diff_hours_between) : 5.5;
                $time_diff_hours_from_now = ($now->getTimestamp() - $last_point['time']->getTimestamp()) / 3600;
                $radius_km = $time_diff_hours_from_now * $avg_speed;
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
                $center_lon = end($points)['lon']; $center_lat = end($points)['lat'];
                $zoekcirkel_layers .= "
                const center_".$deelgebied." = [".$center_lon.", ".$center_lat."];
                const radius_".$deelgebied." = ".$radius_km.";
                map.addSource('polygon_".$deelgebied."', createGeoJSONCircle(center_".$deelgebied.", radius_".$deelgebied."));
                map.addLayer({ 'id': 'circle_fill_".$deelgebied."', 'type': 'fill', 'source': 'polygon_".$deelgebied."', 'paint': { 'fill-color': '".$color."', 'fill-opacity': 0.2 } });
                map.addLayer({ 'id': 'circle_border_".$deelgebied."', 'type': 'line', 'source': 'polygon_".$deelgebied."', 'paint': { 'line-color': '".$color."', 'line-width': 2 } });";
            }
        }
    }

    if (isset($_GET['vossenpad']) && $_GET['vossenpad'] == "true"){
        foreach ($all_fox_data as $deelgebied => $points) {
            if (count($points) < 2) continue;
            $total_distance_km = 0;
            for ($i = 1; $i < count($points); $i++) {
                $total_distance_km += haversineGreatCircleDistance($points[$i-1]['lat'], $points[$i-1]['lon'], $points[$i]['lat'], $points[$i]['lon']);
            }
            $time_diff_seconds = end($points)['time']->getTimestamp() - $points[0]['time']->getTimestamp();
            $total_hours = $time_diff_seconds / 3600;
            $avg_speed_kmh = ($total_hours > 0) ? ($total_distance_km / $total_hours) : 0;
            $coords = array_map(function($p) { return [$p['lon'], $p['lat']]; }, $points);
            
            $color = getFoxColor(ucfirst($deelgebied));

            $vossenpad_stats[$deelgebied] = [ 'speed' => number_format($avg_speed_kmh, 1) . " km/u", 'distance' => number_format($total_distance_km, 1) . " km", 'original_color' => $color ];
            $vossenpad_layers .=  " map.addLayer({ id: 'vossenpad_".$deelgebied."', type: 'line', source: { type: 'geojson', data: { type: 'Feature', properties: {}, geometry: { type: 'LineString', coordinates: ".json_encode($coords)." } } }, layout: { 'line-join': 'round', 'line-cap': 'round' }, paint: { 'line-color': '".$color."', 'line-width': 8, 'line-opacity': 0.75 } });";
        }
    }

    if (isset($_GET['predicted_route']) && $_GET['predicted_route'] == "true"){
        $pk = $mapbox_api_key;
        
        foreach ($all_fox_data as $deelgebied => $points) {
            if (count($points) < 2) continue;
            $coords_for_api = [];
            foreach($points as $point) {
                $coords_for_api[] = $point['lon'] . "," . $point['lat'];
            }
            $api_url = "https://api.mapbox.com/directions/v5/mapbox/walking/" . implode(';', $coords_for_api) . "?steps=true&geometries=geojson&access_token=" . $pk;
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $api_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
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
                    data: { 'type': 'Feature', 'properties': {}, 'geometry': ".$route_geometry." }
                },
                layout: { 'line-join': 'round', 'line-cap': 'round' },
                paint: { 'line-color': '".$color."', 'line-width': 4, 'line-opacity': 0.8, 'line-dasharray': [2, 2] }
                });";
            }
        }
    }
}


?>

const carPaths = <?php echo json_encode($car_paths); ?>;

function removeCarPath() {
    if (map.getLayer('car-path-line')) map.removeLayer('car-path-line');
    if (map.getSource('car-path')) map.removeSource('car-path');
}

function drawCarPath(kenteken) {
    removeCarPath(); 
    const pathData = carPaths[kenteken.replace(/_/g, '-')];
    if (pathData && pathData.length > 1) {
        map.addSource('car-path', {
            'type': 'geojson',
            'data': { 'type': 'Feature', 'geometry': { 'type': 'LineString', 'coordinates': pathData } }
        });
        map.addLayer({
            'id': 'car-path-line', 'type': 'line', 'source': 'car-path',
            'layout': { 'line-join': 'round', 'line-cap': 'round' },
            'paint': { 'line-color': '#0000FF', 'line-width': 4, 'line-opacity': 0.8 }
        });
    }
}

map.on('style.load', () => {
  map.setFog({}); 
  
  <?php
  if (!empty($zoekcirkel_js_helper)) echo $zoekcirkel_js_helper;
  if (!empty($zoekcirkel_layers)) echo $zoekcirkel_layers;
  if (!empty($predicted_route_layers)) echo $predicted_route_layers;
  if (!empty($vossenpad_layers)) {
      echo $vossenpad_layers;
      echo "const vossenpad_stats = ".json_encode($vossenpad_stats).";\n";
      echo "
      let selectedPathId = null;
      Object.keys(vossenpad_stats).forEach(team => {
          const layerId = 'vossenpad_' + team;
          map.on('click', layerId, (e) => {
              if (selectedPathId) { map.setPaintProperty(selectedPathId, 'line-color', vossenpad_stats[selectedPathId.replace('vossenpad_', '')].original_color); }
              map.setPaintProperty(layerId, 'line-color', '#808080');
              selectedPathId = layerId;
              const stats = vossenpad_stats[team];
              new mapboxgl.Popup()
                  .setLngLat(e.lngLat)
                  .setHTML(`<h4>Team \${team}</h4><p><strong>Gem. snelheid:</strong> \${stats.speed}</p><p><strong>Afstand:</strong> \${stats.distance}</p>`)
                  .addTo(map)
                  .on('close', () => {
                      map.setPaintProperty(layerId, 'line-color', stats.original_color);
                      selectedPathId = null;
                  });
          });
          map.on('mouseenter', layerId, () => { map.getCanvas().style.cursor = 'pointer'; });
          map.on('mouseleave', layerId, () => { map.getCanvas().style.cursor = ''; });
      });";
  }
  ?>
});
</script>

</body>

</html>