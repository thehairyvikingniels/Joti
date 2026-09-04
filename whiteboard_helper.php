<?php
// AJAX handler for whiteboard assignments: moves users and vehicles between tasks and manages custom categories.
require_once('includes/auth.php');

if ($privilege < 1 && (!$is_kiosk || ($_SESSION['kiosk_priv'] ?? 0) < 1)) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Geen rechten voor bewerken (403 Forbidden)"]);
    exit();
}

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';

if ($action === 'move_user') {
    $user_id = intval($_POST['user_id'] ?? 0);
    $target_type = $_POST['target_type'] ?? '';
    $target_ref = $_POST['target_ref'] ?? '';
    
    if (!$user_id || !$target_type) {
        echo json_encode(["status" => "error", "message" => "Invalid parameters"]);
        exit();
    }
    
    // 1. Unassign from cars
    $stmt = $conn->prepare("DELETE FROM Auto_Bijrijders WHERE gebruiker_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    
    // 2. Unassign from tasks (hints, opdrachten, custom)
    $stmt = $conn->prepare("DELETE FROM Toewijzingen WHERE gebruiker_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    
    // 3. Assign to new target
    $target_user = fetchUserById($conn, $user_id);
    $target_name = $target_user ? ($target_user['voornaam'] . ' ' . $target_user['achternaam']) : "Gebruiker #$user_id";
    $actor_id = $_SESSION['id'] ?? ($_SESSION['user_id'] ?? null);
    $actor_name = $_SESSION['gebruikersnaam'] ?? (!empty($_SESSION['username']) ? $_SESSION['username'] : ($actor_id ? "Gebruiker #$actor_id" : "Systeem"));

    if ($target_type === 'auto') {
        $is_bestuurder = (isset($_POST['is_bestuurder']) && $_POST['is_bestuurder'] == '1') ? 1 : 0;
        
        // If becoming driver, remove previous driver of this car
        if ($is_bestuurder) {
            $s = $conn->prepare("UPDATE Auto_Bijrijders SET is_driver = 0 WHERE auto = ?");
            $s->bind_param("s", $target_ref);
            $s->execute();
            $s->close();
        }
        
        $stmt = $conn->prepare("INSERT INTO Auto_Bijrijders (auto, gebruiker_id, is_driver) VALUES (?, ?, ?)");
        $stmt->bind_param("sii", $target_ref, $user_id, $is_bestuurder);
        $stmt->execute();
        $stmt->close();
        send_push_notification($user_id, "Taak gewijzigd", "Je bent toegewezen aan auto " . $target_ref . ".", "/whiteboard", "whiteboard", null, "assignment_changes");

        $detail = ($actor_id === $user_id)
            ? "{$actor_name} heeft zichzelf toegewezen aan Auto {$target_ref}" . ($is_bestuurder ? " (Bestuurder)" : "")
            : "{$actor_name} heeft {$target_name} toegewezen aan Auto {$target_ref}" . ($is_bestuurder ? " (Bestuurder)" : "");

        recordAuditLog($conn, 'whiteboard', 'assign_user', $detail, [
            'actor_user_id' => $actor_id,
            'actor_username' => $actor_name,
            'subject_user_id' => $user_id,
            'subject_username' => $target_user['gebruikersnaam'] ?? $target_name,
            'target_type' => 'car',
            'target_id' => $target_ref,
            'target_label' => "Auto {$target_ref}",
            'metadata' => ['is_driver' => $is_bestuurder]
        ]);
        
    } elseif ($target_type === 'hint' || $target_type === 'opdracht' || $target_type === 'custom' || $target_type === 'hunt') {
        $ref_int = intval($target_ref);
        $stmt = $conn->prepare("INSERT INTO Toewijzingen (gebruiker_id, type, referentie_id) VALUES (?, ?, ?)");
        $stmt->bind_param("isi", $user_id, $target_type, $ref_int);
        $stmt->execute();
        $stmt->close();
        send_push_notification($user_id, "Taak gewijzigd", "Je bent toegewezen aan een nieuwe taak op het whiteboard.", "/whiteboard", "whiteboard", null, "assignment_changes");

        $typeLabel = ucfirst($target_type);
        $detail = ($actor_id === $user_id)
            ? "{$actor_name} heeft zichzelf toegewezen aan {$typeLabel} #{$ref_int}"
            : "{$actor_name} heeft {$target_name} toegewezen aan {$typeLabel} #{$ref_int}";

        recordAuditLog($conn, 'whiteboard', 'assign_user', $detail, [
            'actor_user_id' => $actor_id,
            'actor_username' => $actor_name,
            'subject_user_id' => $user_id,
            'subject_username' => $target_user['gebruikersnaam'] ?? $target_name,
            'target_type' => $target_type,
            'target_id' => $ref_int,
            'target_label' => "{$typeLabel} #{$ref_int}"
        ]);
    } else {
        send_push_notification($user_id, "Taak verwijderd", "Je bent verwijderd van een taak op het whiteboard.", "/whiteboard", "whiteboard", null, "assignment_changes");

        $detail = ($actor_id === $user_id)
            ? "{$actor_name} heeft zichzelf afgemeld van whiteboard taken"
            : "{$actor_name} heeft {$target_name} afgemeld van whiteboard taken";

        recordAuditLog($conn, 'whiteboard', 'unassign_user', $detail, [
            'actor_user_id' => $actor_id,
            'actor_username' => $actor_name,
            'subject_user_id' => $user_id,
            'subject_username' => $target_user['gebruikersnaam'] ?? $target_name,
            'target_type' => 'whiteboard',
            'target_label' => 'Geen taak'
        ]);
    }
    
    echo json_encode(["status" => "success"]);
    exit();
}

if ($action === 'move_car') {
    $auto_kenteken = $_POST['auto'] ?? '';
    $target_type = $_POST['target_type'] ?? '';
    $target_ref = $_POST['target_ref'] ?? '';
    
    if (!$auto_kenteken || !$target_type) {
        echo json_encode(["status" => "error"]);
        exit();
    }
    
    // Unassign car from previous tasks
    $stmt = $conn->prepare("DELETE FROM Auto_Toewijzingen WHERE auto = ?");
    $stmt->bind_param("s", $auto_kenteken);
    $stmt->execute();
    $stmt->close();
    
    // Fetch users in the car
    $stmt_car_users = $conn->prepare("SELECT gebruiker_id FROM Auto_Bijrijders WHERE auto = ?");
    $stmt_car_users->bind_param("s", $auto_kenteken);
    $stmt_car_users->execute();
    $res = $stmt_car_users->get_result();
    $car_users = [];
    while ($r = $res->fetch_assoc()) {
        $car_users[] = $r['gebruiker_id'];
    }
    $stmt_car_users->close();

    $actor_id = $_SESSION['id'] ?? ($_SESSION['user_id'] ?? null);
    $actor_name = $_SESSION['gebruikersnaam'] ?? (!empty($_SESSION['username']) ? $_SESSION['username'] : ($actor_id ? "Gebruiker #$actor_id" : "Systeem"));
    
    if ($target_type === 'hint' || $target_type === 'opdracht' || $target_type === 'custom' || $target_type === 'hunt') {
        $ref_int = intval($target_ref);
        $stmt = $conn->prepare("INSERT INTO Auto_Toewijzingen (auto, type, referentie_id) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $auto_kenteken, $target_type, $ref_int);
        $stmt->execute();
        $stmt->close();
        if (!empty($car_users)) {
            send_push_notification($car_users, "Taak gewijzigd", "Jouw auto is toegewezen aan een nieuwe taak.", "/whiteboard", "whiteboard", null, "assignment_changes");
        }

        $typeLabel = ucfirst($target_type);
        recordAuditLog($conn, 'whiteboard', 'assign_car', "{$actor_name} heeft Auto {$auto_kenteken} toegewezen aan {$typeLabel} #{$ref_int}", [
            'actor_user_id' => $actor_id,
            'actor_username' => $actor_name,
            'target_type' => 'car',
            'target_id' => $auto_kenteken,
            'target_label' => "Auto {$auto_kenteken}",
            'metadata' => ['target_type' => $target_type, 'target_ref' => $ref_int, 'passengers' => $car_users]
        ]);
    } elseif ($target_type === 'unassigned') {
        // Remove all people from the car when unassigning
        $stmt = $conn->prepare("DELETE FROM Auto_Bijrijders WHERE auto = ?");
        $stmt->bind_param("s", $auto_kenteken);
        $stmt->execute();
        $stmt->close();
        if (!empty($car_users)) {
            send_push_notification($car_users, "Taak verwijderd", "De toewijzingen van jouw auto zijn verwijderd.", "/whiteboard", "whiteboard", null, "assignment_changes");
        }

        recordAuditLog($conn, 'whiteboard', 'unassign_car', "{$actor_name} heeft toewijzingen van Auto {$auto_kenteken} gewist", [
            'actor_user_id' => $actor_id,
            'actor_username' => $actor_name,
            'target_type' => 'car',
            'target_id' => $auto_kenteken,
            'target_label' => "Auto {$auto_kenteken}",
            'metadata' => ['removed_passengers' => $car_users]
        ]);
    }
    
    echo json_encode(["status" => "success"]);
    exit();
}

if ($action === 'add_category') {
    $naam = trim($_POST['naam'] ?? '');
    $kleur = $_POST['kleur'] ?? '#3B82F6';
    
    if ($naam) {
        $stmt = $conn->prepare("INSERT INTO Whiteboard_Categorieen (naam, kleur) VALUES (?, ?)");
        $stmt->bind_param("ss", $naam, $kleur);
        if ($stmt->execute()) {
            $catId = $stmt->insert_id;
            $actor_id = $_SESSION['id'] ?? null;
            $actor_name = $_SESSION['gebruikersnaam'] ?? (!empty($_SESSION['username']) ? $_SESSION['username'] : "Onbekend");
            recordAuditLog($conn, 'whiteboard', 'add_category', "{$actor_name} heeft categorie '{$naam}' toegevoegd aan het whiteboard", [
                'actor_user_id' => $actor_id,
                'actor_username' => $actor_name,
                'target_type' => 'category',
                'target_id' => $catId,
                'target_label' => $naam,
                'metadata' => ['color' => $kleur]
            ]);
            echo json_encode(["status" => "success", "id" => $catId]);
        } else {
            echo json_encode(["status" => "error"]);
        }
        $stmt->close();
    } else {
        echo json_encode(["status" => "error", "message" => "Naam verplicht"]);
    }
    exit();
}

if ($action === 'del_category') {
    $id = intval($_POST['id'] ?? 0);
    if ($id) {
        $stmt = $conn->prepare("DELETE FROM Whiteboard_Categorieen WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        
        $stmt = $conn->prepare("DELETE FROM Toewijzingen WHERE type = 'custom' AND referentie_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        $actor_id = $_SESSION['id'] ?? null;
        $actor_name = $_SESSION['gebruikersnaam'] ?? (!empty($_SESSION['username']) ? $_SESSION['username'] : "Onbekend");
        recordAuditLog($conn, 'whiteboard', 'del_category', "{$actor_name} heeft whiteboard categorie #{$id} verwijderd", [
            'actor_user_id' => $actor_id,
            'actor_username' => $actor_name,
            'target_type' => 'category',
            'target_id' => $id,
            'target_label' => "Categorie #{$id}"
        ]);
        
        echo json_encode(["status" => "success"]);
    } else {
        echo json_encode(["status" => "error"]);
    }
    exit();
}

echo json_encode(["status" => "error", "message" => "Ongeldige actie"]);
