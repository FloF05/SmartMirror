<?php
header('Content-Type: application/json');

$configPath = __DIR__ . '/../uploads/calendar_settings.json';
$legacyConfigPath = __DIR__ . '/../config/calendar_settings.json';
$calendarFile = __DIR__ . '/../uploads/calendar.ics';

$settings = [
    'view' => 'month'
];

if (!file_exists($configPath)) {
    if (file_exists($legacyConfigPath)) {
        copy($legacyConfigPath, $configPath);
    } else {
        file_put_contents($configPath, json_encode($settings, JSON_PRETTY_PRINT));
    }
}

if (file_exists($configPath)) {
    $decoded = json_decode(file_get_contents($configPath), true);
    if (is_array($decoded)) {
        $settings = array_merge($settings, $decoded);
    }
}

$events = [];

if (file_exists($calendarFile)) {
    $content = file_get_contents($calendarFile);
    $lines = preg_split('/\r\n|\n|\r/', $content);
    $currentEvent = null;

    foreach ($lines as $line) {
        if (preg_match('/^BEGIN:VEVENT$/', $line)) {
            $currentEvent = [];
            continue;
        }

        if (preg_match('/^END:VEVENT$/', $line)) {
            if ($currentEvent !== null && isset($currentEvent['summary']) && isset($currentEvent['start'])) {
                $events[] = [
                    'summary' => $currentEvent['summary'],
                    'date' => date('Y-m-d', strtotime($currentEvent['start']))
                ];
            }
            $currentEvent = null;
            continue;
        }

        if ($currentEvent === null) {
            continue;
        }

        if (preg_match('/^SUMMARY:(.*)$/', $line, $matches)) {
            $currentEvent['summary'] = trim($matches[1]);
        }

        if (preg_match('/^DTSTART(?:;[^:]+)?:([^\s]+)$/', $line, $matches)) {
            $currentEvent['start'] = $matches[1];
        }
    }
}

echo json_encode([
    'view' => $settings['view'] ?? 'month',
    'events' => $events
], JSON_UNESCAPED_UNICODE);
