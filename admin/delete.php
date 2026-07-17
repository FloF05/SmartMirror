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
        $_POST["delete"]
    )
)
{

    header(
        "Location: index.php"
    );

    exit;

}


$filename =
basename(
    $_POST["delete"]
);


$filePath =
$uploadDirectory
. $filename;


if(
    is_file(
        $filePath
    )
)
{

    unlink(
        $filePath
    );


    header(
        "Location: index.php"
        . "?success=delete"
    );

}

else
{

    header(
        "Location: index.php"
        . "?error=delete"
    );

}


exit;