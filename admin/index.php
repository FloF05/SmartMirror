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
    "settings" => "Gespeichert. Der Spiegel zeigt die Änderung in wenigen Sekunden.",
    "upload"   => "Hochgeladen. Der Spiegel zeigt die Bilder in wenigen Sekunden.",
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

// Beim Hochladen mehrerer Bilder: wie viele davon gescheitert sind
$failedUploads = (int) ($_GET["failed"] ?? 0);

if (($_GET["error"] ?? "") === "upload" && $failedUploads > 0) {
    $error = $failedUploads === 1
        ? "Ein Bild konnte nicht hochgeladen werden. Die übrigen sind angekommen."
        : "$failedUploads Bilder konnten nicht hochgeladen werden. Die übrigen sind angekommen.";
}

// Countdown-Zeilen ohne erkennbares Datum werden nicht übernommen - das
// soll nicht still passieren.
$skippedCountdown = (int) ($_GET["skipped"] ?? 0);

$calendarImported = is_file(calendarFile());

// Countdown-Einträge als bearbeitbarer Text: eine Zeile je Eintrag, das
// Datum deutsch geschrieben - so tippt man es auch auf dem Handy.
$countdownText = implode("\n", array_map(
    function (array $item): string {
        $date = DateTimeImmutable::createFromFormat("!Y-m-d", $item["date"]);

        return ($date ? $date->format("d.m.Y") : $item["date"]) . " " . $item["label"];
    },
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

<?php if ($skippedCountdown > 0): ?>
    <p class="notice">
        <?= $skippedCountdown === 1 ? "Eine Countdown-Zeile wurde" : "$skippedCountdown Countdown-Zeilen wurden" ?>
        nicht übernommen, weil kein gültiges Datum davorstand.
        Richtig ist zum Beispiel: <code>24.12.2026 Weihnachten</code>
    </p>
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
    <p class="hint">Eine Zeile je Eintrag: erst das Datum, dann die Beschriftung, zum Beispiel <code>24.12.2026 Weihnachten</code></p>

    <div class="field-group">
        <textarea name="countdown_items" rows="4"
                  placeholder="24.12.2026 Weihnachten"><?= htmlspecialchars($countdownText) ?></textarea>
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


    <div class="save-bar">
        <button type="submit">Einstellungen speichern</button>
    </div>

</form>


<h2>Bilder hochladen</h2>
<p class="hint">Mehrere Fotos auf einmal auswählen geht auch. Sie werden vor dem Hochladen verkleinert – das geht schneller und schont den Spiegel. Querformat füllt die Diashow am besten aus.</p>

<form id="upload-form" method="POST" action="upload.php" enctype="multipart/form-data">
    <input type="file" id="upload-input" name="image" accept="image/*" multiple required>
    <button type="submit">Hochladen</button>
    <p id="upload-status" class="hint" aria-live="polite"></p>
</form>

<script>
(() => {
    const form = document.getElementById("upload-form");
    const input = document.getElementById("upload-input");
    const status = document.getElementById("upload-status");

    // Längste Seite in Pixeln. Größer ist die Diashow auf dem Spiegel nicht -
    // ein Foto mit 12 Megapixeln belegt beim Anzeigen dagegen rund 48 MB
    // Arbeitsspeicher, bei 512 MB im Pi Zero viel zu viel.
    const MAX_EDGE = 1600;

    // Ohne diese Fähigkeiten bleibt es beim normalen Formular
    if (!form || !window.fetch || !window.FormData || !HTMLCanvasElement.prototype.toBlob) {
        return;
    }

    const loadImage = file => new Promise((resolve, reject) => {
        const url = URL.createObjectURL(file);
        const image = new Image();

        image.onload = () => { URL.revokeObjectURL(url); resolve(image); };
        image.onerror = () => { URL.revokeObjectURL(url); reject(new Error("Bild nicht lesbar")); };
        image.src = url;
    });

    // Aktuelle Browser zeichnen Handyfotos schon richtig herum (die Drehung
    // steht bei Handyfotos nur als Vermerk in der Datei). Das neue JPEG
    // trägt die Drehung deshalb direkt in den Pixeln.
    const shrink = async file => {
        const image = await loadImage(file);
        const scale = Math.min(1, MAX_EDGE / Math.max(image.naturalWidth, image.naturalHeight));

        const canvas = document.createElement("canvas");
        canvas.width = Math.max(1, Math.round(image.naturalWidth * scale));
        canvas.height = Math.max(1, Math.round(image.naturalHeight * scale));

        const context = canvas.getContext("2d");
        context.fillStyle = "#000";
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.drawImage(image, 0, 0, canvas.width, canvas.height);

        return new Promise((resolve, reject) => {
            canvas.toBlob(
                blob => blob ? resolve(blob) : reject(new Error("Umwandlung fehlgeschlagen")),
                "image/jpeg",
                0.85
            );
        });
    };

    form.addEventListener("submit", async event => {
        const files = Array.from(input.files || []);

        if (files.length === 0) {
            return;
        }

        event.preventDefault();
        form.querySelector("button").disabled = true;

        let failed = 0;

        for (let i = 0; i < files.length; i += 1) {
            status.textContent = files.length === 1
                ? "Wird hochgeladen …"
                : "Bild " + (i + 1) + " von " + files.length + " wird hochgeladen …";

            try {
                const data = new FormData();
                data.append("image", await shrink(files[i]), "foto.jpg");

                const response = await fetch("upload.php", { method: "POST", body: data });

                if (!response.ok || response.url.includes("error=")) {
                    failed += 1;
                }
            } catch (error) {
                failed += 1;
            }
        }

        window.location.href = failed === 0
            ? "index.php?success=upload"
            : "index.php?error=upload&failed=" + failed;
    });
})();
</script>


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
