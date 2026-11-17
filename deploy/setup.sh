#!/usr/bin/env bash
#
# Richtet den SmartMirror auf einem frischen Raspberry Pi OS Lite ein.
#
#   sudo deploy/setup.sh
#
# Das Skript darf mehrfach laufen - es ändert nur, was noch nicht stimmt.
#
# Steuern lässt es sich über Umgebungsvariablen:
#
#   KIOSK=auto|yes|no              Anzeige auf dem Display einrichten
#                                  (auto: ja, sofern ein Browser da ist)
#   KIOSK_BROWSER=cog|firefox|chromium
#                                  welcher Browser die Anzeige übernimmt.
#                                  Ohne Angabe: die bisherige Wahl aus
#                                  /etc/default/smartmirror-kiosk, sonst
#                                  cog auf ARMv6 (Pi Zero W) und
#                                  chromium auf allem Neueren.
#   ROTATION=0|90|180|270          Drehung des Bildes. Ohne Angabe: der
#                                  bisherige Wert, sonst 90 (Hochformat).
#   UPGRADE=no                     apt-get upgrade überspringen - spart auf
#                                  dem Pi Zero bei Wiederholungen viel Zeit.
#
# Beispiel:  sudo KIOSK_BROWSER=firefox ROTATION=270 deploy/setup.sh

set -euo pipefail

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

KIOSK="${KIOSK:-auto}"
KIOSK_BROWSER="${KIOSK_BROWSER:-}"
ROTATION="${ROTATION:-}"
UPGRADE="${UPGRADE:-yes}"

KIOSK_CONFIG=/etc/default/smartmirror-kiosk

# ---------------------------------------------------------------------------

info()  { printf "\n\033[1;34m==>\033[0m %s\n" "$*"; }
ok()    { printf "    \033[0;32mok\033[0m  %s\n" "$*"; }
warn()  { printf "    \033[0;33m!\033[0m   %s\n" "$*"; }
fail()  { printf "\n\033[0;31mFehler:\033[0m %s\n\n" "$*" >&2; exit 1; }

[[ $EUID -eq 0 ]] || fail "Bitte mit sudo starten:  sudo deploy/setup.sh"

# Der Benutzer, dem das Projekt gehört - nicht root.
TARGET_USER="${SUDO_USER:-mirror}"
[[ "$TARGET_USER" != "root" ]] || fail "Bitte als normaler Benutzer mit sudo starten, nicht als root."

id "$TARGET_USER" >/dev/null 2>&1 || fail "Benutzer '$TARGET_USER' existiert nicht."
TARGET_UID="$(id -u "$TARGET_USER")"

info "SmartMirror-Einrichtung"
ok "Projekt:  $PROJECT_DIR"
ok "Benutzer: $TARGET_USER (UID $TARGET_UID)"

# Auf einem 512-MB-Pi scheitert systemctl gelegentlich am Speicher. Das
# Skript kommt damit klar - die Dienste sind über ihre Unit-Dateien trotzdem
# aktiviert und starten spätestens beim nächsten Booten.
if ! systemctl --version >/dev/null 2>&1; then
    warn "systemctl antwortet nicht - Dienste starten erst nach dem Neustart."
fi

# /var/log wird weiter unten als tmpfs eingerichtet und ist nach jedem
# Neustart leer. Dienste mit eigenem Log-Unterverzeichnis finden es dann
# nicht mehr und starten nicht - nginx ist genau so ein Fall: ohne
# /var/log/nginx scheitert schon "nginx -t".
#
# systemd-tmpfiles legt die Verzeichnisse beim Booten wieder an, und zwar
# vor den Diensten. Muss ganz am Anfang stehen, damit alles Folgende
# darauf bauen kann.
cat > /etc/tmpfiles.d/smartmirror-logs.conf <<'TMPFILES'
d /var/log/nginx 0755 root adm -
d /var/log/apt   0755 root root -
TMPFILES

mkdir -p /var/log/nginx /var/log/apt
systemd-tmpfiles --create /etc/tmpfiles.d/smartmirror-logs.conf >/dev/null 2>&1 || true
ok "Log-Verzeichnisse vorhanden"

