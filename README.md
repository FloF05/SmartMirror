# SmartMirror

Webbasierter Smart Mirror auf einem Raspberry Pi Zero W, eingestellt
bequem vom Handy aus.

Ein Nginx mit PHP liefert ein Vollbild-Dashboard aus, das ein Browser
direkt auf dem Display anzeigt. Alle Inhalte lassen sich über eine
Verwaltungsseite im Browser ändern – der Spiegel lädt sich danach von
selbst neu.

## Dokumentation

| Dokument | Für wen |
|---|---|
| **[Bedienung](docs/BEDIENUNG.md)** | Alle, die den Spiegel vom Handy aus einstellen wollen |
| **[Einrichtung](docs/EINRICHTUNG.md)** | Neuaufbau des Raspberry Pi, WLAN-Wechsel, Fehlersuche |
| **[Projektstatus](docs/PROJEKTSTATUS.md)** | Was umgesetzt ist, wie es gebaut ist, was offen ist |

## Module

| Modul | Inhalt |
|---|---|
| Uhr | Uhrzeit, deutsches Datum, Begrüßung, Kalenderwoche |
| Wetter | Aktuell, 4-Tage-Vorhersage, Sonnenzeiten, Mondphase, Luftqualität |
| Countdown | Tage bis zu frei wählbaren Terminen |
| Termine | Agenda oder Monatsraster aus ICS – mit Serien- und mehrtägigen Terminen und deutschen Feiertagen |
| Listen | Einkaufs- oder To-do-Liste |
| Nachrichten | Schlagzeilen aus einem RSS-Feed |
| Zitat | Wechselt täglich, ohne Netzwerk |
| Diashow | Hochgeladene Fotos, zufällige Reihenfolge |
| Systeminfo | Name und Adresse im Netz, CPU-Last, RAM, Laufzeit |

Jedes Modul lässt sich einzeln abschalten.

## Aufbau

```text
SmartMirror/
│
├── app/          Einstellungen, Modul-Lader, Kalender-Parser, Feiertage
├── config/       Standardwerte (config.php) und API-Key (secrets.php)
├── modules/      je Modul ein Ordner mit .php, .css und .js
├── api/          JSON-Endpunkte für Wetter, Kalender, Nachrichten, Systeminfo
├── admin/        Verwaltungsseite
├── css/          Layout des Dashboards
├── deploy/       Setup, Update, Kiosk-Start, Nginx-Vorlage, Prüfskript
├── docs/         Bedienung, Einrichtung, Projektstatus
│
├── data/         Einstellungen und Kalender – nicht in Git
├── uploads/      Fotos – nicht in Git
└── cache/        Wetter, Nachrichten, Kalender – nicht in Git
```

Einstellungen kommen aus `config/config.php` (Standardwerte, versioniert) und
werden von `data/settings.json` überschrieben (in der Verwaltung gesetzt).
Zugriff ausschließlich über `loadSettings()` und `saveSettings()` in
[app/settings.php](app/settings.php).

## Entwicklung

Lokal testen, ohne Raspberry Pi:

```bash
php -S localhost:8000
```

Wetter, Nachrichten und Systeminfo bleiben dabei leer – unter Windows fehlen
die PHP-Erweiterung `curl` und das `/proc`-Dateisystem. Alles andere
funktioniert.

Ausrollen: am PC committen und pushen, dann auf dem Pi

```bash
cd ~/Projects/SmartMirror && deploy/update.sh
```

## Technik

Raspberry Pi Zero W · Raspberry Pi OS Lite 13 „Trixie“ (32 Bit) · Nginx ·
PHP-FPM 8.4 · cog / WPE WebKit direkt auf DRM/KMS · OpenWeather API

Chromium braucht ARMv7 und läuft auf dem Pi Zero W nicht. cog zeichnet
ohne Desktop und ohne Compositor direkt auf das Display – der leichteste
Weg für einen Rechenkern und 512 MB RAM. Ab Pi 3 oder Zero 2 W richtet
`setup.sh` stattdessen Chromium ein.
