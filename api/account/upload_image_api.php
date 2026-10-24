<?php
require_once($_SERVER['DOCUMENT_ROOT'].'/config.php');
use App\Service\AdminAuthorizationService;
use App\Service\RaceImageService;
use App\Factory\EntityManagerFactory;
use App\Entity\Race;
use App\Enum\ImageType;

AdminAuthorizationService::DoAdminCheck();

header('Content-Type: application/json');

// The race counter numbers the file (RaceImageService::upload), never the client:
// an upload cannot replace an existing image.
try {
    $imageType = ImageType::tryFrom((string) ($_POST['type'] ?? ''))
        ?? throw new RuntimeException('Type invalide : portrait ou avatar attendu.');
    $race = EntityManagerFactory::getEntityManager()->getRepository(Race::class)->find((int) ($_POST['raceId'] ?? 0))
        ?? throw new RuntimeException('Race inconnue.');
    $file = $_FILES['image'] ?? null;
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
        || !is_uploaded_file((string) $file['tmp_name'])) {
        throw new RuntimeException('Aucun fichier reçu (ou envoi incomplet).');
    }

    $created = (new RaceImageService())->upload($imageType, $race->getName(), (string) $file['tmp_name']);
    echo json_encode([
        'success' => true,
        'message' => 'Envoi réussi : ' . htmlspecialchars($imageType->uploadDirectory($race->getName(), '') . '/' . $created),
    ]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
