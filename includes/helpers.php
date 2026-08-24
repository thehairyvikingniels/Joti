<?php
// Utility functions for converting timestamps, calculating coordinates, and git build info.

/**
 * Convert timestamp or datetime string to human-readable Dutch relative time.
 *
 * @param string|int|null $ts
 * @return string
 */
if (!function_exists('time2str')) {
    function time2str($ts) {
        if (empty($ts)) {
            return 'Nooit';
        }
        // Parse UTC timestamps correctly
        if (!ctype_digit($ts)) {
            $dt = DateTime::createFromFormat('Y-m-d H:i:s', $ts, new DateTimeZone('UTC'));
            if ($dt) {
                $ts = $dt->getTimestamp();
            } else {
                $ts = strtotime($ts);
            }
        }

        $diff = time() - $ts;
        if ($diff == 0) {
            return 'Zojuist';
        } elseif ($diff > 0) {
            $day_diff = floor($diff / 86400);
            if ($day_diff == 0) {
                if ($diff < 60) return 'Zojuist';
                if ($diff < 120) return '1 min geleden';
                if ($diff < 3600) return floor($diff / 60) . ' min geleden';
                if ($diff < 7200) return '1 uur geleden';
                if ($diff < 86400) return floor($diff / 3600) . ' uur geleden';
            }
            if ($day_diff == 1) { return 'Gisteren'; }
            if ($day_diff < 7) { return $day_diff . ' dagen geleden'; }
            if ($day_diff < 8) { return ceil($day_diff / 7) . ' week geleden'; }
            if ($day_diff < 31) { return ceil($day_diff / 7) . ' weken geleden'; }
            if ($day_diff < 60) { return 'Vorige maand'; }
            return date('d-m-Y H:i', $ts);
        } else {
            $diff = abs($diff);
            $day_diff = floor($diff / 86400);
            if ($day_diff == 0) {
                if ($diff < 120) { return 'Over een min'; }
                if ($diff < 3600) { return 'Over ' . floor($diff / 60) . ' min'; }
                if ($diff < 7200) { return 'Over een uur'; }
                if ($diff < 86400) { return 'Over ' . floor($diff / 3600) . ' uur'; }
            }
            if ($day_diff == 1) { return 'Morgen'; }
            if ($day_diff < 4) { return date('l', $ts); }
            if ($day_diff < 7 + (7 - date('w'))) { return 'Volgende week'; }
            if (ceil($day_diff / 7) < 4) { return 'Over ' . ceil($day_diff / 7) . ' weken'; }
            if (date('n', $ts) == date('n') + 1) { return 'Volgende maand'; }
            return date('d-m-Y H:i', $ts);
        }
    }
}

if (!function_exists('timeToString')) {
    function timeToString($ts) {
        return time2str($ts);
    }
}

/**
 * Calculates the great-circle distance between two points, with the Haversine formula.
 */
if (!function_exists('haversineDistance')) {
    function haversineDistance($latitudeFrom, $longitudeFrom, $latitudeTo, $longitudeTo, $earthRadius = 6371000) {
        $latFrom = deg2rad($latitudeFrom);
        $lonFrom = deg2rad($longitudeFrom);
        $latTo = deg2rad($latitudeTo);
        $lonTo = deg2rad($longitudeTo);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) + cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
        return round($angle * $earthRadius);
    }
}

/**
 * RD coordinaten omzetten naar WGS84 coordinaten
 */
if (!function_exists('convertRdToWgs')) {
    function convertRdToWgs($rdx, $rdy){
        $dx = ($rdx - 155000) * pow(10,-5);
        $dy = ($rdy - 463000) * pow(10,-5);
        
        $somN = (3235.65389 * $dy) + (-32.58297 * pow($dx,2)) + (-0.2475 * pow($dy,2)) + (-0.84978 * pow($dx,2) * $dy) + (-0.0655 * pow($dy,3)) + (-0.01709 * pow($dx,2) * pow($dy,2)) + (-0.00738 * $dx) + (0.0053 * pow($dx,4)) + (-0.00039 * pow($dx,2) * pow($dy,3)) + (0.00033 * pow($dx,4) * $dy) + (-0.00012 * $dx * $dy);
        $somE = (5260.52916 * $dx) + (105.94684 * $dx * $dy) + (2.45656 * $dx * pow($dy,2)) + (-0.81885 * pow($dx,3)) + (0.05594 * $dx * pow($dy,3)) + (-0.05607 * pow($dx,3) * $dy) + (0.01199 * $dy) + (-0.00256 * pow($dx,3) * pow($dy,2)) + (0.00128 * $dx * pow($dy,4)) + (0.00022 * pow($dy,2)) + (-0.00022 * pow($dx,2)) + (0.00026 * pow($dx,5));
            
        $a["lat"] = 52.15517 + ($somN / 3600);
        $a["lon"] = 5.387206 + ($somE / 3600);
        return($a);
    }
}

