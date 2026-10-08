<?php
declare(strict_types=1);

/**
 * includes/helpers.php
 *
 * Stateless utility functions for time formatting, coordinate math, security tokens, and theming.
 */

/**
 * Parse an incoming timestamp or datetime string into a valid Unix epoch timestamp.
 * Treats standard MySQL datetime strings (without timezone offset) as UTC.
 *
 * @param int|string|null $ts Unix timestamp or parseable datetime string.
 * @return int|null
 */
if (!function_exists('parseToTimestamp')) {
    function parseToTimestamp(int|string|null $ts): ?int {
        if ($ts === null || $ts === '' || $ts === 0 || $ts === '0' || $ts === '0000-00-00 00:00:00') {
            return null;
        }
        if (is_numeric($ts)) {
            return (int)$ts;
        }
        $str = trim((string)$ts);
        // If standard MySQL datetime without timezone offset (e.g. '2026-09-01 10:12:39'), treat as UTC
        if (preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}$/', $str)) {
            $str .= ' UTC';
        }
        $parsed = strtotime($str);
        return $parsed !== false ? $parsed : null;
    }
}

/**
 * Convert a timestamp into a relative human-readable Dutch string (e.g. 'Zojuist', '5 minuten geleden', 'Gisteren').
 *
 * @param int|string|null $ts Unix timestamp or parseable datetime string.
 * @return string
 */
if (!function_exists('time2str')) {
    function time2str(int|string|null $ts): string {
        $timestamp = parseToTimestamp($ts);
        if ($timestamp === null) {
            return 'Nooit';
        }

        $diff = time() - $timestamp;

        if ($diff >= 0) {
            if ($diff < 60) return 'Zojuist';
            if ($diff < 120) return '1 minuut geleden';
            if ($diff < 3600) return floor($diff / 60) . ' minuten geleden';
            if ($diff < 7200) return '1 uur geleden';
            if ($diff < 86400) return floor($diff / 3600) . ' uur geleden';

            $dayDiff = (int)floor($diff / 86400);
            if ($dayDiff === 1) return 'Gisteren';
            if ($dayDiff < 7) return $dayDiff . ' dagen geleden';
            if ($dayDiff < 31) {
                $weeks = (int)ceil($dayDiff / 7);
                return $weeks === 1 ? '1 week geleden' : $weeks . ' weken geleden';
            }
            if ($dayDiff < 60) return 'Vorige maand';

            $dt = (new DateTimeImmutable())->setTimestamp($timestamp)->setTimezone(new DateTimeZone('Europe/Amsterdam'));
            $months = [1 => 'jan', 2 => 'feb', 3 => 'mrt', 4 => 'apr', 5 => 'mei', 6 => 'jun', 7 => 'jul', 8 => 'aug', 9 => 'sep', 10 => 'okt', 11 => 'nov', 12 => 'dec'];
            $m = (int)$dt->format('n');
            return $dt->format('j') . ' ' . ($months[$m] ?? $dt->format('M')) . ' ' . $dt->format('Y');
        } else {
            $absDiff = abs($diff);
            if ($absDiff < 60) return 'Binnenkort';
            if ($absDiff < 120) return 'Over 1 minuut';
            if ($absDiff < 3600) return 'Over ' . floor($absDiff / 60) . ' minuten';
            if ($absDiff < 7200) return 'Over 1 uur';
            if ($absDiff < 86400) return 'Over ' . floor($absDiff / 3600) . ' uur';

            $dayDiff = (int)floor($absDiff / 86400);
            if ($dayDiff === 1) return 'Morgen';
            if ($dayDiff < 7) return 'Over ' . $dayDiff . ' dagen';
            if ($dayDiff < 31) {
                $weeks = (int)ceil($dayDiff / 7);
                return $weeks === 1 ? 'Over 1 week' : 'Over ' . $weeks . ' weken';
            }
            if ($dayDiff < 60) return 'Volgende maand';

            $dt = (new DateTimeImmutable())->setTimestamp($timestamp)->setTimezone(new DateTimeZone('Europe/Amsterdam'));
            return $dt->format('d-m-Y H:i');
        }
    }
}

