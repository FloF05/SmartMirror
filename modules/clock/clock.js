(() => {

    const config = (window.mirrorConfig && window.mirrorConfig.clock) || {};

    const showSeconds = config.show_seconds !== false;
    const use12Hour = String(config.format) === "12";

    const days = [
        "Sonntag",
        "Montag",
        "Dienstag",
        "Mittwoch",
        "Donnerstag",
        "Freitag",
        "Samstag"
    ];

    const months = [
        "Januar",
        "Februar",
        "März",
        "April",
        "Mai",
        "Juni",
        "Juli",
        "August",
        "September",
        "Oktober",
        "November",
        "Dezember"
    ];

    const pad = value => String(value).padStart(2, "0");

    const greetingFor = hour => {
        if (hour >= 5 && hour < 12) return "Guten Morgen";
        if (hour >= 12 && hour < 18) return "Guten Tag";
        if (hour >= 18 && hour < 22) return "Guten Abend";
        return "Gute Nacht";
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

    if (!timeElement || !dateElement || !greetingElement) {
        return;
    }

    const updateClock = () => {
        const now = new Date();

        timeElement.textContent = formatTime(now);

        dateElement.textContent =
            days[now.getDay()]
            + ", "
            + now.getDate()
            + ". "
            + months[now.getMonth()]
            + " "
            + now.getFullYear();

        greetingElement.textContent = greetingFor(now.getHours());
    };

    updateClock();

    // Ohne Sekundenanzeige reicht ein Takt von 10 Sekunden - das spart
    // dem Pi Zero jede Menge unnötiger Aufrufe.
    setInterval(updateClock, showSeconds ? 1000 : 10000);

})();
