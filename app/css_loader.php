<?php

function loadModuleCSS(array $modules): void
{
    foreach ($modules as $module) {

        $css = "modules/$module/$module.css";

        if (file_exists($css)) {
            echo '<link rel="stylesheet" href="' . $css . '">' . PHP_EOL;
        }
    }
}
?>