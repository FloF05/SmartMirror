<?php

require __DIR__ . "/../app/settings.php";

header("Content-Type: application/json");

$settings = loadSettings();


// RFC 5545 bricht lange Zeilen um: jede Folgezeile beginnt mit einem
// Leerzeichen oder Tab und gehört an die vorherige angehängt. Ohne diesen
// Schritt reißen Exporte aus Google oder Apple Kalender mitten im
// Termintitel ab.
function unfoldIcsLines(string $content): array
{
    $lines  = preg_split('/\r\n|\n|\r/', $content);
    $result = [];

    foreach ($lines as $line) {

        if ($line !== "" && ($line[0] === " " || $line[0] === "\t")) {

            if ($result !== []) {
                $result[array_key_last($result)] .= substr($line, 1);
                continue;
            }
        }

        $result[] = $line;
    }

    return $result;
}


// Textwerte sind escaped: \n für Zeilenumbruch, \, \; \\ für Sonderzeichen.
function unescapeIcsText(string $value): string
{
    return str_replace(
        ['\\n', '\\N', '\\,', '\\;', '\\\\'],
        ["\n", "\n", ",", ";", "\\"],
        $value
    );
}


// Liefert [Datum als Y-m-d, Uhrzeit als H:i oder null].
// Ganztägige Termine haben nur ein Datum (VALUE=DATE), terminierte
// zusätzlich eine Uhrzeit, teils mit Zeitzone oder als UTC ("Z").
function parseIcsDate(string $value): ?array
{
    $value = trim($value);

    if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $value, $m)) {
        return ["$m[1]-$m[2]-$m[3]", null];
    }

    if (preg_match('/^(\d{8}T\d{6}Z?)$/', $value)) {

        $timestamp = strtotime($value);

        if ($timestamp === false) {
            return null;
        }

        return [date("Y-m-d", $timestamp), date("H:i", $timestamp)];
    }

    return null;
}


function parseIcsFile(string $path): array
{
    $content = file_get_contents($path);

    if ($content === false) {
        return [];
    }

    $events  = [];
    $current = null;

    foreach (unfoldIcsLines($content) as $line) {

        if ($line === "BEGIN:VEVENT") {
            $current = [];
            continue;
        }

        if ($line === "END:VEVENT") {

            if (isset($current["summary"], $current["date"])) {
                $events[] = [
                    "summary" => $current["summary"],
                    "date"    => $current["date"],
                    "time"    => $current["time"] ?? null
                ];
            }

            $current = null;
            continue;
        }

        if ($current === null) {
            continue;
        }

        // Eigenschaft und Wert trennen: NAME;PARAMETER=WERT:Wert
        $separator = strpos($line, ":");

        if ($separator === false) {
            continue;
        }

        $name  = strtoupper(strtok(substr($line, 0, $separator), ";"));
        $value = substr($line, $separator + 1);

        if ($name === "SUMMARY") {
            $current["summary"] = trim(unescapeIcsText($value));
            continue;
        }

        if ($name === "DTSTART") {

            $parsed = parseIcsDate($value);

            if ($parsed !== null) {
                $current["date"] = $parsed[0];
                $current["time"] = $parsed[1];
            }
        }
    }

    usort(
        $events,
        fn(array $a, array $b): int
            => [$a["date"], $a["time"] ?? ""] <=> [$b["date"], $b["time"] ?? ""]
    );

    return $events;
}


$events = is_file(calendarFile())
    ? parseIcsFile(calendarFile())
    : [];

echo json_encode([
    "view"   => $settings["calendar"]["view"],
    "events" => $events
], JSON_UNESCAPED_UNICODE);