/**
 * Format a timestamp or UTC datetime string into a Dutch Europe/Amsterdam datetime format.
 *
 * @param int|string|null $ts Unix timestamp or UTC datetime string
 * @param string $format DateTime format (default: 'd-m-Y H:i:s')
 * @return string Formatted date or 'Nooit'
 */
if (!function_exists('formatAmsterdamDateTime')) {
    function formatAmsterdamDateTime(int|string|null $ts, string $format = 'd-m-Y H:i:s'): string {
        $timestamp = parseToTimestamp($ts);
        if ($timestamp === null) {
            return 'Nooit';
        }
        $dt = (new DateTimeImmutable())->setTimestamp($timestamp)->setTimezone(new DateTimeZone('Europe/Amsterdam'));
        return $dt->format($format);
    }
}

/**
 * Backward compatibility alias for time2str.
 *
 * @param int|string|null $ts
 * @return string
 */
if (!function_exists('timeToString')) {
    function timeToString(int|string|null $ts): string {
        return time2str($ts);
    }
}

/**
 * Calculate the great-circle distance between two GPS coordinates using the Haversine formula in meters.
 *
 * @param float $latitudeFrom Starting latitude
 * @param float $longitudeFrom Starting longitude
 * @param float $latitudeTo Destination latitude
 * @param float $longitudeTo Destination longitude
 * @param float $earthRadius Earth radius in meters (default: 6,371,000 m)
 * @return float Distance in meters
 */
if (!function_exists('haversineDistance')) {
    function haversineDistance(
        float $latitudeFrom,
        float $longitudeFrom,
        float $latitudeTo,
        float $longitudeTo,
        float $earthRadius = 6371000.0
    ): float {
        $latFrom = deg2rad($latitudeFrom);
        $lonFrom = deg2rad($longitudeFrom);
        $latTo = deg2rad($latitudeTo);
        $lonTo = deg2rad($longitudeTo);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) + cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
        return (float)round($angle * $earthRadius);
    }
}

/**
 * Convert Dutch Rijksdriehoekstelsel (RD) coordinates to WGS84 GPS latitude/longitude.
 *
 * @param float $rdx RD X coordinate
 * @param float $rdy RD Y coordinate
 * @return array{lat: float, lon: float}
 */
if (!function_exists('convertRdToWgs')) {
    function convertRdToWgs(float $rdx, float $rdy): array {
        $dx = ($rdx - 155000) * pow(10, -5);
        $dy = ($rdy - 463000) * pow(10, -5);

        $somN = (3235.65389 * $dy) + (-32.58297 * pow($dx, 2)) + (-0.2475 * pow($dy, 2)) + (-0.84978 * pow($dx, 2) * $dy) + (-0.0655 * pow($dy, 3)) + (-0.01709 * pow($dx, 2) * pow($dy, 2)) + (-0.00738 * $dx) + (0.0053 * pow($dx, 4)) + (-0.00039 * pow($dx, 2) * pow($dy, 3)) + (0.00033 * pow($dx, 4) * $dy) + (-0.00012 * $dx * $dy);
        $somE = (5260.52916 * $dx) + (105.94684 * $dx * $dy) + (2.45656 * $dx * pow($dy, 2)) + (-0.81885 * pow($dx, 3)) + (0.05594 * $dx * pow($dy, 3)) + (-0.05607 * pow($dx, 3) * $dy) + (0.01199 * $dy) + (-0.00256 * pow($dx, 3) * pow($dy, 2)) + (0.00128 * $dx * pow($dy, 4)) + (0.00022 * pow($dy, 2)) + (-0.00022 * pow($dx, 2)) + (0.00026 * pow($dx, 5));

        return [
            'lat' => 52.15517 + ($somN / 3600),
            'lon' => 5.387206 + ($somE / 3600)
        ];
    }
}

/**
 * Convert WGS84 GPS latitude/longitude to Dutch Rijksdriehoekstelsel (RD) coordinates.
 *
 * @param float $lat GPS Latitude
 * @param float $lon GPS Longitude
 * @return array{rd_x: float, rd_y: float}
 */
