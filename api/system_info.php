<?php
header('Content-Type: application/json');

function getUptime(): string {
    if (file_exists('/proc/uptime')) {
        $uptime = file_get_contents('/proc/uptime');
        $seconds = (int) floatval(explode(' ', $uptime)[0]);
        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        return sprintf('%d d %02d:%02d h', $days, $hours, $minutes);
    }

    return 'unbekannt';
}

function getMemory(): string {
    if (file_exists('/proc/meminfo')) {
        $lines = file('/proc/meminfo');
        $memTotal = 0;
        $memAvailable = 0;

        foreach ($lines as $line) {
            if (preg_match('/^MemTotal:\s+(\d+) kB/', $line, $matches)) {
                $memTotal = (int) $matches[1];
            }
            if (preg_match('/^MemAvailable:\s+(\d+) kB/', $line, $matches)) {
                $memAvailable = (int) $matches[1];
            }
        }

        if ($memTotal > 0) {
            $used = $memTotal - $memAvailable;
            $usedMb = round($used / 1024, 1);
            $totalMb = round($memTotal / 1024, 1);
            return $usedMb . ' / ' . $totalMb . ' MB';
        }
    }

    return 'unbekannt';
}

function getCpu(): string {
    if (file_exists('/proc/loadavg')) {
        $load = trim(file_get_contents('/proc/loadavg'));
        return explode(' ', $load)[0];
    }

    return 'unbekannt';
}

function getHostnameValue(): string {
    return trim(shell_exec('hostname')) ?: 'unbekannt';
}

echo json_encode([
    'cpu' => getCpu(),
    'memory' => getMemory(),
    'uptime' => getUptime(),
    'hostname' => getHostnameValue()
], JSON_UNESCAPED_UNICODE);
