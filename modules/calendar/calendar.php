<?php
$calendarMonthLabel = strftime('%B %Y');
?>

<div class="calendar-module">
    <div class="calendar-header">
        <h2>Kalender</h2>
        <div id="calendar-month" class="calendar-month"><?= htmlspecialchars($calendarMonthLabel) ?></div>
    </div>

    <div id="calendar-grid" class="calendar-grid"></div>
    <div id="calendar-empty" class="calendar-empty">Keine Termine gefunden.</div>
</div>