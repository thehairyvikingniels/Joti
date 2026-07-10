<?php
require("dblogin.php");
$conn->query("ALTER TABLE Gebruikers ADD COLUMN profile_picture VARCHAR(255) DEFAULT NULL;");
echo "DB Updated.";
?>
