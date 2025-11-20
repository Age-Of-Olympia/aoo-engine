<?php

namespace App\Service;

use App\Entity\EntityManagerFactory;
use App\Entity\PlayerPassive;
use App\Entity\ActionPassive;
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
        $results = $repo->findBy(['player_id' => $playerId]);
    
        $passiveArray = [];
        foreach ($results as $playerPassive) {
            $actionPassive = $playerPassive->getPassive();
            if ($actionPassive !== null) {
                $passiveArray[] = $actionPassive;
            }
    }
    
    return $passiveArray;
    }

    public function getComputedValueByPlayerIdById(int $playerId, $id): int
    {
        $repo = $this->entityManager->getRepository(ActionPassive::class);
        
        $result = $repo->findOneBy([
            'id' => $id,
        ]);
        
        if($result->getCarac() == "fixed"){
            return $result->getValue();
        }

        $player = new Player($playerId);
        $player->get_caracs();
        return floor($player->caracs->{$result->getCarac()} * $result->getValue());
    }

    public function setEsquivePlayer(Player $player): void
    {
        $passives = $this->getPassivesByPlayerId($player->getId());
        $esquive = 0;
        $player->get_caracs();

        foreach($passives as $passive){
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

    public function checkPassiveConditionsByPlayerById(Player $player, ActionPassive $passive): bool
    {
        $conditions = $passive->getConditions();
        if(is_null($conditions)){
            return true;
        }
        if(isset($conditions["weapon"])){
            $equipedItems = $player->getEquipedItems();
            $emptyHandCondition = in_array("poing", $conditions["weapon"]);
            $emptyHands = true;
            foreach($equipedItems as $item){
                if(in_array($item->name, $conditions["weapon"])){
                    return true;
                }
                if($emptyHands && ($item->equiped == "main1" ||  $item->equiped == "deuxmains")){
                    $emptyHands = false;
                }
            }
            if($emptyHandCondition){
                return $emptyHands;
            }
            return false;
        }
        return true;
    }

}