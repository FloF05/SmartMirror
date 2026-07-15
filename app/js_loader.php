<?php

function loadModuleJS(array $modules): void
{
    foreach ($modules as $module) {

        $js = "modules/$module/$module.js";

        if (file_exists($js)) {
            echo '<script src="' . $js . '"></script>' . PHP_EOL;
        }
    }
}
?>