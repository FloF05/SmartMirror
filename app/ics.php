<?php

// ICS-Kalender lesen (RFC 5545), ohne externe Bibliothek.
//
// Abgedeckt ist, was Exporte aus Google, Apple und Outlook im Alltag
// enthalten:
//
//   * ganztägige und terminierte Termine - UTC, mit TZID oder ohne Zone
//   * mehrtägige Termine über DTEND oder DURATION
//   * Serientermine (RRULE): FREQ=DAILY, WEEKLY, MONTHLY, YEARLY mit
//     INTERVAL, COUNT, UNTIL, BYDAY, BYMONTHDAY und BYMONTH
//   * Ausnahmen über EXDATE und einzeln verschobene Termine einer Serie
//     (RECURRENCE-ID)
//   * abgesagte Termine (STATUS:CANCELLED) fallen weg
//
// Nicht abgedeckt sind BYSETPOS, BYWEEKNO, BYYEARDAY, RDATE sowie Serien
// im Stunden- oder Minutentakt. Solche Serien erscheinen nur an ihrem
// ersten Termin - lieber einer zu wenig als falsche Termine.


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
        ['\n', '\N', '\,', '\;', '\\\\'],
        ["\n", "\n", ",", ";", "\\"],
        $value
    );
}


// Zerlegt "DTSTART;TZID=Europe/Berlin:20260825T100000" in
// [Name, Parameter, Wert]. Der Doppelpunkt in einem Parameter in
// Anführungszeichen trennt nicht.
function parseIcsLine(string $line): ?array
{
    $separator = null;
    $inQuotes  = false;
    $length    = strlen($line);

    for ($i = 0; $i < $length; $i++) {

        if ($line[$i] === '"') {
            $inQuotes = !$inQuotes;
        } elseif ($line[$i] === ':' && !$inQuotes) {
            $separator = $i;
            break;
        }
    }

    if ($separator === null) {
        return null;
    }

    $parts  = explode(";", substr($line, 0, $separator));
    $name   = strtoupper(trim(array_shift($parts)));
    $params = [];

    foreach ($parts as $part) {

        $equals = strpos($part, "=");

        if ($equals !== false) {
            $params[strtoupper(substr($part, 0, $equals))] = trim(substr($part, $equals + 1), '"');
        }
    }

    return [$name, $params, substr($line, $separator + 1)];
}


// Outlook schreibt Windows-Namen ("W. Europe Standard Time"), Google
// manchmal Pfade wie "/mozilla.org/20050126_1/Europe/Berlin". Was PHP
// danach immer noch nicht kennt, gilt als lokale Zeit - für einen Spiegel
// in Deutschland ist das fast immer richtig.
function icsTimeZone(?string $tzid): ?DateTimeZone
{
    static $cache = [];

    if ($tzid === null || trim($tzid) === "") {
        return null;
    }

    if (array_key_exists($tzid, $cache)) {
        return $cache[$tzid];
    }

    $windowsNames = [
        "W. Europe Standard Time"       => "Europe/Berlin",
        "Central Europe Standard Time"  => "Europe/Budapest",
        "Central European Standard Time" => "Europe/Warsaw",
        "Romance Standard Time"         => "Europe/Paris",
        "GMT Standard Time"             => "Europe/London",
        "UTC"                           => "UTC"
    ];

    $candidates = [$windowsNames[$tzid] ?? $tzid];

    if (preg_match('~([A-Za-z]+/[A-Za-z_+\-]+(?:/[A-Za-z_+\-]+)?)$~', $tzid, $m)) {
        $candidates[] = $m[1];
    }

    foreach ($candidates as $name) {
        try {
            return $cache[$tzid] = new DateTimeZone($name);
        } catch (Exception $e) {
            // nächsten Kandidaten versuchen
        }
    }

    return $cache[$tzid] = null;
}


