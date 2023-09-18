<?php
session_start();
if (!isset($_SESSION['id'])){
  header("Location: index");
}
require("dblogin.php");

if(!empty($_POST['username']) && !empty($_POST['firstname']) && !empty($_POST['lastname']) && !empty($_POST['email'])) {
  $sql = "UPDATE Gebruikers SET gebruikersnaam='".addslashes($_POST['username'])."', voornaam='".addslashes($_POST['firstname'])."', achternaam='".addslashes($_POST['lastname'])."', email='".addslashes($_POST['email'])."' WHERE id='".$_SESSION['id']."'";

  if (mysqli_query($conn, $sql)) {
    $e = "Succesvol veranderd";
    header("Location: instellingen.php?e=".urlencode($e)."&t=gegevens#gegevens");
  } else {
    $e = "Error updating record: " . mysqli_error($conn);
    header("Location: instellingen.php?e=".urlencode($e)."&t=gegevens#gegevens");
  }
  print_r($_POST);
} elseif (!empty($_POST['pswd0']) && !empty($_POST['pswd1'])) {
  if ($_POST['pswd0'] == $_POST['pswd1']){
    $sql = "UPDATE Gebruikers SET wachtwoord='".sha1(addslashes($_POST['pswd1'])."niels als salt")."' WHERE id='".$_SESSION['id']."'";

    if (mysqli_query($conn, $sql)) {
      $e = "Succesvol gewijzigd";
      header("Location: instellingen.php?e=".urlencode($e)."&t=wachtwoord#wachtwoord");
    } else {
      $e = "Error updating record: " . mysqli_error($conn);
      header("Location: instellingen.php?e=".urlencode($e)."&t=wachtwoord#wachtwoord");
    }
  } else {
    $e = "Wachtwoorden komen niet overeen...<br> Probeer opnieuw :-)";
    header("Location: instellingen.php?e=".urlencode($e)."&t=wachtwoord#wachtwoord");
  }
} elseif (!empty($_POST['api'])) {
  $api_key = explode(".",$_POST['api']);
  $api_check = substr(sha1($api_key[0]."salt"),0,7);
  if ($api_key[1] == $api_check){
    $api = substr(md5(rand(0,1000000000)),0,8);
    $api = substr($api.".".sha1($api."salt"),0,16);
    $sql = "UPDATE Gebruikers SET api='".$api."' WHERE id='".$_SESSION['id']."'";
    if (mysqli_query($conn, $sql)) {
      $e = "Succesvol gewijzigd";
      header("Location: instellingen.php?e=".urlencode($e)."&t=api#api");
    } else {
      $e = "Error updating record: " . mysqli_error($conn);
      header("Location: instellingen.php?e=".urlencode($e)."&t=api#api");
    }
  }
}