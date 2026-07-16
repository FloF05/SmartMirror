function loadWeather()
{

    fetch("api/weather.php")

        .then(response => response.json())

        .then(data =>
        {

            if(data.error)
            {
                console.error(data.error);

                return;
            }


            document.getElementById("weather-location")
                .innerText =
                data.name;


            document.getElementById("weather-temp")
                .innerText =
                Math.round(data.main.temp)
                + " °C";


            document.getElementById("weather-description")
                .innerText =
                data.weather[0].description;


            document.getElementById("weather-feels")
                .innerText =
                "Gefühlt: "
                + Math.round(data.main.feels_like)
                + " °C";


            document.getElementById("weather-humidity")
                .innerText =
                "Luftfeuchtigkeit: "
                + data.main.humidity
                + " %";


            document.getElementById("weather-wind")
                .innerText =
                "Wind: "
                + data.wind.speed
                + " m/s";


            let icon =
                data.weather[0].icon;


            document.getElementById("weather-icon")
                .innerHTML =
                `<img src="https://openweathermap.org/img/wn/${icon}@2x.png">`;


            let now =
                new Date();


            document.getElementById("weather-updated")
                .innerText =
                "Aktualisiert: "
                + now.toLocaleTimeString(
                    "de-DE",
                    {
                        hour: "2-digit",
                        minute: "2-digit"
                    }
                );

        })

        .catch(error =>
        {

            console.error(
                "Fehler beim Laden des Wetters:",
                error
            );

        });

}


loadWeather();


setInterval(
    loadWeather,
    600000
);