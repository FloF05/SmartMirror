<?php

require __DIR__ . "/../app/settings.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit;
}

$settings = loadSettings();


// --- Module ---------------------------------------------------------------

$submitted = is_array($_POST["modules"] ?? null)
    ? $_POST["modules"]
    : [];

$settings["modules"] = array_values(array_filter(
    array_keys($settings["available_modules"]),
    fn(string $module): bool => in_array($module, $submitted, true)
));


// --- Diashow --------------------------------------------------------------

// In der Oberfläche in Sekunden, gespeichert in Millisekunden.
$seconds = (float) str_replace(",", ".", (string) ($_POST["slideshow_interval"] ?? ""));

if ($seconds >= 1 && $seconds <= 600) {
    $settings["slideshow"]["interval"] = (int) round($seconds * 1000);
}


// --- Wetter ---------------------------------------------------------------

$city = trim((string) ($_POST["weather_city"] ?? ""));

if ($city !== "") {

    if (!isValidUtf8($city)) {
        header("Location: index.php?error=encoding");
        exit;
    }

    $settings["weather"]["city"] = $city;
}

$country = strtoupper(trim((string) ($_POST["weather_country"] ?? "")));

if (preg_match('/^[A-Z]{2}$/', $country)) {
    $settings["weather"]["country"] = $country;
}


// --- Uhr ------------------------------------------------------------------

$settings["clock"]["show_seconds"] = isset($_POST["clock_show_seconds"]);

if (in_array($_POST["clock_format"] ?? "", ["12", "24"], true)) {
    $settings["clock"]["format"] = $_POST["clock_format"];
}


// --- Kalender -------------------------------------------------------------

if (in_array($_POST["calendar_view"] ?? "", ["month", "week"], true)) {
    $settings["calendar"]["view"] = $_POST["calendar_view"];
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


// --- Speichern ------------------------------------------------------------

// saveSettings() verwirft nebenbei den Wetter-Cache und zählt die
// Refresh-Version hoch, damit sich der Spiegel selbst neu lädt.
if (!saveSettings($settings)) {
    header("Location: index.php?error=permission");
    exit;
}

header("Location: index.php?success=settings");
exit;
