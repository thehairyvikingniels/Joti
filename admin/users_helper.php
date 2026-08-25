<?php
// Handles administrative user actions: role changes, password resets, impersonation, and profile picture uploads.
require_once(__DIR__ . '/../includes/auth.php');

if ($privilege < 2) {
    header("Location: ../home");
    exit();
}

// 1. Update or delete user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user']) && isset($_POST['priv'])) {
    $target_user_id = intval($_POST['user']);
    $new_priv = intval($_POST['priv']);

    $stmt_current = $conn->prepare("SELECT priv FROM Gebruikers WHERE id=?");
    $stmt_current->bind_param("i", $target_user_id);
    $stmt_current->execute();
    $result_current = $stmt_current->get_result();
    $current_priv = 0;
    if ($result_current->num_rows > 0) {
        $row_current = $result_current->fetch_assoc();
        $current_priv = (int)$row_current['priv'];
    }
    $stmt_current->close();

    $allowed = false;
    if ($privilege >= 3) {
        $allowed = true;
    } elseif ($privilege == 2) {
        if ($current_priv <= 2 && ($new_priv <= 2 || $new_priv == 4)) {
            $allowed = true;
        }
    }

    if ($allowed) {
        if ($new_priv === 4) {
            $stmt_update = $conn->prepare("DELETE FROM Gebruikers WHERE id=?");
            $stmt_update->bind_param("i", $target_user_id);
        } else {
            $stmt_update = $conn->prepare("UPDATE Gebruikers SET priv=? WHERE id=?");
            $stmt_update->bind_param("ii", $new_priv, $target_user_id);
        }

        if ($stmt_update->execute()) {
            $stmt_update->close();
            header("Location: users?msg=success");
            exit();
        } else {
            $err = "Error: " . $stmt_update->error;
            $stmt_update->close();
            header("Location: users?error=" . urlencode($err));
            exit();
        }
    } else {
        header("Location: users?error=" . urlencode("Je hebt niet de juiste rechten om deze actie uit te voeren."));
        exit();
    }
}

// 2. Reset password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password_user_id']) && isset($_POST['new_password'])) {
    $target_user_id = intval($_POST['reset_password_user_id']);
    $new_password = $_POST['new_password'];

    $stmt_target = $conn->prepare("SELECT priv FROM Gebruikers WHERE id=?");
    $stmt_target->bind_param("i", $target_user_id);
    $stmt_target->execute();
    $result_target = $stmt_target->get_result();

    if ($result_target->num_rows > 0) {
        $row_target = $result_target->fetch_assoc();
        $target_priv = (int)$row_target['priv'];

        $allowed = false;
        if ($privilege >= 3) {
            $allowed = true;
        } elseif ($privilege == 2 && $target_priv <= 1) {
            $allowed = true;
        }

        if ($allowed) {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt_reset = $conn->prepare("UPDATE Gebruikers SET wachtwoord=? WHERE id=?");
            $stmt_reset->bind_param("si", $hashed, $target_user_id);
            if ($stmt_reset->execute()) {
                $stmt_reset->close();
                $stmt_target->close();
                header("Location: users?msg=password_reset");
                exit();
            } else {
                $err = "Error: " . $stmt_reset->error;
                $stmt_reset->close();
                $stmt_target->close();
                header("Location: users?error=" . urlencode($err));
                exit();
            }
        }
    }
    $stmt_target->close();
    header("Location: users?error=" . urlencode("Je hebt niet de juiste rechten om dit wachtwoord te resetten."));
    exit();
}

// 3. Impersonate user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['impersonate_user_id'])) {
    $target_user_id = intval($_POST['impersonate_user_id']);
    
    $stmt_target = $conn->prepare("SELECT id, priv FROM Gebruikers WHERE id=?");
    $stmt_target->bind_param("i", $target_user_id);
    $stmt_target->execute();
    $result_target = $stmt_target->get_result();
    
    if ($result_target->num_rows > 0) {
        $row_target = $result_target->fetch_assoc();
        $target_priv = (int)$row_target['priv'];
        
        $allowed = false;
        if ($privilege >= 3) {
            $allowed = true;
        } elseif ($privilege == 2 && $target_priv <= 1) {
            $allowed = true;
        }
        
        if ($allowed) {
            $_SESSION['original_id'] = $_SESSION['id'];
            $_SESSION['id'] = (int)$row_target['id'];
            $_SESSION['priv'] = (int)$row_target['priv'];
            header("Location: ../home");
            exit();
        } else {
            header("Location: users?error=" . urlencode("Je hebt niet de juiste rechten om deze gebruiker te imiteren."));
            exit();
        }
    }
    $stmt_target->close();
    header("Location: users?error=" . urlencode("Gebruiker niet gevonden."));
    exit();
}

