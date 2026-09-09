<?php

namespace App\Service;

use App\Factory\EntityManagerFactory;
use App\Entity\PlayerBonus;

class PlayerBonusService
{
    private $entityManager;

    public function __construct()
    {
        // Fetch the entity manager from your custom factory
        $this->entityManager = EntityManagerFactory::getEntityManager();
    }

    public function setBonusByPlayerIdByName(int $playerId, $name, $n): void
    {
        $repo = $this->entityManager->getRepository(PlayerBonus::class);

        $bonus = $repo->findOneBy([
            'player_id' => $playerId,
            'name' => $name
        ]);

        if ($bonus) {
            $bonus->setN($n);
            $this->entityManager->flush();
        } else {
            $bonus = new PlayerBonus();
            $bonus->setPlayerId($playerId);
            $bonus->setName($name);
            $bonus->setN($n);

            $this->entityManager->persist($bonus);
            $this->entityManager->flush();
        }
    }

}