if (!function_exists('convertWgsToRd')) {
    function convertWgsToRd(float $lat, float $lon): array {
        $phi_0 = 52.15517440;
        $lam_0 = 5.38720621;
        $dphi = 0.36 * ($lat - $phi_0);
        $dlam = 0.36 * ($lon - $lam_0);

        $somX = (190094.945 * $dlam)
              + (-11832.228 * $dphi * $dlam)
              + (-114.221 * pow($dphi, 2) * $dlam)
              + (-32.391 * pow($dlam, 3))
              + (-0.705 * $dphi)
              + (-2.340 * pow($dphi, 3) * $dlam)
              + (-0.608 * $dphi * pow($dlam, 3))
              + (0.148 * pow($dphi, 4) * $dlam);

        $somY = (309056.544 * $dphi)
              + (3638.893 * pow($dlam, 2))
              + (72.01 * $dphi * pow($dlam, 2))
              + (-157.976 * pow($dphi, 2))
              + (31.62 * pow($dphi, 3))
              + (-3.921 * pow($dphi, 4))
              + (-0.106 * pow($dlam, 4));

        return [
            'rd_x' => (float)round(155000 + $somX),
            'rd_y' => (float)round(463000 + $somY)
        ];
    }
}

/**
 * Dynamically computes coordinate start digits and prefill configurations
 * for each deelgebied based on current scout group locations in Groepen.
 *
 * @param mysqli $conn
 * @return array<string, array{prefill_x: string, placeholder_x: string, prefill_y: string, placeholder_y: string, is_fixed_x: bool}>
 */
if (!function_exists('getDeelgebiedenCoordinatePrefills')) {
    function getDeelgebiedenCoordinatePrefills(mysqli $conn): array {
        $stmt = $conn->prepare("SELECT deelgebied, lat, lon FROM Groepen WHERE lat IS NOT NULL AND lon IS NOT NULL");
        if (!$stmt) {
            return [];
        }
        $stmt->execute();
        $res = $stmt->get_result();

        $byArea = [];
        while ($row = $res->fetch_assoc()) {
            $area = trim((string)$row['deelgebied']);
            if ($area === '') continue;
            if (!isset($byArea[$area])) {
                $byArea[$area] = ['xs' => [], 'ys' => []];
            }
            $rd = convertWgsToRd((float)$row['lat'], (float)$row['lon']);
            $byArea[$area]['xs'][] = $rd['rd_x'];
            $byArea[$area]['ys'][] = $rd['rd_y'];
        }
        $stmt->close();

        $prefills = [];
        foreach ($byArea as $area => $coords) {
            $minX = min($coords['xs']);
            $maxX = max($coords['xs']);
            $minY = min($coords['ys']);
            $maxY = max($coords['ys']);

            // 100% 1 if all scout groups in this deelgebied have RD X in [100000, 199999]
            $is100X1 = ($minX >= 100000 && $maxX < 200000);
            // 100% 4 if all scout groups in this deelgebied have RD Y in [400000, 499999]
            $is100Y4 = ($minY >= 400000 && $maxY < 500000);

            $prefills[$area] = [
                'prefill_x' => $is100X1 ? '1***' : '',
                'placeholder_x' => $is100X1 ? '1***' : '****',
                'prefill_y' => $is100Y4 ? '4***' : '',
                'placeholder_y' => $is100Y4 ? '4***' : '4***',
                'is_fixed_x' => $is100X1
            ];
            $prefills[strtolower($area)] = $prefills[$area];
        }

        // Oscar hunts across all deelgebieden (Gelderland wide)
        $prefills['Oscar'] = [
            'prefill_x' => '',
            'placeholder_x' => '****',
            'prefill_y' => '4***',
            'placeholder_y' => '4***',
            'is_fixed_x' => false
        ];
        $prefills['oscar'] = $prefills['Oscar'];

        return $prefills;
    }
}

/**
 * Resolve client IP address taking reverse proxy headers (HTTP_X_FORWARDED_FOR) into account.
 *
 * @return string
 */
