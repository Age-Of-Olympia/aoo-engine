<?php

namespace App\Service;

use App\Entity\EntityManagerFactory;
use App\Entity\PlayerReductionPassive;

class PlayerReductionPassiveService
{
    private $entityManager;

    public function __construct()
    {
        // Fetch the entity manager from your custom factory
        $this->entityManager = EntityManagerFactory::getEntityManager();
    }

    public function getPassivesByPlayerId(int $playerId): array
    {
        $repo = $this->entityManager->getRepository(PlayerReductionPassive::class);

        return $repo->findBy(['player_id' => $playerId]);
    }

}