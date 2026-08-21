#!/usr/bin/env bash
#
# Holt die neueste Version aus GitHub und lädt den Spiegel neu.
#
#   deploy/update.sh
#
# Ohne sudo ausführen - gebraucht wird es nur, falls Rechte nachgezogen
# werden müssen.

set -euo pipefail

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_DIR"

info() { printf "\n\033[1;34m==>\033[0m %s\n" "$*"; }
ok()   { printf "    \033[0;32mok\033[0m  %s\n" "$*"; }

info "Änderungen holen"
git pull --ff-only
ok "Stand: $(git log -1 --format='%h %s')"

info "Schreibordner prüfen"
# Die Ordner tragen das setgid-Bit aus setup.sh, neue Dateien erben die
# Gruppe www-data also von selbst. Hier muss nur sichergestellt werden,
# dass die Ordner überhaupt existieren.
for dir in uploads data cache logs; do
    mkdir -p "$dir"
    [[ -w "$dir" ]] || echo "    ! $dir ist nicht beschreibbar - 'sudo deploy/setup.sh' erneut ausführen"
done
ok "uploads/ data/ cache/ logs/"

info "Spiegel neu laden"
# Refresh-Version hochzählen: das offene Chromium-Fenster merkt das beim
# nächsten Poll und lädt sich selbst neu - niemand muss ans Display.
php -r 'require "app/settings.php"; echo "Version " . bumpRefreshVersion() . PHP_EOL;'

echo
ok "Fertig. Der Spiegel aktualisiert sich innerhalb von 10 Sekunden."
echo
