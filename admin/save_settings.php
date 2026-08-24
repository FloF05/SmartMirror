<?php

require __DIR__ . "/../app/settings.php";
require __DIR__ . "/../app/holidays.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit;
}

$settings = loadSettings();

function postText(string $key): string
{
    return trim((string) ($_POST[$key] ?? ""));
}

// Mehrzeilige Textfelder in eine bereinigte Liste verwandeln
function postLines(string $key, int $max = 40): array
{
    $raw = str_replace("\r\n", "\n", (string) ($_POST[$key] ?? ""));

    $lines = array_filter(
        array_map("trim", explode("\n", $raw)),
        fn(string $line): bool => $line !== "" && isValidUtf8($line)
    );

    return array_slice(array_values($lines), 0, $max);
}


// --- Module ---------------------------------------------------------------

$submitted = is_array($_POST["modules"] ?? null) ? $_POST["modules"] : [];

$settings["modules"] = array_values(array_filter(
    array_keys($settings["available_modules"]),
    fn(string $module): bool => in_array($module, $submitted, true)
));


// --- Uhr ------------------------------------------------------------------

$settings["clock"]["show_seconds"] = isset($_POST["clock_show_seconds"]);
$settings["clock"]["show_week"]    = isset($_POST["clock_show_week"]);

if (in_array($_POST["clock_format"] ?? "", ["12", "24"], true)) {
    $settings["clock"]["format"] = $_POST["clock_format"];
}


// --- Wetter ---------------------------------------------------------------

$city = postText("weather_city");

if ($city !== "") {

    if (!isValidUtf8($city)) {
        header("Location: index.php?error=encoding");
        exit;
    }

    $settings["weather"]["city"] = $city;
}

$country = strtoupper(postText("weather_country"));

if (preg_match('/^[A-Z]{2}$/', $country)) {
    $settings["weather"]["country"] = $country;
}

foreach (["forecast", "sun", "moon", "air"] as $option) {
    $settings["weather"][$option] = isset($_POST["weather_" . $option]);
}


// --- Diashow --------------------------------------------------------------

$seconds = (float) str_replace(",", ".", postText("slideshow_interval"));

if ($seconds >= 1 && $seconds <= 600) {
    $settings["slideshow"]["interval"] = (int) round($seconds * 1000);
}


// --- Termine --------------------------------------------------------------

if (in_array($_POST["calendar_view"] ?? "", ["agenda", "month"], true)) {
    $settings["calendar"]["view"] = $_POST["calendar_view"];
}

$days = (int) postText("calendar_days_ahead");

if ($days >= 1 && $days <= 365) {
    $settings["calendar"]["days_ahead"] = $days;
}

$max = (int) postText("calendar_max_events");

if ($max >= 1 && $max <= 20) {
    $settings["calendar"]["max_events"] = $max;
}

$settings["calendar"]["show_holidays"] = isset($_POST["calendar_show_holidays"]);

if (array_key_exists($_POST["calendar_state"] ?? "", germanStates())) {
    $settings["calendar"]["state"] = $_POST["calendar_state"];
}

if (isset($_POST["calendar_remove"]) && is_file(calendarFile())) {
    @unlink(calendarFile());
}

$upload = $_FILES["calendar_ics"] ?? null;

if ($upload !== null && ($upload["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {

    if ($upload["error"] !== UPLOAD_ERR_OK) {
        header("Location: index.php?error=upload");
        exit;
    }

    if (strtolower(pathinfo($upload["name"], PATHINFO_EXTENSION)) !== "ics") {
        header("Location: index.php?error=type");
        exit;
    }

    if (!ensureDirectory(dirname(calendarFile()))) {
        header("Location: index.php?error=permission");
        exit;
    }

    if (!move_uploaded_file($upload["tmp_name"], calendarFile())) {
        header("Location: index.php?error=save");
        exit;
    }
}


// --- Countdown ------------------------------------------------------------
// Eingabeformat je Zeile:  2026-12-24 | Weihnachten

$countdown = [];

foreach (postLines("countdown_items", 10) as $line) {

    $parts = array_map("trim", explode("|", $line, 2));

    if (count($parts) !== 2 || $parts[1] === "") {
        continue;
    }

    $date = DateTimeImmutable::createFromFormat("Y-m-d", $parts[0]);

    if ($date === false || $date->format("Y-m-d") !== $parts[0]) {
        continue;
    }

    $countdown[] = ["date" => $parts[0], "label" => $parts[1]];
}

$settings["countdown"]["items"] = $countdown;


// --- Listen ---------------------------------------------------------------

$title = postText("todo_title");

if ($title !== "" && isValidUtf8($title)) {
    $settings["todo"]["title"] = $title;
}

$settings["todo"]["items"] = postLines("todo_items", 30);


// --- Nachrichten ----------------------------------------------------------

$feed = postText("news_feed");

if ($feed !== "" && preg_match('#^https?://#i', $feed)) {
    $settings["news"]["feed"] = $feed;
}

$count = (int) postText("news_count");

if ($count >= 1 && $count <= 10) {
    $settings["news"]["count"] = $count;
}


// --- Zitate ---------------------------------------------------------------

$quotes = postLines("quote_items", 100);

if ($quotes !== []) {
    $settings["quote"]["items"] = $quotes;
}


// --- Speichern ------------------------------------------------------------

// saveSettings() verwirft nebenbei den Wetter-Cache und zählt die
// Refresh-Version hoch, damit sich der Spiegel selbst neu lädt.
if (!saveSettings($settings)) {
    header("Location: index.php?error=permission");
    exit;
}

// Der Nachrichten-Cache hängt an der Feed-Adresse und muss mit weg
$newsCache = mirrorRoot() . "/cache/news.json";

if (is_file($newsCache)) {
    @unlink($newsCache);
}

header("Location: index.php?success=settings");
exit;
