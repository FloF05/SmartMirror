#!/usr/bin/env bash
#
# Startet den SmartMirror im Vollbild auf dem angeschlossenen Display.
#
# Aufgerufen von smartmirror-kiosk.service - nicht von Hand starten,
# solange der Dienst läuft. Eingestellt wird alles in
#
#     /etc/default/smartmirror-kiosk
#
#   KIOSK_BROWSER  cog       WPE WebKit direkt auf dem Display (DRM/KMS),
#                            ganz ohne Compositor. Der leichteste Weg und
#                            der Standard für den Pi Zero W.
#                  firefox   Firefox ESR in cage (Wayland). Rückfallebene,
#                            falls cog auf der Hardware nicht läuft.
#                  chromium  Chromium in cage. Standard ab ARMv7
#                            (Pi 3, Pi 4, Zero 2 W).
#
#   ROTATION       0, 90, 180 oder 270 - Drehung des Bildes in Grad.
#                  Steht das Bild quer oder auf dem Kopf: anderen Wert
#                  probieren. cog und cage zählen nicht zwingend in
#                  dieselbe Richtung - nach einem Browserwechsel prüfen.
#
#   URL            Startseite, Standard http://localhost/
#
#   VIDEO_MODE     optional, nur cog: Auflösung erzwingen, z. B. 1600x900@60

set -uo pipefail

export KIOSK_BROWSER="${KIOSK_BROWSER:-cog}"
export ROTATION="${ROTATION:-0}"
export URL="${URL:-http://localhost/}"

log() { echo "kiosk: $*" >&2; }

# systemd gibt lokalen Sitzungen am Bildschirm seit Version 254 die
# Capability CAP_WAKE_ALARM mit. WebKit schottet seine Hilfsprozesse mit
# bwrap ab, und bwrap bricht bei unerwarteten Capabilities ab ("Unexpected
# capabilities but not setuid") - cog stirbt dann sofort mit SIGTRAP. Also
# einmal alle Capabilities ablegen, bevor irgendein Browser startet. Dafür
# sind keine Sonderrechte nötig.
if [[ -z "${SMARTMIRROR_CAPS_DROPPED:-}" ]] && command -v setpriv >/dev/null 2>&1; then
    export SMARTMIRROR_CAPS_DROPPED=1
    exec setpriv --inh-caps=-all --ambient-caps=-all -- "$0" "$@"
fi

case "$ROTATION" in
    0|90|180|270) ;;
    *)
        log "ROTATION=$ROTATION ist ungültig (erlaubt: 0, 90, 180, 270) - nehme 0."
        ROTATION=0
        ;;
esac

# --- Zweiter Aufruf: läuft bereits innerhalb von cage ---------------------

if [[ "${1:-}" == "--in-cage" ]]; then

    # cage dreht nicht selbst, nimmt die Drehung aber über das
    # wlr-output-management-Protokoll entgegen - genau das spricht wlr-randr.
    if [[ "$ROTATION" != "0" ]]; then

        OUTPUT="$(wlr-randr 2>/dev/null | awk '/^[^ \t]/ { print $1; exit }')"

        if [[ -n "$OUTPUT" ]]; then
            wlr-randr --output "$OUTPUT" --transform "$ROTATION" \
                || log "Drehung von $OUTPUT fehlgeschlagen"
        else
            log "Kein Ausgang gefunden - Bild bleibt ungedreht"
        fi
    fi

    case "$KIOSK_BROWSER" in

        firefox)
            export MOZ_ENABLE_WAYLAND=1
            exec firefox-esr --kiosk --private-window "$URL"
            ;;

        chromium)
            CHROMIUM="$(command -v chromium || command -v chromium-browser || true)"

            exec "$CHROMIUM" \
                --kiosk \
                --noerrdialogs \
                --disable-infobars \
                --disable-session-crashed-bubble \
                --disable-features=Translate,TranslateUI \
                --no-first-run \
                --check-for-update-interval=31536000 \
                --autoplay-policy=no-user-gesture-required \
                --overscroll-history-navigation=0 \
                --disable-pinch \
                --ozone-platform=wayland \
                "$URL"
            ;;
    esac

    log "KIOSK_BROWSER=$KIOSK_BROWSER läuft nicht in cage"
    exit 1
fi

# --- Erster Aufruf: vom Dienst ---------------------------------------------

# Erst starten, wenn der Webserver antwortet. Sonst zeigt der Browser beim
# Booten eine Fehlerseite und bleibt dort stehen.
until curl -sf -o /dev/null --max-time 5 "$URL"; do
    sleep 2
done

case "$KIOSK_BROWSER" in

    cog)
        # rotation zählt Vierteldrehungen und funktioniert nur mit dem
        # GLES-Renderer. --webprocess-failure=exit: stürzt der Prozess ab,
        # der die Seite darstellt (etwa weil der Speicher knapp wurde),
        # beendet sich cog und systemd startet es neu. Ohne das bliebe
        # dauerhaft eine Fehlerseite stehen.
        if [[ -n "${VIDEO_MODE:-}" ]]; then
            export COG_PLATFORM_DRM_VIDEO_MODE="$VIDEO_MODE"
        fi

        exec cog \
            --platform=drm \
            --platform-params="renderer=gles,rotation=$((ROTATION / 90))" \
            --bg-color=black \
            --webprocess-failure=exit \
            "$URL"
        ;;

    firefox|chromium)
        # -d: keine Fensterdekorationen. Das Skript ruft sich selbst in cage
        # noch einmal auf, um vor dem Browser die Drehung zu setzen.
        exec cage -d -- "$0" --in-cage
        ;;

    *)
        log "Unbekannter KIOSK_BROWSER=$KIOSK_BROWSER - erlaubt: cog, firefox, chromium"
        exit 1
        ;;
esac
