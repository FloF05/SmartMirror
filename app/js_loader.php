<?php

// Gibt die Einstellungen einmal als window.mirrorConfig aus und lädt
// danach die JavaScript-Dateien der aktiven Module. clock.js und
// slideshow.js lesen ihre Werte von dort.
function loadModuleJS(array $modules, array $settings): void
{
    $clientConfig = [
        "clock"     => $settings["clock"],
        "slideshow" => $settings["slideshow"],
        "calendar"  => $settings["calendar"]
    ];

    echo '<script>window.mirrorConfig = '
        . json_encode($clientConfig, JSON_UNESCAPED_UNICODE)
        . ';</script>' . PHP_EOL;

    foreach ($modules as $module) {

        $path = "modules/$module/$module.js";

        if (is_file(mirrorRoot() . "/" . $path)) {
            echo '<script src="' . $path . '"></script>' . PHP_EOL;
        }
    }
}
