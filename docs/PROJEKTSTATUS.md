# SmartMirror Projekt Status

Stand: November 2026

## Allgemeine Informationen

**Projekt:**
SmartMirror

**Ziel:**
Webbasierter Smart Mirror auf einem Raspberry Pi Zero W mit modularer
Architektur, verwaltet über einen Adminbereich im Browser.

### Hardware

* Raspberry Pi Zero W (BCM2835, ARMv6, ein Kern, 512 MB RAM)
* Display mit HDMI-Controller, Hochformat (Layout für 900 x 1600)
* Raspberry Pi OS Lite 13 "Trixie", 32 Bit (armhf), PHP 8.4

Ein 64-Bit-System gibt es für den Pi Zero W nicht. Trixie ist die letzte
Generation von Raspberry Pi OS, die ARMv6 noch unterstützt.

### Raspberry Pi

**Hostname:**
mirror

**Benutzer:**
mirror

**Projektpfad:**

```text
/home/mirror/Projects/SmartMirror
```

---

# Entwicklungsumgebung

## Hauptentwicklungsrechner

Windows-PC mit Visual Studio Code

## Workflow

```text
VS Code  ->  git commit  ->  git push  ->  GitHub (privat)
                                            |
                                            v
                                   deploy/update.sh
                                            |
                                            v
                                     Raspberry Pi
```

GitHub dient gleichzeitig als Remote Repository, Versionsverwaltung und
Backup der Codebasis. Der Pi greift per SSH-Key darauf zu.

**Repository:**

```text
git@github.com:FloF05/SmartMirror.git
```

**Branch:** main

---

# Aktueller Entwicklungsstand

## Kapitel 1 - Raspberry Pi Einrichtung

**Status:** Abgeschlossen

* [x] Raspberry Pi OS Lite 32 Bit (Trixie) installiert
* [x] WLAN eingerichtet
* [x] SSH aktiviert
* [x] SSH-Key für das private GitHub-Repository
* [x] System aktualisiert

## Kapitel 2 - Webserver

**Status:** Abgeschlossen

* [x] Nginx installiert
* [x] PHP-FPM eingerichtet
* [x] Nginx auf den Projektpfad konfiguriert

Die Nginx-Konfiguration liegt als Vorlage unter
`deploy/nginx-smartmirror.conf`. Die PHP-Version wird beim Einrichten
automatisch erkannt.

## Kapitel 3 - Modulare Architektur

**Status:** Abgeschlossen

* [x] Modul-Lader
* [x] Zentrale Einstellungsverwaltung (app/settings.php)
* [x] Separate Secrets-Datei
* [x] CSS und JS mit Versionsnummer (?v=<Änderungszeit>), damit der
      Browser nach einem Update nie alte Dateien aus dem Cache zeigt

## Kapitel 4 - Layout

**Status:** Abgeschlossen

* [x] Typografisches Layout ohne gefüllte Flächen - hinter Spiegelglas
      würden sie milchig glühen
* [x] Einspaltig im Hochformat (900 x 1600), zweispaltig im Querformat
* [x] Module einzeln abschaltbar, ohne Lücken im Layout

## Kapitel 5 - Module

**Status:** Abgeschlossen

| Modul | Inhalt |
|---|---|
| Uhr | Uhrzeit, deutsches Datum, Begrüßung, Kalenderwoche |
| Wetter | Aktuell, 4-Tage-Vorhersage, Sonnenzeiten, Mondphase, Luftqualität |
| Countdown | Tage bis zu frei wählbaren Terminen |
| Termine | Agenda oder Monatsraster aus ICS, mit Feiertagen |
| Listen | Einkaufs- oder To-do-Liste |
| Nachrichten | RSS-Schlagzeilen |
| Zitat | Zitat des Tages |
| Diashow | Bilder aus uploads/ |
| Systeminfo | Host, CPU-Last, RAM, Uptime |

## Kapitel 6 - Kalender

**Status:** Abgeschlossen

* [x] ICS-Import über den Adminbereich
* [x] Zeilenumbrüche und Escape-Sequenzen nach RFC 5545
* [x] UTC, TZID (auch Windows-Namen aus Outlook) und lokale Zeit
* [x] Serientermine (RRULE): täglich, wöchentlich, monatlich, jährlich
      mit INTERVAL, COUNT, UNTIL, BYDAY, BYMONTHDAY, BYMONTH
* [x] Ausnahmen (EXDATE) und verschobene Einzeltermine (RECURRENCE-ID)
* [x] Mehrtägige Termine ("bis Fr 20.11."), laufende stehen unter "Heute"
* [x] Abgesagte Termine werden ausgeblendet
* [x] Deutsche Feiertage je Bundesland, lokal berechnet
* [x] Ergebnis wird zwischengespeichert (cache/calendar.json) - der Pi
      Zero rechnet Serien nur einmal am Tag oder nach einem Import

Der Parser liegt in `app/ics.php`. Serien werden in der Zeitzone ihres
Starttermins berechnet, damit "jeden Montag 10 Uhr" auch über die
Zeitumstellung hinweg 10 Uhr bleibt.

