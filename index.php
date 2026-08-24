<?php

require __DIR__ . "/app/settings.php";
require __DIR__ . "/app/module_loader.php";
require __DIR__ . "/app/css_loader.php";
require __DIR__ . "/app/js_loader.php";

// Die Seite selbst nicht zwischenspeichern lassen. Sie verweist auf
// versionierte CSS- und JS-Dateien - liefert der Browser eine alte
// Fassung der Seite aus, greifen auch die alten Versionsnummern.
header("Cache-Control: no-store, must-revalidate");

$settings = loadSettings();
$modules  = $settings["modules"];

// Die Version, mit der diese Seite gerendert wurde. Meldet die API später
// eine höhere, hat jemand im Adminbereich etwas geändert -> neu laden.
$renderedRefreshVersion = refreshVersion();

$mirrorClasses = "mirror";

if (in_array("clock", $modules, true)) {
    $mirrorClasses .= " has-clock";
}

?>
<!DOCTYPE html>
<html lang="de">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title><?= htmlspecialchars($settings["name"]) ?></title>

<link rel="stylesheet" href="<?= assetUrl("css/layout.css") ?>">

<?php loadModuleCSS($modules); ?>

</head>

<body>

<div class="<?= $mirrorClasses ?>">

<?php foreach ($modules as $module) {
    loadModule($module, $settings);
} ?>

</div>

<?php loadModuleJS($modules, $settings); ?>

<script>
    const renderedVersion = <?= $renderedRefreshVersion ?>;

    const checkRefresh = async () => {
        try {
            const response = await fetch('api/refresh_state.php', { cache: 'no-store' });

            if (!response.ok) {
                return;
            }

            const data = await response.json();

            if (parseInt(data.version, 10) > renderedVersion) {
                window.location.reload();
            }
        } catch (error) {
            // Netzwerkaussetzer sind hier belanglos - der nächste Poll fängt es auf.
        }
    };

    setInterval(checkRefresh, 10000);
</script>

</body>

</html>
