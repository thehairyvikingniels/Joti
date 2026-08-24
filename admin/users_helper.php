<?php
// Handles user role modifications, password resets, and user deletions.
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
        if ($privilege >= 3 && $target_priv <= 2) {
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

header("Location: users");
exit();
