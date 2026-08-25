<?php
// Calculates distances between tracking users and scouting groups to send proximity welcome push notifications.
define("NAME", "welcome_push");
define("START_TIME", microtime(true));
date_default_timezone_set('Europe/Amsterdam');
$output = "";
$status_code = 200;

require_once(__DIR__ . "/../dblogin.php");

try {
    // 1. Fetch active users (geotijd < 5 min)
    $five_mins_ago = time() - 300;
    $stmt_users = $conn->prepare("SELECT id, voornaam, lat, lon FROM Gebruikers WHERE geotijd >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
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
                $distance = haversineDistance($user['lat'], $user['lon'], $group['lat'], $group['lon']);
                
                if ($distance <= 150) {
                    $url = '/groepen#groep-' . $group['id'];
                    
                    $stmt_check = $conn->prepare("SELECT added_on FROM Notification_Backlog WHERE user_id = ? AND initiator = 'cron/welcome' AND url = ? ORDER BY added_on DESC LIMIT 1");
                    $stmt_check->bind_param("is", $user['id'], $url);
                    $stmt_check->execute();
                    $res_check = $stmt_check->get_result();
                    
                    $should_send = true;
                    if ($res_check->num_rows > 0) {
                        $row = $res_check->fetch_assoc();
                        $last_sent = strtotime($row['added_on']);
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
} catch (Throwable $e) {
    $status_code = 500;
    $output .= "\nException: " . $e->getMessage();
    error_log("cron/welcome.php error: " . $e->getMessage());
} finally {
    recordCronLog($conn, NAME, START_TIME, $output, $status_code);
    $conn->close();
}
