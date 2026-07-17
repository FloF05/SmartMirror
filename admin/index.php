<?php

$uploadDirectory =
__DIR__ . "/../uploads/";


$images = [];


if(
    is_dir(
        $uploadDirectory
    )
)
{

    $files =
    scandir(
        $uploadDirectory
    );


    foreach(
        $files
        as $file
    )
    {

        $filePath =
        $uploadDirectory
        . $file;


        if(
            !is_file(
                $filePath
            )
        )
        {
            continue;
        }


        $extension =
        strtolower(
            pathinfo(
                $file,
                PATHINFO_EXTENSION
            )
        );


        if(
            in_array(
                $extension,
                [
                    "jpg",
                    "jpeg",
                    "png",
                    "webp"
                ]
            )
        )
        {

            $images[] =
            $file;

        }

    }

}

?>


<!DOCTYPE html>

<html lang="de">


<head>

<meta charset="UTF-8">


<title>
SmartMirror Bildverwaltung
</title>


<link
    rel="stylesheet"
    href="style.css"
>


</head>


<body>


<h1>
SmartMirror Bildverwaltung
</h1>



<?php if(
    isset(
        $_GET["success"]
    )
): ?>

<p class="success">

Aktion erfolgreich.

</p>

<?php endif; ?>



<?php if(
    isset(
        $_GET["error"]
    )
): ?>

<p class="error">

Es ist ein Fehler aufgetreten.

</p>

<?php endif; ?>



<h2>
Bild hochladen
</h2>


<form
    method="POST"
    action="upload.php"
    enctype="multipart/form-data"
>


<input
    type="file"
    name="image"
    accept=".jpg,.jpeg,.png,.webp"
    required
>


<button
    type="submit"
>

Hochladen

</button>


</form>



<h2>
Vorhandene Bilder
</h2>


<div class="gallery">


<?php foreach(
    $images
    as $image
): ?>


<div class="image-card">


<img
    src="../uploads/<?= htmlspecialchars(
        $image
    ) ?>"
>


<p>

<?= htmlspecialchars(
    $image
) ?>

</p>


<form
    method="POST"
    action="delete.php"
>


<input
    type="hidden"
    name="delete"
    value="<?= htmlspecialchars(
        $image
    ) ?>"
>


<button
    type="submit"
>

Löschen

</button>


</form>


</div>


<?php endforeach; ?>


</div>


</body>


</html>