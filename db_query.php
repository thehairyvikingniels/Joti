<?php
require 'dblogin.php';
$res = $conn->query("SHOW COLUMNS FROM Groepen");
while ($row = $res->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
echo "------\n";
$res2 = $conn->query("SHOW COLUMNS FROM Gebruikers");
while ($row = $res2->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
