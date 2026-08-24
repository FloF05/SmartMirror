(() => {

    const container = document.getElementById("news-list");

    if (!container) {
        return;
    }

    const showMessage = text => {
        container.innerHTML = "";

        const empty = document.createElement("div");
        empty.className = "news-empty";
        empty.textContent = text;

        container.appendChild(empty);
    };

    const load = async () => {
        try {
            const response = await fetch("api/news.php");

            if (!response.ok) {
                showMessage("Nachrichten nicht erreichbar.");
                return;
            }

            const data = await response.json();
            const items = Array.isArray(data.items) ? data.items : [];

            if (items.length === 0) {
                showMessage(data.error || "Keine Schlagzeilen.");
                return;
            }

            container.innerHTML = "";

            items.forEach(item => {
                const row = document.createElement("div");
                row.className = "news-item";

                const time = document.createElement("div");
                time.className = "news-time";
                time.textContent = item.time || "";

                const title = document.createElement("div");
                title.className = "news-title";
                title.textContent = item.title;

                row.append(time, title);
                container.appendChild(row);
            });

        } catch (error) {
            showMessage("Nachrichten nicht erreichbar.");
        }
    };

    load();

    // Der Server hält den Feed 15 Minuten im Cache - öfter zu fragen
    // brächte nichts.
    setInterval(load, 900000);

})();
