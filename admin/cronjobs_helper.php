<?php
session_start();
if (!isset($_SESSION['id'])){
  header("Location: ../index");
}
require("../dblogin.php");

if (isset($_GET['cronjobs'])) {
    $return = array();
    $sql = "SELECT cj.name, cj.enabled, cj.URL, cj.description, cj.interval, cl.exec_time, cl.exec_length, cl.exec_stat, cl.exec_output
        FROM Cronjobs cj LEFT JOIN Cronlogs cl ON cj.name = cl.name
        WHERE cl.exec_time IS NULL
            OR cl.exec_time = (
                SELECT MAX(cl2.exec_time)
                FROM Cronlogs cl2
                WHERE cl2.name = cj.name
            )";
    $result = mysqli_query($conn, $sql);

    if (mysqli_num_rows($result) > 0) {
        // output data of each row
        $i = 0;
        while($row = mysqli_fetch_assoc($result)) {
            $name = ucfirst($row['name']);
            $interval = number_format($row['interval'] / 60, 1, ',')." min";
            $exec_time = date("d/m H:i:s",strtotime($row['exec_time']));
            $exec_length = number_format($row['exec_length'] / 1000, 2, ',')." sec";
            $exec_status = $row['exec_stat'];
            $exec_output = $row['exec_output'];

            if ($row['enabled'] === "1") {
                $enabled = '<i class="fas fa-toggle-on fa-fw"></i>';
            } else {
                $enabled = '<i class="fas fa-toggle-off fa-fw"></i>';
            }


            switch ($exec_status) {
                case 200: // succes
                $stat_color = "w3-text-green";
                break;
                case 429: // too many requests
                $stat_color = "w3-text-yellow";
                break;
                case 500: // script error
                $stat_color = "w3-text-red";
                break;
                default:
                $stat_color = "w3-text-red";
                break;
            }

            $return[$i]['enabled'] = $enabled;
            $return[$i]['stat_color'] = $stat_color;
            $return[$i]['description'] = $row['description'];
            $return[$i]['url'] = $row['URL'];
            $return[$i]['name'] = $name;
            $return[$i]['interval'] = $interval;
            $return[$i]['exec_time'] = $exec_time;
            $return[$i]['exec_length'] = $exec_length;
            $return[$i]['exec_status'] = $exec_status;
            $return[$i]['exec_next'] = $row['interval'] + strtotime($row['exec_time']) - time();
            if ($return[$i]['exec_next'] <= 0) {
                $return[$i]['exec_next'] = "executing...";
            } else {
                $return[$i]['exec_next'] .= " sec";
            }
            $i++;
        }
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($return, true);     
}