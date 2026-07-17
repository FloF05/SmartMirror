<?php

$uploadDirectory =
__DIR__ . "/../uploads/";


if(
    $_SERVER["REQUEST_METHOD"] !== "POST"
)
{
    header(
        "Location: index.php"
    );

    exit;
}


if(
    !isset(
        $_FILES["image"]
    )
)
{

    header(
        "Location: index.php"
    );

    exit;

}


if(
    $_FILES["image"]["error"]
    !==
    UPLOAD_ERR_OK
)
{

    header(
        "Location: index.php"
        . "?error=upload"
    );

    exit;

}


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

    header(
        "Location: index.php"
        . "?error=type"
    );

    exit;

}


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

    header(
        "Location: index.php"
        . "?success=upload"
    );

}

else
{

    header(
        "Location: index.php"
        . "?error=save"
    );

}


exit;