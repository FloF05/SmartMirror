<?php

require __DIR__ . "/../app/settings.php";
require __DIR__ . "/../app/holidays.php";
require __DIR__ . "/../app/ics.php";

header("Content-Type: application/json");
header("Cache-Control: no-store");

$settings = loadSettings();
$calendar = $settings["calendar"];

$daysAhead = max(1, (int) ($calendar["days_ahead"] ?? 21));
$maxEvents = max(1, (int) ($calendar["max_events"] ?? 6));
$state     = (string) ($calendar["state"] ?? "");

$local = new DateTimeZone(date_default_timezone_get());
$today = new DateTimeImmutable("today", $local);
$limit = $today->modify("+{$daysAhead} days");

// Das Monatsraster braucht den ganzen laufenden Monat, die Agenda die
// nächsten Tage. Berechnet wird beides in einem Durchgang.
$from  = $today->modify("first day of this month");
$until = max($limit, $today->modify("last day of this month"))->setTime(23, 59, 59);


// --- Cache ---------------------------------------------------------------
//
// Serien aufzulösen kostet auf dem Pi Zero bei einem großen Google-Export
// spürbar Rechenzeit. Das Ergebnis ändert sich aber nur, wenn eine neue
// Datei importiert wird, eine Einstellung sich ändert oder ein neuer Tag
// beginnt - genau daraus entsteht der Schlüssel.
$icsFile  = calendarFile();
$hasIcs   = is_file($icsFile);
$cacheKey = md5(json_encode([
    2,                                   // Formatversion der Antwort
    $hasIcs ? filemtime($icsFile) : 0,
    $hasIcs ? filesize($icsFile) : 0,
    $today->format("Y-m-d"),
    $calendar
]));

$cacheFile = mirrorRoot() . "/cache/calendar.json";

if (is_file($cacheFile)) {

    $cached = json_decode((string) file_get_contents($cacheFile), true);

    if (is_array($cached) && ($cached["key"] ?? "") === $cacheKey && isset($cached["response"])) {
        echo json_encode($cached["response"], JSON_UNESCAPED_UNICODE);
        exit;
    }
}


// --- Termine -------------------------------------------------------------

$events = [];

if ($hasIcs) {
    $content = file_get_contents($icsFile);

    if ($content !== false) {
        $events = icsOccurrences($content, $from, $until);
    }
}

$holidays = !empty($calendar["show_holidays"])
    ? holidaysBetween($state, $from, $until)
    : [];

// Feiertage als ganztägige Einträge in dieselbe Liste, damit die Agenda
// alles in einer chronologischen Reihenfolge zeigt.
foreach ($holidays as $date => $name) {
    $events[] = [
        "summary" => $name,
        "date"    => $date,
        "end"     => null,
        "time"    => null,
        "type"    => "holiday"
    ];
}

$todayKey = $today->format("Y-m-d");
$limitKey = $limit->format("Y-m-d");

// Agenda: alles, was heute oder später noch stattfindet. Ein mehrtägiger
// Termin, der schon läuft, steht unter "Heute".
$agenda = [];

foreach ($events as $event) {

    $lastDay = $event["end"] ?? $event["date"];

    if ($lastDay < $todayKey || $event["date"] > $limitKey) {
        continue;
    }

    $event["day"]     = max($event["date"], $todayKey);
    $event["ongoing"] = $event["date"] < $todayKey;

    $agenda[] = $event;
}

// Pro Tag: Feiertage und Ganztägiges zuerst, dann nach Uhrzeit
$order = fn(array $e, string $dayKey): array => [
    $e[$dayKey],
    $e["type"] === "holiday" ? 0 : 1,
    $e["time"] ?? "",
    $e["summary"]
];

usort($agenda, fn(array $a, array $b): int => $order($a, "day") <=> $order($b, "day"));
usort($events, fn(array $a, array $b): int => $order($a, "date") <=> $order($b, "date"));

$response = [
    "view"     => $calendar["view"] ?? "agenda",
    "today"    => $todayKey,
    "events"   => $events,
    "agenda"   => array_slice($agenda, 0, $maxEvents),
    "holidays" => $holidays
];

if (ensureDirectory(dirname($cacheFile))) {
    @file_put_contents(
        $cacheFile,
        json_encode(["key" => $cacheKey, "response" => $response], JSON_UNESCAPED_UNICODE),
        LOCK_EX
    );
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