# Ist ein Paket installiert? Direkt über dpkg statt über systemctl -
# das funktioniert auch, wenn systemctl gerade nicht ansprechbar ist.
package_installed() {
    dpkg-query -W -f='${Status}' "$1" 2>/dev/null | grep -q "install ok installed"
}

# --- 1. System und Pakete --------------------------------------------------

info "System aktualisieren"
apt-get update -qq

if [[ "$UPGRADE" == "no" ]]; then
    ok "upgrade übersprungen (UPGRADE=no)"
else
    DEBIAN_FRONTEND=noninteractive apt-get upgrade -y -qq
fi

info "Pakete installieren"
DEBIAN_FRONTEND=noninteractive apt-get install -y -qq \
    nginx \
    php-fpm php-curl php-mbstring \
    git curl \
    avahi-daemon \
    fonts-dejavu-core
ok "Serverpakete installiert"

# --- Browser für die Anzeige wählen ----------------------------------------

ARCH="$(uname -m)"

# Bisherige Einstellungen übernehmen, damit ein erneuter Lauf nichts
# zurückdreht, was nach dem ersten Einrichten angepasst wurde.
PREVIOUS_BROWSER=""
PREVIOUS_ROTATION=""

if [[ -f "$KIOSK_CONFIG" ]]; then
    PREVIOUS_BROWSER="$(sed -n 's/^KIOSK_BROWSER=//p' "$KIOSK_CONFIG" | tail -1)"
    PREVIOUS_ROTATION="$(sed -n 's/^ROTATION=//p' "$KIOSK_CONFIG" | tail -1)"
fi

if [[ -z "$KIOSK_BROWSER" ]]; then
    if [[ -n "$PREVIOUS_BROWSER" ]]; then
        KIOSK_BROWSER="$PREVIOUS_BROWSER"
    elif [[ "$ARCH" == "armv6l" ]]; then
        # Chromium ist zwar paketiert, braucht aber ARMv7. cog ist ein
        # schlanker Kiosk-Browser auf Basis von WPE WebKit und zeichnet
        # direkt auf das Display - ohne Compositor, ohne Desktop.
        KIOSK_BROWSER="cog"
    else
        KIOSK_BROWSER="chromium"
    fi
fi

ROTATION="${ROTATION:-${PREVIOUS_ROTATION:-90}}"

case "$KIOSK_BROWSER" in
    cog|firefox|chromium) ;;
    *) fail "KIOSK_BROWSER=$KIOSK_BROWSER ist unbekannt - erlaubt: cog, firefox, chromium" ;;
esac

case "$ROTATION" in
    0|90|180|270) ;;
    *) fail "ROTATION=$ROTATION ist ungültig - erlaubt: 0, 90, 180, 270" ;;
esac

if [[ "$KIOSK" != "no" ]]; then

    info "Anzeige-Browser installieren"
    ok "Architektur: $ARCH"
    ok "Browser:     $KIOSK_BROWSER"

    # WPE WebKit lädt libGLESv2 erst zur Laufzeit nach und zieht das Paket
    # deshalb nicht als Abhängigkeit mit. Fehlt es, stürzt der
    # Darstellungsprozess beim ersten Seitenaufbau ab ("Couldn't open
    # libGLESv2.so.2") - so geschehen auf dem Pi Zero W.
    case "$KIOSK_BROWSER" in
        cog)      BROWSER_PACKAGES=(cog libgles2 libegl1 libegl-mesa0 libgl1-mesa-dri) ;;
        firefox)  BROWSER_PACKAGES=(firefox-esr cage wlr-randr) ;;
        chromium) BROWSER_PACKAGES=(chromium cage wlr-randr) ;;
    esac

    if [[ "$KIOSK_BROWSER" == "chromium" && "$ARCH" == "armv6l" ]]; then
        warn "Chromium braucht ARMv7 - auf diesem Pi wird es kaum starten."
    fi

    if DEBIAN_FRONTEND=noninteractive apt-get install -y -qq "${BROWSER_PACKAGES[@]}"; then
        ok "Installiert: ${BROWSER_PACKAGES[*]}"
    elif [[ "$KIOSK_BROWSER" == "chromium" ]] \
        && DEBIAN_FRONTEND=noninteractive apt-get install -y -qq chromium-browser cage wlr-randr; then
        # Ältere Versionen von Raspberry Pi OS nennen das Paket anders
        ok "Installiert: chromium-browser cage wlr-randr"
    else
        warn "Pakete für $KIOSK_BROWSER nicht installierbar - Anzeige wird übersprungen."
        warn "Der Server läuft davon unberührt weiter."
        KIOSK="no"
    fi
