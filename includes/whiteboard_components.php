<?php
declare(strict_types=1);

/**
 * includes/whiteboard_components.php
 *
 * View component renderers for tactile whiteboard users and vehicles.
 */

/**
 * Render user avatar draggable badge for whiteboard.
 *
 * @param array<string, mixed> $user
 * @return string
 */
function renderUser(array $user): string {
    $name = htmlspecialchars(ucfirst((string)$user['voornaam']) . ' ' . ucfirst((string)$user['achternaam']), ENT_QUOTES);
    $short = strtoupper(substr((string)$user['voornaam'], 0, 1));
    $html = '<div class="wb-user cursor-move inline-flex flex-col items-center justify-center flex-shrink-0" draggable="true" ondragstart="drag(event)" id="user_' . $user['id'] . '" data-userid="' . $user['id'] . '" title="' . $name . '">';
    if (!empty($user['profile_picture'])) {
        $html .= '<img class="h-10 w-10 rounded-full ring-2 ring-white object-cover bg-white pointer-events-none mx-auto block flex-shrink-0" src="profile_image.php?hash=' . urlencode((string)$user['profile_picture']) . '&res=low" alt="' . $name . '"/>';
    } else {
        $html .= '<div class="flex items-center justify-center h-10 w-10 rounded-full ring-2 ring-white bg-blue-500 text-white font-bold text-xs pointer-events-none mx-auto flex-shrink-0">' . $short . '</div>';
    }
    $html .= '<div class="user-name text-[10px] text-center truncate max-w-[48px] mt-1 opacity-80 text-gray-800">' . htmlspecialchars(ucfirst((string)$user['voornaam']), ENT_QUOTES) . '</div>';
    $html .= '</div>';
    return $html;
}

require_once(__DIR__ . '/helpers.php');

/**
 * Render compact inactive vehicle badge.
 *
 * @param string $kenteken
 * @param array<string, mixed> $car
 * @param array<int, array<string, mixed>> $users
 * @return string
 */
function renderCompactCar(string $kenteken, array $car, array $users): string {
    $safePlate = htmlspecialchars($kenteken, ENT_QUOTES);
    $iconColor = getRdwColorHex(isset($car['rdw_kleur']) ? (string)$car['rdw_kleur'] : null);
    $colorTitle = !empty($car['rdw_kleur']) ? "RDW Kleur: " . ucfirst(strtolower((string)$car['rdw_kleur'])) : 'Auto';
    $seatsSuffix = !empty($car['aantal_zitplaatsen']) ? ' - ' . (int)$car['aantal_zitplaatsen'] . ' zitplaatsen' : '';

    $whiteDropShadow = (strtoupper((string)($car['rdw_kleur'] ?? '')) === 'WIT') ? ' filter: drop-shadow(0 0 1px #475569);' : '';
    $iconStyle = 'style="color: ' . $iconColor . '; font-size: 8.5px;' . $whiteDropShadow . '"';

    $tooltip = htmlspecialchars('Auto ' . $safePlate . ' (' . $colorTitle . $seatsSuffix . ')', ENT_QUOTES);

    $maxOccupancy = !empty($car['aantal_zitplaatsen']) ? (int)$car['aantal_zitplaatsen'] : null;
    $maxOccupancyAttr = $maxOccupancy !== null ? ' data-max-occupancy="' . $maxOccupancy . '"' : '';

    $html = '<div class="car-draggable compact-car bg-yellow-400 text-black text-[10px] font-bold px-2 py-0.5 rounded border border-black m-1 inline-flex items-center gap-1.5 shadow cursor-move self-start select-none" draggable="true" ondragstart="dragCar(event)" id="car_' . $safePlate . '" data-kenteken="' . $safePlate . '"' . $maxOccupancyAttr . ' title="' . $tooltip . '">';
    $html .= '<span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-white shadow-xs border border-slate-300 flex-shrink-0">';
    $html .= '<i class="fas fa-car" ' . $iconStyle . '></i>';
    $html .= '</span>';
    $html .= '<span>' . strtoupper($safePlate) . '</span>';
    $html .= '</div>';
    return $html;
}

/**
 * Render top-down CSS car view.
 *
 * @param string $kenteken
 * @param array<string, mixed> $car
 * @param array<int, array<string, mixed>> $users
 * @return string
 */
