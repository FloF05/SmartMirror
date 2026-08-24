# SmartMirror

Webbasierter Smart Mirror auf einem Raspberry Pi Zero W.

Ein Nginx mit PHP liefert ein Vollbild-Dashboard aus, das ein Browser im
Kiosk-Modus auf dem Display anzeigt. Alle Inhalte lassen sich über einen
Adminbereich im Browser verwalten – der Spiegel lädt sich nach jeder
Änderung von selbst neu.

> Auf ARMv6-Hardware (Pi Zero W, Pi 1) richtet `setup.sh` nur den Server
> ein – für diese CPU gibt es kein Chromium. Welcher Browser dort die
> Anzeige übernehmen kann, klärt `deploy/probe.sh`.

## Module

| Modul | Inhalt |
|---|---|
| Uhr | Uhrzeit, deutsches Datum, Begrüßung, Kalenderwoche |
| Wetter | Aktuell, 4-Tage-Vorhersage, Sonnenzeiten, Mondphase, Luftqualität |
| Countdown | Tage bis zu frei wählbaren Terminen |
| Termine | Agenda oder Monatsraster aus ICS, inklusive deutscher Feiertage |
| Listen | Einkaufs- oder To-do-Liste, im Adminbereich pflegbar |
| Nachrichten | Schlagzeilen aus einem RSS-Feed |
| Zitat | Wechselt täglich, ohne Netzwerk |
| Diashow | Bilder aus `uploads/`, zufällige Reihenfolge |
| Systeminfo | Host, CPU-Last, RAM, Uptime |

Jedes Modul lässt sich im Adminbereich einzeln abschalten.

## Einrichtung

Vollständige Anleitung für einen neuen Pi: **[SETUP.md](SETUP.md)**

```bash
git clone https://github.com/FloF05/SmartMirror.git
cd SmartMirror
sudo deploy/setup.sh
```

## Aufbau

```text
SmartMirror/
│
├── app/          Modul-Lader und zentrale Einstellungsverwaltung
├── config/       Standardwerte (config.php) und API-Key (secrets.php)
├── modules/      je Modul ein Ordner mit .php, .css und .js
├── api/          JSON-Endpunkte für Wetter, Kalender, Systeminfo, Refresh
├── admin/        Weboberfläche zur Verwaltung
├── css/          Layout des Dashboards
├── deploy/       Setup-Skript, Nginx-Konfiguration, Kiosk-Dienst
│
├── data/         Laufzeitdaten – nicht in Git
├── uploads/      Bilder – nicht in Git
└── cache/        Wetter-Cache – nicht in Git
```

Einstellungen kommen aus `config/config.php` (Standardwerte, versioniert) und
werden von `data/settings.json` überschrieben (im Adminbereich gesetzt).
Zugriff ausschließlich über `loadSettings()` und `saveSettings()` in
[app/settings.php](app/settings.php).

## Entwicklung

Lokal testen – ohne Raspberry Pi:

```bash
php -S localhost:8000
```

Wetter und Systeminfo bleiben dabei leer: Für das Wetter fehlt die
PHP-Erweiterung `curl`, für die Systeminfo das `/proc`-Dateisystem.
Alles andere funktioniert.

Ausrollen auf den Pi:

```bash
deploy/update.sh
```

## Technik

Raspberry Pi OS Lite · Nginx · PHP-FPM · Chromium im Kiosk-Modus über cage
(Wayland) · OpenWeather API
