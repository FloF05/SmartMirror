#!/usr/bin/env bash
#
# Prüft, was auf dieser Hardware für die Display-Anzeige zur Verfügung steht.
#
#   deploy/probe.sh
#
# Ändert nichts am System - liest nur aus. Die Ausgabe komplett kopieren,
# daraus lässt sich der passende Kiosk-Modus bauen.

set -uo pipefail

# Paketnamen und Versionen sind sprachabhängig - Englisch erzwingen.
export LC_ALL=C

line() { printf "\n\033[1;34m== %s\033[0m\n" "$*"; }

# Zeigt an, ob ein Paket installierbar ist und in welcher Version.
check_pkg() {
    local pkg="$1"
    local candidate

    candidate="$(apt-cache policy "$pkg" 2>/dev/null \
        | awk -F': *' '/Candidate:/ {print $2}')"

    if [[ -z "$candidate" || "$candidate" == "(none)" ]]; then
        printf "  %-28s ---\n" "$pkg"
    elif command -v "$pkg" >/dev/null 2>&1; then
        printf "  %-28s %s  (installiert)\n" "$pkg" "$candidate"
    else
        printf "  %-28s %s\n" "$pkg" "$candidate"
    fi
}

line "Hardware"
printf "  %-28s %s\n" "Architektur" "$(uname -m)"
printf "  %-28s %s\n" "Kernel" "$(uname -r)"
printf "  %-28s %s\n" "Modell" "$(tr -d '\0' < /proc/device-tree/model 2>/dev/null || echo unbekannt)"
printf "  %-28s %s\n" "CPU-Kerne" "$(nproc)"
printf "  %-28s %s\n" "RAM" "$(free -h | awk '/^Mem:/ {print $2}')"
printf "  %-28s %s\n" "Swap" "$(free -h | awk '/^Swap:/ {print $2}')"

line "Betriebssystem"
printf "  %-28s %s\n" "Version" "$(. /etc/os-release && echo "$PRETTY_NAME")"
printf "  %-28s %s\n" "PHP" "$(php -r 'echo PHP_VERSION;' 2>/dev/null || echo 'nicht installiert')"

line "Paketquellen aktualisieren"
if [[ $EUID -eq 0 ]]; then
    apt-get update -qq && echo "  aktualisiert"
else
    sudo apt-get update -qq && echo "  aktualisiert"
fi

line "Browser mit JavaScript"
echo "  (Dillo und NetSurf können kein JavaScript und scheiden für den"
echo "   Spiegel aus - sie stehen hier nur zur Vollständigkeit.)"
echo
for pkg in chromium chromium-browser firefox-esr cog surf epiphany-browser \
           luakit midori badwolf netsurf-gtk dillo; do
    check_pkg "$pkg"
done

line "Anzeige-Stack"
echo "  Wayland:"
for pkg in cage seatd wayfire labwc sway; do
    check_pkg "$pkg"
done
echo "  X11:"
for pkg in xserver-xorg xinit matchbox-window-manager unclutter x11-xserver-utils; do
    check_pkg "$pkg"
done

line "Grafiktreiber"
if [[ -d /dev/dri ]]; then
    printf "  %-28s %s\n" "/dev/dri" "$(ls /dev/dri | tr '\n' ' ')"
else
    printf "  %-28s %s\n" "/dev/dri" "nicht vorhanden"
fi
printf "  %-28s %s\n" "Framebuffer" "$(ls /dev/fb* 2>/dev/null | tr '\n' ' ' || echo 'keiner')"

line "Webserver"
printf "  %-28s %s\n" "nginx" "$(systemctl is-active nginx 2>/dev/null || echo 'inaktiv')"
printf "  %-28s %s\n" "php-fpm" "$(systemctl is-active "php$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null)-fpm" 2>/dev/null || echo 'inaktiv')"
printf "  %-28s %s\n" "Seite erreichbar" "$(curl -s -o /dev/null -w '%{http_code}' http://localhost/ 2>/dev/null || echo 'nein')"

printf "\n\033[1;34m== Fertig\033[0m\n"
echo "  Die komplette Ausgabe kopieren - daraus ergibt sich der Kiosk-Modus."
echo
