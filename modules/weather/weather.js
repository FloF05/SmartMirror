async function loadWeather() {

    try {

        const response =
            await fetch("api/weather.php");


        if (!response.ok) {

            throw new Error(
                "HTTP-Fehler: " + response.status
            );

        }


        const data =
            await response.json();


        console.log("Wetterdaten:", data);


        document.getElementById("weather-temp")
            .innerText =
            Math.round(data.main.temp) + "°C";


        document.getElementById("weather-description")
            .innerText =
            data.weather[0].description;


        document.getElementById("weather-feels")
            .innerText =
            "Gefühlt: "
            +
            Math.round(data.main.feels_like)
            +
            "°C";


    }

    catch (error) {

        console.error(
            "Fehler beim Laden des Wetters:",
            error
        );


        document.getElementById("weather-temp")
            .innerText =
            "Fehler";


        document.getElementById("weather-description")
            .innerText =
            "Wetterdaten nicht verfügbar";


    }

}


loadWeather();