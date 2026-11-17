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
und konfiguriert Nginx passend. Getestet ist Raspberry Pi OS 13 (Trixie).

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

Beim ersten Mal fragt SSH, ob es dem Host vertrauen soll – mit `yes` bestätigen.
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
* SD-Karte schonen: Swap nur im RAM (zram), Logs in den RAM
* Anzeige einrichten: auf dem Pi Zero W **cog** (WPE WebKit), das ohne
  Desktop und ohne Compositor direkt auf das Display zeichnet. Ab ARMv7
  (Pi 3, Pi 4, Zero 2 W) Chromium in cage.
* Probelauf: prüft, ob der Browser auf dieser CPU überhaupt startet

Auf einem Pi Zero W dauert das **30 bis 45 Minuten**. Das Skript darf
jederzeit erneut laufen – es ändert nur, was noch nicht stimmt.

Danach neu starten:

```bash
sudo reboot
```

Nach dem Neustart erscheint der Spiegel auf dem Display. Auf dem Pi Zero
dauert der erste Seitenaufbau ein bis zwei Minuten.

Steuern lässt sich das Skript über Umgebungsvariablen:

```bash
sudo KIOSK=no deploy/setup.sh                 # nur Server, keine Anzeige
sudo KIOSK_BROWSER=firefox deploy/setup.sh    # anderer Browser
sudo ROTATION=270 deploy/setup.sh             # andere Drehung
sudo UPGRADE=no deploy/setup.sh               # ohne apt upgrade, viel schneller
```

---

## Schritt 6 – Anzeige prüfen

Steht der Spiegel richtig herum auf dem Display, ist dieser Schritt erledigt.
Sonst – oder wenn das Display schwarz bleibt:

```bash
deploy/probe.sh
```

Das Skript liest nur aus und ändert nichts. Es meldet Hardware, Swap,
verfügbare Browser, ob am HDMI-Ausgang ein Display erkannt wird, wie der
Kiosk eingestellt ist und seine letzten Meldungen.

### Bild gedreht oder auf dem Kopf

Browser und Drehung stehen in einer einzigen Datei:

```bash
sudo nano /etc/default/smartmirror-kiosk
```

```ini
KIOSK_BROWSER=cog
ROTATION=90
```

`ROTATION` kennt `0`, `90`, `180` und `270`. Steht das Bild auf dem Kopf,
`270` statt `90` nehmen (oder umgekehrt), bei einem Display, das schon von
sich aus hochkant ist, `0`. Danach:

```bash
sudo systemctl restart smartmirror-kiosk
```

Die Datei liegt bewusst außerhalb des Projekts: `git pull` lässt sie in Ruhe,
und `setup.sh` ändert darin nur, was man ihm ausdrücklich mitgibt
(`KIOSK_BROWSER=…`, `ROTATION=…`).

### Browser wechseln

Läuft cog auf der Hardware nicht, ist Firefox ESR die Rückfallebene:

```bash
sudo UPGRADE=no KIOSK_BROWSER=firefox deploy/setup.sh
sudo reboot
```

> Browser ohne JavaScript – Dillo, NetSurf – scheiden aus. Der Spiegel
> aktualisiert Uhr, Wetter und Kalender vollständig über JavaScript.

---

## Schritt 7 – Inhalte einrichten

Im Browser am PC: **<http://mirror.local/admin/>**

Dort lassen sich einstellen:

* welche Module angezeigt werden
* Uhrformat, Sekundenanzeige, Kalenderwoche
* Wetter-Standort
* Countdown-Termine, Einkaufsliste, Nachrichten-Feed, Zitate
* Bildwechsel-Intervall der Diashow
* Kalenderansicht, Bundesland für Feiertage und ICS-Import
* Bilder hochladen und löschen

Jede Änderung lädt den Spiegel innerhalb von zehn Sekunden von selbst neu –
niemand muss ans Display.

> Die Bilder aus dem alten Aufbau liegen auf dem Windows-PC unter
> `E:\Projekte\SmartMirror\uploads\`. Sie sind bewusst **nicht** Teil des
> Repositories und werden hier einfach wieder hochgeladen.

---

## WLAN wechseln

Raspberry Pi OS verwaltet WLAN mit NetworkManager. Ein weiteres Netz kommt
dazu, ohne dass das bisherige verloren geht – der Pi verbindet sich dann
automatisch mit dem, das gerade erreichbar ist:

```bash
sudo nmcli connection add type wifi con-name "NeuesNetz" ifname wlan0 \
    ssid "NeuesNetz" \
    wifi-sec.key-mgmt wpa-psk wifi-sec.psk "PASSWORT" \
    connection.autoconnect yes connection.autoconnect-priority 10