// Liefert ["at" => DateTimeImmutable, "allDay" => bool] oder null.
//
// Terminierte Zeitpunkte bleiben in ihrer Ursprungszone. Serien werden in
// dieser Zone berechnet - nur so bleibt ein Termin "jeden Montag 10 Uhr"
// auch über die Zeitumstellung hinweg um 10 Uhr.
function parseIcsDateTime(string $value, array $params, DateTimeZone $local): ?array
{
    $value = trim($value);

    if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $value, $m)) {

        $at = DateTimeImmutable::createFromFormat('!Y-m-d', "$m[1]-$m[2]-$m[3]", $local);

        return $at ? ["at" => $at, "allDay" => true] : null;
    }

    if (!preg_match('/^(\d{4})(\d{2})(\d{2})T(\d{2})(\d{2})(\d{2})(Z?)$/', $value, $m)) {
        return null;
    }

    $zone = $m[7] === "Z"
        ? new DateTimeZone("UTC")
        : (icsTimeZone($params["TZID"] ?? null) ?? $local);

    $at = DateTimeImmutable::createFromFormat(
        '!Y-m-d H:i:s',
        "$m[1]-$m[2]-$m[3] $m[4]:$m[5]:$m[6]",
        $zone
    );

    return $at ? ["at" => $at, "allDay" => false] : null;
}


function icsWeekday(string $code): int
{
    return array_search($code, ["", "MO", "TU", "WE", "TH", "FR", "SA", "SU"], true) ?: 0;
}


// Zerlegt eine RRULE. null heißt: nicht unterstützt - der Termin wird
// dann wie ein Einzeltermin behandelt.
function parseRrule(string $value, DateTimeZone $local): ?array
{
    $parts = [];

    foreach (explode(";", $value) as $part) {

        [$key, $val] = array_pad(explode("=", $part, 2), 2, "");

        $parts[strtoupper(trim($key))] = strtoupper(trim($val));
    }

    $freq = $parts["FREQ"] ?? "";

    if (!in_array($freq, ["DAILY", "WEEKLY", "MONTHLY", "YEARLY"], true)) {
        return null;
    }

    foreach (["BYSETPOS", "BYWEEKNO", "BYYEARDAY", "BYHOUR", "BYMINUTE", "BYSECOND"] as $unsupported) {
        if (isset($parts[$unsupported])) {
            return null;
        }
    }

    $until = null;

    if (!empty($parts["UNTIL"])) {

        $parsed = parseIcsDateTime($parts["UNTIL"], [], $local);

        if ($parsed !== null) {
            // UNTIL als reines Datum schließt den ganzen Tag ein
            $until = $parsed["allDay"]
                ? $parsed["at"]->setTime(23, 59, 59)
                : $parsed["at"];
        }
    }

    $byDay = [];

    foreach (array_filter(explode(",", $parts["BYDAY"] ?? "")) as $entry) {
        if (preg_match('/^([+-]?\d{1,2})?(MO|TU|WE|TH|FR|SA|SU)$/', trim($entry), $m)) {
            $byDay[] = [(int) $m[1], icsWeekday($m[2])];
        }
    }

    $integers = fn(string $key): array => array_values(array_filter(
        array_map("intval", explode(",", $parts[$key] ?? "")),
        fn(int $number): bool => $number !== 0
    ));

    return [
        "freq"       => $freq,
        "interval"   => max(1, (int) ($parts["INTERVAL"] ?? 1)),
        "count"      => isset($parts["COUNT"]) ? max(1, (int) $parts["COUNT"]) : null,
        "until"      => $until,
        "byDay"      => $byDay,
        "byMonthDay" => $integers("BYMONTHDAY"),
        "byMonth"    => array_values(array_filter(
            $integers("BYMONTH"),
            fn(int $month): bool => $month >= 1 && $month <= 12
        ))
    ];
}


