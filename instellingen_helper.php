<?php
session_start();
if (!isset($_SESSION['id'])) {
    header("Location: index");
    die();
}
require("dblogin.php");

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
    
    // Wachtwoord updaten
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
}
?>