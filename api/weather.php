<?php

header("Content-Type: application/json");

require_once __DIR__ . "/../config/config.php";

$secrets = require __DIR__ . "/../config/secrets.php";


$city = $config["weather"]["city"];
$country = $config["weather"]["country"];
$units = $config["weather"]["units"];
$apiKey = $secrets["openweather_key"];


$url = "https://api.openweathermap.org/data/2.5/weather"
     . "?q=" . urlencode($city . "," . $country)
     . "&appid=" . urlencode($apiKey)
     . "&units=" . urlencode($units)
     . "&lang=de";


$ch = curl_init($url);


curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);


$response = curl_exec($ch);


if ($response === false) {

    http_response_code(500);

    echo json_encode([
        "error" => "Wetterdaten konnten nicht abgerufen werden.",
        "details" => curl_error($ch)
    ]);

    curl_close($ch);

    exit;

}


$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);


if ($httpCode !== 200) {

    http_response_code($httpCode);

    echo $response;

    exit;

}


echo $response;