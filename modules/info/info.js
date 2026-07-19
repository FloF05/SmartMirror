document.addEventListener('DOMContentLoaded', () => {
    const cpuValue = document.querySelector('[data-info="cpu"]');
    const memoryValue = document.querySelector('[data-info="memory"]');
    const uptimeValue = document.querySelector('[data-info="uptime"]');
    const hostnameValue = document.querySelector('[data-info="hostname"]');

    if (!cpuValue || !memoryValue || !uptimeValue || !hostnameValue) {
        return;
    }

    const updateInfo = async () => {
        try {
            const response = await fetch('api/system_info.php');
            if (!response.ok) {
                throw new Error('Request failed');
            }

            const data = await response.json();
            cpuValue.textContent = data.cpu || '--';
            memoryValue.textContent = data.memory || '--';
            uptimeValue.textContent = data.uptime || '--';
            hostnameValue.textContent = data.hostname || '--';
        } catch (error) {
            cpuValue.textContent = '--';
            memoryValue.textContent = '--';
            uptimeValue.textContent = '--';
            hostnameValue.textContent = '--';
        }
    };

    updateInfo();
    setInterval(updateInfo, 30000);
});