function renderCar(string $kenteken, array $car, array $users): string {
    $safePlate = htmlspecialchars($kenteken, ENT_QUOTES);
    $colorHex = getRdwColorHex(isset($car['rdw_kleur']) ? (string)$car['rdw_kleur'] : null);
    $maxOccupancy = !empty($car['aantal_zitplaatsen']) ? (int)$car['aantal_zitplaatsen'] : null;
    $maxOccupancyAttr = $maxOccupancy !== null ? ' data-max-occupancy="' . $maxOccupancy . '"' : '';
    $maxSeatsTitle = $maxOccupancy !== null ? ' (' . $maxOccupancy . ' zitplaatsen)' : '';

    $html = '<div class="car-draggable vehicle-card vehicle-car rounded-[38px] p-2 m-2 flex flex-col items-center relative shadow-xl overflow-hidden select-none" style="width: 132px; min-height: 205px; background-color: ' . $colorHex . '; border: 2px solid rgba(0,0,0,0.3); box-shadow: 0 8px 24px rgba(0,0,0,0.35);" draggable="true" ondragstart="dragCar(event)" id="car_' . $safePlate . '" data-kenteken="' . $safePlate . '"' . $maxOccupancyAttr . ' title="Auto ' . $safePlate . $maxSeatsTitle . '">';
    
    // Side Mirrors
    $html .= '<div class="absolute top-9 -left-1.5 w-2.5 h-4 rounded-l-full shadow-md border-l border-y border-black/30 pointer-events-none" style="background-color: ' . $colorHex . ';"></div>';
    $html .= '<div class="absolute top-9 -right-1.5 w-2.5 h-4 rounded-r-full shadow-md border-r border-y border-black/30 pointer-events-none" style="background-color: ' . $colorHex . ';"></div>';
    
    // Front Headlights
    $html .= '<div class="absolute top-1.5 left-3.5 w-4 h-2.5 bg-yellow-300 rounded-full shadow-[0_0_10px_rgba(250,204,21,0.95)] border border-yellow-200 pointer-events-none"></div>';
    $html .= '<div class="absolute top-1.5 right-3.5 w-4 h-2.5 bg-yellow-300 rounded-full shadow-[0_0_10px_rgba(250,204,21,0.95)] border border-yellow-200 pointer-events-none"></div>';
    
    // Curved Front Windshield
    $html .= '<div class="w-10/12 h-6 bg-sky-200/40 rounded-t-xl mt-3 mb-1 border-b border-gray-600/60 shadow-inner pointer-events-none"></div>';
    
    // Cabin Interior
    $html .= '<div class="relative w-full flex-1 flex flex-col items-center bg-slate-950/85 rounded-xl p-1 shadow-inner border border-black/30">';
    $html .= '<div class="wb-zone passenger-zone w-full" id="zone_auto_pass_' . $safePlate . '" data-type="auto" data-ref="' . $safePlate . '" data-driver="0" ondrop="drop(event)" ondragover="allowDrop(event)">';
    $html .= '<div class="wb-zone driver-seat" style="grid-column: 1; grid-row: 1; min-height: auto;" id="zone_auto_driver_' . $safePlate . '" data-type="auto" data-ref="' . $safePlate . '" data-driver="1" ondrop="dropDriver(event)" ondragover="allowDrop(event)">';

    if (!empty($car['bestuurder']) && isset($users[$car['bestuurder']])) {
        $html .= renderUser($users[$car['bestuurder']]);
    } else {
        $html .= '<div class="steering-wheel-placeholder"><i class="fas fa-steering-wheel text-gray-400 text-xs opacity-50"></i></div>';
    }
    $html .= '</div>';

    if (!empty($car['bijrijders']) && is_array($car['bijrijders'])) {
        foreach ($car['bijrijders'] as $uid) {
            if (isset($users[$uid])) {
                $html .= renderUser($users[$uid]);
            }
        }
    }
    $html .= '</div>';
    $html .= '</div>';
    
    // Rear Windshield
    $html .= '<div class="w-10/12 h-4 bg-sky-200/30 rounded-b-lg mb-3 border-t border-gray-600/60 mt-1 pointer-events-none"></div>';
    
    // Taillights & Plate
    $html .= '<div class="absolute bottom-1.5 left-3.5 w-4.5 h-2 bg-red-600 rounded-full shadow-[0_0_8px_rgba(220,38,38,0.9)] pointer-events-none"></div>';
    $html .= '<div class="absolute bottom-1.5 right-3.5 w-4.5 h-2 bg-red-600 rounded-full shadow-[0_0_8px_rgba(220,38,38,0.9)] pointer-events-none"></div>';
    $html .= '<div class="absolute bottom-2 left-1/2 -translate-x-1/2 bg-yellow-400 text-black text-[10px] font-bold px-2 py-0.5 rounded border border-black shadow pointer-events-none">' . strtoupper($safePlate) . '</div>';
    $html .= '</div>';

    return $html;
}