## Kapitel 7 - Adminbereich und automatisches Neuladen

**Status:** Abgeschlossen

```text
config/config.php     Standardwerte, liegen in Git
        |
        v
data/settings.json    im Adminbereich gesetzte Werte, nur lokal
        |
        v
loadSettings()        was der Spiegel tatsächlich verwendet
```

Jede Änderung im Adminbereich zählt eine Versionsnummer in
`data/refresh_state.json` hoch. Der Spiegel fragt sie alle zehn Sekunden ab
und lädt sich bei einer höheren Nummer selbst neu.

## Kapitel 8 - Anzeige auf dem Display (Kiosk)

**Status:** Umgesetzt, Test auf dem Pi steht aus

Chromium ist für den Pi Zero W keine Option: Es ist zwar paketiert, braucht
aber ARMv7. Die Anzeige übernimmt deshalb **cog**, ein Kiosk-Browser auf
Basis von WPE WebKit. cog zeichnet über DRM/KMS direkt auf das Display -
ohne X11, ohne Wayland-Compositor, ohne Desktop. Das ist der leichteste
Weg, den es für diese Hardware gibt.

```text
deploy/kiosk.sh                  startet den gewählten Browser
deploy/smartmirror-kiosk.service systemd-Dienst, eigene Sitzung auf tty1
/etc/default/smartmirror-kiosk   Browser und Drehung (nicht in Git)
```

* [x] cog direkt auf DRM/KMS, Drehung über den GLES-Renderer
* [x] Firefox ESR in cage als Rückfallebene
* [x] Chromium in cage für ARMv7 und neuer
* [x] Startet erst, wenn der Webserver antwortet
* [x] Neustart nach einem Absturz, aber keine Endlosschleife
* [x] Probelauf in setup.sh prüft, ob WebKit auf der CPU startet
* [ ] Auf dem echten Pi Zero W getestet
* [ ] Richtige Drehung (90 oder 270) am Display bestätigt

## Kapitel 9 - Deployment und Dauerbetrieb

**Status:** Abgeschlossen

```text
deploy/
├── setup.sh                    richtet einen frischen Pi komplett ein
├── update.sh                   holt Änderungen und lädt den Spiegel neu
├── kiosk.sh                    startet den Browser auf dem Display
├── probe.sh                    liest Hardware, Swap und Kiosk-Status aus
├── nginx-smartmirror.conf      Nginx-Vorlage
└── smartmirror-kiosk.service   systemd-Dienst für die Anzeige
```

* [x] setup.sh mehrfach ausführbar, ändert nur, was noch nicht stimmt
* [x] Funktionstest am Ende von setup.sh (Seite, Admin, Wetter, Kalender)
* [x] Swap nur im RAM: unter Trixie über rpi-swap (Mechanism=zram), unter
      Bookworm über zram-tools
* [x] /var/log und /tmp als tmpfs, Log-Verzeichnisse über tmpfiles.d
* [x] Nginx-Zugriffslog abgeschaltet
* [x] Bildschirmabschaltung verhindert (consoleblank=0)

Unter Trixie belegt rpi-swap bereits /dev/zram0. Das früher zusätzlich
installierte zram-tools konnte deshalb nicht starten - setup.sh entfernt
es jetzt und stellt rpi-swap auf reinen zram-Betrieb ohne Swap-Datei auf
der SD-Karte.

---

# Wichtig für den Betrieb

## Was nicht in Git liegt

```text
config/secrets.php    der API-Key
data/                 Einstellungen und importierter Kalender
uploads/              die Bilder
cache/                Wetter-, Nachrichten- und Kalender-Cache
```

Sobald der Pi eine dieser Dateien schreibt, würde `git pull` sonst mit
einem Konflikt abbrechen.

**Folge:** Hochgeladene Bilder existieren nur auf der SD-Karte des Pi. Ein
automatisches Backup gibt es bewusst nicht. Die Originale liegen auf dem
Windows-PC.

## Zeilenenden

`.gitattributes` erzwingt LF für alle Textdateien. Ohne das checkt Git unter
Windows die Shell-Skripte mit CRLF aus, und auf dem Pi scheitert dann jeder
Aufruf mit `bad interpreter: bash^M`.

---

# Offene Punkte

## Abschluss

* [ ] Kiosk auf dem Pi testen, Drehung bestätigen
* [ ] Pi ins neue WLAN bringen (zusätzliches NetworkManager-Profil)

## Bewusst zurückgestellt

* [ ] Automatische Backups
* [ ] Benutzer-Login im Adminbereich (Heimnetz)

## Mögliche Erweiterungen

* [ ] Kalender als Abo-URL statt Datei-Import
* [ ] Display nachts abschalten
* [ ] Spotify-Integration
* [ ] Home-Assistant-Unterstützung

---

# Letzter Stand

Der Smart Mirror ist funktional fertig. Offen ist nur noch der Test der
Anzeige auf dem echten Pi Zero W und der Umzug ins neue WLAN. Die
Anleitung für einen kompletten Neuaufbau steht in `SETUP.md`.