fi

# --- 2. PHP-Version ermitteln ----------------------------------------------

info "PHP-Version bestimmen"

PHP_VERSION="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
PHP_SOCKET="/run/php/php${PHP_VERSION}-fpm.sock"
PHP_SERVICE="php${PHP_VERSION}-fpm"

# Direkt nach der Unit-Datei schauen statt systemctl fragen: auf einem
# 512-MB-Pi kann systemctl nach einer grossen apt-Transaktion kurzzeitig
# nicht startbar sein ("Could not execute systemctl"). Die Datei liegt
# dann trotzdem da - der Dienst ist also sehr wohl installiert.
unit_installed() {
    local unit="$1"

    [[ -f "/lib/systemd/system/${unit}.service" ]] \
        || [[ -f "/usr/lib/systemd/system/${unit}.service" ]] \
        || [[ -f "/etc/systemd/system/${unit}.service" ]]
}

unit_installed "$PHP_SERVICE" \
    || fail "Dienst ${PHP_SERVICE} nicht gefunden. Ist php-fpm installiert?"

systemctl enable --now "$PHP_SERVICE" >/dev/null 2>&1 || true
ok "PHP $PHP_VERSION erkannt, Socket: $PHP_SOCKET"

# PHP nimmt von Haus aus nur 2 MB pro Datei an - ein Handyfoto hat 3 bis
# 10 MB. Die Verwaltung verkleinert Fotos zwar schon im Browser, das hier
# ist die Absicherung, falls ein Browser das nicht kann.
PHP_UPLOAD_INI="/etc/php/${PHP_VERSION}/fpm/conf.d/99-smartmirror.ini"

if [[ -d "$(dirname "$PHP_UPLOAD_INI")" ]]; then
    cat > "$PHP_UPLOAD_INI" <<'PHPINI'
; SmartMirror (deploy/setup.sh): Fotos direkt vom Handy annehmen
upload_max_filesize = 20M
post_max_size = 24M
PHPINI
    systemctl restart "$PHP_SERVICE" >/dev/null 2>&1 || true
    ok "Uploads bis 20 MB erlaubt"
fi

# --- 3. Verzeichnisse und Rechte -------------------------------------------

info "Verzeichnisse anlegen"

for dir in uploads data cache logs; do
    mkdir -p "$PROJECT_DIR/$dir"
done

# Gruppe www-data, damit PHP-FPM schreiben darf. Das setgid-Bit (2) sorgt
# dafür, dass neu hochgeladene Dateien die Gruppe erben - sonst gehören sie
# www-data allein und der Benutzer mirror kommt nicht mehr heran.
for dir in uploads data cache logs; do
    chown -R "$TARGET_USER:www-data" "$PROJECT_DIR/$dir"

    # Ordner und Dateien getrennt: das setgid-Bit gehört auf Ordner,
    # Bilder und JSON-Dateien sollen nicht ausführbar werden.
    find "$PROJECT_DIR/$dir" -type d -exec chmod 2775 {} +
    find "$PROJECT_DIR/$dir" -type f -exec chmod 664 {} +
done

# Nginx muss sich bis zum Projekt durchhangeln können
chmod o+x "/home/$TARGET_USER" 2>/dev/null || true
ok "uploads/ data/ cache/ logs/ beschreibbar für www-data"

# --- 4. API-Key ------------------------------------------------------------

info "OpenWeather-API-Key"

SECRETS="$PROJECT_DIR/config/secrets.php"

if [[ -f "$SECRETS" ]] && ! grep -q "HIER_DEN_" "$SECRETS"; then
    ok "config/secrets.php ist bereits gefüllt"
