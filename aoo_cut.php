<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
    $file = $_FILES['image'];
    $filePath = $file['tmp_name'];
    $fileName = $file['name'];

    // Vérifier si le fichier est une image
    $imageInfo = getimagesize($filePath);
    if ($imageInfo === false) {
        die('Le fichier téléchargé n\'est pas une image valide.');
    }

    $width = $imageInfo[0];
    $height = $imageInfo[1];
    $mime = $imageInfo['mime'];

    // Limite de taille en octets (par exemple, 2 Mo)
    $maxFileSize = 2 * 1024 * 1024; // 2 Mo
    
    if ($_FILES['image']['size'] > $maxFileSize) {
        die('Le fichier est trop volumineux. La taille maximale autorisée est de 2 Mo.');
    }
    
    // Vérifier si l'image est découpable en tiles de 50x50
    if ($width % 50 !== 0 || $height % 50 !== 0) {
        die('L\'image doit avoir des dimensions multiples de 50x50.');
    }

    // Charger l'image en fonction de son type
    switch ($mime) {
        case 'image/png':
            $image = imagecreatefrompng($filePath);
            break;
        case 'image/jpeg':
            $image = imagecreatefromjpeg($filePath);
            break;
        case 'image/gif':
            $image = imagecreatefromgif($filePath);
            break;
        default:
            die('Format d\'image non supporté.');
    }

    if (!$image) {
        die('Impossible de charger l\'image.');
    }

    // Créer un dossier temporaire pour les tiles
    $tempDir = sys_get_temp_dir() . '/tiles_' . uniqid();
    if (!mkdir($tempDir)) {
        die('Impossible de créer un dossier temporaire.');
    }

    // Découper l'image en tiles de 50x50
    $tileIndex = 0;
    for ($y = 0; $y < $height; $y += 50) {
        for ($x = 0; $x < $width; $x += 50) {
            $tile = imagecreatetruecolor(50, 50);
            imagesavealpha($tile, true);
            $transparency = imagecolorallocatealpha($tile, 0, 0, 0, 127);
            imagefill($tile, 0, 0, $transparency);
            imagecopy($tile, $image, 0, 0, $x, $y, 50, 50);

            $tilePath = "$tempDir/tile_$tileIndex.png";
            imagepng($tile, $tilePath);
            imagedestroy($tile);

            $tileIndex++;
        }
    }

    imagedestroy($image);

    // Créer un fichier ZIP des tiles
    $zipPath = $tempDir . '/tiles.zip';
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
        die('Impossible de créer le fichier ZIP.');
    }

    foreach (glob("$tempDir/tile_*.png") as $tileFile) {
        $zip->addFile($tileFile, basename($tileFile));
    }

    $zip->close();

    // Envoyer le fichier ZIP à l'utilisateur
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="tiles.zip"');
    header('Content-Length: ' . filesize($zipPath));

    readfile($zipPath);

    // Nettoyer les fichiers temporaires
    array_map('unlink', glob("$tempDir/*"));
    rmdir($tempDir);

    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Uploader une Image</title>
</head>
<body>
    <h1>Uploader une Image</h1>
    <form action="" method="post" enctype="multipart/form-data">
        <label for="image">Choisissez une image :</label>
        <input type="file" name="image" id="image" accept="image/*" required>
        <p>La taille maximale autorisée est de 2 Mo.</p>
        <button type="submit">Envoyer</button>
    </form>
    <script>
        document.getElementById('image').addEventListener('change', function () {
            const file = this.files[0];
            const maxSize = 2 * 1024 * 1024; // 2 Mo
        
            if (file && file.size > maxSize) {
                alert('Le fichier est trop volumineux. La taille maximale autorisée est de 2 Mo.');
                this.value = ''; // Réinitialise l'input
            }
        });
    </script>
</body>
</html>