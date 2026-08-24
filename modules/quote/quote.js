(() => {

    const quotes = (window.mirrorConfig && window.mirrorConfig.quote
        && window.mirrorConfig.quote.items) || [];

    const element = document.getElementById("quote-text");

    if (!element || quotes.length === 0) {
        return;
    }

    // Auswahl über den Tag im Jahr: gleiches Zitat den ganzen Tag, ohne
    // Serveraufruf, und um Mitternacht wechselt es von selbst.
    const dayOfYear = date => {
        const start = new Date(date.getFullYear(), 0, 0);
        return Math.floor((date - start) / 86400000);
    };

    const render = () => {
        element.textContent = quotes[dayOfYear(new Date()) % quotes.length];
    };

    render();
    setInterval(render, 3600000);

})();
