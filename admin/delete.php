<?php

require __DIR__ . "/../app/settings.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST" || !isset($_POST["delete"])) {
    header("Location: index.php");
    exit;
}

$filename = basename((string) $_POST["delete"]);
$filePath = uploadsDirectory() . "/" . $filename;

if (!is_file($filePath) || !unlink($filePath)) {
    header("Location: index.php?error=delete");
    exit;
}

bumpRefreshVersion();

header("Location: index.php?success=delete");
exit;
