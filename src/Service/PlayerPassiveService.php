<?php

namespace App\Service;

use App\Entity\EntityManagerFactory;
use App\Entity\PlayerPassive;
use Classes\Player;

class PlayerPassiveService
{
    private $entityManager;

    public function __construct()
    {
        // Fetch the entity manager from your custom factory
        $this->entityManager = EntityManagerFactory::getEntityManager();
    }

    public function getPassivesByPlayerId(int $playerId): array
    {
        $repo = $this->entityManager->getRepository(PlayerPassive::class);

        return $repo->findBy(['player_id' => $playerId]);
    }

    public function getComputedValueByPlayerIdByName(int $playerId, $name): int
    {
        $repo = $this->entityManager->getRepository(PlayerPassive::class);

        $results = $repo->findOneBy([
            'player_id' => $playerId,
            'name' => $name
        ]);
        
        if($results->getCarac() == "fixed"){
            return $results->getValue();
        }

        $player = new Player($playerId);
        $player->get_caracs();
        return floor($player->caracs->{$results->getCarac()} * $results->getValue());
    }

    public function setEsquivePlayer(Player $player): void
    {
        $repo = $this->entityManager->getRepository(PlayerPassive::class);
        $esquive = 0;

        $results = $repo->findBy([
            'player_id' => $player->id
        ]);
        
        $player->get_caracs();

        foreach($results as $passive){
            if (in_array("esquive", $passive->getTraits())){
                if($passive->getCarac() == "fixed"){
                    $esquive += $passive->getValue();
                }
                else{
                    $esquive += floor($player->caracs->{$passive->getCarac()} * $passive->getValue());
                }
            }
        }
        
        $player->caracs->esquive = $esquive;
    }

}