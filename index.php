<?php

require "config/config.php";

require "app/module_loader.php";
require "app/css_loader.php";
require "app/js_loader.php";

$reloadRequested = isset($_GET['reload']) && $_GET['reload'] === '1';
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

<?php if ($reloadRequested): ?>
<script>
    window.addEventListener('load', () => {
        setTimeout(() => {
            window.location.reload();
        }, 1000);
    });
</script>
<?php endif; ?>


</body>


</html>
