<?php

$uploadDirectory =
__DIR__ . "/../uploads/";


$message = "";



/*
--------------------------------------------------
BILD LÖSCHEN
--------------------------------------------------
*/


if(
    $_SERVER["REQUEST_METHOD"] === "POST"
    &&
    isset($_POST["delete"])
)
{

    $filename =
    basename(
        $_POST["delete"]
    );


    $filePath =
    $uploadDirectory
    . $filename;


    if(
        is_file($filePath)
    )
    {

        unlink($filePath);


        $message =
        "Bild erfolgreich gelöscht.";

    }

}



/*
--------------------------------------------------
BILD HOCHLADEN
--------------------------------------------------
*/


if(
    $_SERVER["REQUEST_METHOD"] === "POST"
    &&
    isset($_FILES["image"])
)
{

    if(
        $_FILES["image"]["error"]
        ===
        UPLOAD_ERR_OK
    )
    {

        $file =
        $_FILES["image"];


        $extension =
        strtolower(
            pathinfo(
                $file["name"],
                PATHINFO_EXTENSION
            )
        );


        $allowedExtensions = [

            "jpg",
            "jpeg",
            "png",
            "webp"

        ];


        if(
            !in_array(
                $extension,
                $allowedExtensions
            )
        )
        {

            $message =
            "Dateityp nicht erlaubt.";

        }


        else
        {

            $filename =
            uniqid(
                "image_",
                true
            )
            . "."
            . $extension;


            $destination =
            $uploadDirectory
            . $filename;


            if(
                move_uploaded_file(
                    $file["tmp_name"],
                    $destination
                )
            )
            {

                $message =
                "Bild erfolgreich hochgeladen.";

            }

            else
            {

                $message =
                "Fehler beim Speichern.";

            }

        }

    }

}



/*
--------------------------------------------------
BILDER AUSLESEN
--------------------------------------------------
*/


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


<style>

body
{

    font-family:
    Arial,
    sans-serif;

    background:
    #111;

    color:
    white;

    padding:
    30px;

}


.gallery
{

    display:
    grid;

    grid-template-columns:
    repeat(
        auto-fill,
        minmax(
            200px,
            1fr
        )
    );

    gap:
    20px;

}


.image-card
{

    background:
    #222;

    padding:
    10px;

}


.image-card img
{

    width:
    100%;

    height:
    180px;

    object-fit:
    contain;

}


button
{

    padding:
    8px;

    cursor:
    pointer;

}


</style>

</head>


<body>


<h1>
SmartMirror Bildverwaltung
</h1>



<?php if($message): ?>

<p>

<?= htmlspecialchars(
    $message
) ?>

</p>

<?php endif; ?>



<h2>
Bild hochladen
</h2>


<form
    method="POST"
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