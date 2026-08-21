<?php

require __DIR__ . "/../app/settings.php";

$settings = loadSettings();

$images = [];

if (is_dir(uploadsDirectory())) {

    foreach (scandir(uploadsDirectory()) as $file) {

        if (!is_file(uploadsDirectory() . "/" . $file)) {
            continue;
        }

        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        if (in_array($extension, ["jpg", "jpeg", "png", "webp"], true)) {
            $images[] = $file;
        }
    }
}

sort($images);

$successMessages = [
    "settings" => "Einstellungen gespeichert. Der Spiegel lädt sich in wenigen Sekunden neu.",
    "upload"   => "Bild hochgeladen.",
    "delete"   => "Bild gelöscht."
];

$errorMessages = [
    "upload"     => "Die Datei konnte nicht hochgeladen werden. Ist sie zu groß?",
    "type"       => "Dieser Dateityp wird nicht unterstützt.",
    "save"       => "Die Datei konnte nicht gespeichert werden.",
    "delete"     => "Das Bild konnte nicht gelöscht werden.",
    "encoding"   => "Der Ortsname enthält ungültige Zeichen.",
    "permission" => "Keine Schreibrechte. Prüfe die Rechte auf data/ und uploads/."
];

$success = $successMessages[$_GET["success"] ?? ""] ?? null;
$error   = $errorMessages[$_GET["error"] ?? ""] ?? null;

$calendarImported = is_file(calendarFile());

?>
<!DOCTYPE html>
<html lang="de">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>SmartMirror Verwaltung</title>

<link rel="stylesheet" href="style.css">

</head>

<body>

<h1>SmartMirror Verwaltung</h1>

<?php if ($success !== null): ?>
    <p class="success"><?= htmlspecialchars($success) ?></p>
<?php endif; ?>

<?php if ($error !== null): ?>
    <p class="error"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>


<form method="POST" action="save_settings.php" enctype="multipart/form-data">

    <h2>Module</h2>

    <div class="field-group">
        <?php foreach ($settings["available_modules"] as $module => $label): ?>
            <label class="checkbox">
                <input
                    type="checkbox"
                    name="modules[]"
                    value="<?= htmlspecialchars($module) ?>"
                    <?= in_array($module, $settings["modules"], true) ? "checked" : "" ?>
                >
                <?= htmlspecialchars($label) ?>
            </label>
        <?php endforeach; ?>
    </div>


    <h2>Uhr</h2>

    <div class="field-group">
        <label class="checkbox">
            <input
                type="checkbox"
                name="clock_show_seconds"
                <?= $settings["clock"]["show_seconds"] ? "checked" : "" ?>
            >
            Sekunden anzeigen
        </label>

        <label>
            Format
            <select name="clock_format">
                <option value="24" <?= $settings["clock"]["format"] === "24" ? "selected" : "" ?>>24 Stunden</option>
                <option value="12" <?= $settings["clock"]["format"] === "12" ? "selected" : "" ?>>12 Stunden (AM/PM)</option>
            </select>
        </label>
    </div>


    <h2>Wetter</h2>

    <div class="field-group">
        <label>
            Stadt
            <input
                type="text"
                name="weather_city"
                value="<?= htmlspecialchars($settings["weather"]["city"]) ?>"
                required
            >
        </label>

        <label>
            Land (zweistelliger Code, z. B. DE)
            <input
                type="text"
                name="weather_country"
                value="<?= htmlspecialchars($settings["weather"]["country"]) ?>"
                maxlength="2"
                pattern="[A-Za-z]{2}"
            >
        </label>
    </div>


    <h2>Diashow</h2>

    <div class="field-group">
        <label>
            Bildwechsel alle … Sekunden
            <input
                type="number"
                name="slideshow_interval"
                value="<?= htmlspecialchars((string) ($settings["slideshow"]["interval"] / 1000)) ?>"
                min="1"
                max="600"
                step="1"
            >
        </label>
    </div>


    <h2>Kalender</h2>

    <div class="field-group">
        <label>
            Ansicht
            <select name="calendar_view">
                <option value="month" <?= $settings["calendar"]["view"] === "month" ? "selected" : "" ?>>Monat</option>
                <option value="week" <?= $settings["calendar"]["view"] === "week" ? "selected" : "" ?>>Woche</option>
            </select>
        </label>

        <label>
            ICS-Datei importieren
            <input type="file" name="calendar_ics" accept=".ics">
        </label>

        <?php if ($calendarImported): ?>
            <p class="hint">
                Importiert am
                <?= htmlspecialchars(date("d.m.Y H:i", filemtime(calendarFile()))) ?>
            </p>
            <label class="checkbox">
                <input type="checkbox" name="calendar_remove">
                Importierten Kalender entfernen
            </label>
        <?php else: ?>
            <p class="hint">Noch kein Kalender importiert.</p>
        <?php endif; ?>
    </div>


    <button type="submit">Einstellungen speichern</button>

</form>


<h2>Bild hochladen</h2>

<form method="POST" action="upload.php" enctype="multipart/form-data">

    <input
        type="file"
        name="image"
        accept=".jpg,.jpeg,.png,.webp"
        required
    >

    <button type="submit">Hochladen</button>

</form>


<h2>Vorhandene Bilder (<?= count($images) ?>)</h2>

<?php if ($images === []): ?>

    <p class="hint">Noch keine Bilder vorhanden.</p>

<?php else: ?>

    <div class="gallery">

        <?php foreach ($images as $image): ?>

            <div class="image-card">

                <img src="../uploads/<?= rawurlencode($image) ?>" alt="">

                <p><?= htmlspecialchars($image) ?></p>

                <form method="POST" action="delete.php">
                    <input
                        type="hidden"
                        name="delete"
                        value="<?= htmlspecialchars($image) ?>"
                    >
                    <button type="submit">Löschen</button>
                </form>

            </div>

        <?php endforeach; ?>

    </div>

<?php endif; ?>

</body>

</html>