else
    cp -n "$PROJECT_DIR/config/secrets.example.php" "$SECRETS" 2>/dev/null || true

    echo
    echo "    Den kostenlosen Key gibt es unter:"
    echo "    https://home.openweathermap.org/api_keys"
    echo
    read -rp "    API-Key (leer lassen und später eintragen): " API_KEY || API_KEY=""

    if [[ -n "$API_KEY" ]]; then
        sed -i "s|HIER_DEN_OPENWEATHER_API_KEY_EINTRAGEN|${API_KEY}|" "$SECRETS"
        ok "API-Key eingetragen"
    else
        warn "Kein Key gesetzt - das Wettermodul bleibt leer."
        warn "Später nachtragen in: config/secrets.php"
    fi

    chown "$TARGET_USER:www-data" "$SECRETS"
    chmod 640 "$SECRETS"
fi

# --- 5. Nginx --------------------------------------------------------------

info "Nginx konfigurieren"


sed -e "s|__PHP_SOCKET__|${PHP_SOCKET}|g" \
    -e "s|__PROJECT_DIR__|${PROJECT_DIR}|g" \
    "$PROJECT_DIR/deploy/nginx-smartmirror.conf" \
    > /etc/nginx/sites-available/smartmirror

ln -sf /etc/nginx/sites-available/smartmirror /etc/nginx/sites-enabled/smartmirror
rm -f /etc/nginx/sites-enabled/default

nginx -t >/dev/null 2>&1 || { nginx -t; fail "Nginx-Konfiguration fehlerhaft."; }

systemctl enable nginx >/dev/null 2>&1 || true

if systemctl restart nginx >/dev/null 2>&1; then
    ok "Nginx läuft"
else
    echo
    systemctl status nginx --no-pager -l 2>&1 | tail -15 || true
    fail "Nginx startet nicht - Ausgabe siehe oben."
fi

# --- 6. Kiosk-Dienst -------------------------------------------------------

KIOSK_SMOKE=""

if [[ "$KIOSK" == "no" ]]; then

    info "Kiosk-Dienst übersprungen"
    ok "Der Spiegel ist über den Browser eines anderen Geräts erreichbar."

else

    info "Kiosk-Dienst einrichten"

    # Zugriff auf Grafik und Eingabegeräte
    usermod -aG video,render,input,tty "$TARGET_USER"

    # /run/user/UID muss es geben, bevor der Browser startet
    loginctl enable-linger "$TARGET_USER" >/dev/null 2>&1 || true

    # Die Einstellungen liegen ausserhalb des Projekts, damit ein git pull
    # sie nicht überschreibt. kiosk.sh liest sie bei jedem Start. Gibt es
    # die Datei schon, werden nur Browser und Drehung angepasst - was dort
    # sonst von Hand eingetragen wurde (VIDEO_MODE etwa), bleibt stehen.
    if [[ -f "$KIOSK_CONFIG" ]] \
        && grep -q '^KIOSK_BROWSER=' "$KIOSK_CONFIG" \
        && grep -q '^ROTATION=' "$KIOSK_CONFIG"; then
        sed -i -e "s|^KIOSK_BROWSER=.*|KIOSK_BROWSER=${KIOSK_BROWSER}|" \
               -e "s|^ROTATION=.*|ROTATION=${ROTATION}|" \
               "$KIOSK_CONFIG"
    else
    cat > "$KIOSK_CONFIG" <<KIOSKCONF
# Anzeige des SmartMirrors - gelesen von deploy/kiosk.sh.
# Nach einer Änderung:  sudo systemctl restart smartmirror-kiosk

# cog (WPE WebKit direkt auf dem Display), firefox oder chromium (beide in cage)
KIOSK_BROWSER=${KIOSK_BROWSER}

# Drehung in Grad: 0, 90, 180, 270. Bild quer oder auf dem Kopf: anderen Wert nehmen.
ROTATION=${ROTATION}

URL=http://localhost/

# Nur cog: Auflösung erzwingen, falls das Display falsch erkannt wird
#VIDEO_MODE=1600x900@60

# Nur cage (firefox, chromium): falls die GPU-Beschleunigung Ärger macht
#WLR_RENDERER=pixman
KIOSKCONF
    fi
    ok "Einstellungen: $KIOSK_CONFIG"

    # Meldet den Kiosk bei systemd-logind als Sitzung an - erst dann
    # bekommt er Bildschirm und Eingabegeräte. Vorbild ist die
    # Dienst-Vorlage aus dem Wiki von cage.
    cat > /etc/pam.d/smartmirror-kiosk <<'PAM'
