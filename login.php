<?php
session_start();
require('dblogin.php');
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
  $vn = addslashes($_POST['voornaam']);
  $an = addslashes($_POST['achternaam']);
  $email = addslashes($_POST['email']);
  $gebr = addslashes($_POST['gebruikersnaam']);
  $telnum = addslashes($_POST['telefoon']);
  $pswd0 = addslashes($_POST['pswd0']);
  $pswd1 = addslashes($_POST['pswd1']);
  
  $sql = "SELECT id FROM Gebruikers WHERE gebruikersnaam='".$gebr."'";
  $result = mysqli_query($conn, $sql);
  
  if (mysqli_num_rows($result) > 0) {
    $error = "Deze gebruikersnaam is al bezet...<br>Probeer een andere.";
    header("Location: index?m_error=".urlencode($error));
    die();
  } else {
      $api = substr(md5(rand(0,1000000000)),0,8);
      $api = substr($api.".".sha1($api."salt"),0,16);
      $sql = "INSERT INTO Gebruikers (gebruikersnaam, wachtwoord, api, voornaam, achternaam, email, priv, telefoon)
      VALUES ('".$gebr."', '".sha1($pswd0."niels als salt")."', '".$api."', '".$vn."', '".$an."', '".$email."',0,'".$telnum."')";
      
      if (mysqli_query($conn, $sql)) {
        $error = "Account succesvol aangemaakt. Je kan meteen inloggen.";
        header("Location: index?m_error=".urlencode($error));
        die();
      } else {
        $error = "Error: " . $sql . "<br>" . mysqli_error($conn);
        header("Location: index?m_error=".urlencode($error));
        die();
      }
  }
} else if ((empty($_POST['username'])) OR (empty($_POST['pswd']))){
  $error = "Wel alles invullen heh!";
  header("Location: index?error=$error");
  die();
} else {
  $username = mysqli_real_escape_string($conn, $_POST['username']);
  $pswd = mysqli_real_escape_string($conn, $_POST['pswd']);

  $sql = "SELECT id, priv FROM Gebruikers WHERE (gebruikersnaam = '".$username."' OR email = '".$username."') AND wachtwoord='".sha1($pswd."niels als salt")."'";
  $result = mysqli_query($conn, $sql);

  if (mysqli_num_rows($result) > 0) {
      while($row = mysqli_fetch_assoc($result)) {
        $_SESSION['id'] = $row['id'];
        $_SESSION['priv'] = $row['priv'];
        $_SESSION['gps'] = "false";
        header("Location: home");        
      }
  } else {
    $error = "Gebruikersnaam of wachtwoord onjuist";
    header("Location: index?error=$error");
    die();
  }
}