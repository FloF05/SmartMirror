<?php

function loadModuleJS($modules)
{

    global $config;


    foreach($modules as $module)
    {

        if($module === "slideshow")
        {

            echo "<script>";

            echo "const slideshowInterval = "
            . $config["slideshow"]["interval"]
            . ";";

            echo "</script>";

        }


        $path =
        "modules/"
        . $module
        . "/"
        . $module
        . ".js";


        echo '<script src="' . $path . '"></script>';

    }

}
?>