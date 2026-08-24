<?php

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
