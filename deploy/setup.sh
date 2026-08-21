#!/usr/bin/env bash
#
# Richtet den SmartMirror auf einem frischen Raspberry Pi OS Lite ein.
#
#   sudo deploy/setup.sh
#
# Das Skript darf mehrfach laufen - es ändert nur, was noch nicht stimmt.

set -euo pipefail

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ROTATION="${ROTATION:-90}"

# Der Kiosk-Teil (Browser im Vollbild auf dem Display) braucht Hardware, die
# einen aktuellen Browser packt. Auf ARMv6 - Pi Zero W, Pi 1 - gibt es kein
# Chromium; welcher Browser dort läuft, klärt deploy/probe.sh.
#
#   sudo deploy/setup.sh                 Server, Kiosk nur wenn möglich
#   sudo KIOSK=no deploy/setup.sh        nur Server
#   sudo KIOSK=yes deploy/setup.sh       Kiosk erzwingen
KIOSK="${KIOSK:-auto}"

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

# --- 1. System und Pakete --------------------------------------------------

info "System aktualisieren"
apt-get update -qq
DEBIAN_FRONTEND=noninteractive apt-get upgrade -y -qq

info "Pakete installieren"
DEBIAN_FRONTEND=noninteractive apt-get install -y -qq \
    nginx \
    php-fpm php-curl php-mbstring \
    git curl \
    avahi-daemon \
    zram-tools
ok "Serverpakete installiert"

# --- Kiosk-Fähigkeit klären ------------------------------------------------

ARCH="$(uname -m)"
CHROMIUM=""

if [[ "$KIOSK" != "no" ]]; then

    info "Anzeige-Umgebung prüfen"
    ok "Architektur: $ARCH"

    if [[ "$ARCH" == "armv6l" && "$KIOSK" == "auto" ]]; then
        warn "ARMv6 erkannt - für diese CPU gibt es kein Chromium."
        warn "Der Kiosk-Teil wird übersprungen, der Server läuft trotzdem."
        warn "Welcher Browser hier läuft, klärt:  deploy/probe.sh"
        KIOSK="no"
    else
        # Der Paketname unterscheidet sich je nach OS-Version.
        for candidate in chromium chromium-browser; do
            if command -v "$candidate" >/dev/null 2>&1; then
                CHROMIUM="$(command -v "$candidate")"
                break
            fi
        done

        if [[ -z "$CHROMIUM" ]]; then
            DEBIAN_FRONTEND=noninteractive apt-get install -y -qq chromium 2>/dev/null \
                || DEBIAN_FRONTEND=noninteractive apt-get install -y -qq chromium-browser 2>/dev/null \
                || true
            CHROMIUM="$(command -v chromium 2>/dev/null || command -v chromium-browser 2>/dev/null || true)"
        fi

        if [[ -z "$CHROMIUM" ]]; then
            warn "Kein Chromium installierbar - Kiosk-Teil wird übersprungen."
            warn "Alternativen zeigt:  deploy/probe.sh"
            KIOSK="no"
        else
            DEBIAN_FRONTEND=noninteractive apt-get install -y -qq cage seatd || {
                warn "cage nicht verfügbar - Kiosk-Teil wird übersprungen."
                KIOSK="no"
            }
            [[ "$KIOSK" == "no" ]] || ok "Browser: $CHROMIUM"
        fi
    fi
fi

# --- 2. PHP-Version ermitteln ----------------------------------------------

info "PHP-Version bestimmen"

PHP_VERSION="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
PHP_SOCKET="/run/php/php${PHP_VERSION}-fpm.sock"
PHP_SERVICE="php${PHP_VERSION}-fpm"

systemctl list-unit-files | grep -q "^${PHP_SERVICE}.service" \
    || fail "Dienst ${PHP_SERVICE} nicht gefunden. Ist php-fpm installiert?"

systemctl enable --now "$PHP_SERVICE" >/dev/null 2>&1 || true
ok "PHP $PHP_VERSION über $PHP_SOCKET"

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
systemctl restart nginx
ok "Nginx läuft"

# --- 6. Kiosk-Dienst -------------------------------------------------------

if [[ "$KIOSK" == "no" ]]; then

    info "Kiosk-Dienst übersprungen"
    ok "Der Spiegel ist über den Browser eines anderen Geräts erreichbar."

else

    info "Kiosk-Dienst einrichten"

    # Zugriff auf Grafik und Eingabegeräte
    usermod -aG video,render,input,tty "$TARGET_USER"

    # Ohne linger gibt es kein /run/user/UID und cage startet nicht
    loginctl enable-linger "$TARGET_USER" >/dev/null 2>&1 || true
    systemctl enable --now seatd >/dev/null 2>&1 || true

    sed -e "s|__USER__|${TARGET_USER}|g" \
        -e "s|__UID__|${TARGET_UID}|g" \
        -e "s|__CHROMIUM__|${CHROMIUM}|g" \
        -e "s|__ROTATION__|${ROTATION}|g" \
        "$PROJECT_DIR/deploy/smartmirror-kiosk.service" \
        > /etc/systemd/system/smartmirror-kiosk.service

    # Die Textkonsole darf tty1 nicht belegen, sonst kämpft sie mit cage darum
    systemctl disable getty@tty1.service >/dev/null 2>&1 || true

    systemctl daemon-reload
    systemctl enable smartmirror-kiosk >/dev/null 2>&1
    ok "Dienst installiert (Drehung: $ROTATION Grad)"

fi

# --- 7. SD-Karte schonen ---------------------------------------------------

info "SD-Karte schonen"

# Swap auf der SD-Karte ist der schnellste Weg, sie kaputtzuschreiben.
# Ersatz ist zram: komprimierter Swap im RAM. Das schont die Karte genauso,
# lässt dem Pi Zero 2 W mit seinen 512 MB aber Luft, bevor der Kernel
# anfängt, Chromium abzuschießen.
if systemctl list-unit-files | grep -q "^dphys-swapfile"; then
    dphys-swapfile swapoff >/dev/null 2>&1 || true
    dphys-swapfile uninstall >/dev/null 2>&1 || true
    systemctl disable dphys-swapfile >/dev/null 2>&1 || true
    ok "Swap auf der SD-Karte deaktiviert"
fi

cat > /etc/default/zramswap <<'ZRAM'
ALGO=zstd
PERCENT=50
PRIORITY=100
ZRAM
systemctl enable --now zramswap >/dev/null 2>&1 || systemctl restart zramswap || true
ok "zram als Swap aktiv"

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

# --- Fertig ----------------------------------------------------------------

info "Einrichtung abgeschlossen"

cat <<EOF

    Der Spiegel ist erreichbar unter:

        http://mirror.local/          Anzeige
        http://mirror.local/admin/    Verwaltung

    Jetzt neu starten, damit tmpfs und zram greifen:

        sudo reboot

EOF

if [[ "$KIOSK" == "no" ]]; then
cat <<EOF
    Die Anzeige auf dem angeschlossenen Display ist noch offen.
    Welcher Browser auf dieser Hardware läuft, zeigt:

        deploy/probe.sh

EOF
else
cat <<EOF
    Nach dem Neustart zeigt das Display den Spiegel. Prüfen mit:

        systemctl status smartmirror-kiosk
        free -h

EOF
fi
