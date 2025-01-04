<?php

include ($_SERVER['DOCUMENT_ROOT'].'/checks/admin-check.php');

use App\Entity\EntityManagerFactory;
use App\Entity\Race;
use App\Enum\ImageType;

/**
 * Resize an image to the specified width and height using GD.
 *
 * @param string $sourcePath  Path to the uploaded temporary file.
 * @param string $destPath    Path to where the resized image should be saved.
 * @param int    $newWidth    New image width.
 * @param int    $newHeight   New image height.
 *
 * @throws Exception
 */
function resizeImage(string $sourcePath, string $destPath, int $newWidth, int $newHeight): void
{
    // Get original image dimensions
    [$width, $height, $type] = getimagesize($sourcePath);

    // Create a new GD image from file
    switch ($type) {
        case IMAGETYPE_JPEG:
            $src = imagecreatefromjpeg($sourcePath);
            break;
        case IMAGETYPE_PNG:
            $src = imagecreatefrompng($sourcePath);
            break;
        default:
            throw new Exception("Unsupported image type. Please upload a JPEG or PNG.");
    }

    // Create a blank truecolor image
    $dst = imagecreatetruecolor($newWidth, $newHeight);

    // For PNG - preserve transparency (optional)
    if ($type === IMAGETYPE_PNG) {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
    }

    // Copy and resize
    imagecopyresampled(
        $dst,   // Destination
        $src,   // Source
        0, 0,   // Dest X, Dest Y
        0, 0,   // Src X,  Src Y
        $newWidth, $newHeight,
        $width, $height
    );

    // Save the resized image as JPEG (or PNG if you want to maintain format)
    switch ($type) {
        case IMAGETYPE_JPEG:
            imagejpeg($dst, $destPath, 90);
            break;
        case IMAGETYPE_PNG:
            imagepng($dst, $destPath, 1);
            break;
        default:
        throw new Exception("Unsupported image type. Please upload a JPEG or PNG.");
    }

    // Cleanup
    imagedestroy($src);
    imagedestroy($dst);
}

// 2) Handle the form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type   = $_POST['type'] ?? '';   // 'portrait' or 'avatar'
    $raceId = $_POST['raceId'] ?? '';
    $number = $_POST['number'] ?? '';

    // Ensure we have a file
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        die("Error: No file uploaded or there was an upload error.");
    }

    // Validate / convert $type to our enum
    $imageType = ImageType::tryFrom($type);
    if ($imageType === null) {
        die("Error: Invalid type. Must be 'portrait' or 'avatar'.");
    }

    $entityManager = EntityManagerFactory::getEntityManager();
    /** @var Race|null $race */
    $raceRepository = $entityManager->getRepository(Race::class);
    $race = $raceRepository->find($raceId);

    if (!$race) {
        die("Error: Invalid Race ID.");
    }

    // We'll use $race->getName() to build the subdirectory
    $raceName = $race->getName();

    // Build the correct upload directory for the race
    $uploadDir = $imageType->uploadDirectory($raceName, $_SERVER['DOCUMENT_ROOT']);

    // Make sure directory exists
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // Build main filename
    $fileName         = $imageType->buildFilename((int)$number);
    $destinationPath  = $uploadDir . '/' . $fileName;

    // Get main dimensions for this image type
    [$width, $height] = $imageType->dimensions();

    // Temp path to uploaded file
    $tempPath = $_FILES['image']['tmp_name'];

    try {
        // 1) Resize & save the main image
        resizeImage($tempPath, $destinationPath, $width, $height);

        // 2) If there's a mini version (PORTRAIT), we create & save it
        if ($miniDims = $imageType->miniDimensions()) {
            [$miniWidth, $miniHeight] = $miniDims;

            $miniFileName = $imageType->buildMiniFilename((int)$number);
            $miniPath     = $uploadDir . '/' . $miniFileName;

            resizeImage($tempPath, $miniPath, $miniWidth, $miniHeight);
        }

        if ($imageType === ImageType::PORTRAIT) {
            $currentNumber = $race->getPortraitNextNumber();
            $race->incrementPortraitNextNumber();
        } else {
            $currentNumber = $race->getAvatarNextNumber();
            $race->incrementAvatarNextNumber();
        }

        $entityManager->persist($race);
        $entityManager->flush();

        header('Content-Type: application/json');

        $resMessage = "Envoi réussi !<br>";
        $resMessage .= "Chemin du fichier principal : " . htmlspecialchars($destinationPath) . "<br>";
        if (isset($miniPath)) {
            $resMessage .= "Chemin du fichier mini : " . htmlspecialchars($miniPath) . "<br>";
        }

        echo json_encode([
            'success' => true,
            'message' => $resMessage,
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage() 
        ]);

    }
}
