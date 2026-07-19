<?php

$settingsPath = __DIR__ . '/../config/calendar_settings.json';
$calendarTarget = __DIR__ . '/../uploads/calendar.ics';

$settings = [
    'view' => 'month'
];

if (file_exists($settingsPath)) {
    $decoded = json_decode(file_get_contents($settingsPath), true);
    if (is_array($decoded)) {
        $settings = array_merge($settings, $decoded);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

if (isset($_POST['calendar_view']) && in_array($_POST['calendar_view'], ['month', 'week'], true)) {
    $settings['view'] = $_POST['calendar_view'];
}

if (!empty($_FILES['calendar_ics']['tmp_name']) && $_FILES['calendar_ics']['error'] === UPLOAD_ERR_OK) {
    $extension = strtolower(pathinfo($_FILES['calendar_ics']['name'], PATHINFO_EXTENSION));
    if ($extension !== 'ics') {
        header('Location: index.php?error=type');
        exit;
    }

    if (!move_uploaded_file($_FILES['calendar_ics']['tmp_name'], $calendarTarget)) {
        header('Location: index.php?error=save');
        exit;
    }
}

file_put_contents($settingsPath, json_encode($settings, JSON_PRETTY_PRINT));
header('Location: index.php?success=calendar');
exit;
