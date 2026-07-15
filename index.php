<?php

require "config/config.php";

require "app/module_loader.php";
require "app/css_loader.php";
require "app/js_loader.php";

?>


<!DOCTYPE html>

<html lang="de">


<head>

<meta charset="UTF-8">

<title>
<?= $config["name"] ?>
</title>


<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/layout.css">

<?php loadModuleCSS($config["modules"]); ?>


</head>


<body>


<div class="mirror">


<?php


foreach($config["modules"] as $module)
{

    loadModule($module);

}


?>


</div>


<?php loadModuleJS($config["modules"]); ?>


</body>


</html>
