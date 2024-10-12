<?php
// $servername = "localhost";
// $username = "nielsmd365_joti";
// $password = "jotihunt2019";
// $dbname = "nielsmd365_joti";

$servername = "localhost";
$username = "maarleveld_one_joti";
$password = "jVfxEi8VxemB7mTF";
$dbname = "maarleveld_one_joti";

date_default_timezone_set('Europe/Amsterdam');

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}




// Functions
function time2str($ts) {
    if(!ctype_digit($ts)) {
        $ts = strtotime($ts);
    }
    $diff = time() - $ts;
    if($diff == 0) {
        return 'Nu';
    } elseif($diff > 0) {
        $day_diff = floor($diff / 86400);
        if($day_diff == 0) {
            if($diff < 60) return 'Zojuist';
            if($diff < 120) return '1 min geleden';
            if($diff < 3600) return floor($diff / 60) . ' min geleden';
            if($diff < 7200) return '1 uur geleden';
            if($diff < 86400) return floor($diff / 3600) . ' uur geleden';
        }
        if($day_diff == 1) { return 'Gisteren'; }
        if($day_diff < 7) { return $day_diff . ' dagen geleden'; }
        if($day_diff < 8) { return ceil($day_diff / 7) . ' week geleden'; }
        if($day_diff < 31) { return ceil($day_diff / 7) . ' weken geleden'; }
        if($day_diff < 60) { return 'Vorige maand'; }
        return date('F Y', $ts);
    } else {
        $diff = abs($diff);
        $day_diff = floor($diff / 86400);
        if($day_diff == 0) {
            if($diff < 120) { return 'Over een min'; }
            if($diff < 3600) { return 'Over ' . floor($diff / 60) . ' min'; }
            if($diff < 7200) { return 'Over een uur'; }
            if($diff < 86400) { return 'Over ' . floor($diff / 3600) . ' uur'; }
        }
        if($day_diff == 1) { return 'Morgen'; }
        if($day_diff < 4) { return date('l', $ts); }
        if($day_diff < 7 + (7 - date('w'))) { return 'Volgende week'; }
        if(ceil($day_diff / 7) < 4) { return 'Over ' . ceil($day_diff / 7) . ' weken'; }
        if(date('n', $ts) == date('n') + 1) { return 'Volgende maand'; }
        return date('F Y', $ts);
    }
}

// RD coordinaten omzetten naar WGS84 coordinaten
// https://forum.geocaching.nl/topic/7886-co%C3%B6rdinaat-transformaties-rd-wgs/#elComment_117766
function rdtowgs($rdx, $rdy){
  $dx = ($rdx - 155000) * pow(10,-5);
  $dy = ($rdy - 463000) * pow(10,-5);
  
  $somN = (3235.65389 * $dy) + (-32.58297 * pow($dx,2)) + (-0.2475 * pow($dy,2)) + (-0.84978 * pow($dx,2) * $dy) + (-0.0655 * pow($dy,3)) + (-0.01709 * pow($dx,2) * pow($dy,2)) + (-0.00738 * $dx) + (0.0053 * pow($dx,4)) + (-0.00039 * pow($dx,2) * pow($dy,3)) + (0.00033 * pow($dx,4) * $dy) + (-0.00012 * $dx * $dy);
  $somE = (5260.52916 * $dx) + (105.94684 * $dx * $dy) + (2.45656 * $dx * pow($dy,2)) + (-0.81885 * pow($dx,3)) + (0.05594 * $dx * pow($dy,3)) + (-0.05607 * pow($dx,3) * $dy) + (0.01199 * $dy) + (-0.00256 * pow($dx,3) * pow($dy,2)) + (0.00128 * $dx * pow($dy,4)) + (0.00022 * pow($dy,2)) + (-0.00022 * pow($dx,2)) + (0.00026 * pow($dx,5));
    
  $a["lat"] = 52.15517 + ($somN / 3600);
  $a["lon"] = 5.387206 + ($somE / 3600);
  return($a);
}


/**
 * Calculates the great-circle distance between two points, with
 * the Haversine formula.
 * @param float $latitudeFrom Latitude of start point in [deg decimal]
 * @param float $longitudeFrom Longitude of start point in [deg decimal]
 * @param float $latitudeTo Latitude of target point in [deg decimal]
 * @param float $longitudeTo Longitude of target point in [deg decimal]
 * @param float $earthRadius Mean earth radius in [m]
 * @return float Distance between points in [m] (same as earthRadius)
 */
function latlon_dist($latitudeFrom, $longitudeFrom, $latitudeTo, $longitudeTo, $earthRadius = 6371000) {
    // convert from degrees to radians
    $latFrom = deg2rad($latitudeFrom);
    $lonFrom = deg2rad($longitudeFrom);
    $latTo = deg2rad($latitudeTo);
    $lonTo = deg2rad($longitudeTo);

    $latDelta = $latTo - $latFrom;
    $lonDelta = $lonTo - $lonFrom;

    $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) + cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
    return round($angle * $earthRadius);
}
?>