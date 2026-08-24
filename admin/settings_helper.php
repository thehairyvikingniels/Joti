<?php
// Handles site settings updates, additions, and deletions.
require_once(__DIR__ . '/../includes/auth.php');

if ($privilege < 3) {
    header("Location: ../home");
    exit();
}

// 1. UPDATE site settings
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'update_settings') {
    $all_updates_successful = true;
    $error_message = '';

    $stmt_upd = $conn->prepare("UPDATE Site_Instellingen SET Waarde = ? WHERE Instelling = ?");
    if ($stmt_upd) {
        $uitzonderingen = ['action', 'add_setting_name', 'add_setting_value', 'add_setting_description'];
        foreach ($_POST as $instelling => $waarde) {
            if (!in_array($instelling, $uitzonderingen, true)) {
                $inst_clean = trim($instelling);
                $waarde_clean = trim($waarde);
                $stmt_upd->bind_param("ss", $waarde_clean, $inst_clean);
                if (!$stmt_upd->execute()) {
                    $all_updates_successful = false;
                    $error_message = "Fout bij bijwerken: " . htmlspecialchars($inst_clean);
                    break;
                }
            }
        }
        $stmt_upd->close();

        if ($all_updates_successful) {
            header("Location: settings?msg=" . urlencode("De instellingen zijn succesvol opgeslagen!"));
            exit();
        } else {
            header("Location: settings?error=" . urlencode($error_message));
            exit();
        }
    }
}

// 2. ADD new setting
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'add_setting') {
    $newName = trim($_POST['add_setting_name'] ?? '');
    $newValue = trim($_POST['add_setting_value'] ?? '');
    $newDescription = trim($_POST['add_setting_description'] ?? '');

    if (!empty($newName)) {
        $check_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM Site_Instellingen WHERE Instelling = ?");
        $check_stmt->bind_param("s", $newName);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $check_row = $check_result->fetch_assoc();
        $check_stmt->close();

        if ($check_row['cnt'] > 0) {
            header("Location: settings?error=" . urlencode("Instelling '" . $newName . "' bestaat al."));
            exit();
        } else {
            $insert_stmt = $conn->prepare("INSERT INTO Site_Instellingen (Instelling, Waarde, Omschrijving) VALUES (?, ?, ?)");
            if ($insert_stmt) {
                $insert_stmt->bind_param("sss", $newName, $newValue, $newDescription);
                if ($insert_stmt->execute()) {
                    $insert_stmt->close();
                    header("Location: settings?msg=" . urlencode("Nieuwe instelling '" . $newName . "' is succesvol toegevoegd!"));
                    exit();
                } else {
                    $err = "Fout: " . $insert_stmt->error;
                    $insert_stmt->close();
                    header("Location: settings?error=" . urlencode($err));
                    exit();
                }
            }
        }
    } else {
        header("Location: settings?error=" . urlencode("Naam van de instelling mag niet leeg zijn."));
        exit();
    }
}

// 3. DELETE setting
if (isset($_GET['delete_setting'])) {
    $setting_to_delete = trim($_GET['delete_setting']);
    if (!empty($setting_to_delete)) {
        $delete_stmt = $conn->prepare("DELETE FROM Site_Instellingen WHERE Instelling = ?");
        if ($delete_stmt) {
            $delete_stmt->bind_param("s", $setting_to_delete);
            if ($delete_stmt->execute()) {
                $delete_stmt->close();
                header("Location: settings?msg=" . urlencode("Instelling '" . $setting_to_delete . "' is succesvol verwijderd!"));
                exit();
            } else {
                $err = "Fout: " . $delete_stmt->error;
                $delete_stmt->close();
                header("Location: settings?error=" . urlencode($err));
                exit();
            }
        }
    }
}

header("Location: settings");
exit();