// Liest alle VEVENTs einer Datei.
function readIcsEvents(string $content, DateTimeZone $local): array
{
    $events  = [];
    $current = null;

    // VALARM und andere Unterkomponenten haben eigene Eigenschaften -
    // eine Erinnerung per Mail bringt etwa ein eigenes SUMMARY mit, das
    // sonst den Termintitel überschreiben würde.
    $depth = 0;

    foreach (unfoldIcsLines($content) as $line) {

        $line = rtrim($line);

        if ($line === "BEGIN:VEVENT") {
            $current = [
                "uid"          => "",
                "summary"      => null,
                "start"        => null,
                "allDay"       => false,
                "end"          => null,
                "duration"     => null,
                "rrule"        => null,
                "exdates"      => [],
                "recurrenceId" => null,
                "cancelled"    => false
            ];
            $depth = 0;
            continue;
        }

        if ($current === null) {
            continue;
        }

        if ($line === "END:VEVENT") {

            if ($current["summary"] !== null && $current["start"] !== null && !$current["cancelled"]) {
                $events[] = $current;
            }

            $current = null;
            continue;
        }

        if (str_starts_with($line, "BEGIN:")) {
            $depth++;
            continue;
        }

        if (str_starts_with($line, "END:")) {
            $depth = max(0, $depth - 1);
            continue;
        }

        if ($depth > 0) {
            continue;
        }

        $parsed = parseIcsLine($line);

        if ($parsed === null) {
            continue;
        }

        [$name, $params, $value] = $parsed;

        switch ($name) {

            case "UID":
                $current["uid"] = trim($value);
                break;

            case "SUMMARY":
                $current["summary"] = trim(unescapeIcsText($value));
                break;

            case "STATUS":
                $current["cancelled"] = strtoupper(trim($value)) === "CANCELLED";
                break;

            case "DTSTART":
                $date = parseIcsDateTime($value, $params, $local);

                if ($date !== null) {
                    $current["start"]  = $date["at"];
                    $current["allDay"] = $date["allDay"];
                }
                break;

            case "DTEND":
                $date = parseIcsDateTime($value, $params, $local);

                if ($date !== null) {
                    $current["end"] = $date["at"];
                }
                break;

            case "DURATION":
                // Negative Dauern ergeben keinen Sinn für die Anzeige
                if (!str_starts_with(trim($value), "-")) {
                    try {
                        $current["duration"] = new DateInterval(ltrim(trim($value), "+"));
                    } catch (Exception $e) {
                        // ungültige Dauer - Termin bleibt ohne Ende
                    }
                }
                break;

            case "RRULE":
                $current["rrule"] = parseRrule($value, $local);
                break;

            case "EXDATE":
                foreach (explode(",", $value) as $single) {

                    $date = parseIcsDateTime($single, $params, $local);

                    if ($date !== null) {
                        $current["exdates"][icsOccurrenceKey($date["at"], $date["allDay"], $local)] = true;
                    }
                }
                break;

            case "RECURRENCE-ID":
                $date = parseIcsDateTime($value, $params, $local);

                if ($date !== null) {
                    $current["recurrenceId"] = icsOccurrenceKey($date["at"], $date["allDay"], $local);
                }
                break;
        }
    }

    return $events;
}


// Eindeutiger Schlüssel eines Termins in lokaler Zeit - damit lassen sich
// EXDATE und RECURRENCE-ID mit den berechneten Serienterminen abgleichen,
// auch wenn die eine Angabe in UTC und die andere mit TZID kommt.
function icsOccurrenceKey(DateTimeImmutable $at, bool $allDay, DateTimeZone $local): string
{
    return $allDay
        ? $at->format("Y-m-d")
        : $at->setTimezone($local)->format("Y-m-d H:i");
}


// Mögliche Termine einer Periode, aufsteigend sortiert, dazu der Beginn
// der Periode. Liegt der schon hinter dem Zeitraum, ist die Serie durch.
function rruleCandidates(array $rule, DateTimeImmutable $start, int $period): array
{
    $step = $period * $rule["interval"];

    switch ($rule["freq"]) {

        case "DAILY":
            $day = $start->modify("+{$step} days");

            $matches =
                ($rule["byDay"] === [] || in_array((int) $day->format("N"), array_column($rule["byDay"], 1), true))
                && ($rule["byMonth"] === [] || in_array((int) $day->format("n"), $rule["byMonth"], true))
                && ($rule["byMonthDay"] === [] || in_array((int) $day->format("j"), $rule["byMonthDay"], true));

            return [$day, $matches ? [$day] : []];

        case "WEEKLY":
            // Wochen beginnen am Montag (WKST=MO, der Standard)
            $weekStart = $start
                ->modify("-" . ((int) $start->format("N") - 1) . " days")
                ->modify("+{$step} weeks");

            $weekdays = $rule["byDay"] !== []
                ? array_unique(array_column($rule["byDay"], 1))
                : [(int) $start->format("N")];

            sort($weekdays);

            $days = [];

            foreach ($weekdays as $weekday) {

                $day = $weekStart->modify("+" . ($weekday - 1) . " days");

                if ($rule["byMonth"] === [] || in_array((int) $day->format("n"), $rule["byMonth"], true)) {
                    $days[] = $day;
                }
            }

            return [$weekStart, $days];

        case "MONTHLY":
            $first = $start->modify("first day of this month")->modify("+{$step} months");

            if ($rule["byMonth"] !== [] && !in_array((int) $first->format("n"), $rule["byMonth"], true)) {
                return [$first, []];
            }

            return [$first, monthCandidates($rule, $first, $start)];

        case "YEARLY":
            $year   = (int) $start->format("Y") + $step;
            $months = $rule["byMonth"] !== [] ? $rule["byMonth"] : [(int) $start->format("n")];

            sort($months);

            $days = [];

            foreach ($months as $month) {
                $days = array_merge($days, monthCandidates($rule, $start->setDate($year, $month, 1), $start));
            }

            return [$start->setDate($year, 1, 1), $days];
    }

    return [$start, []];
}


