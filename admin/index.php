<?php

$uploadDirectory =
__DIR__ . "/../uploads/";


$message = "";


if($_SERVER["REQUEST_METHOD"] === "POST")
{

    if(
        isset($_FILES["image"])
        &&
        $_FILES["image"]["error"] === UPLOAD_ERR_OK
    )
    {

        $file = $_FILES["image"];


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

    else
    {

        $message =
        "Keine Datei ausgewählt.";

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

</head>


<body>


<h1>
Bild hochladen
</h1>


<?php if($message): ?>

<p>

<?= htmlspecialchars($message) ?>

</p>

<?php endif; ?>


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


<br><br>


<button
    type="submit"
>

Bild hochladen

</button>


</form>


</body>


</html>