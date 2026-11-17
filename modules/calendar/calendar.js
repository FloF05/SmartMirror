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

    const shortDate = key => {
        const date = new Date(key + "T00:00:00");

        return weekdays[date.getDay()] + " "
            + pad(date.getDate()) + "." + pad(date.getMonth() + 1) + ".";
    };

    // "Heute", "Morgen", sonst "Mi 27.08."
    const dateLabel = key => {
        const date = new Date(key + "T00:00:00");

        const today = new Date();
        today.setHours(0, 0, 0, 0);

        const diff = Math.round((date - today) / 86400000);

        if (diff === 0) return "Heute";
        if (diff === 1) return "Morgen";

        return shortDate(key);
    };

    const renderAgenda = data => {
        const entries = Array.isArray(data.agenda) ? data.agenda : [];

        if (entries.length === 0) {
            showMessage("Keine Termine in nächster Zeit.");
            return;
        }

        // Nach Tag gruppieren, damit das Datum nicht bei jedem Eintrag steht
        const byDay = new Map();

        // Ein laufender mehrtägiger Termin steht unter dem heutigen Tag
        entries.forEach(entry => {
            const key = entry.day || entry.date;

            if (!byDay.has(key)) {
                byDay.set(key, []);
            }
            byDay.get(key).push(entry);
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

                if (entry.end) {
                    const until = document.createElement("div");
                    until.className = "agenda-until";
                    until.textContent = "bis " + shortDate(entry.end);
                    item.appendChild(until);
                }

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

        // Mehrtägige Termine markieren jeden Tag, den sie abdecken
        const dates = new Set();

        (data.events || []).forEach(event => {
            if (event.type === "holiday") {
                return;
            }

            const day = new Date(event.date + "T00:00:00");
            const last = new Date((event.end || event.date) + "T00:00:00");

            while (day <= last) {
                dates.add(day.getFullYear() + "-" + pad(day.getMonth() + 1) + "-" + pad(day.getDate()));
                day.setDate(day.getDate() + 1);
            }
        });
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

    // Alle 15 Minuten: Termine ändern sich nur über den Adminbereich, und
    // der stößt ohnehin ein Neuladen an. Der Takt sorgt aber dafür, dass
    // nach Mitternacht "Heute" und "Morgen" wieder stimmen. Der Server
    // hält das Ergebnis vor, ein Abruf kostet ihn also kaum etwas.
    setInterval(load, 900000);

})();