// Termine innerhalb eines Monats nach BYMONTHDAY und BYDAY.
// Ohne beides gilt der Tag aus DTSTART - und wo es den nicht gibt (der
// 31. im April, der 29. Februar außerhalb von Schaltjahren), fällt der
// Termin aus, wie RFC 5545 es vorsieht.
function monthCandidates(array $rule, DateTimeImmutable $first, DateTimeImmutable $start): array
{
    $year        = (int) $first->format("Y");
    $month       = (int) $first->format("n");
    $daysInMonth = (int) $first->format("t");

    $byMonthDay = [];

    foreach ($rule["byMonthDay"] as $number) {

        $day = $number > 0 ? $number : $daysInMonth + $number + 1;

        if ($day >= 1 && $day <= $daysInMonth) {
            $byMonthDay[] = $day;
        }
    }

    $byDay = [];

    foreach ($rule["byDay"] as [$ordinal, $weekday]) {

        // Alle Tage dieses Wochentags im Monat, dann ggf. der n-te davon
        $firstMatch = 1 + (($weekday - (int) $first->format("N") + 7) % 7);
        $matches    = range($firstMatch, $daysInMonth, 7);

        if ($ordinal > 0) {
            $matches = isset($matches[$ordinal - 1]) ? [$matches[$ordinal - 1]] : [];
        } elseif ($ordinal < 0) {
            $index   = count($matches) + $ordinal;
            $matches = $index >= 0 ? [$matches[$index]] : [];
        }

        $byDay = array_merge($byDay, $matches);
    }

    if ($rule["byMonthDay"] !== [] && $rule["byDay"] !== []) {
        // Beides angegeben: nur Tage, die beide Bedingungen erfüllen
        $days = array_intersect($byMonthDay, $byDay);
    } elseif ($rule["byMonthDay"] !== []) {
        $days = $byMonthDay;
    } elseif ($rule["byDay"] !== []) {
        $days = $byDay;
    } else {
        $startDay = (int) $start->format("j");
        $days     = $startDay <= $daysInMonth ? [$startDay] : [];
    }

    $days = array_unique($days);
    sort($days);

    return array_map(
        fn(int $day): DateTimeImmutable => $first->setDate($year, $month, $day),
        $days
    );
}


// Beginnzeitpunkte eines Termins im Zeitraum [$from, $until].
function expandIcsEvent(array $event, DateTimeImmutable $from, DateTimeImmutable $until): array
{
    $start = $event["start"];
    $rule  = $event["rrule"];

    if ($rule === null) {
        return $start >= $from && $start <= $until ? [$start] : [];
    }

    $limit = $rule["until"] !== null && $rule["until"] < $until ? $rule["until"] : $until;

    // Ohne COUNT darf weit Zurückliegendes übersprungen werden. Sonst
    // rechnet der Pi Zero bei einer täglichen Serie seit 2015 jedes Mal
    // Tausende Termine durch, nur um sie wegzuwerfen.
    $firstPeriod = 0;

    if ($rule["count"] === null && $from > $start) {

        $days   = (int) $start->diff($from)->days;
        $months = ((int) $from->format("Y") - (int) $start->format("Y")) * 12
                + ((int) $from->format("n") - (int) $start->format("n"));

        $elapsed = match ($rule["freq"]) {
            "DAILY"   => intdiv($days, $rule["interval"]) - 1,
            "WEEKLY"  => intdiv(intdiv($days, 7), $rule["interval"]) - 1,
            "MONTHLY" => intdiv($months, $rule["interval"]) - 1,
            "YEARLY"  => intdiv(intdiv($months, 12), $rule["interval"]) - 1,
            default   => 0
        };

        $firstPeriod = max(0, $elapsed);
    }

    $result = [];
    $count  = 0;

    // Obergrenze als Sicherung gegen kaputte Regeln
    for ($period = $firstPeriod; $period < $firstPeriod + 5000; $period++) {

        [$periodStart, $candidates] = rruleCandidates($rule, $start, $period);

        if ($periodStart > $limit) {
            break;
        }

        foreach ($candidates as $candidate) {

            // DTSTART ist immer der erste Termin der Serie
            if ($candidate < $start) {
                continue;
            }

            if ($candidate > $limit) {
                break 2;
            }

            $count++;

            if ($rule["count"] !== null && $count > $rule["count"]) {
                break 2;
            }

            if ($candidate >= $from) {
                $result[] = $candidate;
            }
        }
    }

    return $result;
}


