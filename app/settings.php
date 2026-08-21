<?php

// Zentrale Einstellungsverwaltung.
//
// config/config.php  -> Standardwerte, liegen in Git
// data/settings.json -> im Adminbereich gesetzte Werte, nur lokal
//
// Alles, was Einstellungen braucht, geht über loadSettings().
// Geschrieben wird ausschließlich über saveSettings().


function mirrorRoot(): string
{
    return dirname(__DIR__);
}


function settingsFile(): string
{
    return mirrorRoot() . "/data/settings.json";
}


function refreshStateFile(): string
{
    return mirrorRoot() . "/data/refresh_state.json";
}


function calendarFile(): string
{
    return mirrorRoot() . "/data/calendar.ics";
}


function weatherCacheFile(): string
{
    return mirrorRoot() . "/cache/weather.json";
}


function uploadsDirectory(): string
{
    return mirrorRoot() . "/uploads";
}


// Prüft auf gültiges UTF-8, ohne die mbstring-Erweiterung vorauszusetzen.
function isValidUtf8(string $value): bool
{
    return $value === "" || preg_match('//u', $value) === 1;
}


function ensureDirectory(string $path): bool
{
    if (is_dir($path)) {
        return true;
    }

    return @mkdir($path, 0775, true) || is_dir($path);
}


// Verbindet Standardwerte mit gespeicherten Werten.
// Verschachtelte Arrays werden zusammengeführt, Listen (z. B. "modules")
// dagegen komplett ersetzt - sonst könnte man kein Modul abschalten.
function mergeSettings(array $defaults, array $override): array
{
    foreach ($override as $key => $value) {

        $mergeable =
            is_array($value)
            && !array_is_list($value)
            && isset($defaults[$key])
            && is_array($defaults[$key]);

        if ($mergeable) {
            $defaults[$key] = mergeSettings($defaults[$key], $value);
        } else {
            $defaults[$key] = $value;
        }
    }

    return $defaults;
}


function loadSettings(bool $fresh = false): array
{
    static $cached = null;

    if ($cached !== null && !$fresh) {
        return $cached;
    }

    $defaults = require mirrorRoot() . "/config/config.php";
    $settings = $defaults;

    if (is_file(settingsFile())) {

        $decoded = json_decode(
            (string) file_get_contents(settingsFile()),
            true
        );

        if (is_array($decoded)) {
            $settings = mergeSettings($defaults, $decoded);
        }
    }

    // Die Liste der verfügbaren Module ist fest - eine alte oder
    // manipulierte settings.json darf sie nicht überschreiben.
    $settings["available_modules"] = $defaults["available_modules"];

    // Nur Module durchlassen, die es wirklich gibt, und in der
    // Reihenfolge aus available_modules - das Layout hängt daran.
    $active = is_array($settings["modules"] ?? null)
        ? $settings["modules"]
        : $defaults["modules"];

    $settings["modules"] = array_values(array_filter(
        array_keys($defaults["available_modules"]),
        fn(string $module): bool => in_array($module, $active, true)
    ));

    if (!empty($settings["timezone"])) {
        date_default_timezone_set($settings["timezone"]);
    }

    $cached = $settings;

    return $settings;
}


function saveSettings(array $settings): bool
{
    if (!ensureDirectory(dirname(settingsFile()))) {
        return false;
    }

    // available_modules ist fest verdrahtet und gehört nicht in die
    // gespeicherte Datei - sonst friert eine alte Modulliste ein.
    unset($settings["available_modules"]);

    // INVALID_UTF8_SUBSTITUTE: ohne das Flag bricht json_encode bei einem
    // einzigen kaputten Zeichen komplett ab und nichts wird gespeichert.
    $json = json_encode(
        $settings,
        JSON_PRETTY_PRINT
        | JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_INVALID_UTF8_SUBSTITUTE
    );

    if ($json === false) {
        return false;
    }

    if (file_put_contents(settingsFile(), $json, LOCK_EX) === false) {
        return false;
    }

    // Wetter-Cache verwerfen, sonst zeigt der Spiegel nach einem
    // Standortwechsel bis zu 10 Minuten die alte Stadt.
    if (is_file(weatherCacheFile())) {
        @unlink(weatherCacheFile());
    }

    loadSettings(true);
    bumpRefreshVersion();

    return true;
}


function refreshVersion(): int
{
    if (!is_file(refreshStateFile())) {
        return 0;
    }

    $decoded = json_decode(
        (string) file_get_contents(refreshStateFile()),
        true
    );

    return is_array($decoded)
        ? (int) ($decoded["version"] ?? 0)
        : 0;
}


// Zählt die Version hoch. Der geöffnete Spiegel merkt das beim nächsten
// Poll und lädt sich selbst neu - so muss niemand ans Display.
function bumpRefreshVersion(): int
{
    ensureDirectory(dirname(refreshStateFile()));

    $version = refreshVersion() + 1;

    file_put_contents(
        refreshStateFile(),
        json_encode(
            [
                "version" => $version,
                "updated" => time()
            ],
            JSON_PRETTY_PRINT
        ),
        LOCK_EX
    );

    return $version;
}
