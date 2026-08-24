<?php
// Load global settings
$site_settings = [];
$stmt_settings = $conn->prepare("SELECT Instelling, Waarde FROM Site_Instellingen");
$stmt_settings->execute();
$res_settings = $stmt_settings->get_result();
if ($res_settings) {
    while($r = $res_settings->fetch_assoc()) {
        $site_settings[$r['Instelling']] = $r['Waarde'];
    }
}
$stmt_settings->close();

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

// Backward compatibility aliases
$siteSettings = $site_settings;
$vossen_names = $fox_names;
$vossen_colors = $fox_colors;
?>
