<?php
// Handles user authentication and registration, validates credentials, upgrades password hashes, and initializes sessions.
session_start();
require_once('dblogin.php');

if (isset($_POST['pswd1'])){
  if ((empty($_POST['voornaam'])) OR (empty($_POST['achternaam'])) OR (empty($_POST['email'])) OR (empty($_POST['gebruikersnaam'])) OR (empty($_POST['pswd0'])) OR (empty($_POST['pswd1']))){
    $error = "Wel alles invullen heh...!";
    header("Location: index?m_error=".urlencode($error));
    die();
  } elseif ($_POST['pswd0'] != $_POST['pswd1']) {
    $error = "Wachtwoorden komen niet overeen...";
    header("Location: index?m_error=".urlencode($error));
    die();
  }
  
  $first_name = $_POST['voornaam'];
  $last_name = $_POST['achternaam'];
  $email = $_POST['email'];
  $reg_username = $_POST['gebruikersnaam'];
  $telnum = $_POST['telefoon'];
  $pswd0 = $_POST['pswd0'];
  
  $stmt = $conn->prepare("SELECT id FROM Gebruikers WHERE gebruikersnaam=?");
  $stmt->bind_param("s", $reg_username);
  $stmt->execute();
  $result = $stmt->get_result();
  
  if ($result->num_rows > 0) {
    $stmt->close();
    $error = "Deze gebruikersnaam is al bezet...<br>Probeer een andere.";
    header("Location: index?m_error=".urlencode($error));
    die();
  } else {
      $stmt->close();
      $api = substr(md5(rand(0,1000000000)),0,8);
      $api = substr($api.".".sha1($api."salt"),0,16);
      
      $hashed_password = password_hash($pswd0, PASSWORD_DEFAULT);
      
      $stmt_insert = $conn->prepare("INSERT INTO Gebruikers (gebruikersnaam, wachtwoord, api, voornaam, achternaam, email, priv, telefoon) VALUES (?, ?, ?, ?, ?, ?, 0, ?)");
      $stmt_insert->bind_param("sssssss", $reg_username, $hashed_password, $api, $first_name, $last_name, $email, $telnum);
      
      if ($stmt_insert->execute()) {
        $stmt_insert->close();
        $error = "Account succesvol aangemaakt. Je kan meteen inloggen.";
        header("Location: index?m_error=".urlencode($error));
        die();
      } else {
        $error_msg = $stmt_insert->error;
        $stmt_insert->close();
        $error = "Error: " . $error_msg;
        header("Location: index?m_error=".urlencode($error));
        die();
      }
  }
} else if ((empty($_POST['username'])) OR (empty($_POST['pswd']))){
  $error = "Wel alles invullen heh!";
  header("Location: index?error=".urlencode($error));
  die();
} else {
  $username = $_POST['username'];
  $pswd = $_POST['pswd'];

  $stmt_login = $conn->prepare("SELECT id, priv, wachtwoord, theme FROM Gebruikers WHERE gebruikersnaam = ? OR email = ?");
  $stmt_login->bind_param("ss", $username, $username);
  $stmt_login->execute();
  $result = $stmt_login->get_result();

  if ($result->num_rows > 0) {
      $row = $result->fetch_assoc();
      $stmt_login->close();

      $valid_password = false;
      $needs_rehash = false;

      // Verify password using password_verify
      if (password_verify($pswd, $row['wachtwoord'])) {
          $valid_password = true;
          // Check if the hashing algorithm has been updated or cost has changed
          if (password_needs_rehash($row['wachtwoord'], PASSWORD_DEFAULT)) {
              $needs_rehash = true;
          }
      } elseif ($row['wachtwoord'] === sha1($pswd."niels als salt")) {
          // Legacy support: verify old sha1 hash and flag for transparent upgrade
          $valid_password = true;
          $needs_rehash = true;
      }

      if ($valid_password) {
          // Transparently upgrade the password hash in the database if needed
          if ($needs_rehash) {
              $new_hash = password_hash($pswd, PASSWORD_DEFAULT);
              $update_stmt = $conn->prepare("UPDATE Gebruikers SET wachtwoord = ? WHERE id = ?");
              $update_stmt->bind_param("si", $new_hash, $row['id']);
              $update_stmt->execute();
              $update_stmt->close();
          }

          // Update login timestamps in UTC
          $login_time = gmdate('Y-m-d H:i:s');
          $stmt_login_time = $conn->prepare("UPDATE Gebruikers SET last_login = ?, first_login = COALESCE(first_login, ?) WHERE id = ?");
          $stmt_login_time->bind_param("ssi", $login_time, $login_time, $row['id']);
          $stmt_login_time->execute();
          $stmt_login_time->close();

          // Setup user session
          unset($_SESSION['kiosk_id'], $_SESSION['kiosk_priv'], $_SESSION['kiosk_naam']);
          $_SESSION['id'] = $row['id'];
          $_SESSION['priv'] = $row['priv'];
          $_SESSION['voornaam'] = $row['voornaam'] ?? '';
          $_SESSION['achternaam'] = $row['achternaam'] ?? '';
          $_SESSION['gebruikersnaam'] = $row['gebruikersnaam'] ?? '';
          $_SESSION['gps'] = "false";
          $_SESSION['theme'] = $row['theme'] ?? 'light';
          
          // show welcome modal on next page load for new users (priv == 0)
          if ($row['priv'] == 0) {
            $_SESSION['show_welcome_modal'] = true;
          } else {
            unset($_SESSION['show_welcome_modal']);
          }
          header("Location: home");
          die();
      } else {
          $error = "Gebruikersnaam of wachtwoord onjuist";
          header("Location: index?error=".urlencode($error));
          die();
      }
  } else {
    $stmt_login->close();
    $error = "Gebruikersnaam of wachtwoord onjuist";
    header("Location: index?error=".urlencode($error));
    die();
  }
}
?>