/**
 * Helper function to resolve client IP considering reverse proxy headers
 */
if (!function_exists('getClientIP')) {
    function getClientIP() {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ipList[0]);
        }
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }
}

// Backward compatibility aliases
if (!function_exists('latlon_dist')) {
    function latlon_dist($lat1, $lon1, $lat2, $lon2, $r = 6371000) {
        return haversineDistance($lat1, $lon1, $lat2, $lon2, $r);
    }
}

if (!function_exists('rdtowgs')) {
    function rdtowgs($x, $y) {
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

        // 1. Try via Git CLI command
        $gitOutput = @shell_exec('git -C ' . escapeshellarg($repoDir) . ' log -1 --format="%h|%cd" --date=format:"%d-%m-%Y %H:%M" 2>/dev/null');
        if ($gitOutput && str_contains($gitOutput, '|')) {
            $parts = explode('|', trim($gitOutput));
            if (!empty($parts[0])) {
                $info['hash'] = $parts[0];
                $info['date'] = $parts[1] ?? 'unknown';
                return $info;
            }
        }

        // 2. Direct filesystem fallback (.git/HEAD and loose/packed refs)
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
            // Detached HEAD
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
        $config = [];
        
        switch ($theme) {
            case 'dark':
                $config['bg'] = '#111827';
                $config['text'] = '#F3F4F6';
                $config['sidebar_bg'] = '#000000';
                $config['sidebar_text'] = '#D1D5DB';
                $config['sidebar_active'] = '#1F2937';
                $config['card_bg'] = '#1F2937';
                $config['card_border'] = '#374151';
                $config['primary'] = '#3B82F6';
                $config['font'] = "'Inter', sans-serif";
                break;
            case 'rose-gold':
                $config['bg'] = '#FFF5F7';
                $config['text'] = '#702459';
                $config['sidebar_bg'] = '#FFE4E6';
                $config['sidebar_text'] = '#831843';
                $config['sidebar_active'] = '#FCC2D7';
                $config['card_bg'] = '#FFFFFF';
                $config['card_border'] = '#FBCFE8';
                $config['primary'] = '#D53F8C';
                $config['font'] = "'Quicksand', sans-serif";
                break;
            case 'cyber':
                $config['bg'] = '#000000';
                $config['text'] = '#22C55E';
                $config['sidebar_bg'] = '#0A0A0A';
                $config['sidebar_text'] = '#16A34A';
                $config['sidebar_active'] = '#14532D';
                $config['card_bg'] = '#050505';
                $config['card_border'] = '#22C55E';
                $config['primary'] = '#4ADE80';
                $config['font'] = "'JetBrains Mono', monospace";
                break;
            case 'nature':
                $config['bg'] = '#F0FDF4';
                $config['text'] = '#14532D';
                $config['sidebar_bg'] = '#14532D';
                $config['sidebar_text'] = '#DCFCE7';
                $config['sidebar_active'] = '#166534';
                $config['card_bg'] = '#FFFFFF';
                $config['card_border'] = '#BBF7D0';
                $config['primary'] = '#16A34A';
                $config['font'] = "'Merriweather', serif";
                break;
            case 'coral':
                $config['bg'] = '#FFF7ED';
                $config['text'] = '#7C2D12';
                $config['sidebar_bg'] = '#9A3412';
                $config['sidebar_text'] = '#FFEDD5';
                $config['sidebar_active'] = '#7C2D12';
                $config['card_bg'] = '#FFFFFF';
                $config['card_border'] = '#FED7AA';
                $config['primary'] = '#EA580C';
                $config['font'] = "'Outfit', sans-serif";
                break;
            case 'light':
            default:
                $config['bg'] = '#F3F4F6';
                $config['text'] = '#111827';
                $config['sidebar_bg'] = '#1F2937';
                $config['sidebar_text'] = '#E5E7EB';
                $config['sidebar_active'] = '#374151';
                $config['card_bg'] = '#FFFFFF';
                $config['card_border'] = '#E5E7EB';
                $config['primary'] = '#3B82F6';
                $config['font'] = "'Inter', sans-serif";
                break;
        }
        return $config;
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
            return $seconds . "s";
        }
        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;
        if ($minutes < 60) {
            return $minutes . "m " . $remainingSeconds . "s";
        }
        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;
        return $hours . "u " . $remainingMinutes . "m";
    }
}

/**
 * Format duration in seconds into HH:MM:SS format (e.g. '01:23:45').
 *
 * @param int 
 * @return string
 */

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

if (!function_exists('format_seconds')) {
    function format_seconds(int $seconds): string {
        return formatDurationHms($seconds);
    }
}
