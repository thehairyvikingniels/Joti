<?php
$vos = array();
$topbarGroupName = 'Jotihunt';
if (!empty($siteSettings['GROUP_ID'])) {
    $stmt_gn = $conn->prepare("SELECT naam FROM Groepen WHERE id = ?");
    $stmt_gn->bind_param("i", $siteSettings['GROUP_ID']);
    $stmt_gn->execute();
    $result_gn = $stmt_gn->get_result();
    if ($result_gn->num_rows > 0) {
        $row_gn = $result_gn->fetch_assoc();
        if (!empty($row_gn['naam'])) {
            $topbarGroupName = $row_gn['naam'];
        }
    }
    $stmt_gn->close();
}

foreach ($vossen_names as $vosnaam) {
    $vos[$vosnaam]["Kleur"] = "grey"; // Standaard w3css kleur bij geen data
    $vos[$vosnaam]["duratie"] = "-";
    $vos[$vosnaam]["Status"] = 0;
    
    // Fetch latest status
    $stmt = $conn->prepare("SELECT status, datumtijd FROM Voslog WHERE vos = ? ORDER BY datumtijd DESC LIMIT 1");
    $stmt->bind_param("s", $vosnaam);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $latest_status = $row['status'];
        $vos[$vosnaam]["Status"] = $latest_status;
        
        // Find when this status originally started (the first row after the last time it was DIFFERENT)
        $sql_change = "SELECT MIN(datumtijd) as changed_time FROM Voslog 
                       WHERE vos = ? AND datumtijd > (
                           SELECT COALESCE(MAX(datumtijd), '2000-01-01') FROM Voslog WHERE vos = ? AND status <> ?
                       )";
        $stmt_change = $conn->prepare($sql_change);
        $stmt_change->bind_param("ssi", $vosnaam, $vosnaam, $latest_status);
        $stmt_change->execute();
        $res_change = $stmt_change->get_result();
        
        if ($res_change->num_rows > 0) {
            $row_change = $res_change->fetch_assoc();
            if (!empty($row_change['changed_time'])) {
                $vos[$vosnaam]["verandering"] = $row_change['changed_time'];

                // dynamic duration
                $diff = time() - strtotime($row_change['changed_time']);
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
        $stmt_change->close();
        
        if ($vos[$vosnaam]["Status"] == 0){
            $vos[$vosnaam]["Kleur"] = "red";
        } elseif ($vos[$vosnaam]["Status"] == 1){
            $vos[$vosnaam]["Kleur"] = "orange";
        } elseif ($vos[$vosnaam]["Status"] == 2){
            $vos[$vosnaam]["Kleur"] = "green";
        }
    }
    $stmt->close();
}
?>

<div class="w3-bar w3-top w3-black w3-large" style="z-index:4;">

  <button class="w3-bar-item w3-button w3-hide-large w3-hover-none w3-hover-text-light-grey" onclick="w3_open();"><i class="fa fa-bars"></i> &nbsp;Menu</button>

  <div class="w3-hide-small w3-hide-medium w3-bar-item" style="display:flex; gap:6px; align-items:center; flex:1; min-width:0;">
    <?php
      foreach ($vossen_names as $n) {
        echo '<div class="w3-center w3-padding-small w3-round w3-'.htmlspecialchars($vos[$n]["Kleur"]).'" style="flex:1; min-width:90px; box-sizing:border-box; height:34px; display:flex; align-items:center; justify-content:center; font-size:0.95rem;">';
        echo '<span style="font-weight:700; margin-right:6px;">'.htmlspecialchars(substr($n,0,1)).'</span><span>'.htmlspecialchars($vos[$n]["duratie"]).'</span>';
        echo '</div>';
      }
    ?>
  </div>

  <span class="w3-bar-item w3-right"><?= htmlspecialchars($topbarGroupName) ?></span>

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