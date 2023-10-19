<?php
define("NAME", "articles");
define("JOTI_URL", "https://jotihunt.nl");
define("START_TIME", microtime(true));
date_default_timezone_set('Europe/Amsterdam');
$output = "";

$token = "S5d78c5180a4457.86389445";
// $servername = "localhost";
// $username = "nielsmd365_joti";
// $password = "jotihunt2019";
// $dbname = "nielsmd365_joti";

$servername = "localhost";
$username = "maarleveld_one_joti";
$password = "jVfxEi8VxemB7mTF";
$dbname = "maarleveld_one_joti";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$datumtijd = date('Y-m-d H:i:s');


echo "-ARTICLES</br>";

$data = json_decode(file_get_contents(JOTI_URL."/api/2.0/articles"),true);

foreach($data['data'] as $item) {
  echo $item['id']." - ".$item['title']."</br>";

  $news = $conn->prepare("INSERT INTO Nieuws (id, titel, inhoud, datum) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE id = id");
  $assignment = $conn->prepare("INSERT INTO Opdrachten (id, titel, inhoud, datum, eindtijd, maxpunten) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE id = id");
  $hint = $conn->prepare("INSERT INTO Hints (id, titel, inhoud, datum) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE id = id");

  if ($item['type'] == 'news') {
  
    $id = $item['id'];
    $title = $item['title'];
    $content = $item['message']['content'];
    $pubTime = date("Y-m-d H:i:s", strtotime($item['publish_at']));

    $news->bind_param("isss", $id, $title, $content, $pubTime);
    $news->execute();

  } else if ($item['type'] == 'assignment') {

    $id = $item['id'];
    $title = $item['title'];
    $content = $item['message']['content'];
    $pubTime = date("Y-m-d H:i:s", strtotime($item['publish_at']));
    $endTime = date("Y-m-d H:i:s", strtotime($item['message']['end_time']));
    $maxPoints = $item['message']['max_points'];

    $assignment->bind_param("issssi", $id, $title, $content, $pubTime, $endTime, $maxPoints);
    $assignment->execute();

  } else if ($item['type'] == 'hint') {

    $id = $item['id'];
    $title = $item['title'];
    $content = $item['message']['content'];
    $pubTime = date("Y-m-d H:i:s", strtotime($item['publish_at']));

    $hint->bind_param("isss", $id, $title, $content, $pubTime);
    $hint->execute();
  }
}


echo "-ARTICLES</br>";


define("END_TIME", microtime(true));
$duration = intval((END_TIME - START_TIME)*1000);
$output = addslashes($output);


// prepare and bind
$stmt = $conn->prepare("INSERT INTO Cronlogs (name, exec_time, exec_length, exec_stat, exec_output)
VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("ssiis", $p1, $p2, $p3, $p4, $p5);
$p1 = NAME;
$p2 = $datumtijd;
$p3 = $duration;
$p4 = 200;
$p5 = $output;
$stmt->execute();

$stmt->close();
$conn->close();

function log2DB(string $entry) {
    global $output;
    echo $entry;
    $output .= $entry."\n";
}