```

Die höhere Priorität sorgt dafür, dass das neue Netz gewinnt, wenn beide in
Reichweite sind. Prüfen:

```bash
nmcli -f NAME,TYPE,AUTOCONNECT,AUTOCONNECT-PRIORITY connection show
nmcli device wifi list
```

> Kommt der Pi im neuen Netz nicht hoch, ist am Router oft „nur WPA3“
> eingestellt. Der WLAN-Chip des Pi Zero W kann WPA3 nicht zuverlässig –
> am Router auf „WPA2/WPA3“ (Mischbetrieb) stellen.

---

## Alltag: Änderungen ausrollen

Am PC entwickeln, committen, pushen. Auf dem Pi:

```bash
cd ~/Projects/SmartMirror
deploy/update.sh
```

Das holt die Änderungen und lädt den Spiegel neu. Hat sich etwas an der
Einrichtung selbst geändert (Kiosk, Nginx), sagt `update.sh` das und nennt
den Befehl, mit dem es greift.

---

## Wenn etwas nicht läuft

### Das Display bleibt schwarz

```bash
deploy/probe.sh
systemctl status smartmirror-kiosk
journalctl -u smartmirror-kiosk -n 50
```

Häufigste Ursachen:

* **`HDMI-A-1 disconnected`** in `probe.sh` – das Display war beim Booten
  aus oder meldet sich nicht. Display einschalten, Pi neu starten. Hilft das
  nicht, in `/boot/firmware/cmdline.txt` an die eine Zeile
  ` video=HDMI-A-1:1600x900@60D` anhängen (Auflösung des Displays), das `D`
  erzwingt den Ausgang.
* **`Illegal instruction`** oder Rückgabewert 132 – der Browser läuft auf
  dieser CPU nicht. Auf Firefox wechseln (siehe Schritt 6).
* **Der Dienst steht auf `failed`** – nach sechs Fehlstarts in zehn Minuten
  gibt systemd auf, damit der Pi nicht in einer Endlosschleife hängt. Ursache
  steht im Journal. Nach der Korrektur:
  `sudo systemctl reset-failed smartmirror-kiosk && sudo systemctl restart smartmirror-kiosk`
* **Der Browser startet und stirbt nach einer Weile** – meist zu wenig
  Speicher. `free -h` zeigt, ob zram als Swap aktiv ist,
  `journalctl -k | grep -i "out of memory"`, ob der Kernel den Browser
  abgeschossen hat. Weniger Module oder kleinere Bilder helfen.
* **Der Dienst existiert gar nicht** – `setup.sh` lief mit `KIOSK=no`, oder
  die Browser-Pakete ließen sich nicht installieren. `setup.sh` erneut
  ausführen und die Ausgabe lesen.

Hinweis: `setup.sh` schaltet die Textkonsole auf `tty1` ab, weil sie sich
sonst mit dem Kiosk um den Bildschirm streitet. Der Zugang läuft ab dann
über SSH.

### `mirror.local` ist nicht erreichbar

```bash
systemctl status avahi-daemon
hostname          # muss "mirror" ausgeben
```

Ersatzweise über die IP-Adresse aus dem Router verbinden. PC und Pi müssen
im selben Netz sein.

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

* Der ICS-Import löst Serientermine täglich, wöchentlich, monatlich und
  jährlich auf, mit Ausnahmen und verschobenen Einzelterminen. Seltene
  Regeln (`BYSETPOS`, `BYWEEKNO`, `RDATE`, Serien im Stundentakt) erscheinen
  nur an ihrem ersten Termin.
* Der Kalender ist ein Import, kein Abo: Neue Termine kommen erst mit dem
  nächsten Hochladen der ICS-Datei.
* Der Adminbereich hat bewusst keinen Login – er ist für jeden im Heimnetz
  erreichbar.
* Es gibt kein automatisches Backup. Hochgeladene Bilder existieren nur auf
  der SD-Karte des Pi.
