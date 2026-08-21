<?php

$stateFile = __DIR__ . '/../uploads/refresh_state.json';

$state = [
    'version' => 0,
    'updated' => time()
];

if (file_exists($stateFile)) {
    $decoded = json_decode(file_get_contents($stateFile), true);
    if (is_array($decoded)) {
        $state = array_merge($state, $decoded);
    }
}

$state['version'] = (int) ($state['version'] ?? 0) + 1;
$state['updated'] = time();

file_put_contents($stateFile, json_encode($state, JSON_PRETTY_PRINT));

header('Location: index.php?success=calendar');
exit;
