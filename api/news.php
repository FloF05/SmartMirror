<?php

// Liest einen RSS-Feed und liefert die Schlagzeilen. Serverseitig
// zwischengespeichert, damit der Pi nicht bei jedem Seitenaufruf ins Netz
// muss.

require __DIR__ . "/../app/settings.php";

header("Content-Type: application/json");

$settings = loadSettings();
$news     = $settings["news"];

$cacheFile     = mirrorRoot() . "/cache/news.json";
$cacheDuration = 900;

if (
    is_file($cacheFile)
    && (time() - filemtime($cacheFile)) < $cacheDuration
) {
    echo file_get_contents($cacheFile);
    exit;
}

$feedUrl = (string) ($news["feed"] ?? "");
$count   = max(1, min(10, (int) ($news["count"] ?? 4)));

if ($feedUrl === "" || !preg_match('#^https?://#i', $feedUrl)) {
    echo json_encode(["items" => [], "error" => "Keine gültige Feed-Adresse."], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!function_exists("curl_init")) {
    echo json_encode(["items" => [], "error" => "PHP-Erweiterung curl fehlt."], JSON_UNESCAPED_UNICODE);
    exit;
}

$ch = curl_init($feedUrl);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_USERAGENT, "SmartMirror/1.0");

$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

if ($body === false || $code !== 200) {

    // Alte Schlagzeilen sind besser als eine leere Zeile
    if (is_file($cacheFile)) {
        echo file_get_contents($cacheFile);
        exit;
    }

    echo json_encode(["items" => [], "error" => "Feed nicht erreichbar."], JSON_UNESCAPED_UNICODE);
    exit;
}


// SimpleXML ist der saubere Weg, steckt aber in php-xml und ist damit
// nicht überall vorhanden. Der Rückfallweg über reguläre Ausdrücke reicht
// für Titel und Datum eines RSS-Feeds aus.
function parseFeedTitles(string $xml, int $limit): array
{
    $items = [];

    if (function_exists("simplexml_load_string")) {

        $previous = libxml_use_internal_errors(true);
        $feed     = simplexml_load_string($xml);
        libxml_use_internal_errors($previous);

        if ($feed !== false) {

            // RSS 2.0: channel/item, Atom: entry
            $entries = $feed->channel->item ?? $feed->entry ?? [];

            foreach ($entries as $entry) {

                $items[] = [
                    "title" => trim((string) $entry->title),
                    "date"  => trim((string) ($entry->pubDate ?? $entry->updated ?? ""))
                ];

                if (count($items) >= $limit) {
                    break;
                }
            }

            return $items;
        }
    }

    if (preg_match_all('#<item\b.*?</item>#is', $xml, $blocks)) {

        foreach ($blocks[0] as $block) {

            if (!preg_match('#<title>(.*?)</title>#is', $block, $match)) {
                continue;
            }

            $title = $match[1];
            $title = preg_replace('#^\s*<!\[CDATA\[(.*?)\]\]>\s*$#s', '$1', $title);

            $date = "";
            if (preg_match('#<pubDate>(.*?)</pubDate>#is', $block, $dateMatch)) {
                $date = trim($dateMatch[1]);
            }

            $items[] = [
                "title" => trim(html_entity_decode($title, ENT_QUOTES | ENT_XML1, "UTF-8")),
                "date"  => $date
            ];

            if (count($items) >= $limit) {
                break;
            }
        }
    }

    return $items;
}


$items = parseFeedTitles($body, $count);

// Datumsangaben in ein kurzes deutsches Format bringen
foreach ($items as $index => $item) {

    $timestamp = $item["date"] !== "" ? strtotime($item["date"]) : false;

    $items[$index]["time"] = $timestamp !== false
        ? date("H:i", $timestamp)
        : "";

    unset($items[$index]["date"]);
}

$json = json_encode(["items" => $items], JSON_UNESCAPED_UNICODE);

ensureDirectory(dirname($cacheFile));
file_put_contents($cacheFile, $json, LOCK_EX);

echo $json;
