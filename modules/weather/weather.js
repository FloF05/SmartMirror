(() => {

    // Einfarbige Zeichen aus DejaVu Sans statt der bunten PNG-Symbole von
    // OpenWeather: Die würden auf einem Spiegel als farbige Flecken leuchten
    // und bei jedem Laden eine zusätzliche Anfrage ins Netz kosten.
    const symbols = {
        "01d": "\u2600", "01n": "\u263D",
        "02d": "\u2600", "02n": "\u263D",
        "03d": "\u2601", "03n": "\u2601",
        "04d": "\u2601", "04n": "\u2601",
        "09d": "\u2602", "09n": "\u2602",
        "10d": "\u2602", "10n": "\u2602",
        "11d": "\u2607", "11n": "\u2607",
        "13d": "\u2744", "13n": "\u2744",
        "50d": "\u2248", "50n": "\u2248"
    };

    const symbolFor = code => symbols[code] || "\u2601";

    const el = id => document.getElementById(id);

    const setText = (id, value) => {
        const node = el(id);
        if (node) {
            node.textContent = value;
        }
    };

    const renderExtra = data => {
        const container = el("weather-extra");

        if (!container) {
            return;
        }

        const parts = [];

        if (data.sun) {
            parts.push(["\u2191", data.sun.sunrise]);
            parts.push(["\u2193", data.sun.sunset]);
        }

        if (data.moon) {
            parts.push([data.moon.icon, data.moon.phase + " · " + data.moon.illumination + " %"]);
        }

        if (data.air) {
            parts.push(["\u25CE", "Luft " + data.air.label]);
        }

        container.innerHTML = "";

        parts.forEach(([glyph, text]) => {
            const item = document.createElement("div");

            const symbol = document.createElement("span");
            symbol.className = "glyph";
            symbol.textContent = glyph;

            item.appendChild(symbol);
            item.appendChild(document.createTextNode(text));

            container.appendChild(item);
        });
    };

    const renderForecast = days => {
        const container = el("weather-forecast");

        if (!container) {
            return;
        }

        container.innerHTML = "";

        (days || []).forEach(day => {
            const item = document.createElement("div");
            item.className = "forecast-day";

            const name = document.createElement("div");
            name.className = "forecast-name";
            name.textContent = day.day;

            const symbol = document.createElement("div");
            symbol.className = "forecast-symbol";
            symbol.textContent = symbolFor(day.icon);

            const temp = document.createElement("div");
            temp.className = "forecast-temp";
            temp.textContent = day.max + "°";

            const low = document.createElement("span");
            low.className = "low";
            low.textContent = day.min + "°";
            temp.appendChild(low);

            item.append(name, symbol, temp);
            container.appendChild(item);
        });
    };

    const load = async () => {
        try {
            const response = await fetch("api/weather.php");

            if (!response.ok) {
                return;
            }

            const data = await response.json();

            if (data.error || !data.current) {
                setText("weather-description", data.error || "Keine Wetterdaten");
                return;
            }

            const now = data.current;

            setText("weather-symbol", symbolFor(now.icon));
            setText("weather-temp", now.temp + "°");
            setText("weather-description", now.description);
            setText("weather-city", now.city);
            setText("weather-feels", "gefühlt " + now.feels + "°");
            setText("weather-humidity", now.humidity + " % Feuchte");
            setText("weather-wind", now.wind + " m/s");

            renderExtra(data);
            renderForecast(data.forecast);

        } catch (error) {
            // Netzaussetzer sind belanglos, der nächste Durchlauf fängt es auf
        }
    };

    load();
    setInterval(load, 900000);

})();
