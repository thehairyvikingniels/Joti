<?php
// Interactive full-screen Mapbox GIS map displaying real-time positions of cars, hunters, scouting huts, and fox tracks.
define("PAGE_NAME", "maps");
require_once('includes/auth.php');

$lat = (float)($_GET['lat'] ?? 52.121581);
$lon = (float)($_GET['lon'] ?? 5.910389);
$zoom = (float)($_GET['zoom'] ?? 9);
$target_point = null;

if (isset($_GET['punt_lat'], $_GET['punt_lon'])) {
    $zoom = 14;
    $lat = (float)$_GET['punt_lat'];
    $lon = (float)$_GET['punt_lon'];
    $target_point = [$lon, $lat];
}

$helft1_end = !empty($site_settings['FOXEXCHANGE_STARTDATE']) ? new DateTime($site_settings['FOXEXCHANGE_STARTDATE']) : new DateTime('2025-10-11T22:45:00+02:00');
$helft2_start = !empty($site_settings['FOXEXCHANGE_ENDDATE']) ? new DateTime($site_settings['FOXEXCHANGE_ENDDATE']) : new DateTime('2025-10-12T23:15:00+02:00');

$deelgebieden_all = ['Alpha', 'Bravo', 'Charlie', 'Delta', 'Echo', 'Foxtrot', 'Golf', 'Hotel'];
$deelgebieden_filter = [];
if (!empty($_GET['teams'])) {
    $teams_from_url = explode(',', $_GET['teams']);
    $deelgebieden_filter = array_intersect($deelgebieden_all, array_map('ucfirst', $teams_from_url));
}

$time_filter_sql = '';
$time_filter_params = [];
$time_filter_types = '';
$show_helft1 = isset($_GET['helft1']) && $_GET['helft1'] === 'true';
$show_helft2 = isset($_GET['helft2']) && $_GET['helft2'] === 'true';

if ($show_helft1 && !$show_helft2) {
    $time_filter_sql = ' AND ingestuurd_op <= ?';
    $time_filter_params[] = $helft1_end->format('Y-m-d H:i:s');
    $time_filter_types .= 's';
} elseif (!$show_helft1 && $show_helft2) {
    $time_filter_sql = ' AND ingestuurd_op >= ?';
    $time_filter_params[] = $helft2_start->format('Y-m-d H:i:s');
    $time_filter_types .= 's';
} elseif (!$show_helft1 && !$show_helft2) {
    $time_filter_sql = ' AND 1=0';
}

$map_payload = [
    'apiKey' => $site_settings['API_KEY_MAPBOX'] ?? '',
    'center' => [$lon, $lat],
    'zoom' => $zoom,
    'targetPoint' => $target_point,
    'groups' => [],
    'cars' => [],
    'people' => [],
    'hints' => [],
    'searchCircles' => [],
    'foxPaths' => [],
    'predictedRoutes' => []
];

// 1. Groups
if (isset($_GET['groepen']) && $_GET['groepen'] === 'true') {
    $sql_groepen = 'SELECT * FROM Groepen';
    $params = [];
    $types = '';
    if (!empty($deelgebieden_filter)) {
        $placeholders = implode(',', array_fill(0, count($deelgebieden_filter), '?'));
        $sql_groepen .= " WHERE deelgebied IN ($placeholders)";
        $types = str_repeat('s', count($deelgebieden_filter));
        $params = $deelgebieden_filter;
    }
    $rows = dbFetchAll($conn, $sql_groepen, $params, $types);
    foreach ($rows as $row) {
        $dg = lcfirst((string)$row['deelgebied']);
        $map_payload['groups'][] = [
            'naam' => ucfirst((string)$row['naam']),
            'deelgebied' => $dg ?: 'unknown',
            'lon' => (float)$row['lon'],
            'lat' => (float)$row['lat'],
            'address' => trim("{$row['straat']} {$row['huisnummer']} {$row['plaats']}")
        ];
    }
}

