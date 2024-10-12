<?php

// this is to randomise the "deeelgebieden" of the diffewrent groups.

require_once("dblogin.php");
define("DEELGEBIEDEN", array("Alpha", "Bravo", "Charlie", "Delta", "Echo", "Foxtrot"));

$sql = "SELECT * FROM Groepen";
$result = mysqli_query($conn, $sql);

if (mysqli_num_rows($result) > 0) {
    while($row = mysqli_fetch_assoc($result)) {
        $sql = "UPDATE Groepen SET deelgebied = '".DEELGEBIEDEN[array_rand(DEELGEBIEDEN)]."' WHERE id = '".$row['id']."'";
        if (mysqli_query($conn, $sql)) {
          
        }
    }
}