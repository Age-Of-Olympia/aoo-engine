<?php

namespace App\Service;

use App\Entity\EntityManagerFactory;
use App\Entity\PlayerEffect;

class PlayerEffectService
{
    private $entityManager;

    public function __construct()
    {
        // Fetch the entity manager from your custom factory
        $this->entityManager = EntityManagerFactory::getEntityManager();
    }

    public function getEffectsByPlayerId(int $playerId): array
    {
        $repo = $this->entityManager->getRepository(PlayerEffect::class);

        return $repo->findBy(['player_id' => $playerId]);
    }

}