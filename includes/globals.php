<?php
// Load global settings
$siteSettings = [];
$res_settings = $conn->query("SELECT Instelling, Waarde FROM Site_Instellingen");
if ($res_settings) {
    while($r = $res_settings->fetch_assoc()) {
        $siteSettings[$r['Instelling']] = $r['Waarde'];
    }
}

// Parse dynamic fox names and colors
$vossen_names = array_map('trim', explode(',', $siteSettings['FOX_NAMES'] ?? 'Alpha,Bravo,Charlie,Delta,Echo,Foxtrot,Golf,Hotel'));
$vossen_colors = array_map('trim', explode(',', $siteSettings['FOX_COLORS'] ?? '#9829FF,#36D12B,#FF8A00,#F5F02C,#FFA12E,#F52E2B,#FF6F6F,#00BFA5'));

if (!function_exists('getFoxColor')) {
    function getFoxColor($foxName) {
        global $vossen_names, $vossen_colors;
        $idx = array_search(trim($foxName), $vossen_names);
        if ($idx !== false && isset($vossen_colors[$idx])) {
            return $vossen_colors[$idx];
        }
        return "#000000";
    }
}
?>
