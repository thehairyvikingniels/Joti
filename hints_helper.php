<?php
// hints_helper.php - AJAX backend handler for hint coordinate persistence, multi-user sync scanner, and Mapbox probe analysis.
define('PAGE_NAME', 'hints');
require_once(__DIR__ . '/includes/auth.php');

header('Content-Type: application/json; charset=utf-8');

if ($privilege < 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Geen toegang. Inloggen vereist.']);
    exit();
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'save_coordinates':
        handleSaveCoordinates($conn, $privilege, (int)($_SESSION['id'] ?? 0));
        break;

    case 'get_hint_coordinates':
        handleGetHintCoordinates($conn);
        break;

    case 'probe_coordinates':
        handleProbeCoordinates($conn);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Ongeldige actie opgegeven.']);
        exit();
}

/**
 * Saves validated complete 6-digit RD coordinates directly into Voslocaties.
 */
function handleSaveCoordinates(mysqli $conn, int $privilege, int $userId): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
        return;
    }

    $hintId = (int)($_POST['hint_id'] ?? 0);
    $subarea = trim((string)($_POST['subarea'] ?? ''));
    $rdX = trim((string)($_POST['rd_x'] ?? ''));
    $rdY = trim((string)($_POST['rd_y'] ?? ''));

    if ($hintId <= 0 || empty($subarea)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Hint ID en deelgebied zijn verplicht.']);
        return;
    }

    // Check for wildcards
    if (str_contains($rdX, '*') || str_contains($rdY, '*')) {
        echo json_encode([
            'success' => false,
            'error' => 'Coördinaten met een sterretje (*) kunnen niet worden opgeslagen. Gebruik de knop "Probeer" om wildcards op de kaart te testen.'
        ]);
        return;
    }

    // Accept 4-digit hectometer inputs (e.g. 1924, 4452) or legacy 6-digit RD inputs
    if (preg_match('/^\d{4}$/', $rdX)) {
        $xMeters = (float)($rdX . '00');
        $displayX = $rdX;
    } elseif (preg_match('/^\d{6}$/', $rdX)) {
        $xMeters = (float)$rdX;
        $displayX = substr($rdX, 0, 4);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'X-coördinaat moet uit 4 cijfers bestaan (bijv. 1924).'
        ]);
        return;
    }

    if (preg_match('/^\d{4}$/', $rdY)) {
        $yMeters = (float)($rdY . '00');
        $displayY = $rdY;
    } elseif (preg_match('/^\d{6}$/', $rdY)) {
        $yMeters = (float)$rdY;
        $displayY = substr($rdY, 0, 4);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Y-coördinaat moet uit 4 cijfers bestaan (bijv. 4452).'
        ]);
        return;
    }

    // Convert RD to WGS84 (Lat, Lon)
    $wgs = convertRdToWgs($xMeters, $yMeters);
    $lat = $wgs['lat'];
    $lon = $wgs['lon'];

    // Verify coordinates fall within sensible Dutch boundaries
    if ($lat < 50.5 || $lat > 53.7 || $lon < 3.0 || $lon > 7.3) {
        echo json_encode([
            'success' => false,
            'error' => 'De opgegeven coördinaten vallen buiten Nederland.'
        ]);
        return;
    }

    $opmerking = "Hint #{$hintId}";
    $code = "{$subarea} {$displayX} {$displayY}";
    $ingestuurdOp = date('Y-m-d H:i:s');

    // Check if an existing entry for this specific hint and subarea exists
    $stmtCheck = $conn->prepare("SELECT id FROM Voslocaties WHERE type = 'Hint' AND deelgebied = ? AND opmerking = ? LIMIT 1");
    $stmtCheck->bind_param("ss", $subarea, $opmerking);
    $stmtCheck->execute();
    $resCheck = $stmtCheck->get_result();
    $existing = $resCheck->fetch_assoc();
    $stmtCheck->close();

    if ($existing) {
        $existingId = (int)$existing['id'];
        $stmtUpd = $conn->prepare("UPDATE Voslocaties SET ingestuurd_op = ?, ingeleverd_door = ?, coordinaat_x = ?, coordinaat_y = ?, code = ? WHERE id = ?");
        $stmtUpd->bind_param("siddsi", $ingestuurdOp, $userId, $lat, $lon, $code, $existingId);
        $success = $stmtUpd->execute();
        $stmtUpd->close();
    } else {
        $stmtIns = $conn->prepare("INSERT INTO Voslocaties (ingestuurd_op, type, deelgebied, ingeleverd, ingeleverd_door, coordinaat_x, coordinaat_y, code, opmerking) VALUES (?, 'Hint', ?, 0, ?, ?, ?, ?, ?)");
        $stmtIns->bind_param("ssiddss", $ingestuurdOp, $subarea, $userId, $lat, $lon, $code, $opmerking);
        $success = $stmtIns->execute();
        $stmtIns->close();
    }

    if (!$success) {
        echo json_encode(['success' => false, 'error' => 'Database fout bij opslaan: ' . $conn->error]);
        return;
    }

    echo json_encode([
        'success' => true,
        'message' => "Coördinaat voor {$subarea} opgeslagen!",
        'hint_id' => $hintId,
        'subarea' => $subarea,
        'rd_x' => $displayX,
        'rd_y' => $displayY,
        'lat' => round($lat, 6),
        'lon' => round($lon, 6),
        'saved_at' => date('H:i:s')
    ]);
}