// Alle Termine, die den Zeitraum [$from, $until] berühren, mit aufgelösten
// Serien. Jeder Eintrag:
//
//   summary  Titel
//   date     erster Tag (Y-m-d)
//   end      letzter Tag (Y-m-d), nur bei mehrtägigen Terminen, sonst null
//   time     Beginn (H:i), bei ganztägigen Terminen null
function icsOccurrences(string $content, DateTimeImmutable $from, DateTimeImmutable $until): array
{
    $local  = $from->getTimezone();
    $events = readIcsEvents($content, $local);

    // Einzeln verschobene Termine einer Serie stehen als eigenes VEVENT
    // in der Datei. Die Serie muss den ursprünglichen Termin dann auslassen.
    $moved = [];

    foreach ($events as $event) {
        if ($event["recurrenceId"] !== null) {
            $moved[$event["uid"]][$event["recurrenceId"]] = true;
        }
    }

    $result = [];

    foreach ($events as $event) {

        $start = $event["start"];

        if ($event["end"] !== null && $event["end"] > $start) {
            $length = $start->diff($event["end"]);
        } elseif ($event["duration"] !== null) {
            $length = $event["duration"];
        } else {
            // Ganztägig ohne Ende heißt laut RFC: genau dieser eine Tag
            $length = new DateInterval($event["allDay"] ? "P1D" : "PT0S");
        }

        // Ein mehrtägiger Termin, der vor dem Zeitraum beginnt, kann noch
        // hineinreichen - deshalb um seine Länge früher suchen.
        $searchFrom = $from->sub($length);

        foreach (expandIcsEvent($event, $searchFrom, $until) as $occurrence) {

            $key = icsOccurrenceKey($occurrence, $event["allDay"], $local);

            if (isset($event["exdates"][$key])) {
                continue;
            }

            if ($event["recurrenceId"] === null && isset($moved[$event["uid"]][$key])) {
                continue;
            }

            $begin = $occurrence->setTimezone($local);
            $end   = $occurrence->add($length)->setTimezone($local);

            // Das Ende ist exklusiv: ganztägig bis 27. heißt "bis einschließlich 26.",
            // terminiert bis 00:00 Uhr ebenso. Ein Abendtermin, der nach
            // Mitternacht endet, zählt dagegen nicht als mehrtägig - erst
            // ab 24 Stunden Dauer.
            if (!$event["allDay"] && $end->getTimestamp() - $begin->getTimestamp() < 86400) {
                $lastDay = $begin;
            } elseif ($end > $begin && $end->format("H:i:s") === "00:00:00") {
                $lastDay = $end->modify("-1 day");
            } else {
                $lastDay = $end;
            }

            $firstDate = $begin->format("Y-m-d");
            $lastDate  = max($firstDate, $lastDay->format("Y-m-d"));

            if ($lastDate < $from->format("Y-m-d")) {
                continue;
            }

            $result[] = [
                "summary" => $event["summary"],
                "date"    => $firstDate,
                "end"     => $lastDate !== $firstDate ? $lastDate : null,
                "time"    => $event["allDay"] ? null : $begin->format("H:i"),
                "type"    => "event"
            ];
        }
    }

    return $result;
}
