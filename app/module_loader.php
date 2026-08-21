<?php

function loadModule(string $module, array $settings): void
{
    $file = mirrorRoot() . "/modules/" . $module . "/" . $module . ".php";

    if (is_file($file)) {
        include $file;
    }
}
