<?php

function checkImageSizeInDirectory($directory, $validExtensions = ['jpg', 'jpeg'], $expectedWidth = 50, $expectedHeight = 50, $miniWidth = 30, $miniHeight = 30) {
    $dir = new RecursiveDirectoryIterator($directory);
    $iterator = new RecursiveIteratorIterator($dir);

    foreach ($iterator as $file) {
        if ($file->isFile() && in_array(strtolower($file->getExtension()), $validExtensions)) {
            $imagePath = $file->getPathname();
            
            // Vérifie si le fichier contient "_mini"
            if (strpos($file->getFilename(), '_mini') !== false) {
                checkImageSize($imagePath, $miniWidth, $miniHeight, "mini");
            } else {
                checkImageSize($imagePath, $expectedWidth, $expectedHeight);
            }
        }
    }
}

function checkImageSize($imagePath, $expectedWidth, $expectedHeight, $type = "standard") {

    list($width, $height) = getimagesize($imagePath);

    // Affiche les messages selon les dimensions de l'image
    if ($width > $expectedWidth || $height > $expectedHeight) {
        echo "<p style='color: orange;'>L'image $imagePath ($type) a une dimension supérieure à {$expectedWidth}x{$expectedHeight} : {$width}x{$height}.</p>\n";
    } elseif ($width < $expectedWidth || $height < $expectedHeight) {
        echo "<p>L'image $imagePath ($type) a une dimension inférieure à {$expectedWidth}x{$expectedHeight} : {$width}x{$height}.</p>\n";
    }
}

//check avatars
checkImageSizeInDirectory('img/avatars', ['webp', 'png'], 50,50);

//check portrait
checkImageSizeInDirectory('img/portraits', ['jpeg', 'jpg'], 210,330, 50,79);
