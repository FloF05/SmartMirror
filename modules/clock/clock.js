function updateClock()
{

    let now = new Date();


    let time =
    now.getHours().toString().padStart(2,'0')
    +
    ":"
    +
    now.getMinutes().toString().padStart(2,'0')
    +
    ":"
    +
    now.getSeconds().toString().padStart(2,'0');


    document.getElementById("clock-time").innerHTML = time;

}


setInterval(updateClock,1000);

updateClock();
