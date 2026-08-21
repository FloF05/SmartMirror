# SmartMirror auf dem Raspberry Pi einrichten

Anleitung für einen komplett neuen Raspberry Pi. Von der leeren SD-Karte bis
zum laufenden Spiegel sind es sieben Schritte.

---

## Was du brauchst

* Raspberry Pi Zero W (ARMv6, 512 MB RAM)
* microSD-Karte (mindestens 8 GB, besser 16 GB)
* Display mit HDMI-Controller
* Raspberry Pi Imager auf dem Windows-PC
* Den OpenWeather-API-Key (falls verlegt: <https://home.openweathermap.org/api_keys>)

---

## Schritt 1 – Image schreiben

Im **Raspberry Pi Imager**:

| Feld | Wert |
|---|---|
| Betriebssystem | Raspberry Pi OS **Lite** (32 Bit), aktuelle Version |
| Speicherkarte | die microSD-Karte |

**32 Bit ist Pflicht.** Der Pi Zero W hat einen ARMv6-Kern (BCM2835,
ein Rechenkern). Ein 64-Bit-Image gibt es für diese CPU nicht.

**Lite** ist ebenfalls Pflicht: die Desktop-Version passt nicht neben einen
Browser in 512 MB RAM.

Die genaue OS-Version ist egal, `setup.sh` erkennt die PHP-Version selbst
und konfiguriert Nginx passend.

> **Hinweis zur Anzeige:** Auf ARMv6 gibt es kein Chromium – der Build der
> Raspberry Pi Foundation setzt ARMv7 voraus. Der Webserver läuft auf dieser
> Hardware einwandfrei, die Vollbild-Anzeige auf dem Display braucht aber
> einen leichteren Browser. Welcher hier in Frage kommt, klärt Schritt 6.

Vor dem Schreiben auf **Einstellungen bearbeiten** klicken. Das ist der
wichtigste Teil – hier vorkonfiguriert, entfällt später die gesamte
Ersteinrichtung:

| Einstellung | Wert |
|---|---|
| Hostname | `mirror` |
| Benutzername | `mirror` |
| Passwort | frei wählbar, aber merken |
| WLAN | SSID und Passwort des Heimnetzes |
| WLAN-Land | `DE` |
| Zeitzone | `Europe/Berlin` |
| Tastaturlayout | `de` |
| SSH aktivieren | ja, Passwort-Anmeldung |

Dann schreiben, Karte in den Pi, Strom dran. Der erste Start dauert ein bis
zwei Minuten.

---

## Schritt 2 – Verbinden

Vom Windows-PC aus:

```powershell
ssh mirror@mirror.local
```

> Falls `mirror.local` nicht gefunden wird, findest du die IP-Adresse im
> Router unter den angemeldeten Geräten und verbindest dich damit:
> `ssh mirror@192.168.x.x`

---

## Schritt 3 – SSH-Key für GitHub

Das Repository ist **privat**. Der Pi kommt ohne Schlüssel nicht daran –
dieser Schritt lässt sich also nicht überspringen.

Auf dem Pi einen Schlüssel erzeugen (dreimal Enter, keine Passphrase):

```bash
ssh-keygen -t ed25519 -C "mirror@raspberry"
cat ~/.ssh/id_ed25519.pub
```

Die ausgegebene Zeile komplett kopieren und bei GitHub eintragen:

**<https://github.com/settings/ssh/new>**

| Feld | Wert |
|---|---|
| Title | `Raspberry Pi Mirror` |
| Key type | Authentication Key |
| Key | die kopierte Zeile |

Verbindung testen:

```bash
ssh -T git@github.com
```

Beim ersten Mal nach der Echtheit des Hosts fragt er – mit `yes` bestätigen.
Richtig ist die Antwort `Hi FloF05! You've successfully authenticated`.

---

## Schritt 4 – Projekt holen

```bash
mkdir -p ~/Projects
cd ~/Projects
git clone git@github.com:FloF05/SmartMirror.git
cd SmartMirror
```

---

## Schritt 5 – Einrichten

```bash
sudo deploy/setup.sh
```

Das Skript erledigt alles Weitere und fragt dabei einmal nach dem
OpenWeather-API-Key:

* System aktualisieren, Pakete installieren (Nginx, PHP-FPM, avahi)
* PHP-Version selbst erkennen und Nginx passend konfigurieren
  – funktioniert mit Bookworm (PHP 8.2) wie Trixie (PHP 8.4)
* Schreibrechte für `uploads/`, `data/`, `cache/` setzen
* SD-Karte schonen: Swap auf zram, Logs in den RAM
* Kiosk-Dienst einrichten – **nur wenn die Hardware einen passenden
  Browser hergibt.** Auf ARMv6 wird der Schritt übersprungen, mit
  entsprechendem Hinweis. Der Server läuft trotzdem vollständig.

Auf einem Pi Zero W dauert das **30 bis 45 Minuten**. Das Skript darf
jederzeit erneut laufen – es ändert nur, was noch nicht stimmt.

Danach neu starten:

```bash
sudo reboot
```

Steuern lässt sich der Kiosk-Teil über eine Umgebungsvariable:

```bash
sudo KIOSK=no  deploy/setup.sh    # nur Server
sudo KIOSK=yes deploy/setup.sh    # Kiosk erzwingen
```

---

## Schritt 6 – Anzeige klären (nur ARMv6)

Auf dem Pi Zero W ist offen, welcher Browser die Vollbild-Anzeige übernimmt.
Das Prüfskript beantwortet das – es liest nur aus und ändert nichts:

```bash
deploy/probe.sh
```

Es meldet Architektur, RAM, verfügbare Browser mit JavaScript-Unterstützung,
den Anzeige-Stack (Wayland und X11) und die Grafiktreiber. Aus dieser Ausgabe
ergibt sich, welcher Kiosk-Modus auf dieser Hardware überhaupt möglich ist.

> Browser ohne JavaScript – Dillo, NetSurf – scheiden aus. Der Spiegel
> aktualisiert Uhr, Wetter und Kalender vollständig über JavaScript.

---

## Schritt 7 – Inhalte einrichten

Im Browser am PC: **<http://mirror.local/admin/>**

Dort lassen sich einstellen:

* welche Module angezeigt werden
* Uhrformat und Sekundenanzeige
* Wetter-Standort
* Bildwechsel-Intervall der Diashow
* Kalenderansicht (Monat oder Woche) und ICS-Import
* Bilder hochladen und löschen

Jede Änderung lädt den Spiegel innerhalb von zehn Sekunden von selbst neu –
niemand muss ans Display.

> Die Bilder aus dem alten Aufbau liegen auf dem Windows-PC unter
> `E:\Projekte\SmartMirror\uploads\`. Sie sind bewusst **nicht** Teil des
> Repositories und werden hier einfach wieder hochgeladen.

---

## Display steht auf dem Kopf?

Die Drehung steuert eine einzige Zeile im Kiosk-Dienst:

```bash
sudo systemctl edit --full smartmirror-kiosk
```

`WLR_OUTPUT_TRANSFORM` auf `270` statt `90` setzen (oder umgekehrt), dann:

```bash
sudo systemctl restart smartmirror-kiosk
```

Mögliche Werte: `normal`, `90`, `180`, `270`.

Bei einem normalen Querformat-Display die Zeile ganz auskommentieren.

---

## Alltag: Änderungen ausrollen

Am PC entwickeln, committen, pushen. Auf dem Pi:

```bash
cd ~/Projects/SmartMirror
deploy/update.sh
```

Das holt die Änderungen und lädt den Spiegel neu.

---

## Wenn etwas nicht läuft

### Das Display bleibt schwarz

```bash
systemctl status smartmirror-kiosk
journalctl -u smartmirror-kiosk -n 50
```

Häufigste Ursachen:

* **`Permission denied` auf `/dev/dri`** – der Benutzer war beim Start noch
  nicht in der Gruppe `video`. Ein Neustart behebt das.
* **Der Browser startet und stirbt sofort** – meist zu wenig Speicher.
  Prüfen mit `free -h`, ob zram als Swap aktiv ist, und mit
  `journalctl -k | grep -i "out of memory"`, ob der Kernel ihn
  abgeschossen hat.
* **Der Dienst existiert gar nicht** – dann hat `setup.sh` den Kiosk-Teil
  übersprungen, weil kein passender Browser gefunden wurde. `deploy/probe.sh`
  zeigt, was zur Verfügung steht.

Hinweis: `setup.sh` schaltet die Textkonsole auf `tty1` ab, weil sie sich
sonst mit dem Kiosk um den Bildschirm streitet. Der Zugang läuft ab dann
über SSH.

### `mirror.local` ist nicht erreichbar

```bash
systemctl status avahi-daemon
hostname          # muss "mirror" ausgeben
```

Ersatzweise über die IP-Adresse aus dem Router verbinden.

### Die Webseite zeigt „502 Bad Gateway"

Nginx findet PHP-FPM nicht:

```bash
ls /run/php/                       # welcher Socket existiert wirklich?
grep fastcgi_pass /etc/nginx/sites-available/smartmirror
```

Stimmen die beiden nicht überein, `sudo deploy/setup.sh` erneut ausführen –
die Erkennung läuft dann neu.

### Das Wetter bleibt leer

```bash
curl http://localhost/api/weather.php
```

Die Antwort nennt den Grund. Meist fehlt der API-Key in
`config/secrets.php`, oder ein frisch erstellter Key ist noch nicht
freigeschaltet – das dauert bei OpenWeather bis zu zwei Stunden.

### Bilder lassen sich nicht hochladen

```bash
ls -la ~/Projects/SmartMirror/uploads
```

Als Gruppe muss `www-data` eingetragen sein, die Rechte müssen `drwxrwsr-x`
lauten. Sonst `sudo deploy/setup.sh` erneut ausführen.

---

## Bekannte Grenzen

* Der ICS-Import liest einzelne Termine. **Serientermine (`RRULE`) werden
  nur an ihrem ersten Datum angezeigt**, mehrtägige Termine nur am Starttag.
* Der Adminbereich hat bewusst keinen Login – er ist für jeden im Heimnetz
  erreichbar.
* Es gibt kein automatisches Backup. Hochgeladene Bilder existieren nur auf
  der SD-Karte des Pi.
