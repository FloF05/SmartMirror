<?php

require __DIR__ . "/../app/settings.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST" || !isset($_FILES["image"])) {
    header("Location: index.php");
    exit;
}

$file = $_FILES["image"];

if ($file["error"] !== UPLOAD_ERR_OK) {
    header("Location: index.php?error=upload");
    exit;
}

$extension = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));

if (!in_array($extension, ["jpg", "jpeg", "png", "webp"], true)) {
    header("Location: index.php?error=type");
    exit;
}

// Zusätzlich zum Dateinamen prüfen, ob wirklich ein Bild ankommt
if (getimagesize($file["tmp_name"]) === false) {
    header("Location: index.php?error=type");
    exit;
}

if (!ensureDirectory(uploadsDirectory())) {
    header("Location: index.php?error=permission");
    exit;
}

$destination = uploadsDirectory() . "/" . uniqid("image_", true) . "." . $extension;

if (!move_uploaded_file($file["tmp_name"], $destination)) {
    header("Location: index.php?error=save");
    exit;
}

bumpRefreshVersion();

header("Location: index.php?success=upload");
exit;
