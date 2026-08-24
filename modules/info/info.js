(() => {

    const fields = ["hostname", "cpu", "memory", "uptime"];

    const nodes = {};

    fields.forEach(name => {
        nodes[name] = document.querySelector('[data-info="' + name + '"]');
    });

    if (!nodes.hostname) {
        return;
    }

    const load = async () => {
        try {
            const response = await fetch("api/system_info.php");

            if (!response.ok) {
                return;
            }

            const data = await response.json();

            fields.forEach(name => {
                if (nodes[name]) {
                    nodes[name].textContent = data[name] || "--";
                }
            });

        } catch (error) {
            // Stille ist hier richtig - die Systeminfo ist Beiwerk
        }
    };

    load();
    setInterval(load, 60000);

})();
