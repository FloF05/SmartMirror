<?php

$uploadDirectory = __DIR__ . "/../../uploads/";

$images = [];

$allowedExtensions = [
    "jpg",
    "jpeg",
    "png",
    "webp"
];


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

?>


<div class="slideshow-module">

    <div id="slideshow">

        <?php foreach($images as $image): ?>

            <img
                src="uploads/<?= htmlspecialchars($image) ?>"
                class="slideshow-image"
            >

        <?php endforeach; ?>

    </div>

</div>