// 4. Admin Profile Picture Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_upload_user_id']) && isset($_FILES['admin_profile_picture']) && $_FILES['admin_profile_picture']['error'] === UPLOAD_ERR_OK) {
    $target_user_id = intval($_POST['admin_upload_user_id']);
    $fileTmpPath = $_FILES['admin_profile_picture']['tmp_name'];
    $fileType = mime_content_type($fileTmpPath);
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    
    $stmt_target = $conn->prepare("SELECT priv FROM Gebruikers WHERE id=?");
    $stmt_target->bind_param("i", $target_user_id);
    $stmt_target->execute();
    $result_target = $stmt_target->get_result();
    
    if ($result_target->num_rows > 0) {
        $row_target = $result_target->fetch_assoc();
        $target_priv = (int)$row_target['priv'];
        
        $allowed = false;
        if ($privilege >= 3) {
            $allowed = true;
        } elseif ($privilege == 2 && $target_priv <= 1) {
            $allowed = true;
        }
        
        if ($allowed && in_array($fileType, $allowedTypes, true)) {
            if (!function_exists('imagecreatefromjpeg')) {
                header("Location: users?error=" . urlencode("De server mist de PHP GD extensie. Installeer php-gd om afbeeldingen te uploaden."));
                exit();
            }
            
            $hash = bin2hex(random_bytes(8));
            $profile_dir = dirname(__DIR__) . '/media/profiles';
            if (!is_dir($profile_dir)) {
                @mkdir($profile_dir, 0775, true);
            }
            
            $src = null;
            if ($fileType === 'image/jpeg') $src = imagecreatefromjpeg($fileTmpPath);
            elseif ($fileType === 'image/png') $src = imagecreatefrompng($fileTmpPath);
            elseif ($fileType === 'image/webp') $src = imagecreatefromwebp($fileTmpPath);
            
            if ($src) {
                $width = imagesx($src);
                $height = imagesy($src);
                $size = min($width, $height);
                $x = ($width - $size) / 2;
                $y = ($height - $size) / 2;
                
                $high_res = imagecreatetruecolor(512, 512);
                $white = imagecolorallocate($high_res, 255, 255, 255);
                imagefill($high_res, 0, 0, $white);
                imagecopyresampled($high_res, $src, 0, 0, (int)$x, (int)$y, 512, 512, (int)$size, (int)$size);
                imagejpeg($high_res, "{$profile_dir}/{$hash}_high.jpg", 90);
                
                $low_res = imagecreatetruecolor(128, 128);
                $white = imagecolorallocate($low_res, 255, 255, 255);
                imagefill($low_res, 0, 0, $white);
                imagecopyresampled($low_res, $src, 0, 0, (int)$x, (int)$y, 128, 128, (int)$size, (int)$size);
                imagejpeg($low_res, "{$profile_dir}/{$hash}_low.jpg", 80);
                
                imagedestroy($src);
                imagedestroy($high_res);
                imagedestroy($low_res);
                
                $stmt_upd = $conn->prepare("UPDATE Gebruikers SET profile_picture=? WHERE id=?");
                $stmt_upd->bind_param("si", $hash, $target_user_id);
                if ($stmt_upd->execute()) {
                    $stmt_upd->close();
                    $stmt_target->close();
                    header("Location: users?msg=pic_success");
                    exit();
                } else {
                    $err = "Database fout: " . $stmt_upd->error;
                    $stmt_upd->close();
                    $stmt_target->close();
                    header("Location: users?error=" . urlencode($err));
                    exit();
                }
            } else {
                $stmt_target->close();
                header("Location: users?error=" . urlencode("Fout bij verwerken afbeelding."));
                exit();
            }
        } elseif (!$allowed) {
            $stmt_target->close();
            header("Location: users?error=" . urlencode("Je hebt niet de juiste rechten om deze profielfoto te wijzigen."));
            exit();
        } else {
            $stmt_target->close();
            header("Location: users?error=" . urlencode("Ongeldig bestandstype. Alleen JPG, PNG en WebP zijn toegestaan."));
            exit();
        }
    }
    $stmt_target->close();
    header("Location: users?error=" . urlencode("Gebruiker niet gevonden."));
    exit();
}

header("Location: users");
exit();
