# SmartMirror – Projektstatus

Stand: 17. November 2026 · **Der Spiegel läuft.**

## Überblick

| | |
|---|---|
| Hardware | Raspberry Pi Zero W (BCM2835, ARMv6, ein Kern, 512 MB RAM) |
| Display | HDMI-Panel 1920 × 1080, hochkant eingebaut, Drehung 90° |
| System | Raspberry Pi OS Lite 13 „Trixie“, 32 Bit, PHP 8.4 |
| Hostname / Benutzer | `mirror` / `mirror` |
| Projektpfad | `/home/mirror/Projects/SmartMirror` |
| Repository | `git@github.com:FloF05/SmartMirror.git` (privat), Branch `main` |
| WLAN | „FRITZ!Box 7690 SU“ (bisher) und „MagentaWLAN-3U9D“ (neuer Standort, Vorrang) |

Ein 64-Bit-System gibt es für den Pi Zero W nicht. Trixie ist die letzte
Generation von Raspberry Pi OS, die ARMv6 noch unterstützt.

## Workflow

```text
VS Code  ->  git commit  ->  git push  ->  GitHub
                                             |
                                             v
                                   deploy/update.sh (auf dem Pi)
```

Der Windows-PC meldet sich per SSH-Schlüssel (`~/.ssh/id_ed25519`) am Pi
an, der Pi mit seinem eigenen Schlüssel bei GitHub.

---

## Was umgesetzt ist

### Webserver und Architektur

* Nginx mit PHP-FPM, Konfiguration als Vorlage in `deploy/`
* Modularer Aufbau, jedes Modul ein Ordner mit `.php`, `.css`, `.js`
* Einstellungen: `config/config.php` (Standard) überschrieben von
  `data/settings.json` (Verwaltung), Zugriff nur über `app/settings.php`
* CSS und JS tragen ihre Änderungszeit als Versionsnummer – der Browser
  zeigt nach einem Update nie veraltete Dateien
* Jede Änderung in der Verwaltung zählt eine Versionsnummer hoch, der
  Spiegel fragt sie alle zehn Sekunden ab und lädt sich selbst neu

### Anzeige

* Typografisches Layout ohne gefüllte Flächen – hinter Spiegelglas würden
  sie milchig glühen
* Einspaltig im Hochformat, zweispaltig im Querformat
* Neun Module: Uhr, Wetter, Countdown, Termine, Liste, Nachrichten, Zitat,
  Diashow, Systeminfo – einzeln abschaltbar
* Systeminfo zeigt die IP-Adresse, damit man die Verwaltung vom Handy aus
  findet

### Kalender

* ICS-Import mit Serienterminen (RRULE: täglich, wöchentlich, monatlich,
  jährlich, mit INTERVAL, COUNT, UNTIL, BYDAY, BYMONTHDAY, BYMONTH)
* Ausnahmen (EXDATE), verschobene Einzeltermine (RECURRENCE-ID), abgesagte
  Termine, mehrtägige Termine
* UTC, TZID und Windows-Zeitzonen aus Outlook
* Deutsche Feiertage je Bundesland, lokal berechnet
* Ergebnis zwischengespeichert in `cache/calendar.json`

Parser: `app/ics.php`. Serien werden in der Zeitzone ihres Starttermins
berechnet, damit „jeden Montag 10 Uhr“ über die Zeitumstellung hinweg
10 Uhr bleibt.

### Verwaltung (vom Handy aus)

* Für Handybildschirme ausgelegt: große Felder, 16-px-Schrift (kein
  Zoomen auf dem iPhone), Speichern-Knopf bleibt unten sichtbar
* Fotos: mehrere auf einmal, werden **im Browser** auf höchstens 1600 px
  verkleinert und richtig gedreht, bevor sie hochgeladen werden. Ein
  12-Megapixel-Foto würde beim Anzeigen sonst rund 48 MB RAM belegen.
* Countdown versteht deutsches Datum (`24.12.2026 Weihnachten`), ungültige
  Zeilen werden gemeldet statt still verworfen