if (!function_exists('getClientIP')) {
    function getClientIP(): string {
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            return trim((string)$_SERVER['HTTP_CF_CONNECTING_IP']);
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipList = explode(',', (string)$_SERVER['HTTP_X_FORWARDED_FOR']);
            $firstIp = trim($ipList[0]);
            if (!empty($firstIp) && strcasecmp($firstIp, 'unknown') !== 0) {
                return $firstIp;
            }
        }
        if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            return trim((string)$_SERVER['HTTP_X_REAL_IP']);
        }
        return (string)($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
    }
}

/**
 * Backward compatibility alias for haversineDistance.
 *
 * @param float $lat1
 * @param float $lon1
 * @param float $lat2
 * @param float $lon2
 * @param float $r
 * @return float
 */
if (!function_exists('latlon_dist')) {
    function latlon_dist(float $lat1, float $lon1, float $lat2, float $lon2, float $r = 6371000.0): float {
        return haversineDistance($lat1, $lon1, $lat2, $lon2, $r);
    }
}

/**
 * Backward compatibility alias for convertRdToWgs.
 *
 * @param float $x
 * @param float $y
 * @return array{lat: float, lon: float}
 */
if (!function_exists('rdtowgs')) {
    function rdtowgs(float $x, float $y): array {
        return convertRdToWgs($x, $y);
    }
}

/**
 * Retrieve current Git commit hash and commit/build date.
 *
 * @return array{hash: string, date: string}
 */
if (!function_exists('getGitBuildInfo')) {
    function getGitBuildInfo(): array {
        $info = ['hash' => 'unknown', 'date' => 'unknown'];
        $repoDir = dirname(__DIR__);

        $gitOutput = @shell_exec('git -C ' . escapeshellarg($repoDir) . ' log -1 --format="%h|%cd" --date=format:"%d-%m-%Y %H:%M" 2>/dev/null');
        if ($gitOutput && str_contains((string)$gitOutput, '|')) {
            $parts = explode('|', trim((string)$gitOutput));
            if (!empty($parts[0])) {
                $info['hash'] = $parts[0];
                $info['date'] = $parts[1] ?? 'unknown';
                return $info;
            }
        }

        $gitBasePath = $repoDir . '/.git';
        $headFile = $gitBasePath . '/HEAD';

        if (!file_exists($headFile)) {
            return $info;
        }

        $headContents = trim((string)file_get_contents($headFile));
        if (str_starts_with($headContents, 'ref:')) {
            $refParts = explode(' ', $headContents);
            $ref = trim($refParts[1] ?? '');
            $refPath = $gitBasePath . '/' . $ref;

            if (file_exists($refPath)) {
                $hash = trim((string)file_get_contents($refPath));
                $info['hash'] = substr($hash, 0, 7);
                $info['date'] = date('d-m-Y H:i', filemtime($refPath));
            } elseif (file_exists($gitBasePath . '/packed-refs')) {
                $packed = (string)file_get_contents($gitBasePath . '/packed-refs');
                if (preg_match('/^([a-f0-9]+)\s+' . preg_quote($ref, '/') . '/m', $packed, $matches)) {
                    $info['hash'] = substr($matches[1], 0, 7);
                    $info['date'] = date('d-m-Y H:i', filemtime($gitBasePath . '/packed-refs'));
                }
            }
        } else {
            $info['hash'] = substr($headContents, 0, 7);
            $info['date'] = date('d-m-Y H:i', filemtime($headFile));
        }

        return $info;
    }
}

/**
 * Retrieve color and typography configuration for a given theme name.
 *
 * @param string $theme Theme identifier
 * @return array{bg: string, text: string, sidebar_bg: string, sidebar_text: string, sidebar_active: string, card_bg: string, card_border: string, primary: string, font: string}
 */
