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
    $html = '<div class="wb-user cursor-move m-1 inline-block flex-shrink-0" draggable="true" ondragstart="drag(event)" id="user_' . $user['id'] . '" data-userid="' . $user['id'] . '" title="' . $name . '">';
    if (!empty($user['profile_picture'])) {
        $html .= '<img class="h-10 w-10 rounded-full ring-2 ring-white object-cover bg-white pointer-events-none" src="profile_image.php?hash=' . urlencode((string)$user['profile_picture']) . '&res=low" alt="' . $name . '"/>';
    } else {
        $html .= '<div class="flex items-center justify-center h-10 w-10 rounded-full ring-2 ring-white bg-blue-500 text-white font-bold text-xs pointer-events-none">' . $short . '</div>';
    }
    $html .= '<div class="user-name text-[10px] text-center truncate w-10 mt-1 opacity-80 text-gray-800">' . htmlspecialchars(ucfirst((string)$user['voornaam']), ENT_QUOTES) . '</div>';
    $html .= '</div>';
    return $html;
}

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
    $html = '<div class="car-draggable compact-car bg-yellow-400 text-black text-[10px] font-bold px-2 py-0.5 rounded border border-black m-1 inline-block shadow cursor-move self-start" draggable="true" ondragstart="dragCar(event)" id="car_' . $safePlate . '" data-kenteken="' . $safePlate . '">';
    $html .= strtoupper($safePlate);
    $html .= '</div>';
    return $html;
}

/**
 * Render full tactile vehicle pill with driver and passenger drop zones.
 *
 * @param string $kenteken
 * @param array<string, mixed> $car
 * @param array<int, array<string, mixed>> $users
 * @return string
 */
function renderCar(string $kenteken, array $car, array $users): string {
    $safePlate = htmlspecialchars($kenteken, ENT_QUOTES);
    $html = '<div class="car-draggable bg-gray-800 rounded-[40px] p-2 m-2 flex flex-col items-center relative shadow-lg overflow-hidden" style="width: 130px; min-height: 200px;" draggable="true" ondragstart="dragCar(event)" id="car_' . $safePlate . '" data-kenteken="' . $safePlate . '">';
    $html .= '<div class="absolute top-1 left-3 w-4 h-3 bg-yellow-400 rounded-full shadow-[0_0_10px_rgba(250,204,21,0.8)]"></div>';
    $html .= '<div class="absolute top-1 right-3 w-4 h-3 bg-yellow-400 rounded-full shadow-[0_0_10px_rgba(250,204,21,0.8)]"></div>';
    $html .= '<div class="w-10/12 h-6 bg-sky-200/40 rounded-t-xl mt-3 mb-2 border-b border-gray-600"></div>';
    $html .= '<div class="relative w-full mt-1 flex-1 flex flex-col items-center">';
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
    $html .= '<div class="w-10/12 h-4 bg-sky-200/30 rounded-b-lg mb-4 border-t border-gray-600 mt-2"></div>';
    $html .= '<div class="absolute bottom-2 left-1/2 -translate-x-1/2 bg-yellow-400 text-black text-[10px] font-bold px-2 py-0.5 rounded border border-black">' . strtoupper($safePlate) . '</div>';
    $html .= '<div class="absolute bottom-1 left-3 w-5 h-2 bg-red-600 rounded-full shadow-[0_0_10px_rgba(220,38,38,0.8)]"></div>';
    $html .= '<div class="absolute bottom-1 right-3 w-5 h-2 bg-red-600 rounded-full shadow-[0_0_10px_rgba(220,38,38,0.8)]"></div>';
    $html .= '</div>';

    return $html;
}
