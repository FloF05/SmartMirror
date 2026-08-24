<?php

// Liefert Wetter, Vorhersage, Sonnenzeiten, Mondphase und Luftqualität in
// einer einzigen Antwort. Der Pi Zero soll pro Aktualisierung genau eine
// Anfrage stellen müssen, nicht vier.

require __DIR__ . "/../app/settings.php";

header("Content-Type: application/json");

$settings = loadSettings();
$weather  = $settings["weather"];

$cacheFile     = weatherCacheFile();
$cacheDuration = 900;

// Frischer Cache? Dann gar nicht erst ins Netz.
if (
    is_file($cacheFile)
    && (time() - filemtime($cacheFile)) < $cacheDuration
) {
    echo file_get_contents($cacheFile);
    exit;
}

function respondError(string $message): never
{
    http_response_code(500);
    echo json_encode(["error" => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!function_exists("curl_init")) {
    respondError("PHP-Erweiterung curl fehlt - 'sudo apt install php-curl' ausführen.");
}

$secretsFile = mirrorRoot() . "/config/secrets.php";

if (!is_file($secretsFile)) {
    respondError("config/secrets.php fehlt - siehe config/secrets.example.php");
}

$secrets = require $secretsFile;
$apiKey  = $secrets["openweather_key"] ?? "";

if ($apiKey === "" || str_starts_with($apiKey, "HIER_DEN_")) {
    respondError("Kein OpenWeather-API-Key hinterlegt.");
}


function fetchJson(string $url): ?array
{
    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $response = curl_exec($ch);
    $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($response === false || $code !== 200) {
        return null;
    }

    $decoded = json_decode($response, true);

    return is_array($decoded) ? $decoded : null;
}


// Mondphase aus dem Abstand zu einem bekannten Neumond.
// Referenz: 6. Januar 2000, 18:14 UTC. Synodischer Monat: 29,530588853 Tage.
function moonPhase(int $timestamp): array
{
    $synodic = 29.530588853;
    $age = fmod(($timestamp - 947182440) / 86400.0, $synodic);

    if ($age < 0) {
        $age += $synodic;
    }

    // Kreis-Zeichen statt Emoji: Auf Raspberry Pi OS Lite ist keine
    // Emoji-Schriftart installiert, Mond-Emoji würden als leere Kästchen
    // erscheinen. Diese Glyphen stecken in DejaVu Sans und sind einfarbig -
    // auf einem Spiegel ohnehin die bessere Wahl.
    $phases = [
        ["Neumond",           "\u{25CB}"],
        ["Zunehmende Sichel", "\u{25D4}"],
        ["Erstes Viertel",    "\u{25D1}"],
        ["Zunehmender Mond",  "\u{25D5}"],
        ["Vollmond",          "\u{25CF}"],
        ["Abnehmender Mond",  "\u{25D5}"],
        ["Letztes Viertel",   "\u{25D0}"],
        ["Abnehmende Sichel", "\u{25D4}"]
    ];

    $index = (int) floor(($age / $synodic) * 8 + 0.5) % 8;

    // Beleuchteter Anteil: 0 bei Neumond, 100 bei Vollmond
    $illumination = (int) round((1 - cos(2 * M_PI * $age / $synodic)) / 2 * 100);

    return [
        "phase"        => $phases[$index][0],
        "icon"         => $phases[$index][1],
        "illumination" => $illumination
    ];
}


$city    = $weather["city"];
$country = $weather["country"];
$units   = $weather["units"];

$query = $country !== "" ? $city . "," . $country : $city;

$base = "https://api.openweathermap.org/data/2.5";

$common = "&appid=" . urlencode($apiKey)
        . "&units=" . urlencode($units)
        . "&lang=de";

$current = fetchJson($base . "/weather?q=" . urlencode($query) . $common);

if ($current === null) {

    // Lieber veraltete Daten zeigen als eine leere Kachel - der Pi hängt
    // im WLAN und verliert die Verbindung gelegentlich.
    if (is_file($cacheFile)) {
        echo file_get_contents($cacheFile);
        exit;
    }

    respondError("Wetterdaten konnten nicht abgerufen werden.");
}

$result = [
    "current" => [
        "city"        => $current["name"] ?? $city,
        "temp"        => (int) round($current["main"]["temp"] ?? 0),
        "feels"       => (int) round($current["main"]["feels_like"] ?? 0),
        "description" => $current["weather"][0]["description"] ?? "",
        "icon"        => $current["weather"][0]["icon"] ?? "01d",
        "humidity"    => (int) ($current["main"]["humidity"] ?? 0),
        "wind"        => round((float) ($current["wind"]["speed"] ?? 0), 1)
    ],
    "updated" => time()
];

// --- Sonnenzeiten ----------------------------------------------------------
// Stecken bereits in der Antwort oben, kosten also keinen weiteren Aufruf.

if (!empty($weather["sun"]) && isset($current["sys"]["sunrise"])) {
    $result["sun"] = [
        "sunrise" => date("H:i", (int) $current["sys"]["sunrise"]),
        "sunset"  => date("H:i", (int) $current["sys"]["sunset"])
    ];
}

// --- Mondphase -------------------------------------------------------------

if (!empty($weather["moon"])) {
    $result["moon"] = moonPhase(time());
}

// --- Luftqualität ----------------------------------------------------------

if (!empty($weather["air"]) && isset($current["coord"]["lat"])) {

    $air = fetchJson(
        $base . "/air_pollution"
        . "?lat=" . urlencode((string) $current["coord"]["lat"])
        . "&lon=" . urlencode((string) $current["coord"]["lon"])
        . "&appid=" . urlencode($apiKey)
    );

    if ($air !== null && isset($air["list"][0]["main"]["aqi"])) {

        $labels = [
            1 => "Sehr gut",
            2 => "Gut",
            3 => "Mäßig",
            4 => "Schlecht",
            5 => "Sehr schlecht"
        ];

        $aqi = (int) $air["list"][0]["main"]["aqi"];

        $result["air"] = [
            "aqi"   => $aqi,
            "label" => $labels[$aqi] ?? "unbekannt",
            "pm25"  => round((float) ($air["list"][0]["components"]["pm2_5"] ?? 0), 1)
        ];
    }
}

// --- 5-Tage-Vorhersage -----------------------------------------------------

if (!empty($weather["forecast"])) {

    $forecast = fetchJson($base . "/forecast?q=" . urlencode($query) . $common);

    if ($forecast !== null && isset($forecast["list"])) {

        $days   = [];
        $today  = date("Y-m-d");
        $labels = ["So", "Mo", "Di", "Mi", "Do", "Fr", "Sa"];

        foreach ($forecast["list"] as $entry) {

            $timestamp = (int) ($entry["dt"] ?? 0);
            $date      = date("Y-m-d", $timestamp);

            if ($date === $today) {
                continue;
            }

            $temp = (float) ($entry["main"]["temp"] ?? 0);

            if (!isset($days[$date])) {
                $days[$date] = [
                    "date" => $date,
                    "day"  => $labels[(int) date("w", $timestamp)],
                    "min"  => $temp,
                    "max"  => $temp,
                    "icon" => $entry["weather"][0]["icon"] ?? "01d"
                ];
            }

            $days[$date]["min"] = min($days[$date]["min"], $temp);
            $days[$date]["max"] = max($days[$date]["max"], $temp);

            // Das Symbol vom Mittag steht am ehesten für den ganzen Tag
            if (date("H", $timestamp) === "12") {
                $days[$date]["icon"] = $entry["weather"][0]["icon"] ?? "01d";
            }
        }

        $result["forecast"] = array_values(array_map(
            fn(array $day): array => [
                "day"  => $day["day"],
                "date" => $day["date"],
                "min"  => (int) round($day["min"]),
                "max"  => (int) round($day["max"]),
                "icon" => $day["icon"]
            ],
            array_slice($days, 0, 4)
        ));
    }
}

$json = json_encode($result, JSON_UNESCAPED_UNICODE);

ensureDirectory(dirname($cacheFile));
file_put_contents($cacheFile, $json, LOCK_EX);

echo $json;
