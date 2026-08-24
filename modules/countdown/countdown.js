(() => {

    const items = (window.mirrorConfig && window.mirrorConfig.countdown
        && window.mirrorConfig.countdown.items) || [];

    const container = document.getElementById("countdown-list");

    if (!container) {
        return;
    }

    // Reine Datumsrechnung im Browser - kein Serveraufruf, keine Netzlast.
    const daysUntil = target => {
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        const then = new Date(target + "T00:00:00");

        if (isNaN(then)) {
            return null;
        }

        return Math.round((then - today) / 86400000);
    };

    const phrase = days => {
        if (days === 0) return ["heute", ""];
        if (days === 1) return ["morgen", ""];
        if (days === -1) return ["gestern", ""];
        if (days > 1) return [String(days), "Tage"];
        return [String(Math.abs(days)), "Tage her"];
    };

    const render = () => {
        container.innerHTML = "";

        const upcoming = items
            .map(item => ({ ...item, days: daysUntil(item.date) }))
            .filter(item => item.days !== null)
            .sort((a, b) => a.days - b.days);

        if (upcoming.length === 0) {
            const empty = document.createElement("div");
            empty.className = "countdown-empty";
            empty.textContent = "Kein Countdown eingetragen.";
            container.appendChild(empty);
            return;
        }

        upcoming.forEach(item => {
            const [value, unit] = phrase(item.days);

            const row = document.createElement("div");
            row.className = "countdown-item";

            const days = document.createElement("span");
            days.className = "countdown-days";
            days.textContent = value;

            const label = document.createElement("span");
            label.className = "countdown-label";
            label.textContent = (unit ? unit + " bis " : "") + item.label;

            row.append(days, label);
            container.appendChild(row);
        });
    };

    render();

    // Einmal pro Stunde reicht - um Mitternacht ändert sich die Zahl.
    setInterval(render, 3600000);

})();
