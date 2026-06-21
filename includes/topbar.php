<?php
$vossen = array("Alpha", "Bravo", "Charlie", "Delta", "Echo", "Foxtrot", "Golf", "Hotel");
$vos = array();

foreach ($vossen as $vosnaam) {
    $vos[$vosnaam]["Kleur"] = "grey"; // Standaard w3css kleur bij geen data
    $vos[$vosnaam]["duratie"] = "-";
    $vos[$vosnaam]["Status"] = 0;
}

$stmt = $conn->prepare("SELECT * FROM Voslog ORDER BY datumtijd DESC LIMIT 1");
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Geen while-loop nodig, we hebben door de LIMIT 1 toch maximaal één rij
    $row = $result->fetch_assoc();

    foreach ($vossen as $vosnaam) {
        $voslc = lcfirst($vosnaam);

        // Kolomnamen kunnen we niet via bind_param binden.
        // Dit is veilig omdat $voslc geforceerd uit onze eigen statische array komt.
        $sql_2 = "SELECT MAX(datumtijd) as datumtijd FROM Voslog orig WHERE {$voslc} <> (SELECT {$voslc} FROM Voslog WHERE id = orig.id - 1)";
        $stmt_2 = $conn->prepare($sql_2);
        $stmt_2->execute();
        $result_2 = $stmt_2->get_result();

        if ($result_2->num_rows > 0) {
            $row_2 = $result_2->fetch_assoc();

            // Extra check of er daadwerkelijk een datum is gevonden
            if (!empty($row_2['datumtijd'])) {
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
        $stmt_2->close();

        // Null coalescing (??) voorkomt foutmeldingen als de kolom toevallig leeg is
        $vos[$vosnaam]["Status"] = $row[$voslc] ?? 0; 

        if ($vos[$vosnaam]["Status"] == 0){
            $vos[$vosnaam]["Kleur"] = "red";
        } elseif ($vos[$vosnaam]["Status"] == 1){
            $vos[$vosnaam]["Kleur"] = "orange";
        } elseif ($vos[$vosnaam]["Status"] == 2){
            $vos[$vosnaam]["Kleur"] = "green";
        }
    }
}
$stmt->close();
?>

<div class="w3-bar w3-top w3-black w3-large" style="z-index:4;">

  <button class="w3-bar-item w3-button w3-hide-large w3-hover-none w3-hover-text-light-grey" onclick="w3_open();"><i class="fa fa-bars"></i> &nbsp;Menu</button>

  <div class="w3-hide-small w3-hide-medium w3-bar-item" style="display:flex; gap:6px; align-items:center; flex:1; min-width:0;">
    <?php
      $names = array("Alpha","Bravo","Charlie","Delta","Echo","Foxtrot","Golf","Hotel");
      foreach ($names as $n) {
        // each item is a bar-item, uses w3-center + color class from PHP
        // fixed height and centered content so thickness is consistent
        echo '<div class="w3-center w3-padding-small w3-round w3-'.htmlspecialchars($vos[$n]["Kleur"]).'" style="flex:1; min-width:90px; box-sizing:border-box; height:34px; display:flex; align-items:center; justify-content:center; font-size:0.95rem;">';
        echo '<span style="font-weight:700; margin-right:6px;">'.htmlspecialchars(substr($n,0,1)).'</span><span>'.htmlspecialchars($vos[$n]["duratie"]).'</span>';
        echo '</div>';
      }
    ?>
  </div>

  <span class="w3-bar-item w3-right"><?= htmlspecialchars($siteSettings['GROUP_NAME'] ?? 'Jotihunt') ?></span>

</div>

<style>
  /* Maak alleen het hoofdgedeelte flexibel, laat de body en sidebar met rust */
  .w3-main {
    display: flex;
    flex-direction: column;
    /* 100% van de schermhoogte, min de 43px marge van de topbar */
    min-height: calc(100vh - 43px); 
  }

  /* Duw de footer áltijd naar de bodem van w3-main */
  #site-footer-wrapper {
    margin-top: auto;
  }
</style>