<?php
declare(strict_types=1);

/**
 * includes/helpers.php
 *
 * Stateless utility functions for time formatting, coordinate math, security tokens, and theming.
 */

/**
 * Convert a timestamp into a relative human-readable English string (e.g. 'just now', '5 minutes ago', 'Yesterday').
 *
 * @param int|string $ts Unix timestamp or parseable datetime string.
 * @return string
 */
if (!function_exists('time2str')) {
    function time2str(int|string|null $ts): string {
        if ($ts === null || $ts === '' || $ts === 0 || $ts === '0') {
            return 'Nooit';
        }
        if (!is_numeric($ts)) {
            $ts = strtotime((string)$ts);
        } else {
            $ts = (int)$ts;
        }

        $diff = time() - $ts;
        if ($diff === 0) {
            return 'now';
        } elseif ($diff > 0) {
            $dayDiff = (int)floor($diff / 86400);
            if ($dayDiff === 0) {
                if ($diff < 60) return 'just now';
                if ($diff < 120) return '1 minute ago';
                if ($diff < 3600) return floor($diff / 60) . ' minutes ago';
                if ($diff < 7200) return '1 hour ago';
                if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
            }
            if ($dayDiff === 1) return 'Yesterday';
            if ($dayDiff < 7) return $dayDiff . ' days ago';
            if ($dayDiff < 31) return ceil($dayDiff / 7) . ' weeks ago';
            if ($dayDiff < 60) return 'last month';
            return date('F Y', $ts);
        } else {
            $diff = abs($diff);
            $dayDiff = (int)floor($diff / 86400);
            if ($dayDiff === 0) {
                if ($diff < 120) return 'in a minute';
                if ($diff < 3600) return 'in ' . floor($diff / 60) . ' minutes';
                if ($diff < 7200) return 'in an hour';
                if ($diff < 86400) return 'in ' . floor($diff / 3600) . ' hours';
            }
            if ($dayDiff === 1) return 'Tomorrow';
            if ($dayDiff < 4) return date('l', $ts);
            if ($dayDiff < 7 + (7 - (int)date('w'))) return 'next week';
            if (ceil($dayDiff / 7) < 4) return 'in ' . ceil($dayDiff / 7) . ' weeks';
            if ((int)date('n', $ts) === (int)date('n') + 1) return 'next month';
            return date('F Y', $ts);
        }
    }
}

/**
 * Backward compatibility alias for time2str.
 *
 * @param int|string $ts
 * @return string
 */
if (!function_exists('timeToString')) {
    function timeToString(int|string|null $ts): string {
        return time2str($ts);
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
 * Resolve client IP address taking reverse proxy headers (HTTP_X_FORWARDED_FOR) into account.
 *
 * @return string
 */
if (!function_exists('getClientIP')) {
    function getClientIP(): string {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipList = explode(',', (string)$_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ipList[0]);
        }
        return (string)($_SERVER['REMOTE_ADDR'] ?? '');
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
