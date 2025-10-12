<?php
$sql = "Select * FROM Voslog ORDER BY datumtijd desc LIMIT 1";

$result = mysqli_query($conn, $sql);



if (mysqli_num_rows($result) > 0) {
  
  $a = 0;

  $vossen = array("Alpha", "Bravo", "Charlie", "Delta", "Echo", "Foxtrot", "Golf", "Hotel");

    while($row = mysqli_fetch_assoc($result)) {

      foreach ($vossen as $vosnaam){

        $voslc = lcfirst($vosnaam);

          $sql_2 = "SELECT MAX(datumtijd) as datumtijd FROM Voslog orig WHERE $vosnaam <> (SELECT $vosnaam FROM Voslog WHERE id = orig.id - 1);";

          $result_2 = mysqli_query($conn, $sql_2);

          if (mysqli_num_rows($result_2) > 0) {

            while($row_2 = mysqli_fetch_assoc($result_2)) {

              $vos[$vosnaam]["verandering"] = $row_2['datumtijd'];

              // dynamic duration: seconds, minutes, hours, or "> 24 uur"
              $diff = time() - strtotime($row_2['datumtijd']);
              if ($diff < 60) {
                $vos[$vosnaam]["duratie"] = $diff . " sec";
              } elseif ($diff < 3600) {
                $vos[$vosnaam]["duratie"] = round($diff / 60) . " min";
              } elseif ($diff < 86400) {
                $vos[$vosnaam]["duratie"] = round($diff / 3600, 1) . " uur";
              } else {
                $vos[$vosnaam]["duratie"] = "24 uur +";
              }

            }

          }

        $vos[$vosnaam]["Status"] = $row[$voslc];

        if ($row[$voslc] == 0){

          $vos[$vosnaam]["Kleur"] = "red";

        } elseif ($row[$voslc] == 1){

          $vos[$vosnaam]["Kleur"] = "orange";

        } elseif ($row[$voslc] == 2){

          $vos[$vosnaam]["Kleur"] = "green";

        }

      }

    }

}

?>


<div class="w3-bar w3-top w3-black w3-large" style="z-index:4;">

  <button class="w3-bar-item w3-button w3-hide-large w3-hover-none w3-hover-text-light-grey" onclick="w3_open();"><i class="fa fa-bars"></i>  Menu</button>

  <!-- Desktop / medium+ : show on medium and large (hide only on small) -->
  <div class="w3-hide-small w3-hide-medium w3-bar-item" style="display:flex; gap:6px; align-items:center; flex:1; min-width:0;">
    <?php
      $names = array("Alpha","Bravo","Charlie","Delta","Echo","Foxtrot","Golf","Hotel");
      foreach ($names as $n) {
        // each item is a bar-item, uses w3-center + color class from PHP
        // fixed height and centered content so thickness is consistent
        echo '<div class="w3-center w3-padding-small w3-round w3-'.$vos[$n]["Kleur"].'" style="flex:1; min-width:90px; box-sizing:border-box; height:34px; display:flex; align-items:center; justify-content:center; font-size:0.95rem;">';
        echo '<span style="font-weight:700; margin-right:6px;">'.substr($n,0,1).'</span><span>'.$vos[$n]["duratie"].'</span>';
        echo '</div>';
      }
    ?>
  </div>

  <!-- Title (fixed) -->
  <span class="w3-bar-item w3-right"><?= $siteSettings['GROUP_NAME']?></span>

</div>