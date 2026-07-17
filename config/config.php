<?php


$config = [

    "name" => "Mirror",

    "location" => "Berlin",

    "timezone" => "Europe/Berlin",

    "clock" => [

        "show_seconds" => true,

        "format" => "24"

    ],

    "weather" => [

    "city" => "Berlin",

    "country" => "DE",

    "units" => "metric"

],

"slideshow" => [

    "interval" => 5000

],

    "modules" => [

        "clock",
        "weather",
        "slideshow",
        "calendar",
        "info"

    ]

];


?>
