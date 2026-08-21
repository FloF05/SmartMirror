<?php

// $settings kommt aus loadModule() in app/module_loader.php

$images = [];

$allowedExtensions = ["jpg", "jpeg", "png", "webp"];

if (is_dir(uploadsDirectory())) {

    foreach (scandir(uploadsDirectory()) as $file) {

        if (!is_file(uploadsDirectory() . "/" . $file)) {
            continue;
        }

        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        if (in_array($extension, $allowedExtensions, true)) {
            $images[] = $file;
        }
    }
}

shuffle($images);

?>

<div class="slideshow-module">

    <?php if ($images !== []): ?>

        <div id="slideshow">

            <?php foreach ($images as $image): ?>

                <img
                    src="uploads/<?= rawurlencode($image) ?>"
                    class="slideshow-image"
                    alt=""
                >

            <?php endforeach; ?>

        </div>

    <?php else: ?>

        <div class="slideshow-empty">
            Keine Bilder vorhanden.
        </div>

    <?php endif; ?>

</div>
