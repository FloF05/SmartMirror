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
    // php_uname statt shell_exec: startet keinen Prozess und funktioniert
    // auch dort, wo shell_exec per Konfiguration gesperrt ist.
    return php_uname('n') ?: 'unbekannt';
}

// Die Adresse im Heimnetz - damit man vom Handy aus die Verwaltung findet,
// auch wo "mirror.local" nicht funktioniert (viele Android-Geräte).
//
// Ein UDP-"connect" verschickt nichts, lässt das System aber die Route
// wählen. Die lokale Seite dieser Verbindung ist genau die Adresse, unter
// der der Pi im Netz erreichbar ist. 192.0.2.1 ist eine reine
// Dokumentationsadresse, dorthin geht nie ein Paket.
function getAddress(): string {
    $socket = @stream_socket_client('udp://192.0.2.1:9', $errno, $errstr, 1);

    if ($socket === false) {
        return '';
    }

    $local = (string) stream_socket_get_name($socket, false);
    fclose($socket);

    $address = preg_replace('/:\d+$/', '', $local);

    return filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && $address !== '127.0.0.1'
        ? $address
        : '';
}

echo json_encode([
    'address' => getAddress(),
    'cpu' => getCpu(),
    'memory' => getMemory(),
    'uptime' => getUptime(),
    'hostname' => getHostnameValue()
], JSON_UNESCAPED_UNICODE);
