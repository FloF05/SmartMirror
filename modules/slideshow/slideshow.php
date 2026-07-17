<?php
require_once __DIR__ . "/../../config/config.php";

$uploadDirectory = __DIR__ . "/../../uploads/";

$images = [];

$allowedExtensions = [
    "jpg",
    "jpeg",
    "png",
    "webp"
];


if(is_dir($uploadDirectory))
{

    $files = scandir($uploadDirectory);


    foreach($files as $file)
    {

        $filePath = $uploadDirectory . $file;


        if(!is_file($filePath))
        {
            continue;
        }


        $extension =
        strtolower(
            pathinfo($file, PATHINFO_EXTENSION)
        );


        if(
            in_array(
                $extension,
                $allowedExtensions
            )
        )
        {
            $images[] = $file;
        }

    }

}


shuffle($images);


?>

<script>

const slideshowInterval =
<?= $config["slideshow"]["interval"] ?>;

</script>

<div class="slideshow-module">

    <?php if(count($images) > 0): ?>

        <div id="slideshow">

            <?php foreach($images as $image): ?>

                <img
                    src="uploads/<?= htmlspecialchars($image) ?>"
                    class="slideshow-image"
                    alt="Slideshow Bild"
                >

            <?php endforeach; ?>

        </div>


    <?php else: ?>

        <div class="slideshow-empty">

            Keine Bilder vorhanden.

        </div>

    <?php endif; ?>

</div>