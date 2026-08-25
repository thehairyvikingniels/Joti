<?php
// Branded 404 error page informing the user that the requested page was not found.
require_once('dblogin.php');
$stmt = $conn->prepare("SELECT Waarde FROM Site_Instellingen WHERE Instelling = 'GROUP_LOGO_SMALL_URL'");
$stmt->execute();
$res = $stmt->get_result();
$logoUrl = "media/geusje.png"; // fallback
if ($res->num_rows > 0) {
    $row = $res->fetch_assoc();
    $logoUrl = $row['Waarde'];
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jotihunt - Pagina Niet Gevonden</title>
    <link rel="shortcut icon" type="image/png" href="/media/geusje.png"/>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://kit.fontawesome.com/870ab34ea3.js" crossorigin="anonymous"></script>
</head>
<body class="bg-gray-900 text-gray-100 flex items-center justify-center h-screen p-4">
    <div class="bg-gray-800 border border-gray-700 rounded-xl p-8 max-w-md w-full text-center shadow-2xl space-y-6">
        <div class="w-20 h-20 bg-gray-700 rounded-full flex items-center justify-center mx-auto overflow-hidden shadow-inner p-2 border border-gray-600">
            <img src="/<?= htmlspecialchars($logoUrl) ?>" class="w-full h-full object-contain opacity-90" alt="Logo">
        </div>

        <div>
            <h1 class="text-3xl font-bold text-white mb-2">404</h1>
            <p class="text-gray-400 text-sm">
                Oeps! De pagina die je zoekt bestaat niet (meer) of is verplaatst.
            </p>
        </div>

        <a href="/home" class="w-full py-3 bg-blue-600 hover:bg-blue-500 text-white font-semibold rounded-lg transition duration-200 flex items-center justify-center gap-2 shadow-lg mt-4 inline-block text-center">
            <i class="fas fa-home"></i> Terug naar Home
        </a>
    </div>
</body>
</html>
