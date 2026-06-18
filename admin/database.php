<?php
define("PAGE_NAME", "a_database");
session_start();

if (!isset($_SESSION['id'])) {
    header("Location: ../index");
    exit();
}

require("../dblogin.php");

$stmt = $conn->prepare("SELECT * FROM Gebruikers WHERE id=?");
$stmt->bind_param("i", $_SESSION['id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $vn = $row['voornaam'];
        $priv = $row['priv'];
    }
}
$stmt->close();

if ($priv < 2) {
    header("Location: ../home");
    exit();
}

// Get global site settings
$sql_settings = "SELECT * FROM Site_Instellingen";
$result_settings = mysqli_query($conn, $sql_settings);
$siteSettings = array();
if (mysqli_num_rows($result_settings) > 0) {
    while ($row_settings = mysqli_fetch_assoc($result_settings)) {
        $siteSettings[$row_settings['Instelling']] = $row_settings['Waarde'];
    }
} else {
    echo "0 results for settings";
    exit();
}

// Fetch Voslocaties
$sql_voslocaties = "SELECT * FROM Voslocaties ORDER BY ingestuurd_op DESC";
$result_voslocaties = mysqli_query($conn, $sql_voslocaties);

?>
<!DOCTYPE html>
<html>
<title>Jotihunt - De Geuzen</title>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="shortcut icon" type="image/png" href="media/geusje.png"/>
<link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">
<link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Raleway">
<script src="https://kit.fontawesome.com/870ab34ea3.js" crossorigin="anonymous"></script>
<style>
    html, body, h1, h2, h3, h4, h5 { font-family: "Raleway", sans-serif }
    .w3-table-all th { background-color: #f2f2f2; cursor: pointer; user-select: none;}
    .w3-button { user-select: none; }
    th .fas {
        margin-left: 5px;
        color: #ccc;
    }
    th .fas.active {
        color: #333;
    }
</style>
<body class="w3-light-grey">

<?php include_once('../includes/topbar.php') ?>

<?php include_once('../includes/sidebar.php') ?>

<div class="w3-main" style="margin-left:200px;margin-top:43px;">

    <header class="w3-container" style="padding-top:22px">
        <h5><b><i class="fas fa-cogs"></i> Admin</b></h5>
    </header>

    <div class="w3-row-padding" style="margin-bottom:100px;">
        <div class="w3-col l12 m12 s12">
            <div class="w3-card-4 w3-white">
                <div class="w3-container w3-blue-gray w3-padding">
                    <h5>Ingestuurde Locaties (Hints, Hunts, etc.)</h5>
                </div>
                <div class="w3-container w3-responsive">
                    <table class="w3-table-all w3-hoverable" id="voslocatiesTable">
                        <thead>
                            <tr class="w3-light-grey">
                                <th onclick="sortTable(0)">Type <i class="fas fa-sort"></i></th>
                                <th onclick="sortTable(1)">Deelgebied <i class="fas fa-sort"></i></th>
                                <th onclick="sortTable(2)">Ingestuurd Op <i class="fas fa-sort"></i></th>
                                <th>Coördinaten (Lat, Lon)</th>
                                <th>Code</th>
                                <th>Opmerking</th>
                                <th>Acties</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if (mysqli_num_rows($result_voslocaties) > 0) {
                                while ($row = mysqli_fetch_assoc($result_voslocaties)) {
                                    echo "<tr>";
                                    echo "<td>" . htmlspecialchars($row['type']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['deelgebied']) . "</td>";
                                    echo "<td>" . date('Y-m-d H:i', strtotime($row['ingestuurd_op'])) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['coordinaat_x']) . ", " . htmlspecialchars($row['coordinaat_y']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['code']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['opmerking']) . "</td>";
                                    echo '<td>
                                            <button onclick="openEditModal(' . htmlspecialchars(json_encode($row)) . ')" class="w3-button w3-blue w3-small"><i class="fas fa-pencil-alt"></i></button>
                                            <button onclick="openDeleteModal(' . $row['id'] . ')" class="w3-button w3-red w3-small"><i class="fas fa-trash-alt"></i></button>
                                          </td>';
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='7' class='w3-center'>Geen locaties gevonden.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div id="editModal" class="w3-modal">
        <div class="w3-modal-content w3-card-4 w3-animate-zoom" style="max-width:600px">
            <header class="w3-container w3-blue-gray">
                <span onclick="document.getElementById('editModal').style.display='none'" class="w3-button w3-display-topright w3-hover-red">&times;</span>
                <h4>Bewerk Locatie</h4>
            </header>
            <form id="editForm" class="w3-container" action="../functies.php" method="POST">
                <input type="hidden" id="edit_id" name="voslocatie_id">
                
                <p>
                    <label>Type</label>
                    <select class="w3-select w3-border" id="edit_type" name="type" required>
                        <option value="Hint">Hint</option>
                        <option value="Hunt">Hunt</option>
                        <option value="Spot">Spot</option>
                        <option value="Voorspelling">Voorspelling</option>
                    </select>
                </p>
                <p>
                    <label>Deelgebied</label>
                    <select class="w3-select w3-border" id="edit_deelgebied" name="deelgebied" required>
                        <option value="Alpha">Alpha</option>
                        <option value="Bravo">Bravo</option>
                        <option value="Charlie">Charlie</option>
                        <option value="Delta">Delta</option>
                        <option value="Echo">Echo</option>
                        <option value="Foxtrot">Foxtrot</option>
                        <option value="Golf">Golf</option>
                        <option value="Hotel">Hotel</option>
                    </select>
                </p>
                <p>
                    <label>Ingestuurd Op</label>
                    <input class="w3-input w3-border" type="datetime-local" id="edit_ingestuurd_op" name="ingestuurd_op" required>
                </p>
                <p>
                    <label>Latitude (X)</label>
                    <input class="w3-input w3-border" type="text" id="edit_coord_x" name="coordinaat_x" required>
                </p>
                <p>
                    <label>Longitude (Y)</label>
                    <input class="w3-input w3-border" type="text" id="edit_coord_y" name="coordinaat_y" required>
                </p>
                <p>
                    <label>Code (max. 8 tekens, verplicht bij Hunt)</label>
                    <input class="w3-input w3-border" type="text" id="edit_code" name="code" maxlength="8">
                </p>
                <p>
                    <label>Opmerking (max. 128 tekens)</label>
                    <input class="w3-input w3-border" type="text" id="edit_opmerking" name="opmerking" maxlength="128">
                </p>

                <div class="w3-container w3-light-grey w3-padding">
                    <button type="button" onclick="document.getElementById('editModal').style.display='none'" class="w3-button w3-right w3-border">Annuleren</button>
                    <button type="submit" name="update_voslocatie" class="w3-button w3-blue">Opslaan</button>
                </div>
            </form>
        </div>
    </div>

    <div id="deleteModal" class="w3-modal">
        <div class="w3-modal-content w3-card-4 w3-animate-zoom" style="max-width:500px">
            <div class="w3-container w3-padding">
                <h4>Verwijder Locatie</h4>
                <p>Weet je zeker dat je dit item wilt verwijderen? Deze actie kan niet ongedaan worden gemaakt.</p>
                <div class="w3-container w3-light-grey w3-padding">
                    <button onclick="document.getElementById('deleteModal').style.display='none'" class="w3-button w3-right w3-border">Annuleren</button>
                    <a id="deleteLink" href="#" class="w3-button w3-red">Verwijderen</a>
                </div>
            </div>
        </div>
    </div>


    <?php require_once('../includes/footer.php') ?>

    </div>

<script>
    let sortDirections = {}; // Object to track sorting direction for each column

    function sortTable(columnIndex) {
        const table = document.getElementById("voslocatiesTable");
        const tbody = table.tBodies[0];
        const rows = Array.from(tbody.rows);
        const header = table.tHead.rows[0].cells[columnIndex];
        const dir = sortDirections[columnIndex] === 'asc' ? 'desc' : 'asc';
        sortDirections = {}; // Reset all directions
        sortDirections[columnIndex] = dir;

        // Reset all sort icons
        document.querySelectorAll('#voslocatiesTable th .fas').forEach((icon, index) => {
            icon.classList.remove('fa-sort-up', 'fa-sort-down', 'active');
            if (index !== columnIndex) {
                icon.classList.add('fa-sort');
            }
        });
        
        const sortIcon = header.querySelector('.fas');
        sortIcon.classList.remove('fa-sort', 'fa-sort-up', 'fa-sort-down');
        sortIcon.classList.add(dir === 'asc' ? 'fa-sort-up' : 'fa-sort-down', 'active');
        
        rows.sort((a, b) => {
            const aText = a.cells[columnIndex].textContent.trim();
            const bText = b.cells[columnIndex].textContent.trim();

            // For date sorting (column 2), convert to timestamp
            if (columnIndex === 2) {
                return dir === 'asc' ? new Date(aText) - new Date(bText) : new Date(bText) - new Date(aText);
            }

            return dir === 'asc' ? aText.localeCompare(bText) : bText.localeCompare(aText);
        });

        rows.forEach(row => tbody.appendChild(row));
    }


    // JavaScript voor het openen en vullen van de modals
    function openEditModal(data) {
        document.getElementById('edit_id').value = data.id;
        document.getElementById('edit_type').value = data.type;
        document.getElementById('edit_deelgebied').value = data.deelgebied;
        // Format date for datetime-local input
        const date = new Date(data.ingestuurd_op.replace(' ', 'T'));
        const localIsoString = new Date(date.getTime() - (date.getTimezoneOffset() * 60000)).toISOString().slice(0, 16);
        document.getElementById('edit_ingestuurd_op').value = localIsoString;
        document.getElementById('edit_coord_x').value = data.coordinaat_x;
        document.getElementById('edit_coord_y').value = data.coordinaat_y;
        document.getElementById('edit_code').value = data.code;
        document.getElementById('edit_opmerking').value = data.opmerking;
        document.getElementById('editModal').style.display = 'block';
    }

    function openDeleteModal(id) {
        document.getElementById('deleteLink').href = '../functies.php?verwijder_voslocatie=' + id;
        document.getElementById('deleteModal').style.display = 'block';
    }

    // GPS refresh functie (bestaande code)
    if ("<?php echo $_SESSION['gps'] ?? 'false'; ?>" == "true") {
        setInterval(function() {
            GPSrefresh();
        }, 5555);
    }

    function GPSrefresh() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(showPosition);
        } else {
            console.log("Geolocation is not supported by this browser.");
        }

        function showPosition(position) {
            console.log("Latitude: " + position.coords.latitude + "<br>Longitude: " + position.coords.longitude);
            if (window.XMLHttpRequest) {
                xmlhttp = new XMLHttpRequest();
            } else {
                xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
            }
            xmlhttp.onreadystatechange = function() {
                if (this.readyState == 4 && this.status == 200) {}
            };
            xmlhttp.open("GET", "../functies.php?lat=" + position.coords.latitude + "&lon=" + position.coords.longitude, true);
            xmlhttp.send();
        }
    }
</script>

</body>
</html>