<?php
// Utility functions for converting timestamps into human-readable relative time strings.

if (!function_exists('time2str')) {
    function time2str($ts) {
        if (empty($ts)) {
            return 'Nooit';
        }
        // Parse UTC timestamps correctly
        if (!ctype_digit($ts)) {
            $dt = DateTime::createFromFormat('Y-m-d H:i:s', $ts, new DateTimeZone('UTC'));
            if ($dt) {
                $ts = $dt->getTimestamp();
            } else {
                $ts = strtotime($ts);
            }
        }

        $diff = time() - $ts;
        if ($diff == 0) {
            return 'Zojuist';
        } elseif ($diff > 0) {
            $day_diff = floor($diff / 86400);
            if ($day_diff == 0) {
                if ($diff < 60) return 'Zojuist';
                if ($diff < 120) return '1 min geleden';
                if ($diff < 3600) return floor($diff / 60) . ' min geleden';
                if ($diff < 7200) return '1 uur geleden';
                if ($diff < 86400) return floor($diff / 3600) . ' uur geleden';
            }
            if ($day_diff == 1) { return 'Gisteren'; }
            if ($day_diff < 7) { return $day_diff . ' dagen geleden'; }
            if ($day_diff < 8) { return ceil($day_diff / 7) . ' week geleden'; }
            if ($day_diff < 31) { return ceil($day_diff / 7) . ' weken geleden'; }
            if ($day_diff < 60) { return 'Vorige maand'; }
            return date('d-m-Y H:i', $ts);
        } else {
            $diff = abs($diff);
            $day_diff = floor($diff / 86400);
            if ($day_diff == 0) {
                if ($diff < 120) { return 'Over een min'; }
                if ($diff < 3600) { return 'Over ' . floor($diff / 60) . ' min'; }
                if ($diff < 7200) { return 'Over een uur'; }
                if ($diff < 86400) { return 'Over ' . floor($diff / 3600) . ' uur'; }
            }
            if ($day_diff == 1) { return 'Morgen'; }
            if ($day_diff < 4) { return date('l', $ts); }
            if ($day_diff < 7 + (7 - date('w'))) { return 'Volgende week'; }
            if (ceil($day_diff / 7) < 4) { return 'Over ' . ceil($day_diff / 7) . ' weken'; }
            if (date('n', $ts) == date('n') + 1) { return 'Volgende maand'; }
            return date('d-m-Y H:i', $ts);
        }
    }
}

if (!function_exists('timeToString')) {
    function timeToString($ts) {
        return time2str($ts);
    }
}

/**
 * Calculates the great-circle distance between two points, with the Haversine formula.
 */
if (!function_exists('haversineDistance')) {
    function haversineDistance($latitudeFrom, $longitudeFrom, $latitudeTo, $longitudeTo, $earthRadius = 6371000) {
        $latFrom = deg2rad($latitudeFrom);
        $lonFrom = deg2rad($longitudeFrom);
        $latTo = deg2rad($latitudeTo);
        $lonTo = deg2rad($longitudeTo);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) + cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
        return round($angle * $earthRadius);
    }
}

/**
 * RD coordinaten omzetten naar WGS84 coordinaten
 */
if (!function_exists('convertRdToWgs')) {
    function convertRdToWgs($rdx, $rdy){
        $dx = ($rdx - 155000) * pow(10,-5);
        $dy = ($rdy - 463000) * pow(10,-5);
        
        $somN = (3235.65389 * $dy) + (-32.58297 * pow($dx,2)) + (-0.2475 * pow($dy,2)) + (-0.84978 * pow($dx,2) * $dy) + (-0.0655 * pow($dy,3)) + (-0.01709 * pow($dx,2) * pow($dy,2)) + (-0.00738 * $dx) + (0.0053 * pow($dx,4)) + (-0.00039 * pow($dx,2) * pow($dy,3)) + (0.00033 * pow($dx,4) * $dy) + (-0.00012 * $dx * $dy);
        $somE = (5260.52916 * $dx) + (105.94684 * $dx * $dy) + (2.45656 * $dx * pow($dy,2)) + (-0.81885 * pow($dx,3)) + (0.05594 * $dx * pow($dy,3)) + (-0.05607 * pow($dx,3) * $dy) + (0.01199 * $dy) + (-0.00256 * pow($dx,3) * pow($dy,2)) + (0.00128 * $dx * pow($dy,4)) + (0.00022 * pow($dy,2)) + (-0.00022 * pow($dx,2)) + (0.00026 * pow($dx,5));
            
        $a["lat"] = 52.15517 + ($somN / 3600);
        $a["lon"] = 5.387206 + ($somE / 3600);
        return($a);
    }
}

/**
 * Helper function to resolve client IP considering reverse proxy headers
 */
if (!function_exists('getClientIP')) {
    function getClientIP() {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ipList[0]);
        }
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }
}

// Backward compatibility aliases
if (!function_exists('latlon_dist')) {
    function latlon_dist($lat1, $lon1, $lat2, $lon2, $r = 6371000) {
        return haversineDistance($lat1, $lon1, $lat2, $lon2, $r);
    }
}

if (!function_exists('rdtowgs')) {
    function rdtowgs($x, $y) {
        return convertRdToWgs($x, $y);
    }
}