if (!function_exists('getThemeConfig')) {
    function getThemeConfig(string $theme): array {
        switch ($theme) {
            case 'dark':
                return [
                    'bg' => '#111827',
                    'text' => '#F3F4F6',
                    'sidebar_bg' => '#000000',
                    'sidebar_text' => '#D1D5DB',
                    'sidebar_active' => '#1F2937',
                    'card_bg' => '#1F2937',
                    'card_border' => '#374151',
                    'primary' => '#3B82F6',
                    'font' => "'Inter', sans-serif"
                ];
            case 'rose-gold':
                return [
                    'bg' => '#FFF5F7',
                    'text' => '#702459',
                    'sidebar_bg' => '#FFE4E6',
                    'sidebar_text' => '#831843',
                    'sidebar_active' => '#FCC2D7',
                    'card_bg' => '#FFFFFF',
                    'card_border' => '#FBCFE8',
                    'primary' => '#D53F8C',
                    'font' => "'Quicksand', sans-serif"
                ];
            case 'cyber':
                return [
                    'bg' => '#000000',
                    'text' => '#22C55E',
                    'sidebar_bg' => '#0A0A0A',
                    'sidebar_text' => '#16A34A',
                    'sidebar_active' => '#14532D',
                    'card_bg' => '#050505',
                    'card_border' => '#22C55E',
                    'primary' => '#4ADE80',
                    'font' => "'JetBrains Mono', monospace"
                ];
            case 'nature':
                return [
                    'bg' => '#F0FDF4',
                    'text' => '#14532D',
                    'sidebar_bg' => '#14532D',
                    'sidebar_text' => '#DCFCE7',
                    'sidebar_active' => '#166534',
                    'card_bg' => '#FFFFFF',
                    'card_border' => '#BBF7D0',
                    'primary' => '#16A34A',
                    'font' => "'Merriweather', serif"
                ];
            case 'coral':
                return [
                    'bg' => '#FFF7ED',
                    'text' => '#7C2D12',
                    'sidebar_bg' => '#9A3412',
                    'sidebar_text' => '#FFEDD5',
                    'sidebar_active' => '#7C2D12',
                    'card_bg' => '#FFFFFF',
                    'card_border' => '#FED7AA',
                    'primary' => '#EA580C',
                    'font' => "'Outfit', sans-serif"
                ];
            case 'light':
            default:
                return [
                    'bg' => '#F3F4F6',
                    'text' => '#111827',
                    'sidebar_bg' => '#1F2937',
                    'sidebar_text' => '#E5E7EB',
                    'sidebar_active' => '#374151',
                    'card_bg' => '#FFFFFF',
                    'card_border' => '#E5E7EB',
                    'primary' => '#3B82F6',
                    'font' => "'Inter', sans-serif"
                ];
        }
    }
}

/**
 * Generate a cryptographically secure random hexadecimal token.
 *
 * @param int $length Byte length of the token (output hex will be length * 2)
 * @return string
 */
if (!function_exists('generateToken')) {
    function generateToken(int $length = 32): string {
        return bin2hex(random_bytes($length));
    }
}

/**
 * Format a number of seconds into human-readable Dutch duration (e.g. '2u 15m' or '45s').
 *
 * @param int $seconds
 * @return string
 */
if (!function_exists('formatSeconds')) {
    function formatSeconds(int $seconds): string {
        if ($seconds < 60) {
            return $seconds . 's';
        }
        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;
        if ($minutes < 60) {
            return $minutes . 'm ' . $remainingSeconds . 's';
        }
        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;
        return $hours . 'u ' . $remainingMinutes . 'm';
    }
}

/**
 * Format duration in seconds into HH:MM:SS format (e.g. '01:23:45').
 *
 * @param int $seconds
 * @return string
 */
if (!function_exists('formatDurationHms')) {
    function formatDurationHms(int $seconds): string {
        $h = floor($seconds / 3600);
        $m = floor(($seconds % 3600) / 60);
        $s = $seconds % 60;
        return sprintf('%02d:%02d:%02d', $h, $m, $s);
    }
}

/**
 * Backward compatibility alias for formatDurationHms.
 *
 * @param int $seconds
 * @return string
 */
if (!function_exists('format_seconds')) {
    function format_seconds(int $seconds): string {
        return formatDurationHms($seconds);
    }
}
/**
 * Append entry to global execution log and output string for cron execution tracking.
 *
 * @param string $entry
 * @return void
 */