// 2. Cars
if (isset($_GET['autos']) && $_GET['autos'] === 'true') {
    $sql_autos = '
        SELECT ap.auto as kenteken, ap.lat, ap.lon, ap.datumtijd, GROUP_CONCAT(g.voornaam SEPARATOR ", ") as bijrijders
        FROM Auto_Positie ap
        INNER JOIN (
            SELECT auto, MAX(datumtijd) AS max_datumtijd
            FROM Auto_Positie
            GROUP BY auto
        ) ap_max ON ap.auto = ap_max.auto AND ap.datumtijd = ap_max.max_datumtijd
        LEFT JOIN Auto_Bijrijders ab ON ap.auto = ab.auto
        LEFT JOIN Gebruikers g ON ab.gebruiker_id = g.id
        GROUP BY ap.auto, ap.lat, ap.lon, ap.datumtijd
    ';
    $cars = dbFetchAll($conn, $sql_autos);
    $two_hours_ago = date('Y-m-d H:i:s', strtotime('-2 hours'));
    foreach ($cars as $car) {
        $date1 = new DateTime($car['datumtijd']);
        $now = new DateTime();
        $interval = $now->diff($date1);
        $minutes_since = ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;
        if ($minutes_since <= 15) {
            $path_rows = dbFetchAll($conn, 'SELECT lon, lat FROM Auto_Positie WHERE auto = ? AND datumtijd >= ? ORDER BY datumtijd ASC', [$car['kenteken'], $two_hours_ago], 'ss');
            $path_coords = array_map(fn($p) => [(float)$p['lon'], (float)$p['lat']], $path_rows);
            $map_payload['cars'][] = [
                'kenteken' => $car['kenteken'],
                'lat' => (float)$car['lat'],
                'lon' => (float)$car['lon'],
                'bijrijders' => htmlspecialchars((string)($car['bijrijders'] ?? '')),
                'timeAgo' => time2str($car['datumtijd']),
                'path' => $path_coords
            ];
        }
    }
}

// 3. People
if (isset($_GET['personen']) && $_GET['personen'] === 'true') {
    $users = dbFetchAll($conn, 'SELECT * FROM Gebruikers WHERE lat IS NOT NULL AND lon IS NOT NULL AND geotijd IS NOT NULL');
    $time_15_mins_ago = time() - (15 * 60);
    foreach ($users as $u) {
        $geotijd_ts = is_numeric($u['geotijd']) ? (int)$u['geotijd'] : strtotime((string)$u['geotijd']);
        if (!empty($u['lat']) && !empty($u['lon']) && $geotijd_ts > $time_15_mins_ago) {
            $map_payload['people'][] = [
                'voornaam' => ucfirst((string)$u['voornaam']),
                'lat' => (float)$u['lat'],
                'lon' => (float)$u['lon'],
                'timeAgo' => time2str($u['geotijd'])
            ];
        }
    }
}

