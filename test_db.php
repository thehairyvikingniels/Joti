<?php
require("dblogin.php");
$res = $conn->query("SHOW COLUMNS FROM Gebruikers LIKE 'theme'");
print_r($res->fetch_assoc());
