<?php
// Handles kiosk service account creation, updates, token regeneration, and deletion.
require_once(__DIR__ . '/../includes/auth.php');

if ($privilege < 2) {
    header("Location: ../home");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $naam = trim($_POST['naam'] ?? '');
        $target_page = trim($_POST['doel_pagina'] ?? '');
        if (empty($target_page)) $target_page = 'home';
        $permissions = intval($_POST['rechten'] ?? 0);
        $ip_whitelist = trim($_POST['ip_whitelist'] ?? '');
        $refresh_interval = intval($_POST['refresh_interval'] ?? 0);
        $token = generateToken();

        if (empty($naam)) {
            header("Location: serviceaccounts?error=" . urlencode("Naam is verplicht."));
            exit();
        } else {
            $stmt = $conn->prepare("INSERT INTO Kiosk_Accounts (auth_token, naam, doel_pagina, rechten, ip_whitelist, refresh_interval) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssisi", $token, $naam, $target_page, $permissions, $ip_whitelist, $refresh_interval);
            if ($stmt->execute()) {
                $stmt->close();
                header("Location: serviceaccounts?msg=success");
                exit();
            } else {
                $err = "Fout bij aanmaken: " . $stmt->error;
                $stmt->close();
                header("Location: serviceaccounts?error=" . urlencode($err));
                exit();
            }
        }
    } elseif ($action === 'edit') {
        $id = intval($_POST['account_id'] ?? 0);
        $naam = trim($_POST['naam'] ?? '');
        $target_page = trim($_POST['doel_pagina'] ?? '');
        if (empty($target_page)) $target_page = 'home';
        $permissions = intval($_POST['rechten'] ?? 0);
        $ip_whitelist = trim($_POST['ip_whitelist'] ?? '');
        $refresh_interval = intval($_POST['refresh_interval'] ?? 0);

        $stmt = $conn->prepare("UPDATE Kiosk_Accounts SET naam=?, doel_pagina=?, rechten=?, ip_whitelist=?, refresh_interval=? WHERE id=?");
        $stmt->bind_param("ssisii", $naam, $target_page, $permissions, $ip_whitelist, $refresh_interval, $id);
        if ($stmt->execute()) {
            $stmt->close();
            header("Location: serviceaccounts?msg=success");
            exit();
        } else {
            $err = "Fout bij bewerken: " . $stmt->error;
            $stmt->close();
            header("Location: serviceaccounts?error=" . urlencode($err));
            exit();
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['account_id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM Kiosk_Accounts WHERE id=?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $stmt->close();
            header("Location: serviceaccounts?msg=success");
            exit();
        } else {
            $err = "Fout bij verwijderen: " . $stmt->error;
            $stmt->close();
            header("Location: serviceaccounts?error=" . urlencode($err));
            exit();
        }
    } elseif ($action === 'regenerate') {
        $id = intval($_POST['account_id'] ?? 0);
        $token = generateToken();
        $stmt = $conn->prepare("UPDATE Kiosk_Accounts SET auth_token=? WHERE id=?");
        $stmt->bind_param("si", $token, $id);
        if ($stmt->execute()) {
            $stmt->close();
            header("Location: serviceaccounts?msg=success");
            exit();
        } else {
            $err = "Fout bij regenereren token: " . $stmt->error;
            $stmt->close();
            header("Location: serviceaccounts?error=" . urlencode($err));
            exit();
        }
    }
}

header("Location: serviceaccounts");
exit();
