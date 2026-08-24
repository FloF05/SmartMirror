<?php

// Standardwerte des SmartMirrors.
//
// Diese Datei liegt in Git und ist die Referenz, falls data/settings.json
// fehlt oder unvollständig ist. Zur Laufzeit gewinnen die im Adminbereich
// gesetzten Werte aus data/settings.json.

return [

    "name" => "Mirror",

    "timezone" => "Europe/Berlin",

    // Alle Module, die es gibt. Reihenfolge bestimmt die Anzeige von oben
    // nach unten. Diese Liste ist fest und wird vom Adminbereich nicht
    // verändert - dort lassen sich Module nur an- und abschalten.
    "available_modules" => [
        "clock"     => "Uhr und Datum",
        "weather"   => "Wetter",
        "countdown" => "Countdown",
        "calendar"  => "Termine",
        "todo"      => "Listen",
        "news"      => "Nachrichten",
        "quote"     => "Zitat des Tages",
        "slideshow" => "Diashow",
        "info"      => "Systeminfo"
    ],

    "modules" => [
        "clock",
        "weather",
        "countdown",
        "calendar",
        "todo",
        "news",
        "quote",
        "slideshow",
        "info"
    ],

    "clock" => [
        "show_seconds" => false,
        "format"       => "24",
        "show_week"    => false
    ],

    "weather" => [
        "city"     => "Berlin",
        "country"  => "DE",
        "units"    => "metric",
        "forecast" => true,
        "sun"      => true,
        "moon"     => true,
        "air"      => true
    ],

    "calendar" => [
        "view"          => "agenda",
        "days_ahead"    => 21,
        "max_events"    => 6,
        "show_holidays" => true,
        "state"         => "BE"
    ],

    "countdown" => [
        "items" => []
    ],

    "todo" => [
        "title" => "Einkaufsliste",
        "items" => []
    ],

    "news" => [
        "feed"  => "https://www.tagesschau.de/xml/rss2/",
        "count" => 4
    ],

    "quote" => [
        "items" => [
            "Der Weg ist das Ziel.",
            "Wer nichts wagt, gewinnt nichts.",
            "Ein guter Tag beginnt mit einem Lächeln.",
            "Kleine Schritte sind auch Schritte.",
            "Heute ist ein guter Tag für einen guten Tag.",
            "Was du heute kannst besorgen, das verschiebe nicht auf morgen.",
            "Erfahrung ist die Summe aller Irrtümer."
        ]
    ],

    "slideshow" => [
        "interval" => 8000
    ]

];
