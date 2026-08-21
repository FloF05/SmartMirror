<?php

require __DIR__ . "/../app/settings.php";

header("Content-Type: application/json");

$settings = loadSettings();

$secretsFile = mirrorRoot() . "/config/secrets.php";

if (!is_file($secretsFile)) {
    http_response_code(500);
    echo json_encode([
        "error" => "config/secrets.php fehlt - siehe config/secrets.example.php"
    ]);
    exit;
}

$secrets = require $secretsFile;
$apiKey  = $secrets["openweather_key"] ?? "";

if ($apiKey === "" || str_starts_with($apiKey, "HIER_DEN_")) {
    http_response_code(500);
    echo json_encode([
        "error" => "Kein OpenWeather-API-Key hinterlegt."
    ]);
    exit;
}

if (!function_exists("curl_init")) {
    http_response_code(500);
    echo json_encode([
        "error" => "PHP-Erweiterung curl fehlt - 'sudo apt install php-curl' ausführen."
    ]);
    exit;
}

$cacheFile     = weatherCacheFile();
$cacheDuration = 600;

if (
    is_file($cacheFile)
    && (time() - filemtime($cacheFile)) < $cacheDuration
) {
    echo file_get_contents($cacheFile);
    exit;
}

$city    = $settings["weather"]["city"];
$country = $settings["weather"]["country"];
$units   = $settings["weather"]["units"];

$query = $country !== ""
    ? $city . "," . $country
    : $city;

$url =
    "https://api.openweathermap.org/data/2.5/weather"
    . "?q=" . urlencode($query)
    . "&appid=" . urlencode($apiKey)
    . "&units=" . urlencode($units)
    . "&lang=de";

$ch = curl_init($url);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

if ($response === false) {

    // Lieber veraltete Wetterdaten zeigen als gar keine - der Pi hängt
    // im WLAN und verliert die Verbindung gelegentlich.
    if (is_file($cacheFile)) {
        echo file_get_contents($cacheFile);
        exit;
    }

    http_response_code(500);
    echo json_encode([
        "error" => "Wetterdaten konnten nicht abgerufen werden."
    ]);
    exit;
}

if ($httpCode !== 200) {
    http_response_code($httpCode);
    echo $response;
    exit;
}

ensureDirectory(dirname($cacheFile));
file_put_contents($cacheFile, $response, LOCK_EX);

echo $response;
