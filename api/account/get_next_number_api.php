<?php

include ($_SERVER['DOCUMENT_ROOT'].'/checks/admin-check.php');

use App\Entity\EntityManagerFactory;
use App\Entity\Race;
use App\Enum\ImageType;

$entityManager = EntityManagerFactory::getEntityManager();

header('Content-Type: application/json');

// 1) Read query params (e.g., /get_next_number_api.php?type=portrait&raceId=2)
$type   = $_GET['type']   ?? '';
$raceId = $_GET['raceId'] ?? '';

// 2) Convert $type to enum
$imageType = ImageType::tryFrom($type);
if (!$imageType) {
    echo json_encode(['error' => 'Invalid type']);
    exit;
}

// 3) Fetch the Race from DB
$raceRepository = $entityManager->getRepository(Race::class);
/** @var Race|null $race */
$race = $raceRepository->find($raceId);

if (!$race) {
    echo json_encode(['error' => 'Invalid Race ID']);
    exit;
}

if ($imageType === ImageType::PORTRAIT) {
  echo json_encode(['nextNumber' => $race->getPortraitNextNumber()]);
} else {
  echo json_encode(['nextNumber' => $race->getAvatarNextNumber()]);
}
