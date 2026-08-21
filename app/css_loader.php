<?php

function loadModuleCSS(array $modules): void
{
    foreach ($modules as $module) {

        $path = "modules/$module/$module.css";

        if (is_file(mirrorRoot() . "/" . $path)) {
            echo '<link rel="stylesheet" href="' . $path . '">' . PHP_EOL;
        }
    }
}
