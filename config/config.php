<?php

// Standardwerte des SmartMirrors.
//
// Diese Datei liegt in Git und ist die Referenz, falls data/settings.json
// fehlt oder unvollständig ist. Zur Laufzeit gewinnen die im Adminbereich
// gesetzten Werte aus data/settings.json.
//
// Geändert wird hier normalerweise nichts mehr - alles Wichtige lässt sich
// unter http://mirror.local/admin/ einstellen.

return [

    "name" => "Mirror",

    "timezone" => "Europe/Berlin",

    // Alle Module, die es gibt - Reihenfolge bestimmt die Anzeige.
    // Diese Liste ist fest und wird vom Adminbereich nicht verändert.
    "available_modules" => [
        "clock"     => "Uhr und Datum",
        "weather"   => "Wetter",
        "slideshow" => "Diashow",
        "calendar"  => "Kalender",
        "info"      => "Systeminfo"
    ],

    // Aktive Module - im Adminbereich an- und abschaltbar
    "modules" => [
        "clock",
        "weather",
        "slideshow",
        "calendar",
        "info"
    ],

    "clock" => [
        "show_seconds" => true,
        "format"       => "24"
    ],

    "weather" => [
        "city"    => "Berlin",
        "country" => "DE",
        "units"   => "metric"
    ],

    "slideshow" => [
        "interval" => 5000
    ],

    "calendar" => [
        "view" => "month"
    ]

];
