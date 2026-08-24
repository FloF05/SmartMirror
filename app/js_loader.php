<?php

// Gibt die Einstellungen einmal als window.mirrorConfig aus und lädt danach
// die JavaScript-Dateien der aktiven Module. Module ohne eigenes Skript -
// etwa die serverseitig gerenderte Liste - werden übersprungen.
function loadModuleJS(array $modules, array $settings): void
{
    // Nur weitergeben, was der Browser wirklich braucht. Der API-Key und
    // andere Serverdetails haben im Quelltext der Seite nichts zu suchen.
    $clientConfig = [
        "clock"     => $settings["clock"],
        "slideshow" => $settings["slideshow"],
        "calendar"  => ["view" => $settings["calendar"]["view"]],
        "countdown" => $settings["countdown"],
        "quote"     => $settings["quote"]
    ];

    echo '<script>window.mirrorConfig = '
        . json_encode(
            $clientConfig,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        )
        . ';</script>' . PHP_EOL;

    foreach ($modules as $module) {

        $path = "modules/$module/$module.js";

        if (is_file(mirrorRoot() . "/" . $path)) {
            echo '<script src="' . $path . '"></script>' . PHP_EOL;
        }
    }
}
