<?php
// Calculates distances between tracking users and scouting groups to send proximity welcome push notifications.
define("NAME", "welcome_push");
define("START_TIME", microtime(true));
date_default_timezone_set('Europe/Amsterdam');
$output = "";

require_once(__DIR__ . "/../dblogin.php");
require_once(__DIR__ . "/../functies.php");

$datumtijd = date('Y-m-d H:i:s');

function log2DB(string $entry) {
    global $output;
    echo $entry . "\n";
    $output .= $entry . "\n";
}

// Function to calculate distance between two coordinates in meters
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earth_radius = 6371000; // meters
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    
    return $earth_radius * $c;
}

// 1. Fetch active users (geotijd < 5 min)
$five_mins_ago = time() - 300;
$stmt_users = $conn->prepare("SELECT id, voornaam, lat, lon FROM Gebruikers WHERE CAST(geotijd AS UNSIGNED) > ?");
$stmt_users->bind_param("i", $five_mins_ago);
$stmt_users->execute();
$active_users = $stmt_users->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_users->close();

if (empty($active_users)) {
    log2DB("No active users found.");
} else {
    // 2. Fetch all groups
    $stmt_groups = $conn->prepare("SELECT id, naam, lat, lon FROM Groepen");
    $stmt_groups->execute();
    $groups = $stmt_groups->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_groups->close();

    foreach ($active_users as $user) {
        if (empty($user['lat']) || empty($user['lon'])) continue;

        foreach ($groups as $group) {
            $distance = calculateDistance($user['lat'], $user['lon'], $group['lat'], $group['lon']);
            
            if ($distance <= 150) {
                // User is within 150m of a group. Check if we already welcomed them here recently.
                $url = '/groepen#groep-' . $group['id'];
                
                $stmt_check = $conn->prepare("SELECT added_on FROM Notification_Backlog WHERE user_id = ? AND initiator = 'cron/welcome' AND url = ? ORDER BY added_on DESC LIMIT 1");
                $stmt_check->bind_param("is", $user['id'], $url);
                $stmt_check->execute();
                $res_check = $stmt_check->get_result();
                
                $should_send = true;
                if ($res_check->num_rows > 0) {
                    $row = $res_check->fetch_assoc();
                    $last_sent = strtotime($row['added_on']);
                    // Spam prevention: don't send if we already sent for THIS group within the last 12 hours
                    if ((time() - $last_sent) < (12 * 3600)) {
                        $should_send = false;
                    }
                }
                $stmt_check->close();
                
                if ($should_send) {
                    log2DB("Welcoming user {$user['voornaam']} (ID: {$user['id']}) to group {$group['naam']} (Distance: " . round($distance) . "m)");
                    
                    send_push_notification(
                        $user['id'],
                        "Welkom bij {$group['naam']}!",
                        "Je bent in de buurt van deze groep.",
                        $url,
                        'cron/welcome',
                        null,
                        'welkomsberichten'
                    );
                }
            }
        }
    }
}

// Log to Cronlogs
define("END_TIME", microtime(true));
$duration = intval((END_TIME - START_TIME)*1000);
$output_clean = addslashes($output);

$stmt = $conn->prepare("INSERT INTO Cronlogs (name, exec_time, exec_length, exec_stat, exec_output) VALUES (?, ?, ?, ?, ?)");
$p1 = NAME;
$p2 = $datumtijd;
$p3 = $duration;
$p4 = 200;
$p5 = $output_clean;
$stmt->bind_param("ssiis", $p1, $p2, $p3, $p4, $p5);
$stmt->execute();
$stmt->close();

$conn->close();
?>