# PAM-Sitzung für den SmartMirror-Kiosk (deploy/setup.sh)
auth     required pam_unix.so nullok
account  required pam_unix.so
session  required pam_unix.so
session  required pam_systemd.so
PAM

    # Firefox zeigt beim ersten Start Begrüßungs- und Hinweisseiten. Im
    # Kiosk ohne Tastatur würden die den Spiegel dauerhaft verdecken.
    if [[ "$KIOSK_BROWSER" == "firefox" ]]; then
        for policy_dir in /etc/firefox/policies /usr/lib/firefox-esr/distribution; do
            mkdir -p "$policy_dir"
            cat > "$policy_dir/policies.json" <<'POLICIES'
{
  "policies": {
    "OverrideFirstRunPage": "",
    "OverridePostUpdatePage": "",
    "DontCheckDefaultBrowser": true,
    "DisableAppUpdate": true,
    "DisableTelemetry": true,
    "DisableFirefoxStudies": true,
    "DisablePocket": true,
    "UserMessaging": {
      "WhatsNew": false,
      "ExtensionRecommendations": false,
      "FeatureRecommendations": false,
      "UrlbarInterventions": false,
      "SkipOnboarding": true,
      "MoreFromMozilla": false
    }
  }
}
POLICIES
        done
        ok "Firefox-Richtlinien gesetzt (keine Begrüßungsseiten)"
    fi

    chmod +x "$PROJECT_DIR/deploy/kiosk.sh"

    sed -e "s|__USER__|${TARGET_USER}|g" \
        -e "s|__UID__|${TARGET_UID}|g" \
        -e "s|__PROJECT_DIR__|${PROJECT_DIR}|g" \
        "$PROJECT_DIR/deploy/smartmirror-kiosk.service" \
        > /etc/systemd/system/smartmirror-kiosk.service

    # Die Textkonsole darf tty1 nicht belegen, sonst kämpft sie mit dem
    # Kiosk um den Bildschirm
    systemctl disable getty@tty1.service >/dev/null 2>&1 || true

    systemctl daemon-reload >/dev/null 2>&1 || true
    systemctl enable smartmirror-kiosk >/dev/null 2>&1 || true
    ok "Dienst installiert (Browser: $KIOSK_BROWSER, Drehung: $ROTATION Grad)"

    # Läuft der Browser auf dieser CPU überhaupt? Ein kurzer Probelauf
    # ohne Bildschirm verrät es: Stürzt cog sofort ab (etwa mit "Illegal
    # instruction"), ist ein anderer Browser nötig. Hält es durch, bis
    # timeout es beendet (Rückgabewert 124), läuft es.
    if [[ "$KIOSK_BROWSER" == "cog" ]]; then

        info "Probelauf: cog ohne Bildschirm (bis zu 90 Sekunden)"

        SMOKE_DIR="$(runuser -u "$TARGET_USER" -- mktemp -d)"
        SMOKE_RC=0

        runuser -u "$TARGET_USER" -- env XDG_RUNTIME_DIR="$SMOKE_DIR" \
            timeout 90 cog --platform=headless --webprocess-failure=exit http://localhost/ \
            >/tmp/smartmirror-cog-test.log 2>&1 || SMOKE_RC=$?

        rm -rf "$SMOKE_DIR"

        if [[ "$SMOKE_RC" -eq 124 ]]; then
            KIOSK_SMOKE="ok"
            ok "cog lädt die Seite und läuft stabil"
        else
            KIOSK_SMOKE="fail"
            warn "cog hat sich nach dem Start beendet (Rückgabewert $SMOKE_RC)."
            warn "Ausgabe:  cat /tmp/smartmirror-cog-test.log"
            warn "132 heisst \"Illegal instruction\": WebKit läuft auf dieser CPU nicht."
            warn "Dann Firefox probieren:"
            warn "    sudo UPGRADE=no KIOSK_BROWSER=firefox deploy/setup.sh"
        fi
    fi

    # Hängt überhaupt ein Display am HDMI-Ausgang?
    HDMI_STATUS="$(cat /sys/class/drm/card*-HDMI-A-1/status 2>/dev/null | head -1 || true)"

    if [[ "$HDMI_STATUS" == "connected" ]]; then
        ok "Display am HDMI-Ausgang erkannt"
    elif [[ -n "$HDMI_STATUS" ]]; then
        warn "HDMI meldet: $HDMI_STATUS - ist das Display an und angeschlossen?"
    fi
