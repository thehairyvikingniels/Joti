<?php
// Load database access functions
require_once(__DIR__ . '/helpers.php');
require_once(__DIR__ . '/db.php');

if (isset($conn) && $conn instanceof mysqli) {
    $conn->set_charset('utf8mb4');
}

// Load global settings
$site_settings = fetchSiteSettings($conn);

// Parse dynamic fox names and colors
$fox_names = array_map('trim', explode(',', $site_settings['FOX_NAMES'] ?? 'Alpha,Bravo,Charlie,Delta,Echo,Foxtrot,Golf,Hotel'));
$fox_colors = array_map('trim', explode(',', $site_settings['FOX_COLORS'] ?? '#9829FF,#36D12B,#FF8A00,#F5F02C,#FFA12E,#F52E2B,#FF6F6F,#00BFA5'));

if (!function_exists('getFoxColor')) {
    function getFoxColor($foxName) {
        global $fox_names, $fox_colors;
        $idx = array_search(trim($foxName), $fox_names);
        if ($idx !== false && isset($fox_colors[$idx])) {
            return $fox_colors[$idx];
        }
        return "#000000";
    }
}
