<?php

namespace App\Service;

use App\Factory\EntityManagerFactory;
use App\Entity\PlayerPnj;

class PlayerPnjService
{
    private $entityManager;

    public function __construct()
    {
        // Fetch the entity manager from your custom factory
        $this->entityManager = EntityManagerFactory::getEntityManager();
    }

    public function getByPlayerIdAndPnjId(int $playerId,int $pnjId): ?PlayerPnj
    {
        $repo = $this->entityManager->getRepository(PlayerPnj::class);

        return $repo->findOneBy(['player_id' => $playerId, 'pnj_id'=>$pnjId]);
    }

    public function getByPlayerId(int $playerId): array
    {
        $repo = $this->entityManager->getRepository(PlayerPnj::class);

        return $repo->findBy(['player_id' => $playerId]);
    }

    /**
     * Characters an account may act as: its main character and its pnjs.
     *
     * @return int[]
     */
    public function controllableIds(int $mainId): array
    {
        $ids = [$mainId];
        foreach ($this->getByPlayerId($mainId) as $playerPnj) {
            $ids[] = (int) $playerPnj->getPnjId();
        }

        return $ids;
    }

    public function create(int $playerId, int $pnjId, bool $displayed ) : void
    {
        $playerPnj = new PlayerPnj();
        $playerPnj->setPlayerId($playerId);
        $playerPnj->setPnjId($pnjId);
        $playerPnj->setDisplayed($displayed); 

        $this->entityManager->persist($playerPnj);
        $this->entityManager->flush();
    }
    
    public function deleteByPlayerIdAndPnjId(int $playerId, int $pnjId): void
    {
        $repo = $this->entityManager->getRepository(PlayerPnj::class);
        $playerPnj = $repo->findOneBy(['player_id' => $playerId, 'pnj_id' => $pnjId]);

        if ($playerPnj) {
            $this->entityManager->remove($playerPnj);
            $this->entityManager->flush();
        }
    }

    public function updatePlayerPnj(int $playerId, int $pnjId, $displayed): void
    {
      
        $repo = $this->entityManager->getRepository(PlayerPnj::class);
        $playerPnj = $repo->findOneBy(['player_id' => $playerId, 'pnj_id' => $pnjId]);

        $playerPnj->setDisplayed($displayed); 

        $this->entityManager->persist($playerPnj);
        $this->entityManager->flush();
    }


}