fi

# --- 7. SD-Karte schonen ---------------------------------------------------

info "SD-Karte schonen"

# Swap auf der SD-Karte ist der schnellste Weg, sie kaputtzuschreiben.
# Ersatz ist zram: komprimierter Swap im RAM. Das schont die Karte genauso,
# lässt dem Pi mit seinen 512 MB aber Luft, bevor der Kernel anfängt,
# den Browser abzuschießen.

if package_installed rpi-swap; then

    # Raspberry Pi OS ab Trixie bringt mit rpi-swap schon einen
    # zram-Swap mit, dazu eine Swap-Datei auf der Karte als Überlauf.
    # rpi-swap belegt /dev/zram0 - zram-tools daneben kann nicht starten,
    # genau daran scheiterte zramswap.service hier früher.
    if package_installed zram-tools; then
        systemctl disable --now zramswap >/dev/null 2>&1 || true
        DEBIAN_FRONTEND=noninteractive apt-get purge -y -qq zram-tools >/dev/null 2>&1 || true
        ok "zram-tools entfernt (kollidierte mit rpi-swap)"
    fi

    # Nur zram, keine Swap-Datei auf der SD-Karte
    mkdir -p /etc/rpi/swap.conf.d
    cat > /etc/rpi/swap.conf.d/50-smartmirror.conf <<'SWAP'
# SmartMirror: Swap nur im RAM (zram), keine Swap-Datei auf der SD-Karte
[Main]
Mechanism=zram
SWAP
    ok "rpi-swap auf reinen zram-Swap gestellt (aktiv nach dem Neustart)"

else

    # Ältere Versionen (Bookworm): Swap-Datei abschalten, zram-tools einrichten
    if unit_installed dphys-swapfile; then
        dphys-swapfile swapoff >/dev/null 2>&1 || true
        dphys-swapfile uninstall >/dev/null 2>&1 || true
        systemctl disable dphys-swapfile >/dev/null 2>&1 || true
        ok "Swap auf der SD-Karte deaktiviert"
    fi

    DEBIAN_FRONTEND=noninteractive apt-get install -y -qq zram-tools

    # Nicht jeder Kernel bringt jeden Kompressor mit - auf ARMv6 fehlt zstd
    # regelmäßig. Steht in /etc/default/zramswap ein Verfahren, das der
    # Kernel nicht kann, startet zramswap.service gar nicht erst.
    modprobe zram >/dev/null 2>&1 || true

    ZRAM_ALGO=""

    if [[ -r /sys/block/zram0/comp_algorithm ]]; then
        for algo in zstd lz4 lzo-rle lzo; do
            if grep -qw "$algo" /sys/block/zram0/comp_algorithm; then
                ZRAM_ALGO="$algo"
                break
            fi
        done
    fi

    ZRAM_ALGO="${ZRAM_ALGO:-lzo}"

    cat > /etc/default/zramswap <<ZRAM
ALGO=${ZRAM_ALGO}
PERCENT=50
PRIORITY=100
ZRAM

    systemctl enable zramswap >/dev/null 2>&1 || true

    if systemctl restart zramswap >/dev/null 2>&1; then
        ok "zram als Swap aktiv (Verfahren: $ZRAM_ALGO)"
    else
        warn "zramswap startet nicht. Der Spiegel läuft trotzdem -"
        warn "die SD-Karte wird nur weniger geschont. Ursache zeigt:"
        warn "    systemctl status zramswap"
        warn "    journalctl -xeu zramswap.service"
    fi
fi

# Logs in den RAM. Zusammen mit 'access_log off' in der Nginx-Konfiguration
# schreibt der Spiegel im Normalbetrieb praktisch nichts mehr auf die Karte.
if ! grep -q "^tmpfs /var/log" /etc/fstab; then
    cat >> /etc/fstab <<'FSTAB'