if (!function_exists('log2DB')) {
    function log2DB(string $entry): void {
        global $output;
        echo $entry . "\n";
        $output = ($output ?? '') . $entry . "\n";
    }
}
/**
 * Record a cron execution entry into the Cronlogs database table.
 *
 * @param mysqli $conn Active database connection.
 * @param string $name Name of the cron job.
 * @param float $startTime Start timestamp from microtime(true).
 * @param string $output Execution output/log text.
 * @param int $statusCode HTTP or execution status code (default 200).
 * @return void
 */
if (!function_exists('recordCronLog')) {
    function recordCronLog(mysqli $conn, string $name, float $startTime, string $output, int $statusCode = 200): void {
        $duration = (int)round((microtime(true) - $startTime) * 1000);
        $dateTime = date('Y-m-d H:i:s');
        $stmt = $conn->prepare('INSERT INTO Cronlogs (name, exec_time, exec_length, exec_stat, exec_output) VALUES (?, ?, ?, ?, ?)');
        if ($stmt) {
            $stmt->bind_param('ssiis', $name, $dateTime, $duration, $statusCode, $output);
            $stmt->execute();
            $stmt->close();
        }
    }
}

/**
 * Format bytes or bits into a human-readable string with custom 800-unit threshold.
 *
 * Threshold rule: value stays in current unit until hitting 800, then shifts to next unit:
 *   799 KB stays 799 KB
 *   900 KB -> 0.9 MB
 *   1,000,000 KB -> 1 GB
 *
 * @param float|int $bytes Number of bytes.
 * @param bool $asBits If true, converts bytes to bits (x8) and outputs (b, Kb, Mb, Gb, Tb).
 * @param int $precision Number of decimals (default 1).
 * @param bool $perSecond If true, appends '/s' (e.g. 'Kb/s', 'MB/s').
 * @return string
 */
if (!function_exists('bitbyte2string')) {
    function bitbyte2string(float|int $bytes, bool $asBits = false, int $precision = 1, bool $perSecond = false): string {
        $units = $asBits
            ? ['b', 'Kb', 'Mb', 'Gb', 'Tb']
            : ['B', 'KB', 'MB', 'GB', 'TB'];

        $val = $asBits ? ($bytes * 8.0) : (float)$bytes;

        if ($val <= 0) {
            return '0 ' . $units[0] . ($perSecond ? '/s' : '');
        }

        $idx = 0;
        $maxIdx = count($units) - 1;

        while ($val >= 800.0 && $idx < $maxIdx) {
            $val /= 1024.0;
            $idx++;
        }

        $rounded = round($val, $precision);
        $numStr = (fmod($rounded, 1.0) == 0.0) ? (string)(int)$rounded : number_format($rounded, $precision, '.', '');

        return $numStr . ' ' . $units[$idx] . ($perSecond ? '/s' : '');
    }
}

/**
 * Render HTML for a fox badge with high-contrast hunt immunity styling (Option 1: Modern Split Pill).
 *
 * @param string $fox_name e.g. "Alpha"
 * @param array $vos_info Array containing 'Kleur', 'duratie', and optional 'immune_until'
 * @param bool $is_mobile True if rendered for mobile grid
 * @return string HTML output
 */
