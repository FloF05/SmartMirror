<?php

require "config/config.php";

require "app/module_loader.php";

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
<link rel="stylesheet" href="modules/clock/clock.css">


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


<script src="modules/clock/clock.js"></script>


</body>


</html>