// 4. Fox Locations & Paths
if (!empty($deelgebieden_filter)) {
    if (isset($_GET['hints']) && $_GET['hints'] === 'true') {
        $placeholders = implode(',', array_fill(0, count($deelgebieden_filter), '?'));
        $sql_hints = "
            SELECT v.*, g.voornaam
            FROM Voslocaties v
            LEFT JOIN Gebruikers g ON v.ingeleverd_door = g.id
            WHERE v.coordinaat_x BETWEEN 51.5 AND 52.6 AND v.coordinaat_y BETWEEN 5.0 AND 6.8
            {$time_filter_sql}
            AND v.deelgebied IN ($placeholders)
        ";
        $params = array_merge($time_filter_params, $deelgebieden_filter);
        $types = $time_filter_types . str_repeat('s', count($deelgebieden_filter));
        $hints = dbFetchAll($conn, $sql_hints, $params, $types);
        foreach ($hints as $h) {
            $loc_time = new DateTime($h['ingestuurd_op']);
            $helft = ($loc_time <= $helft1_end) ? 'Eerste helft' : 'Tweede helft';
            $map_payload['hints'][] = [
                'deelgebied' => ucfirst((string)$h['deelgebied']),
                'helft' => $helft,
                'lat' => (float)$h['coordinaat_x'],
                'lon' => (float)$h['coordinaat_y'],
                'time' => date('d-m H:i', strtotime($h['ingestuurd_op'])),
                'type' => ucfirst((string)$h['type']),
                'door' => !empty($h['voornaam']) ? ucfirst((string)$h['voornaam']) : '',
                'code' => $h['code'] ?? '',
                'opmerking' => $h['opmerking'] ?? '',
                'status' => $h['status'] ?? '',
                'color' => getFoxColor($h['deelgebied'])
            ];
        }
    }

    $all_fox_data = fetchFoxPathPoints($conn, $deelgebieden_filter, $time_filter_sql, $time_filter_types, $time_filter_params);

    // 5. Search circles
    if (isset($_GET['zoekcirkel']) && $_GET['zoekcirkel'] === 'true') {
        $now = new DateTime();
        foreach ($all_fox_data as $deelgebied => $points) {
            if (empty($points)) continue;
            $radius_km = null;
            if (count($points) === 1) {
                $last_point = $points[0];
                $time_diff_hours = ($now->getTimestamp() - $last_point['time']->getTimestamp()) / 3600;
                $radius_km = $time_diff_hours * 5.5;
            } elseif (count($points) >= 2) {
                $last_point = end($points);
                $second_last_point = $points[count($points) - 2];
                $dist_m = haversineDistance($second_last_point['lat'], $second_last_point['lon'], $last_point['lat'], $last_point['lon']);
                $dist_km = $dist_m / 1000;
                $time_diff_hours_between = ($last_point['time']->getTimestamp() - $second_last_point['time']->getTimestamp()) / 3600;
                $avg_speed = ($time_diff_hours_between > 0) ? ($dist_km / $time_diff_hours_between) : 5.5;
                $time_diff_hours_from_now = ($now->getTimestamp() - $last_point['time']->getTimestamp()) / 3600;
                $radius_km = $time_diff_hours_from_now * $avg_speed;
            }
            if ($radius_km !== null && $radius_km > 0) {
                $map_payload['searchCircles'][] = [
                    'id' => $deelgebied,
                    'center' => [end($points)['lon'], end($points)['lat']],
                    'radiusKm' => $radius_km,
                    'color' => getFoxColor($deelgebied)
                ];
            }
        }
    }

    // 6. Fox Paths
    if (isset($_GET['vossenpad']) && $_GET['vossenpad'] === 'true') {
        foreach ($all_fox_data as $deelgebied => $points) {
            if (count($points) < 2) continue;
            $coords = array_map(fn($p) => [$p['lon'], $p['lat']], $points);
            $map_payload['foxPaths'][] = [
                'id' => $deelgebied,
                'coords' => $coords,
                'color' => getFoxColor($deelgebied)
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="utf-8">
<title>Jotify - Map</title>
<meta name="viewport" content="initial-scale=1,maximum-scale=1,user-scalable=no">
<script src="https://api.mapbox.com/mapbox-gl-js/v2.10.0/mapbox-gl.js"></script>
<link href="https://api.mapbox.com/mapbox-gl-js/v2.10.0/mapbox-gl.css" rel="stylesheet">
<style>
body { margin: 0; padding: 0; }
#map { position: absolute; top: 0; bottom: 0; width: 100%; }
.mapboxgl-popup-content {
  color: black;
  max-width: 250px;
}
.mapboxgl-popup-content h4 {
  margin: 0 0 5px 0;
  font-size: 14px;
}
.mapboxgl-popup-content p {
  margin: 0 0 5px 0;
  font-size: 12px;
}
</style>
</head>
<body>
<div id="map"></div>
<script src="js/maps.js"></script>
<script>
const jotiMapData = <?= json_encode($map_payload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
initJotiMap(jotiMapData);
</script>
</body>
</html>
