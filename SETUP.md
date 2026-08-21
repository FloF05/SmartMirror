# SmartMirror auf dem Raspberry Pi einrichten

Anleitung für einen komplett neuen Raspberry Pi. Von der leeren SD-Karte bis
zum laufenden Spiegel sind es fünf Schritte.

---

## Was du brauchst

* Raspberry Pi Zero 2 W
* microSD-Karte (mindestens 8 GB, besser 16 GB)
* Display mit HDMI-Controller
* Raspberry Pi Imager auf dem Windows-PC
* Den OpenWeather-API-Key (falls verlegt: <https://home.openweathermap.org/api_keys>)

---

## Schritt 1 – Image schreiben

Im **Raspberry Pi Imager**:

| Feld | Wert |
|---|---|
| Betriebssystem | Raspberry Pi OS **Lite** (64 Bit) |
| Speicherkarte | die microSD-Karte |

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

## Schritt 3 – Projekt holen

```bash
mkdir -p ~/Projects
cd ~/Projects
git clone https://github.com/FloF05/SmartMirror.git
cd SmartMirror
```

> **Zu einem privaten Repository** kommt der Pi so nicht durch. Dann vorher
> einen SSH-Key anlegen, den öffentlichen Teil bei GitHub unter
> *Settings → SSH and GPG keys* hinterlegen und stattdessen die SSH-Adresse
> klonen:
> ```bash
> ssh-keygen -t ed25519 -C "mirror@raspberry"
> cat ~/.ssh/id_ed25519.pub
> git clone git@github.com:FloF05/SmartMirror.git
> ```

---

## Schritt 4 – Einrichten

```bash
sudo deploy/setup.sh
```

Das Skript erledigt alles Weitere und fragt dabei einmal nach dem
OpenWeather-API-Key:

* System aktualisieren, Pakete installieren
  (Nginx, PHP-FPM, Chromium, cage, avahi)
* PHP-Version selbst erkennen und Nginx passend konfigurieren
  – funktioniert mit Bookworm (PHP 8.2) wie Trixie (PHP 8.4)
* Schreibrechte für `uploads/`, `data/`, `cache/` setzen
* Kiosk-Dienst installieren, der beim Booten startet
* SD-Karte schonen: Swap auf zram, Logs in den RAM

Das Skript darf jederzeit erneut laufen – es ändert nur, was noch nicht stimmt.

Danach neu starten:

```bash
sudo reboot
```

Nach dem Neustart zeigt das Display den Spiegel im Vollbild.

---

## Schritt 5 – Inhalte einrichten

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
* **Chromium startet und stirbt sofort** – meist zu wenig Speicher. Prüfen
  mit `free -h`, ob zram als Swap aktiv ist.

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
