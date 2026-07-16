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


$response = file_get_contents($url);


if ($response === false) {

    http_response_code(500);

    echo json_encode([
        "error" => "Wetterdaten konnten nicht abgerufen werden."
    ]);

    exit;

}


echo $response;
?>