### Kiosk auf dem Pi Zero W

Chromium braucht ARMv7. Die Anzeige übernimmt **cog** (WPE WebKit), das
über DRM/KMS direkt auf das Display zeichnet – ohne X11, ohne Compositor.

```text
deploy/kiosk.sh                   startet den Browser
deploy/smartmirror-kiosk.service  systemd-Dienst, eigene Sitzung auf tty1
/etc/default/smartmirror-kiosk    Browser und Drehung (nicht in Git)
```

Auf dem echten Pi getestet. Zwei Fehler sind dabei aufgetaucht und behoben:

| Fehler | Ursache | Lösung |
|---|---|---|
| `Couldn't open libGLESv2.so.2`, Darstellungsprozess stürzt ab | WebKit lädt die Bibliothek erst zur Laufzeit, `libgles2` ist keine Abhängigkeit von cog | `setup.sh` installiert sie mit |
| `bwrap: Unexpected capabilities`, cog endet mit SIGTRAP | systemd (ab 254) gibt lokalen TTY-Sitzungen `CAP_WAKE_ALARM`, die Sandbox von WebKit lehnt das ab | `kiosk.sh` legt per `setpriv` alle Capabilities ab |

Messwerte: Seite nach etwa 6 Sekunden geladen, danach rund 215 MB RAM frei,
Load um 0,8.

### Deployment und Dauerbetrieb

```text
deploy/
├── setup.sh                    richtet einen frischen Pi komplett ein
├── update.sh                   holt Änderungen und lädt den Spiegel neu
├── kiosk.sh                    startet den Browser auf dem Display
├── probe.sh                    Hardware, Swap, Kiosk-Status, Meldungen
├── nginx-smartmirror.conf      Nginx-Vorlage
└── smartmirror-kiosk.service   systemd-Dienst für die Anzeige
```

* `setup.sh` ist mehrfach ausführbar, prüft am Ende Seite, Verwaltung,
  Wetter und Kalender
* Swap nur im RAM: unter Trixie über rpi-swap (`Mechanism=zram`). Das
  früher zusätzlich installierte zram-tools kollidierte damit und wird
  entfernt.
* `/var/log` und `/tmp` als tmpfs, Log-Verzeichnisse über tmpfiles.d
* Upload-Limit von PHP auf 20 MB (Absicherung, falls ein Browser nicht
  verkleinert)

---

## Wichtig für den Betrieb

**Nicht in Git:** `config/secrets.php` (API-Key), `data/` (Einstellungen,
Kalender), `uploads/` (Fotos), `cache/`. Sobald der Pi eine dieser Dateien
schreibt, würde `git pull` sonst mit einem Konflikt abbrechen. Hochgeladene
Fotos existieren deshalb nur auf der SD-Karte.

**Zeilenenden:** `.gitattributes` erzwingt LF. Ohne das checkt Git unter
Windows die Shell-Skripte mit CRLF aus, und auf dem Pi scheitert jeder
Aufruf mit `bad interpreter: bash^M`.

**Meldungen des Browsers** stehen wegen der eigenen Sitzung nicht unter
`journalctl -u smartmirror-kiosk`, sondern unter ihrem Namen –
`deploy/probe.sh` zeigt sie an.

---

## Offen

### Noch zu tun

* [ ] Am neuen Standort prüfen, ob der Pi ins MagentaWLAN kommt
      (Profil ist eingerichtet, hier nicht in Reichweite)
* [ ] Wetter-Standort und Bundesland in der Verwaltung setzen
* [ ] Fotos hochladen
* [ ] Passwort des Benutzers `mirror` ändern – es ist derzeit leicht zu
      erraten

### Bewusst zurückgestellt

* [ ] Automatische Backups
* [ ] Passwort für die Verwaltung (Heimnetz)

### Mögliche Erweiterungen

* [ ] Kalender als Abo-URL statt Datei-Import
* [ ] Display nachts abschalten
* [ ] QR-Code auf dem Spiegel, der direkt die Verwaltung öffnet
* [ ] Spotify, Home Assistant
