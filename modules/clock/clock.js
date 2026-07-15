function updateClock()
{

    const now = new Date();


    let hours =
    now.getHours()
    .toString()
    .padStart(2,'0');


    let minutes =
    now.getMinutes()
    .toString()
    .padStart(2,'0');


    let seconds =
    now.getSeconds()
    .toString()
    .padStart(2,'0');


    let time =
    hours + ":" + minutes;


    time += ":" + seconds;


    document.getElementById("clock-time")
    .innerHTML = time;



    let days = [

        "Sonntag",
        "Montag",
        "Dienstag",
        "Mittwoch",
        "Donnerstag",
        "Freitag",
        "Samstag"

    ];


    let months = [

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



    let date =
    days[now.getDay()]
    + ", "
    + now.getDate()
    + ". "
    + months[now.getMonth()]
    + " "
    + now.getFullYear();



    document.getElementById("clock-date")
    .innerHTML = date;



    let hour = now.getHours();

    let greeting;



    if(hour >= 5 && hour < 12)
    {
        greeting="Guten Morgen";
    }

    else if(hour >= 12 && hour < 18)
    {
        greeting="Guten Tag";
    }

    else if(hour >=18 && hour <22)
    {
        greeting="Guten Abend";
    }

    else
    {
        greeting="Gute Nacht";
    }



    document.getElementById("clock-greeting")
    .innerHTML=greeting;


}



setInterval(updateClock,1000);

updateClock();