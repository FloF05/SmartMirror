(() => {

    const body = document.getElementById("calendar-body");
    const range = document.getElementById("calendar-range");

    if (!body) {
        return;
    }

    const weekdays = ["So", "Mo", "Di", "Mi", "Do", "Fr", "Sa"];

    const pad = value => String(value).padStart(2, "0");

    const showMessage = text => {
        body.innerHTML = "";

        const empty = document.createElement("div");
        empty.className = "calendar-empty";
        empty.textContent = text;

        body.appendChild(empty);
    };

    // "Heute", "Morgen", sonst "Mi 27.08."
    const dateLabel = key => {
        const date = new Date(key + "T00:00:00");

        const today = new Date();
        today.setHours(0, 0, 0, 0);

        const diff = Math.round((date - today) / 86400000);

        if (diff === 0) return "Heute";
        if (diff === 1) return "Morgen";

        return weekdays[date.getDay()] + " "
            + pad(date.getDate()) + "." + pad(date.getMonth() + 1) + ".";
    };

    const renderAgenda = data => {
        const entries = Array.isArray(data.agenda) ? data.agenda : [];

        if (entries.length === 0) {
            showMessage("Keine Termine in naechster Zeit.");
            return;
        }

        // Nach Tag gruppieren, damit das Datum nicht bei jedem Eintrag steht
        const byDay = new Map();

        entries.forEach(entry => {
            if (!byDay.has(entry.date)) {
                byDay.set(entry.date, []);
            }
            byDay.get(entry.date).push(entry);
        });

        body.innerHTML = "";

        byDay.forEach((dayEntries, key) => {
            const row = document.createElement("div");
            row.className = "agenda-day";

            if (key === data.today) {
                row.classList.add("agenda-day--today");
            }

            const label = document.createElement("div");
            label.className = "agenda-date";
            label.textContent = dateLabel(key);

            const list = document.createElement("div");
            list.className = "agenda-entries";

            dayEntries.forEach(entry => {
                const item = document.createElement("div");
                item.className = "agenda-entry";

                if (entry.type === "holiday") {
                    item.classList.add("agenda-entry--holiday");
                }

                const time = document.createElement("div");
                time.className = "agenda-time";
                time.textContent = entry.time || "\u2014";

                const title = document.createElement("div");
                title.className = "agenda-title";
                title.textContent = entry.summary;

                item.append(time, title);
                list.appendChild(item);
            });

            row.append(label, list);
            body.appendChild(row);
        });

        if (range) {
            range.textContent = "";
        }
    };

    const renderMonth = data => {
        const now = new Date();
        const year = now.getFullYear();
        const month = now.getMonth();

        const first = new Date(year, month, 1);
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const offset = (first.getDay() + 6) % 7;

        const grid = document.createElement("div");
        grid.className = "calendar-grid";

        ["Mo", "Di", "Mi", "Do", "Fr", "Sa", "So"].forEach(name => {
            const cell = document.createElement("div");
            cell.className = "calendar-cell calendar-cell--label";
            cell.textContent = name;
            grid.appendChild(cell);
        });

        for (let i = 0; i < offset; i += 1) {
            grid.appendChild(document.createElement("div"));
        }

        const dates = new Set((data.events || []).map(event => event.date));
        const holidays = data.holidays || {};

        for (let day = 1; day <= daysInMonth; day += 1) {

            const key = year + "-" + pad(month + 1) + "-" + pad(day);

            const cell = document.createElement("div");
            cell.className = "calendar-cell";

            if (key === data.today) {
                cell.classList.add("calendar-cell--today");
            }

            if (holidays[key]) {
                cell.classList.add("calendar-cell--holiday");
                cell.title = holidays[key];
            }

            cell.appendChild(document.createTextNode(String(day)));

            if (dates.has(key)) {
                const dot = document.createElement("div");
                dot.className = "calendar-dot";
                cell.appendChild(dot);
            }

            grid.appendChild(cell);
        }

        body.innerHTML = "";
        body.appendChild(grid);

        if (range) {
            range.textContent = now.toLocaleDateString("de-DE", {
                month: "long",
                year: "numeric"
            });
        }
    };

    const load = async () => {
        try {
            const response = await fetch("api/calendar.php");

            if (!response.ok) {
                showMessage("Kalender nicht erreichbar.");
                return;
            }

            const data = await response.json();

            if (data.view === "month") {
                renderMonth(data);
                return;
            }

            renderAgenda(data);

        } catch (error) {
            showMessage("Kalender konnte nicht geladen werden.");
        }
    };

    load();

    // Stuendlich reicht: Termine aendern sich nur ueber den Adminbereich,
    // und der stoesst ohnehin ein Neuladen des Spiegels an.
    setInterval(load, 3600000);

})();
