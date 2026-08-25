<?php
// AJAX handler for user profile updates, password changes, API token regeneration, avatar uploads, and notification preferences.
require_once('includes/auth.php');
if (!empty($_POST['username']) && !empty($_POST['firstname']) && !empty($_POST['lastname']) && !empty($_POST['email'])) {
    
    // Profielgegevens updaten
    $stmt = $conn->prepare("UPDATE Gebruikers SET gebruikersnaam=?, voornaam=?, achternaam=?, email=? WHERE id=?");
    $stmt->bind_param("ssssi", $_POST['username'], $_POST['firstname'], $_POST['lastname'], $_POST['email'], $_SESSION['id']);

    if ($stmt->execute()) {
        $e = "Succesvol veranderd";
        header("Location: instellingen.php?e=" . urlencode($e) . "&t=gegevens#gegevens");
    } else {
        $e = "Error updating record: " . $stmt->error;
        header("Location: instellingen.php?e=" . urlencode($e) . "&t=gegevens#gegevens");
    }
    $stmt->close();
    die();

} elseif (!empty($_POST['pswd0']) && !empty($_POST['pswd1'])) {
    
    // Update password
    if ($_POST['pswd0'] === $_POST['pswd1']) {
        $hashed_password = password_hash($_POST['pswd1'], PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("UPDATE Gebruikers SET wachtwoord=? WHERE id=?");
        $stmt->bind_param("si", $hashed_password, $_SESSION['id']);

        if ($stmt->execute()) {
            $e = "Succesvol gewijzigd";
            header("Location: instellingen.php?e=" . urlencode($e) . "&t=wachtwoord#wachtwoord");
        } else {
            $e = "Error updating record: " . $stmt->error;
            header("Location: instellingen.php?e=" . urlencode($e) . "&t=wachtwoord#wachtwoord");
        }
        $stmt->close();
    } else {
        $e = "Wachtwoorden komen niet overeen...<br> Probeer opnieuw :-)";
        header("Location: instellingen.php?e=" . urlencode($e) . "&t=wachtwoord#wachtwoord");
    }
    die();

} elseif (!empty($_POST['api'])) {
    
    // API key vernieuwen
    $api_key = explode(".", $_POST['api']);
    $api_check = substr(sha1($api_key[0] . "salt"), 0, 7);
    
    if ($api_key[1] === $api_check) {
        $api = substr(md5(rand(0, 1000000000)), 0, 8);
        $api = substr($api . "." . sha1($api . "salt"), 0, 16);
        
        $stmt = $conn->prepare("UPDATE Gebruikers SET api=? WHERE id=?");
        $stmt->bind_param("si", $api, $_SESSION['id']);
        
        if ($stmt->execute()) {
            $e = "Succesvol gewijzigd";
            header("Location: instellingen.php?e=" . urlencode($e) . "&t=api#api");
        } else {
            $e = "Error updating record: " . $stmt->error;
            header("Location: instellingen.php?e=" . urlencode($e) . "&t=api#api");
        }
        $stmt->close();
    }
    die();

} elseif (isset($_GET['delete_profile_picture'])) {
    $stmt = $conn->prepare("UPDATE Gebruikers SET profile_picture=NULL WHERE id=?");
    $stmt->bind_param("i", $_SESSION['id']);
    $stmt->execute();
    $stmt->close();
    header("Location: instellingen.php?e=" . urlencode("Profielfoto verwijderd") . "&t=profielfoto#profielfoto");
    die();

} elseif (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
    $fileTmpPath = $_FILES['profile_picture']['tmp_name'];
    $fileType = mime_content_type($fileTmpPath);
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    
    if (in_array($fileType, $allowedTypes)) {
        if (!function_exists('imagecreatefromjpeg')) {
            $e = "De server mist de PHP GD extensie. Installeer php-gd om afbeeldingen te uploaden.";
            header("Location: instellingen.php?e=" . urlencode($e) . "&t=profielfoto#profielfoto");
            die();
        }
        
        $hash = bin2hex(random_bytes(8));
        $profile_dir = __DIR__ . '/media/profiles';
        if (!is_dir($profile_dir)) {
            @mkdir($profile_dir, 0775, true);
        }
        
        if ($fileType == 'image/jpeg') $src = imagecreatefromjpeg($fileTmpPath);
        elseif ($fileType == 'image/png') $src = imagecreatefrompng($fileTmpPath);
        elseif ($fileType == 'image/webp') $src = imagecreatefromwebp($fileTmpPath);
        
        if ($src) {
            $width = imagesx($src);
            $height = imagesy($src);
            $size = min($width, $height);
            $x = ($width - $size) / 2;
            $y = ($height - $size) / 2;
            
            $high_res = imagecreatetruecolor(512, 512);
            $white = imagecolorallocate($high_res, 255, 255, 255);
            imagefill($high_res, 0, 0, $white);
            imagecopyresampled($high_res, $src, 0, 0, $x, $y, 512, 512, $size, $size);
            imagejpeg($high_res, __DIR__ . "/media/profiles/{$hash}_high.jpg", 90);
            
            $low_res = imagecreatetruecolor(128, 128);
            $white = imagecolorallocate($low_res, 255, 255, 255);
            imagefill($low_res, 0, 0, $white);
            imagecopyresampled($low_res, $src, 0, 0, $x, $y, 128, 128, $size, $size);
            imagejpeg($low_res, __DIR__ . "/media/profiles/{$hash}_low.jpg", 80);
            
            imagedestroy($src);
            imagedestroy($high_res);
            imagedestroy($low_res);
            
            $stmt = $conn->prepare("UPDATE Gebruikers SET profile_picture=? WHERE id=?");
            $stmt->bind_param("si", $hash, $_SESSION['id']);
            if ($stmt->execute()) {
                $e = "Profielfoto succesvol aangepast!";
            } else {
                $e = "Database fout: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $e = "Fout bij verwerken afbeelding.";
        }
    } else {
        $e = "Ongeldig bestandstype. Alleen JPG, PNG en WebP zijn toegestaan.";
    }
    header("Location: instellingen.php?e=" . urlencode($e) . "&t=profielfoto#profielfoto");
    die();
} elseif (isset($_POST['save_notif_prefs'])) {
    
    $prefs = [
        'welkomsberichten' => isset($_POST['notif_welkomsberichten']),
        'tegenhunt' => isset($_POST['notif_tegenhunt']),
        'assignment_changes' => isset($_POST['notif_assignment_changes']),
        'vosstatus' => isset($_POST['notif_vosstatus']),
        'locatiestatus' => isset($_POST['notif_locatiestatus']),
        'hints' => isset($_POST['notif_hints']),
        'opdrachten' => isset($_POST['notif_opdrachten']),
        'nieuws' => isset($_POST['notif_nieuws'])
    ];
    
    $json_prefs = json_encode($prefs);
    
    $stmt = $conn->prepare("UPDATE Gebruikers SET notification_prefs=? WHERE id=?");
    $stmt->bind_param("si", $json_prefs, $_SESSION['id']);
    
    if ($stmt->execute()) {
        $e = "Notificatie voorkeuren opgeslagen!";
    } else {
        $e = "Database fout: " . $stmt->error;
    }
    $stmt->close();
    header("Location: instellingen.php?e=" . urlencode($e) . "&t=notificaties");
    die();
} elseif (isset($_POST['rename_device_id']) && isset($_POST['new_device_name'])) {
    
    $device_id = (int)$_POST['rename_device_id'];
    $new_name = substr(trim($_POST['new_device_name']), 0, 255);
    
    $stmt = $conn->prepare("UPDATE Notification_Subscriptions SET device_name=? WHERE id=? AND user_id=?");
    $stmt->bind_param("sii", $new_name, $device_id, $_SESSION['id']);
    
    if ($stmt->execute()) {
        $e = "Apparaat succesvol hernoemd!";
    } else {
        $e = "Database fout: " . $stmt->error;
    }
    $stmt->close();
    header("Location: instellingen.php?e=" . urlencode($e) . "&t=notificaties");
    die();
}
?>