/**
 * Fetches all saved Hint coordinates mapped by hint ID and deelgebied for 2-second AJAX scanner.
 */
function handleGetHintCoordinates(mysqli $conn): void {
    $stmt = $conn->prepare("SELECT v.id, v.deelgebied, v.code, v.opmerking, v.ingestuurd_op, v.ingeleverd_door, g.voornaam 
                           FROM Voslocaties v 
                           LEFT JOIN Gebruikers g ON v.ingeleverd_door = g.id 
                           WHERE v.type = 'Hint' AND v.opmerking LIKE 'Hint #%' 
                           ORDER BY v.ingestuurd_op ASC");
    $stmt->execute();
    $result = $stmt->get_result();

    $data = [];
    while ($row = $result->fetch_assoc()) {
        if (!preg_match('/Hint #(\d+)/', (string)$row['opmerking'], $matches)) {
            continue;
        }
        $hintId = (int)$matches[1];
        $subarea = $row['deelgebied'];

        // Extract rdX and rdY from code column (format: "$subarea $rdX $rdY")
        $parts = preg_split('/\s+/', trim((string)$row['code']));
        $rdX = '';
        $rdY = '';
        if (count($parts) >= 3) {
            $rdX = (strlen($parts[1]) > 4) ? substr($parts[1], 0, 4) : $parts[1];
            $rdY = (strlen($parts[2]) > 4) ? substr($parts[2], 0, 4) : $parts[2];
        }

        if (!isset($data[$hintId])) {
            $data[$hintId] = [];
        }

        $data[$hintId][$subarea] = [
            'rd_x' => $rdX,
            'rd_y' => $rdY,
            'saved_at' => $row['ingestuurd_op'],
            'saved_by' => $row['voornaam'] ?? 'Teamlid',
            'user_id' => (int)$row['ingeleverd_door']
        ];
    }
    $stmt->close();

    echo json_encode([
        'success' => true,
        'coordinates' => $data,
        'server_time' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Analyzes candidate coordinate (with or without wildcards) and fetches historical trail points.
 */
function handleProbeCoordinates(mysqli $conn): void {
    $hintId = (int)($_GET['hint_id'] ?? $_POST['hint_id'] ?? 0);
    $subarea = trim((string)($_GET['subarea'] ?? $_POST['subarea'] ?? ''));
    $rdXRaw = trim((string)($_GET['rd_x'] ?? $_POST['rd_x'] ?? ''));
    $rdYRaw = trim((string)($_GET['rd_y'] ?? $_POST['rd_y'] ?? ''));

    if (empty($subarea) || empty($rdXRaw) || empty($rdYRaw)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Deelgebied en coördinaten zijn vereist.']);
        return;
    }

    // Strip leading 'X', 'x', 'Y', 'y', ':', and whitespace
    $rdXRaw = preg_replace('/^[xX\s:]+/', '', $rdXRaw);
    $rdYRaw = preg_replace('/^[yY\s:]+/', '', $rdYRaw);

    $rdXDisplay = $rdXRaw;
    $rdYDisplay = $rdYRaw;

    // 1. Fetch previous 4 points for this fox, excluding any point recorded for the current hint
    if ($hintId > 0) {
        $excludeOpmerking = "Hint #{$hintId}";
        $stmtHistory = $conn->prepare("SELECT id, ingestuurd_op, type, coordinaat_x, coordinaat_y, code, opmerking 
                                      FROM Voslocaties 
                                      WHERE deelgebied = ? AND type IN ('Hint', 'Hunt', 'Spot') 
                                        AND (opmerking != ? OR opmerking IS NULL)
                                      ORDER BY ingestuurd_op DESC 
                                      LIMIT 4");
        $stmtHistory->bind_param("ss", $subarea, $excludeOpmerking);
    } else {
        $stmtHistory = $conn->prepare("SELECT id, ingestuurd_op, type, coordinaat_x, coordinaat_y, code, opmerking 
                                      FROM Voslocaties 
                                      WHERE deelgebied = ? AND type IN ('Hint', 'Hunt', 'Spot') 
                                      ORDER BY ingestuurd_op DESC 
                                      LIMIT 4");
        $stmtHistory->bind_param("s", $subarea);
    }
    $stmtHistory->execute();
    $resHistory = $stmtHistory->get_result();

    $historyPoints = [];
    while ($row = $resHistory->fetch_assoc()) {
        $diff = time() - strtotime($row['ingestuurd_op']);
        if ($diff < 60) {
            $timeAgo = $diff . " sec geleden";
        } elseif ($diff < 3600) {
            $timeAgo = round($diff / 60) . " min geleden";
        } elseif ($diff < 86400) {
            $timeAgo = round($diff / 3600, 1) . " uur geleden";
        } else {
            $timeAgo = date("d/m H:i", strtotime($row['ingestuurd_op']));
        }

        $historyPoints[] = [
            'id' => (int)$row['id'],
            'type' => $row['type'],
            'lat' => (float)$row['coordinaat_x'],
            'lon' => (float)$row['coordinaat_y'],
            'timestamp' => $row['ingestuurd_op'],
            'time_ago' => $timeAgo,
            'code' => $row['code']
        ];
    }
    $stmtHistory->close();

    // Latest historical point (for movement distance calculation)
    $latestKnown = !empty($historyPoints) ? $historyPoints[0] : null;

    // 2. Analyze geometry: single point, candidate cluster (single wildcard), or bounding box
    $starCountX = substr_count($rdXRaw, '*');
    $starCountY = substr_count($rdYRaw, '*');
    $totalStars = $starCountX + $starCountY;
    $hasWildcards = ($totalStars > 0);
    $geometry = [];

    // Complete point: 4 digits (hectometers) or 6 digits (meters) without wildcards
    if (!$hasWildcards && (preg_match('/^\d{4}$/', $rdXRaw) || preg_match('/^\d{6}$/', $rdXRaw)) && (preg_match('/^\d{4}$/', $rdYRaw) || preg_match('/^\d{6}$/', $rdYRaw))) {
        // Complete single point
        $xMeters = (strlen($rdXRaw) === 4) ? (float)($rdXRaw . '00') : (float)$rdXRaw;
        $yMeters = (strlen($rdYRaw) === 4) ? (float)($rdYRaw . '00') : (float)$rdYRaw;
        $wgs = convertRdToWgs($xMeters, $yMeters);
        $distMeters = $latestKnown ? haversineDistance($latestKnown['lat'], $latestKnown['lon'], $wgs['lat'], $wgs['lon']) : null;

        // If candidate point is identical to $latestKnown within 25m and a prior point exists, compare to prior point
        if ($distMeters !== null && $distMeters < 25 && count($historyPoints) > 1) {
            $prior = $historyPoints[1];
            $distMeters = haversineDistance($prior['lat'], $prior['lon'], $wgs['lat'], $wgs['lon']);
            $latestKnown = $prior;
        }

        $geometry = [
            'kind' => 'single',
            'rd_x' => $rdXDisplay,
            'rd_y' => $rdYDisplay,
            'lat' => round($wgs['lat'], 6),
            'lon' => round($wgs['lon'], 6),
            'is_complete' => true,
            'distance_meters' => $distMeters,
            'distance_km' => $distMeters !== null ? round($distMeters / 1000, 2) : null
        ];
    } elseif ($totalStars === 1) {
        // Single wildcard: exactly 10 candidate permutations (0-9)
        $candidates = [];
        $xList = expandWildcardString($rdXRaw);
        $yList = expandWildcardString($rdYRaw);

        foreach ($xList as $cx) {
            foreach ($yList as $cy) {
                $cxMeters = (strlen($cx) === 4) ? (float)($cx . '00') : (float)$cx;
                $cyMeters = (strlen($cy) === 4) ? (float)($cy . '00') : (float)$cy;
                $cwgs = convertRdToWgs($cxMeters, $cyMeters);
                if ($cwgs['lat'] >= 50.5 && $cwgs['lat'] <= 53.7 && $cwgs['lon'] >= 3.0 && $cwgs['lon'] <= 7.3) {
                    $d = $latestKnown ? haversineDistance($latestKnown['lat'], $latestKnown['lon'], $cwgs['lat'], $cwgs['lon']) : null;
                    $candidates[] = [
                        'rd_x' => $cx,
                        'rd_y' => $cy,
                        'lat' => round($cwgs['lat'], 6),
                        'lon' => round($cwgs['lon'], 6),
                        'distance_meters' => $d
                    ];
                }
            }
        }

        $centerPoint = !empty($candidates) ? $candidates[0] : null;
        $distMeters = ($centerPoint && $latestKnown) ? haversineDistance($latestKnown['lat'], $latestKnown['lon'], $centerPoint['lat'], $centerPoint['lon']) : null;

        $geometry = [
            'kind' => 'cluster',
            'rd_x_pattern' => $rdXDisplay,
            'rd_y_pattern' => $rdYDisplay,
            'candidates' => $candidates,
            'total_candidates' => count($candidates),
            'center' => $centerPoint,
            'is_complete' => false,
            'distance_meters' => $distMeters,
            'distance_km' => $distMeters !== null ? round($distMeters / 1000, 2) : null
        ];
    } elseif ($hasWildcards) {
        // Bounding Box for 2+ wildcards or mixed patterns (e.g. 1*** 4***, 19** 44**, 19*4 45**)
        if (strlen($rdXRaw) === 4) {
            $minX = (float)(str_replace('*', '0', $rdXRaw) . '00');
            $maxX = (float)(str_replace('*', '9', $rdXRaw) . '99');
        } else {
            $minX = (float)str_replace('*', '0', $rdXRaw);
            $maxX = (float)str_replace('*', '9', $rdXRaw);
        }

        if (strlen($rdYRaw) === 4) {
            $minY = (float)(str_replace('*', '0', $rdYRaw) . '00');
            $maxY = (float)(str_replace('*', '9', $rdYRaw) . '99');
        } else {
            $minY = (float)str_replace('*', '0', $rdYRaw);
            $maxY = (float)str_replace('*', '9', $rdYRaw);
        }

        // Add 50m buffer if one axis has no wildcards so the polygon displays as a visible corridor
        $polyMinX = ($minX === $maxX) ? $minX - 50 : $minX;
        $polyMaxX = ($minX === $maxX) ? $maxX + 50 : $maxX;
        $polyMinY = ($minY === $maxY) ? $minY - 50 : $minY;
        $polyMaxY = ($minY === $maxY) ? $maxY + 50 : $maxY;

        $sw = convertRdToWgs($polyMinX, $polyMinY);
        $se = convertRdToWgs($polyMaxX, $polyMinY);
        $ne = convertRdToWgs($polyMaxX, $polyMaxY);
        $nw = convertRdToWgs($polyMinX, $polyMaxY);

        $centerX = ($minX + $maxX) / 2;
        $centerY = ($minY + $maxY) / 2;
        $centerWgs = convertRdToWgs($centerX, $centerY);

        $widthMeters = max(1, $maxX - $minX + 1);
        $heightMeters = max(1, $maxY - $minY + 1);
        $widthKm = round($widthMeters / 1000, 2);
        $heightKm = round($heightMeters / 1000, 2);
        $areaKm2 = round(($widthMeters / 1000) * ($heightMeters / 1000), 2);

        $distMeters = $latestKnown ? haversineDistance($latestKnown['lat'], $latestKnown['lon'], $centerWgs['lat'], $centerWgs['lon']) : null;

        if ($minX === $maxX) {
            $dimensions = "Lijn X = {$rdXDisplay} (lengte {$heightKm} km)";
        } elseif ($minY === $maxY) {
            $dimensions = "Lijn Y = {$rdYDisplay} (lengte {$widthKm} km)";
        } else {
            $dimensions = "{$widthKm} km × {$heightKm} km";
        }

        $note = '';
        if ($starCountX === 1 && $starCountY > 1) {
            $note = "X heeft 10 mogelijke hectometer-lijnen binnen dit zoekgebied.";
        } elseif ($starCountY === 1 && $starCountX > 1) {
            $note = "Y heeft 10 mogelijke hectometer-lijnen binnen dit zoekgebied.";
        }

        $geometry = [
            'kind' => 'bbox',
            'rd_x_pattern' => $rdXDisplay,
            'rd_y_pattern' => $rdYDisplay,
            'min_rd' => ['x' => $minX, 'y' => $minY],
            'max_rd' => ['x' => $maxX, 'y' => $maxY],
            'center' => [
                'lat' => round($centerWgs['lat'], 6),
                'lon' => round($centerWgs['lon'], 6)
            ],
            'polygon_coords' => [
                [$sw['lon'], $sw['lat']],
                [$se['lon'], $se['lat']],
                [$ne['lon'], $ne['lat']],
                [$nw['lon'], $nw['lat']],
                [$sw['lon'], $sw['lat']]
            ],
            'dimensions' => $dimensions,
            'area_km2' => $areaKm2,
            'note' => $note,
            'is_complete' => false,
            'distance_meters' => $distMeters,
            'distance_km' => $distMeters !== null ? round($distMeters / 1000, 2) : null
        ];
    } else {
        $geometry = [
            'kind' => 'invalid',
            'error' => 'Ongeldig coördinatenformaat.'
        ];
    }

    echo json_encode([
        'success' => true,
        'subarea' => $subarea,
        'geometry' => $geometry,
        'history_points' => $historyPoints,
        'latest_known' => $latestKnown
    ]);
}

/**
 * Expands a single wildcard string into its possible 0..9 variants.
 */
function expandWildcardString(string $pattern): array {
    $pos = strpos($pattern, '*');
    if ($pos === false) {
        return [$pattern];
    }

    $results = [];
    for ($i = 0; $i <= 9; $i++) {
        $replaced = substr_replace($pattern, (string)$i, $pos, 1);
        $results = array_merge($results, expandWildcardString($replaced));
    }
    return $results;
}
