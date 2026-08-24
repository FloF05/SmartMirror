<?php
// $settings kommt aus loadModule() in app/module_loader.php
//
// Serverseitig gerendert: Die Liste ändert sich nur über den Adminbereich,
// und der stösst ohnehin ein Neuladen des Spiegels an. Ein eigener
// API-Aufruf wäre auf dem Pi Zero verschenkte Rechenzeit.

$todo  = $settings["todo"];
$items = array_values(array_filter(
    is_array($todo["items"] ?? null) ? $todo["items"] : [],
    fn($item): bool => trim((string) $item) !== ""
));
?>

<div class="todo-module">

    <div class="section-label"><?= htmlspecialchars($todo["title"] ?: "Liste") ?></div>

    <?php if ($items === []): ?>

        <div class="todo-empty">Nichts eingetragen.</div>

    <?php else: ?>

        <ul class="todo-list">
            <?php foreach ($items as $item): ?>
                <li><?= htmlspecialchars((string) $item) ?></li>
            <?php endforeach; ?>
        </ul>

    <?php endif; ?>

</div>
