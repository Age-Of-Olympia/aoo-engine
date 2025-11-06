<?php

namespace App\Service;

use App\Entity\EntityManagerFactory;
use App\Entity\PlayerBonus;

class PlayerBonusService
{
    private $entityManager;

    public function __construct()
    {
        // Fetch the entity manager from your custom factory
        $this->entityManager = EntityManagerFactory::getEntityManager();
    }

    public function getBonusByPlayerIdByName(int $playerId, $name): int
    {
        $repo = $this->entityManager->getRepository(PlayerBonus::class);

        $results = $repo->findBy([
            'player_id' => $playerId,
            'name' => $name
        ]);

        return $results[0]->getN();
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

    // Supprime les bonus Ae, A et Mvt
    public function recoverNewTurn(int $playerId): void
    {
        $repo = $this->entityManager->getRepository(PlayerBonus::class);

        $results = $repo->findBy([
            'player_id' => $playerId,
            'name' => ['a', 'ae', 'mvt']
        ]);

        foreach ($results as $bonus) {
            $this->entityManager->remove($bonus);
        }

        $this->entityManager->flush();
    }
}