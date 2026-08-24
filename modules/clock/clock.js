(() => {

    const config = (window.mirrorConfig && window.mirrorConfig.clock) || {};

    const showSeconds = config.show_seconds === true;
    const showWeek = config.show_week === true;
    const use12Hour = String(config.format) === "12";

    const days = [
        "Sonntag", "Montag", "Dienstag", "Mittwoch",
        "Donnerstag", "Freitag", "Samstag"
    ];

    const months = [
        "Januar", "Februar", "März", "April", "Mai", "Juni",
        "Juli", "August", "September", "Oktober", "November", "Dezember"
    ];

    const pad = value => String(value).padStart(2, "0");

    const greetingFor = hour => {
        if (hour >= 5 && hour < 12) return "Guten Morgen";
        if (hour >= 12 && hour < 18) return "Guten Tag";
        if (hour >= 18 && hour < 22) return "Guten Abend";
        return "Gute Nacht";
    };

    // Kalenderwoche nach ISO 8601: Woche 1 ist die mit dem ersten Donnerstag.
    const isoWeek = date => {
        const target = new Date(Date.UTC(
            date.getFullYear(), date.getMonth(), date.getDate()
        ));

        target.setUTCDate(target.getUTCDate() + 4 - (target.getUTCDay() || 7));

        const yearStart = new Date(Date.UTC(target.getUTCFullYear(), 0, 1));

        return Math.ceil(((target - yearStart) / 86400000 + 1) / 7);
    };

    const formatTime = now => {
        const hours = now.getHours();

        let text = use12Hour
            ? pad(hours % 12 === 0 ? 12 : hours % 12)
            : pad(hours);

        text += ":" + pad(now.getMinutes());

        if (showSeconds) {
            text += ":" + pad(now.getSeconds());
        }

        if (use12Hour) {
            text += hours < 12 ? " AM" : " PM";
        }

        return text;
    };

    const timeElement = document.getElementById("clock-time");
    const dateElement = document.getElementById("clock-date");
    const greetingElement = document.getElementById("clock-greeting");
    const weekElement = document.getElementById("clock-week");

    if (!timeElement) {
        return;
    }

    const update = () => {
        const now = new Date();

        timeElement.textContent = formatTime(now);

        if (dateElement) {
            dateElement.textContent =
                days[now.getDay()] + ", " + now.getDate() + ". "
                + months[now.getMonth()] + " " + now.getFullYear();
        }

        if (greetingElement) {
            greetingElement.textContent = greetingFor(now.getHours());
        }

        if (weekElement) {
            weekElement.textContent = showWeek ? "KW " + isoWeek(now) : "";
        }
    };

    update();

    // Ohne Sekundenanzeige genügt ein Takt von 10 Sekunden - das spart dem
    // Pi Zero rund 3000 Neuberechnungen pro Stunde.
    setInterval(update, showSeconds ? 1000 : 10000);

})();
