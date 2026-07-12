<?php


function loadModule($module)
{


    $file = __DIR__ . "/../modules/" . $module . "/" . $module . ".php";


    if(file_exists($file))
    {

        include $file;

    }


}


?>