if (!function_exists('renderFoxBadge')) {
    function renderFoxBadge(string $fox_name, array $vos_info, bool $is_mobile = false): string {
        $color = $vos_info['Kleur'] ?? 'gray';
        $duratie = $vos_info['duratie'] ?? '';
        $initial = substr($fox_name, 0, 1);
        $immune_until = $vos_info['immune_until'] ?? null;
        $is_immune = ($immune_until !== null && $immune_until > time());

        $tw_color = match ($color) {
            'red' => 'bg-red-500 text-white',
            'orange' => 'bg-orange-500 text-white',
            'green' => 'bg-green-500 text-white',
            default => 'bg-gray-200 text-gray-700',
        };

        $base_class = $is_mobile ? 'rounded py-2 px-3 flex items-center justify-center font-bold text-sm shadow-sm' : 'px-2 py-1 rounded text-xs font-bold flex items-center shadow-sm';

        if (!$is_immune) {
            return '<div class="' . $base_class . ' ' . $tw_color . ' whitespace-nowrap">'
                . '<span class="' . ($is_mobile ? 'mr-2' : 'mr-1') . '">' . htmlspecialchars($initial) . '</span>'
                . '<span>' . htmlspecialchars($duratie) . '</span>'
                . '</div>';
        }

        $diff = $immune_until - time();
        $m = floor($diff / 60);
        $s = $diff % 60;
        $timer_text = ($diff > 0) ? "{$m}m {$s}s" : '0m 0s';

        $pad = $is_mobile ? 'py-1.5 px-2.5 gap-2 text-sm' : 'py-0.5 px-1.5 gap-1.5 text-xs';
        $pod_pad = $is_mobile ? 'px-2 py-0.5 text-xs' : 'px-1.5 py-0.5 text-[11px]';
        $lock_size = $is_mobile ? 'text-[11px]' : 'text-[9px]';

        return '<div class="fox-badge-container rounded-md font-bold flex items-center justify-center shadow-sm ' . $tw_color . ' whitespace-nowrap ' . $pad . ' border border-black/10" '
            . 'data-fox="' . htmlspecialchars($fox_name) . '" '
            . 'data-color="' . htmlspecialchars($color) . '" '
            . 'data-initial="' . htmlspecialchars($initial) . '" '
            . 'data-duratie="' . htmlspecialchars($duratie) . '" '
            . 'data-until="' . $immune_until . '" '
            . 'data-mobile="' . ($is_mobile ? '1' : '0') . '">'
            . '<span class="font-extrabold tracking-wide select-none drop-shadow-sm">' . htmlspecialchars($initial) . '</span>'
            . '<div class="bg-slate-950/90 text-amber-300 ring-1 ring-amber-400/50 ' . $pod_pad . ' rounded font-mono font-bold flex items-center shadow-inner tracking-tight">'
            . '<i class="fas fa-lock ' . $lock_size . ' text-amber-400 mr-1.5 opacity-90"></i>'
            . '<span class="immune-countdown" data-until="' . $immune_until . '" data-duratie="' . htmlspecialchars($duratie) . '">' . $timer_text . '</span>'
            . '</div>'
            . '</div>';
    }
}

/**
 * Return FontAwesome icon class for vehicle/hunter type.
 */
if (!function_exists('getVehicleIcon')) {
    function getVehicleIcon(?string $type): string {
        switch ($type) {
            case 'motorcycle': return 'fa-motorcycle';
            case 'scooter': return 'fa-motorcycle';
            case 'bike': return 'fa-bicycle';
            case 'foot': return 'fa-walking';
            case 'other': return 'fa-helicopter';
            default: return 'fa-car';
        }
    }
}

/**
 * Return Dutch label for vehicle/hunter type.
 */
if (!function_exists('getVehicleLabel')) {
    function getVehicleLabel(?string $type): string {
        switch ($type) {
            case 'motorcycle': return 'Motor';
            case 'scooter': return 'Scooter';
            case 'bike': return 'Fiets';
            case 'foot': return 'Lopend';
            case 'other': return 'Overig';
            default: return 'Auto';
        }
    }
}

/**
 * Return hex color code for RDW color name or custom hex string.
 */
if (!function_exists('getRdwColorHex')) {
    function getRdwColorHex(?string $colorName): string {
        if (empty($colorName)) return '#64748b';
        $c = strtoupper(trim($colorName));
        if (preg_match('/^#[0-9A-F]{6}$/i', $c)) {
            return $c;
        }
        switch ($c) {
            case 'ZWART': return '#18181b';
            case 'WIT': return '#cbd5e1';
            case 'GRIJS': return '#64748b';
            case 'ZILVER': return '#94a3b8';
            case 'BLAUW': return '#2563eb';
            case 'ROOD': return '#dc2626';
            case 'GROEN': return '#16a34a';
            case 'GEEL': return '#eab308';
            case 'ORANJE': return '#ea580c';
            case 'BRUIN': return '#78350f';
            case 'PAARS': return '#9333ea';
            case 'ROSE':
            case 'ROZE': return '#ec4899';
            case 'BEIGE': return '#d4b896';
            case 'CREME': return '#fde047';
            case 'GOUD': return '#d97706';
            case 'DIVERSEN':
            case 'MEERKLEURIG': return '#6366f1';
            default: return '#64748b';
        }
    }
}