# SmartMirror: Logs in den RAM, um die SD-Karte zu schonen
tmpfs /var/log tmpfs defaults,noatime,nosuid,nodev,size=32M 0 0
tmpfs /tmp     tmpfs defaults,noatime,nosuid,size=64M       0 0
FSTAB
    ok "/var/log und /tmp als tmpfs eingetragen (aktiv nach dem Neustart)"
else
    ok "tmpfs für /var/log bereits eingetragen"
fi

# --- 8. Display nicht abdunkeln --------------------------------------------

info "Bildschirmabschaltung verhindern"

for cmdline in /boot/firmware/cmdline.txt /boot/cmdline.txt; do
    if [[ -f "$cmdline" ]]; then
        if ! grep -q "consoleblank=0" "$cmdline"; then
            sed -i 's/$/ consoleblank=0/' "$cmdline"
            ok "consoleblank=0 ergänzt in $cmdline"
        else
            ok "consoleblank=0 bereits gesetzt"
        fi
        break
    fi
done

# --- Funktionstest ---------------------------------------------------------

info "Funktionstest"

# Kurz warten, PHP-FPM braucht nach dem Neustart einen Moment für den Socket.
sleep 3

http_code() {
    curl -s -o /dev/null -w '%{http_code}' --max-time 20 "$1" 2>/dev/null || echo 000
}

CODE="$(http_code http://localhost/)"
if [[ "$CODE" == "200" ]]; then
    ok "Spiegelseite antwortet"
else
    warn "Spiegelseite antwortet nicht (HTTP $CODE)"
    warn "    systemctl status nginx $PHP_SERVICE"
fi

CODE="$(http_code http://localhost/admin/)"
if [[ "$CODE" == "200" ]]; then
    ok "Adminbereich antwortet"
else
    warn "Adminbereich antwortet nicht (HTTP $CODE)"
fi

# Beweist in einem Rutsch: PHP läuft, die curl-Erweiterung ist da und der
# API-Key wird akzeptiert.
WEATHER="$(curl -s --max-time 20 http://localhost/api/weather.php 2>/dev/null || true)"

if [[ "$WEATHER" == *'"temp"'* ]]; then
    ok "Wetterdaten werden abgerufen"
elif [[ "$WEATHER" == *"401"* || "$WEATHER" == *"Invalid API key"* ]]; then
    warn "OpenWeather lehnt den Key ab. Frisch erstellte Keys brauchen"
    warn "bis zu zwei Stunden, bis sie funktionieren."
else
    warn "Wetterabruf ohne Ergebnis. Antwort war:"
    warn "    ${WEATHER:0:160}"
fi

CALENDAR="$(curl -s --max-time 30 http://localhost/api/calendar.php 2>/dev/null || true)"

if [[ "$CALENDAR" == *'"agenda"'* ]]; then
    ok "Kalender antwortet"
else
    warn "Kalender antwortet nicht:  ${CALENDAR:0:160}"
fi

# --- Fertig ----------------------------------------------------------------

info "Einrichtung abgeschlossen"

cat <<EOF

    Der Spiegel ist erreichbar unter:

        http://mirror.local/          Anzeige
        http://mirror.local/admin/    Verwaltung

    Jetzt neu starten, damit Kiosk, Swap und tmpfs greifen:

        sudo reboot

EOF

if [[ "$KIOSK" == "no" ]]; then
cat <<EOF
    Die Anzeige auf dem Display ist nicht eingerichtet.
    Was auf dieser Hardware zur Verfügung steht, zeigt:

        deploy/probe.sh

EOF
else
cat <<EOF
    Nach dem Neustart zeigt das Display den Spiegel ($KIOSK_BROWSER).
    Der erste Start dauert auf dem Pi Zero ein bis zwei Minuten. Prüfen mit:

        systemctl status smartmirror-kiosk
        deploy/probe.sh          (zeigt auch die Meldungen des Browsers)

    Drehung oder Browser ändern:  sudo nano $KIOSK_CONFIG
    danach:                       sudo systemctl restart smartmirror-kiosk

EOF
fi

if [[ "$KIOSK_SMOKE" == "fail" ]]; then
cat <<EOF
    Achtung: Der Probelauf von cog ist gescheitert (siehe oben).

EOF
fi
