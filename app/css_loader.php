<?php

// Hängt an jede Datei den Änderungszeitpunkt als Versionsnummer.
//
// Ohne das behält der Browser eine einmal geladene CSS- oder JS-Datei
// unbegrenzt im Cache. Nach einem Update passen dann altes Skript und
// neues Markup nicht mehr zusammen - im Kiosk-Modus ohne Tastatur lässt
// sich das nicht von Hand beheben.
function assetUrl(string $relativePath): string
{
    $absolute = mirrorRoot() . "/" . $relativePath;

    $version = is_file($absolute) ? filemtime($absolute) : 0;

    return $relativePath . "?v=" . $version;
}


function loadModuleCSS(array $modules): void
{
    foreach ($modules as $module) {

        $path = "modules/$module/$module.css";

        if (is_file(mirrorRoot() . "/" . $path)) {
            echo '<link rel="stylesheet" href="' . assetUrl($path) . '">' . PHP_EOL;
        }
    }
}
