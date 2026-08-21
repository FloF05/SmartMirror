<?php
header('Content-Type: application/json');

$stateFile = __DIR__ . '/../uploads/refresh_state.json';
$state = [
    'version' => 0,
    'updated' => 0
];

if (file_exists($stateFile)) {
    $decoded = json_decode(file_get_contents($stateFile), true);
    if (is_array($decoded)) {
        $state = array_merge($state, $decoded);
    }
}

echo json_encode([
    'version' => (int) ($state['version'] ?? 0),
    'updated' => (int) ($state['updated'] ?? 0)
], JSON_UNESCAPED_UNICODE);
