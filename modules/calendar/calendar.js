document.addEventListener('DOMContentLoaded', () => {
    const grid = document.getElementById('calendar-grid');
    const monthLabel = document.getElementById('calendar-month');
    const emptyState = document.getElementById('calendar-empty');

    if (!grid || !monthLabel) {
        return;
    }

    const showEmptyState = (message) => {
        if (emptyState) {
            emptyState.textContent = message;
            emptyState.style.display = 'block';
        }
    };

    const hideEmptyState = () => {
        if (emptyState) {
            emptyState.style.display = 'none';
        }
    };

    // Ganztägige Termine haben keine Uhrzeit, terminierte schon.
    const eventLabel = (event) => event.time
        ? `${event.time} ${event.summary}`
        : event.summary;

    const renderMonthView = (events) => {
        const now = new Date();
        const year = now.getFullYear();
        const month = now.getMonth();

        monthLabel.textContent = now.toLocaleString('de-DE', {
            month: 'long',
            year: 'numeric'
        });

        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const daysInMonth = lastDay.getDate();
        const startWeekday = (firstDay.getDay() + 6) % 7;

        grid.innerHTML = '';
        grid.className = 'calendar-grid';

        const labels = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
        labels.forEach(label => {
            const cell = document.createElement('div');
            cell.className = 'calendar-day calendar-day--label';
            cell.textContent = label;
            grid.appendChild(cell);
        });

        for (let i = 0; i < startWeekday; i += 1) {
            const emptyCell = document.createElement('div');
            emptyCell.className = 'calendar-day';
            grid.appendChild(emptyCell);
        }

        for (let day = 1; day <= daysInMonth; day += 1) {
            const cell = document.createElement('div');
            cell.className = 'calendar-day';

            const dayNumber = document.createElement('div');
            dayNumber.className = 'calendar-day-number';
            dayNumber.textContent = day;
            cell.appendChild(dayNumber);

            const dayKey = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            const dayEvents = events.filter(event => event.date === dayKey);

            dayEvents.slice(0, 2).forEach(event => {
                const eventElement = document.createElement('div');
                eventElement.className = 'calendar-event';
                eventElement.textContent = eventLabel(event);
                cell.appendChild(eventElement);
            });

            if (dayEvents.length > 2) {
                const more = document.createElement('div');
                more.className = 'calendar-event calendar-event--more';
                more.textContent = `+${dayEvents.length - 2} weitere`;
                cell.appendChild(more);
            }

            if (
                day === now.getDate() &&
                month === now.getMonth() &&
                year === now.getFullYear()
            ) {
                cell.classList.add('calendar-day--today');
            }

            grid.appendChild(cell);
        }
    };

    const renderWeekView = (events) => {
        const now = new Date();
        const start = new Date(now);
        const dayOffset = (now.getDay() + 6) % 7;
        start.setDate(now.getDate() - dayOffset);
        start.setHours(0, 0, 0, 0);

        monthLabel.textContent = 'Diese Woche';
        grid.innerHTML = '';
        grid.className = 'calendar-week';

        for (let i = 0; i < 7; i += 1) {
            const day = new Date(start);
            day.setDate(start.getDate() + i);
            const dayKey = `${day.getFullYear()}-${String(day.getMonth() + 1).padStart(2, '0')}-${String(day.getDate()).padStart(2, '0')}`;
            const dayEvents = events.filter(event => event.date === dayKey);

            const panel = document.createElement('div');
            panel.className = 'calendar-week-day';

            const title = document.createElement('h4');
            title.textContent = day.toLocaleDateString('de-DE', {
                weekday: 'short',
                day: '2-digit',
                month: '2-digit'
            });
            panel.appendChild(title);

            if (dayEvents.length === 0) {
                const noEvents = document.createElement('div');
                noEvents.className = 'calendar-event';
                noEvents.textContent = 'Kein Termin';
                panel.appendChild(noEvents);
            } else {
                dayEvents.forEach(event => {
                    const eventElement = document.createElement('div');
                    eventElement.className = 'calendar-event';
                    eventElement.textContent = eventLabel(event);
                    panel.appendChild(eventElement);
                });
            }

            grid.appendChild(panel);
        }
    };

    const loadCalendar = async () => {
        try {
            const response = await fetch('api/calendar.php');
            if (!response.ok) {
                throw new Error('Calendar request failed');
            }

            const data = await response.json();
            const events = Array.isArray(data.events) ? data.events : [];

            if (events.length === 0) {
                showEmptyState('Keine Termine gefunden.');
            } else {
                hideEmptyState();
            }

            if (data.view === 'week') {
                renderWeekView(events);
                return;
            }

            renderMonthView(events);
        } catch (error) {
            showEmptyState('Kalender konnte nicht geladen werden.');
        }
    };

    loadCalendar();
});
