<?php

require "config/config.php";

require "app/module_loader.php";
require "app/css_loader.php";
require "app/js_loader.php";

$reloadRequested = isset($_GET['reload']) && $_GET['reload'] === '1';
$refreshStateFile = __DIR__ . '/uploads/refresh_state.json';
$refreshVersion = 0;

if (file_exists($refreshStateFile)) {
    $decodedRefresh = json_decode(file_get_contents($refreshStateFile), true);
    if (is_array($decodedRefresh)) {
        $refreshVersion = (int) ($decodedRefresh['version'] ?? 0);
    }
}
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

<script>
    const refreshKey = 'smartmirror_refresh_version';
    let currentVersion = parseInt(localStorage.getItem(refreshKey) || '0', 10);

    const checkRefresh = async () => {
        try {
            const response = await fetch('api/refresh_state.php');
            if (!response.ok) {
                return;
            }

            const data = await response.json();
            const serverVersion = parseInt(data.version || '0', 10);

            if (serverVersion > currentVersion) {
                currentVersion = serverVersion;
                localStorage.setItem(refreshKey, String(currentVersion));
                window.location.reload();
            }
        } catch (error) {
            console.log('Refresh check failed', error);
        }
    };

    checkRefresh();
    setInterval(checkRefresh, 2000);
</script>


</body>


</html>
