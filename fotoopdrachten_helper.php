<?php
// AJAX handler for fotoopdrachten: marking submissions, team claiming, and live data polling.
require_once('includes/auth.php');

header('Content-Type: application/json');

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

if ($action === 'mark_submitted') {
    if ($privilege < 1) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Geen bevoegdheid.']);
        exit();
    }

    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Ongeldig foto-opdracht ID.']);
        exit();
    }

    $stmt_get = $conn->prepare("SELECT titel, ingestuurd_op FROM Fotoopdrachten WHERE id = ?");
    $stmt_get->bind_param("i", $id);
    $stmt_get->execute();
    $res_get = $stmt_get->get_result();
    $foto = $res_get->fetch_assoc();
    $stmt_get->close();

    if (!$foto) {
        echo json_encode(['status' => 'error', 'message' => 'Foto-opdracht niet gevonden.']);
        exit();
    }

    $userId = $_SESSION['id'] ?? null;
    $userName = ucfirst($_SESSION['voornaam'] ?? 'Iemand');
    $now = date('Y-m-d H:i:s');

    $stmt_up = $conn->prepare("UPDATE Fotoopdrachten SET ingestuurd_op = COALESCE(ingestuurd_op, ?), ingestuurd_door = COALESCE(ingestuurd_door, ?) WHERE id = ?");
    $stmt_up->bind_param("sii", $now, $userId, $id);
    $success = $stmt_up->execute();
    $stmt_up->close();

    if ($success) {
        recordAuditLog($conn, 'assignment', 'submit_fotoopdracht', "{$userName} heeft foto-opdracht '{$foto['titel']}' gemarkeerd als ingeleverd.", [
            'actor_user_id' => $userId,
            'target_type' => 'fotoopdracht',
            'target_id' => $id,
            'target_label' => $foto['titel']
        ]);

        send_push_notification(
            'ALL',
            'Foto-opdracht Ingezonden',
            "{$userName} heeft '{$foto['titel']}' ingeleverd!",
            "/fotoopdrachten#opdracht-{$id}",
            'fotoopdrachten/mark_submitted',
            null,
            'opdrachten'
        );

        echo json_encode(['status' => 'success', 'message' => 'Foto-opdracht gemarkeerd als ingeleverd!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Fout bij bijwerken in database.']);
    }
    exit();
}

if ($action === 'toggle_team') {
    if ($privilege < 1) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Geen bevoegdheid.']);
        exit();
    }

    $userId = (int)($_SESSION['id'] ?? 0);
    if ($userId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Niet ingelogd.']);
        exit();
    }

    // Lookup Foto-opdrachten category ID
    $catId = 0;
    $stmt_cat = $conn->prepare("SELECT id FROM Whiteboard_Categorieen WHERE naam = 'Foto-opdrachten' LIMIT 1");
    if ($stmt_cat) {
        $stmt_cat->execute();
        $res = $stmt_cat->get_result();
        if ($r = $res->fetch_assoc()) {
            $catId = (int)$r['id'];
        }
        $stmt_cat->close();
    }

    if ($catId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Foto-opdrachten categorie niet gevonden.']);
        exit();
    }

    // Check if user is currently assigned
    $stmt_chk = $conn->prepare("SELECT id FROM Toewijzingen WHERE gebruiker_id = ? AND type = 'custom' AND referentie_id = ?");
    $stmt_chk->bind_param("ii", $userId, $catId);
    $stmt_chk->execute();
    $res_chk = $stmt_chk->get_result();
    $isAssigned = ($res_chk->num_rows > 0);
    $stmt_chk->close();

    $userName = ucfirst($_SESSION['voornaam'] ?? 'Gebruiker');

    if ($isAssigned) {
        // Unassign
        $stmt_del = $conn->prepare("DELETE FROM Toewijzingen WHERE gebruiker_id = ? AND type = 'custom' AND referentie_id = ?");
        $stmt_del->bind_param("ii", $userId, $catId);
        $stmt_del->execute();
        $stmt_del->close();

        recordAuditLog($conn, 'whiteboard', 'unassign_user', "{$userName} heeft het Foto-opdrachten team verlaten.", [
            'actor_user_id' => $userId,
            'target_type' => 'custom',
            'target_id' => $catId,
            'target_label' => 'Foto-opdrachten'
        ]);

        $status = 'unassigned';
    } else {
        // Assign
        $stmt_ins = $conn->prepare("INSERT INTO Toewijzingen (gebruiker_id, type, referentie_id) VALUES (?, 'custom', ?)");
        $stmt_ins->bind_param("ii", $userId, $catId);
        $stmt_ins->execute();
        $stmt_ins->close();

        recordAuditLog($conn, 'whiteboard', 'assign_user', "{$userName} is toegetreden tot het Foto-opdrachten team.", [
            'actor_user_id' => $userId,
            'target_type' => 'custom',
            'target_id' => $catId,
            'target_label' => 'Foto-opdrachten'
        ]);

        $status = 'assigned';
    }

    // Fetch updated team members
    $teamMembers = [];
    $stmt_tm = $conn->prepare("
        SELECT g.id, g.voornaam, g.achternaam, g.profile_picture 
        FROM Toewijzingen t 
        JOIN Gebruikers g ON t.gebruiker_id = g.id 
        WHERE t.type = 'custom' AND t.referentie_id = ?
        ORDER BY g.voornaam ASC
    ");
    $stmt_tm->bind_param("i", $catId);
    $stmt_tm->execute();
    $res_tm = $stmt_tm->get_result();
    while ($m = $res_tm->fetch_assoc()) {
        $teamMembers[] = $m;
    }
    $stmt_tm->close();

    echo json_encode([
        'status' => 'success',
        'action_performed' => $status,
        'team' => $teamMembers,
        'is_in_team' => ($status === 'assigned')
    ]);
    exit();
}

http_response_code(400);
echo json_encode(['status' => 'error', 'message' => 'Onbekende actie.']);
exit();
