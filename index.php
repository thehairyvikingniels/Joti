<?php
session_start();
session_destroy();
?>

<html>
  <head>
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">
    <link rel="stylesheet" href="https://www.w3schools.com/lib/w3-theme-blue-grey.css">
    <link rel='stylesheet' href='https://fonts.googleapis.com/css?family=Open+Sans'>
    <!-- ================================================================================== -->
    <script src="//code.jquery.com/jquery-1.10.2.js"></script>
    <script src="//code.jquery.com/ui/1.11.4/jquery-ui.js"></script>
    <link rel="stylesheet" href="//code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.css">
    <!-- ======================== Fullscreen on homescreen ================================ -->
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <!-- ======================== Fontawsome ============================================== -->
    <script src="https://kit.fontawesome.com/870ab34ea3.js" crossorigin="anonymous"></script>
    <title>Jotihunt - Login</title>
    <meta name="author" content="Niels Maarleveld">
    <link rel="icon" href="media/geusje.png">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">    
  </head>
  <body style='background: lightblue url("media/bg1.jpg") repeat;'>


      <div class="w3-container w3-col l4 m6 w3-display-middle">
        <div class="w3-card w3-margin-top w3-round-xlarge">
            <header class="w3-container w3-theme-d1">
              <h1 class="w3-center">Login</h1>
            </header>
            <div class="w3-container w3-theme-l4">
              <center><img class="w3-margin" src="media/geusje_bevosd.png" style="width:60%"></center>
              
              <?php
              if (isset($_GET['error'])){
                echo '
                  <div class="w3-panel w3-red w3-display-container">
                    <span onclick="this.parentElement.style.display=\'none\'"
                    class="w3-button w3-red w3-large w3-display-topright">&times;</span>
                    <p>'.$_GET['error'].'</p>
                  </div>
                  ';
              }
              ?>
              <form action="login.php" method="post">
                <p>Gebruikersnaam:</p>
                <input style="width:100%" class="w3-input w3-round-xlarge" id="user" type="text" name="username" placeholder="Gebruikersnaam" autofocus>
                <h5>Wachtwoord:</h5>
                <input style="width:100%" class="w3-input w3-round-xlarge" id="pswd" type="password" name="pswd" placeholder="Wachtwoord">
                <button class="w3-btn w3-theme-d4 w3-margin-top w3-center w3-round-xlarge" type="submit">Log In</button>
                <span class="w3-btn w3-theme-d4 w3-margin-top w3-center w3-round-xlarge" onclick="document.getElementById('modal01').style.display='block'">Wordt lid</span>
                <p style="cursor:pointer;" onclick="alert('stuur niels een appje :-)')"><u>Wachtwoord vergeten?</u></p>
              </form>
            </div>
        </div>
    <!-- Footer -->
        <footer class="w3-container w3-theme-d5 w3-padding-16" style="width:100%;">
      <center><p><a href="#">Niels Maarleveld</a> - &copy; <?php echo date("Y");?></p>
    </footer>
      </div>
      <div id="modal01" class="w3-modal">
          <div class="w3-modal-content w3-card-4 w3-animate-top" style="max-width:400px">
            <header class="w3-container w3-theme-d1"> 
              <span onclick="document.getElementById('modal01').style.display='none'" 
              class="w3-button w3-display-topright"><i class="fas fa-times"></i></span>
              <h2>Wordt lid</h2>
            </header>
            <div class="w3-container w3-theme-l4">
              <?php
              if (isset($_GET['m_error'])){
                echo '
                  <div class="w3-panel w3-yellow w3-display-container">
                    <span onclick="this.parentElement.style.display=\'none\'"
                    class="w3-button w3-yellow w3-large w3-display-topright">&times;</span>
                    <p>'.$_GET['m_error'].'</p>
                  </div>
                  ';
                echo "<script>window.onload = document.getElementById('modal01').style.display='block';</script>";
              }
              
              ?>
              <form method="post" action="login.php" onkeydown="return event.key != 'Enter';">
                <b>Voornaam</b>
                <input class="w3-input w3-round-xlarge" type="text" name="voornaam" required minlength="1" maxlength="128"><br>
                <b>Achternaam</b>
                <input class="w3-input w3-round-xlarge" type="text" name="achternaam" required minlength="1" maxlength="128"><br>
                <b>Email</b>
                <input class="w3-input w3-round-xlarge" type="email" name="email" required minlength="4" maxlength="320"><br>
                <b>Gebruikersnaam</b>
                <input class="w3-input w3-round-xlarge" type="text" name="gebruikersnaam" required minlength="5" maxlength="32"><br>
                <b>Telefoon</b><br>
                <u>Moet</u> met 316 beginnen!
                <input class="w3-input w3-round-xlarge" type="phone" name="telefoon" placeholder="+316 12345678" required minlength="11" maxlength="15"><br>
                <b>Wachtwoord</b>
                <input class="w3-input w3-round-xlarge" type="password" name="pswd0" required minlength="8"><br>
                <b>Herhaal wachtwoord</b>
                <input class="w3-input w3-round-xlarge" type="password" name="pswd1" required minlength="8"><br>
                <center><button class="w3-button w3-theme-d2 w3-round-xlarge" type="submit">Maak account aan</button></center>
              </form>
            </div>
            <footer class="w3-container w3-theme-d1">
              <center><p><a href="#">Niels Maarleveld</a> - &copy; <?php echo date("Y");?></p>
            </footer>
          </div>
        </div>
  </body>
</html>