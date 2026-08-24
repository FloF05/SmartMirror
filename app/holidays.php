<?php

// Deutsche Feiertage, lokal berechnet - keine API, kein Netzwerk.
//
// Die beweglichen Feiertage hängen alle am Ostersonntag. Der wird über die
// Gaußsche Osterformel bestimmt, damit die PHP-Erweiterung "calendar"
// (easter_date) nicht vorausgesetzt werden muss.


function easterSunday(int $year): DateTimeImmutable
{
    $a = $year % 19;
    $b = intdiv($year, 100);
    $c = $year % 100;
    $d = intdiv($b, 4);
    $e = $b % 4;
    $f = intdiv($b + 8, 25);
    $g = intdiv($b - $f + 1, 3);
    $h = (19 * $a + $b - $d - $g + 15) % 30;
    $i = intdiv($c, 4);
    $k = $c % 4;
    $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
    $m = intdiv($a + 11 * $h + 22 * $l, 451);

    $month = intdiv($h + $l - 7 * $m + 114, 31);
    $day   = (($h + $l - 7 * $m + 114) % 31) + 1;

    return new DateTimeImmutable(
        sprintf('%04d-%02d-%02d', $year, $month, $day)
    );
}


function germanStates(): array
{
    return [
        "BW" => "Baden-Württemberg",
        "BY" => "Bayern",
        "BE" => "Berlin",
        "BB" => "Brandenburg",
        "HB" => "Bremen",
        "HH" => "Hamburg",
        "HE" => "Hessen",
        "MV" => "Mecklenburg-Vorpommern",
        "NI" => "Niedersachsen",
        "NW" => "Nordrhein-Westfalen",
        "RP" => "Rheinland-Pfalz",
        "SL" => "Saarland",
        "SN" => "Sachsen",
        "ST" => "Sachsen-Anhalt",
        "SH" => "Schleswig-Holstein",
        "TH" => "Thüringen"
    ];
}


// Buß- und Bettag: der Mittwoch vor dem 23. November.
function pentitenceDay(int $year): DateTimeImmutable
{
    $reference = new DateTimeImmutable(sprintf('%04d-11-23', $year));

    return $reference->modify('last wednesday');
}


// Liefert [ 'Y-m-d' => 'Name', ... ] für ein Jahr und ein Bundesland.
function germanHolidays(int $year, string $state = ""): array
{
    $easter = easterSunday($year);

    $offset = fn(int $days): string
        => $easter->modify(($days >= 0 ? "+" : "") . $days . " days")->format("Y-m-d");

    $fixed = fn(string $monthDay): string => sprintf("%04d-%s", $year, $monthDay);

    // Bundesweite Feiertage
    $holidays = [
        $fixed("01-01") => "Neujahr",
        $offset(-2)     => "Karfreitag",
        $offset(1)      => "Ostermontag",
        $fixed("05-01") => "Tag der Arbeit",
        $offset(39)     => "Christi Himmelfahrt",
        $offset(50)     => "Pfingstmontag",
        $fixed("10-03") => "Tag der Deutschen Einheit",
        $fixed("12-25") => "1. Weihnachtstag",
        $fixed("12-26") => "2. Weihnachtstag"
    ];

    // Regionale Feiertage: Name => [Datum, Bundesländer]
    $regional = [
        "Heilige Drei Könige"   => [$fixed("01-06"), ["BW", "BY", "ST"]],
        "Internationaler Frauentag" => [$fixed("03-08"), ["BE", "MV"]],
        "Ostersonntag"          => [$offset(0),  ["BB"]],
        "Pfingstsonntag"        => [$offset(49), ["BB"]],
        "Fronleichnam"          => [$offset(60), ["BW", "BY", "HE", "NW", "RP", "SL"]],
        "Mariä Himmelfahrt"     => [$fixed("08-15"), ["SL"]],
        "Weltkindertag"         => [$fixed("09-20"), ["TH"]],
        "Reformationstag"       => [$fixed("10-31"), ["BB", "HB", "HH", "MV", "NI", "SN", "ST", "SH", "TH"]],
        "Allerheiligen"         => [$fixed("11-01"), ["BW", "BY", "NW", "RP", "SL"]],
        "Buß- und Bettag"       => [pentitenceDay($year)->format("Y-m-d"), ["SN"]]
    ];

    foreach ($regional as $name => [$date, $states]) {
        if (in_array($state, $states, true)) {
            $holidays[$date] = $name;
        }
    }

    ksort($holidays);

    return $holidays;
}


// Feiertage ab heute für die nächsten $days Tage.
function upcomingHolidays(string $state, int $days): array
{
    $today = new DateTimeImmutable("today");
    $until = $today->modify("+{$days} days");

    $holidays = germanHolidays((int) $today->format("Y"), $state);

    // Über den Jahreswechsel hinaus mitnehmen
    if ($until->format("Y") !== $today->format("Y")) {
        $holidays += germanHolidays((int) $until->format("Y"), $state);
        ksort($holidays);
    }

    $result = [];

    foreach ($holidays as $date => $name) {
        if ($date >= $today->format("Y-m-d") && $date <= $until->format("Y-m-d")) {
            $result[$date] = $name;
        }
    }

    return $result;
}
