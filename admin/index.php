<?php

require __DIR__ . "/../app/settings.php";
require __DIR__ . "/../app/holidays.php";

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
    "encoding"   => "Die Eingabe enthält ungültige Zeichen.",
    "permission" => "Keine Schreibrechte. Prüfe die Rechte auf data/ und uploads/."
];

$success = $successMessages[$_GET["success"] ?? ""] ?? null;
$error   = $errorMessages[$_GET["error"] ?? ""] ?? null;

$calendarImported = is_file(calendarFile());

// Countdown-Einträge als bearbeitbarer Text: eine Zeile je Eintrag
$countdownText = implode("\n", array_map(
    fn(array $item): string => $item["date"] . " | " . $item["label"],
    $settings["countdown"]["items"] ?? []
));

$isActive = fn(string $module): bool
    => in_array($module, $settings["modules"], true);

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
    <p class="hint">Abgeschaltete Module verschwinden vollständig vom Spiegel.</p>

    <div class="field-group columns">
        <?php foreach ($settings["available_modules"] as $module => $label): ?>
            <label class="checkbox">
                <input type="checkbox" name="modules[]"
                       value="<?= htmlspecialchars($module) ?>"
                       <?= $isActive($module) ? "checked" : "" ?>>
                <?= htmlspecialchars($label) ?>
            </label>
        <?php endforeach; ?>
    </div>


    <h2>Uhr</h2>

    <div class="field-group">
        <label class="checkbox">
            <input type="checkbox" name="clock_show_seconds"
                   <?= $settings["clock"]["show_seconds"] ? "checked" : "" ?>>
            Sekunden anzeigen
        </label>

        <label class="checkbox">
            <input type="checkbox" name="clock_show_week"
                   <?= $settings["clock"]["show_week"] ? "checked" : "" ?>>
            Kalenderwoche anzeigen
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
            <input type="text" name="weather_city" required
                   value="<?= htmlspecialchars($settings["weather"]["city"]) ?>">
        </label>

        <label>
            Land (zweistelliger Code)
            <input type="text" name="weather_country" maxlength="2" pattern="[A-Za-z]{2}"
                   value="<?= htmlspecialchars($settings["weather"]["country"]) ?>">
        </label>

        <div class="columns">
            <label class="checkbox">
                <input type="checkbox" name="weather_forecast"
                       <?= !empty($settings["weather"]["forecast"]) ? "checked" : "" ?>>
                Vorhersage
            </label>
            <label class="checkbox">
                <input type="checkbox" name="weather_sun"
                       <?= !empty($settings["weather"]["sun"]) ? "checked" : "" ?>>
                Sonnenzeiten
            </label>
            <label class="checkbox">
                <input type="checkbox" name="weather_moon"
                       <?= !empty($settings["weather"]["moon"]) ? "checked" : "" ?>>
                Mondphase
            </label>
            <label class="checkbox">
                <input type="checkbox" name="weather_air"
                       <?= !empty($settings["weather"]["air"]) ? "checked" : "" ?>>
                Luftqualität
            </label>
        </div>
    </div>


    <h2>Termine</h2>

    <div class="field-group">
        <label>
            Darstellung
            <select name="calendar_view">
                <option value="agenda" <?= $settings["calendar"]["view"] === "agenda" ? "selected" : "" ?>>Liste der nächsten Termine</option>
                <option value="month" <?= $settings["calendar"]["view"] === "month" ? "selected" : "" ?>>Monatsraster</option>
            </select>
        </label>

        <label>
            Vorausschau in Tagen
            <input type="number" name="calendar_days_ahead" min="1" max="365"
                   value="<?= (int) $settings["calendar"]["days_ahead"] ?>">
        </label>

        <label>
            Höchstens so viele Einträge anzeigen
            <input type="number" name="calendar_max_events" min="1" max="20"
                   value="<?= (int) $settings["calendar"]["max_events"] ?>">
        </label>

        <label class="checkbox">
            <input type="checkbox" name="calendar_show_holidays"
                   <?= !empty($settings["calendar"]["show_holidays"]) ? "checked" : "" ?>>
            Feiertage anzeigen
        </label>

        <label>
            Bundesland (für die regionalen Feiertage)
            <select name="calendar_state">
                <?php foreach (germanStates() as $code => $name): ?>
                    <option value="<?= $code ?>" <?= $settings["calendar"]["state"] === $code ? "selected" : "" ?>>
                        <?= htmlspecialchars($name) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            ICS-Datei importieren
            <input type="file" name="calendar_ics" accept=".ics">
        </label>

        <?php if ($calendarImported): ?>
            <p class="hint">Importiert am <?= htmlspecialchars(date("d.m.Y H:i", filemtime(calendarFile()))) ?></p>
            <label class="checkbox">
                <input type="checkbox" name="calendar_remove">
                Importierten Kalender entfernen
            </label>
        <?php else: ?>
            <p class="hint">Noch kein Kalender importiert.</p>
        <?php endif; ?>
    </div>


    <h2>Countdown</h2>
    <p class="hint">Eine Zeile je Eintrag im Format <code>JJJJ-MM-TT | Beschriftung</code></p>

    <div class="field-group">
        <textarea name="countdown_items" rows="4"
                  placeholder="2026-12-24 | Weihnachten"><?= htmlspecialchars($countdownText) ?></textarea>
    </div>


    <h2>Liste</h2>
    <p class="hint">Eine Zeile je Eintrag. Vom Handy aus pflegbar.</p>

    <div class="field-group">
        <label>
            Überschrift
            <input type="text" name="todo_title"
                   value="<?= htmlspecialchars($settings["todo"]["title"]) ?>">
        </label>

        <textarea name="todo_items" rows="6"
                  placeholder="Milch"><?= htmlspecialchars(implode("\n", $settings["todo"]["items"] ?? [])) ?></textarea>
    </div>


    <h2>Nachrichten</h2>

    <div class="field-group">
        <label>
            RSS-Feed
            <input type="url" name="news_feed" class="wide"
                   value="<?= htmlspecialchars($settings["news"]["feed"]) ?>">
        </label>

        <label>
            Anzahl Schlagzeilen
            <input type="number" name="news_count" min="1" max="10"
                   value="<?= (int) $settings["news"]["count"] ?>">
        </label>
    </div>


    <h2>Zitate</h2>
    <p class="hint">Eine Zeile je Zitat. Der Spiegel wechselt täglich.</p>

    <div class="field-group">
        <textarea name="quote_items" rows="6"><?= htmlspecialchars(implode("\n", $settings["quote"]["items"] ?? [])) ?></textarea>
    </div>


    <h2>Diashow</h2>

    <div class="field-group">
        <label>
            Bildwechsel in Sekunden
            <input type="number" name="slideshow_interval" min="1" max="600" step="1"
                   value="<?= htmlspecialchars((string) ($settings["slideshow"]["interval"] / 1000)) ?>">
        </label>
    </div>


    <button type="submit">Einstellungen speichern</button>

</form>


<h2>Bild hochladen</h2>

<form method="POST" action="upload.php" enctype="multipart/form-data">
    <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp" required>
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
                    <input type="hidden" name="delete" value="<?= htmlspecialchars($image) ?>">
                    <button type="submit">Löschen</button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>

<?php endif; ?>

</body>

</html>
