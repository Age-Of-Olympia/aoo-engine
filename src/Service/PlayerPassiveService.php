<?php

namespace App\Service;

use App\Action\Condition\ConditionObject;
use App\Entity\EntityManagerFactory;
use App\Entity\PlayerPassive;
use App\Entity\ActionPassive;
use Classes\Player;
use Classes\Db;

class PlayerPassiveService
{
    private $entityManager;

    public function __construct()
    {
        $this->entityManager = EntityManagerFactory::getEntityManager();
    }

    public function getPassivesByPlayerId(int $playerId): array
    {
        $repo = $this->entityManager->getRepository(PlayerPassive::class);
        $results = $repo->findBy(['playerId' => $playerId]);

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

        if($result->getCarac() == "lostPV"){
            return floor(($player->caracs->pv - $player->getRemaining("pv")) * $result->getValue());
        }
        
        if($result->getCarac() == "effects"){
            return floor(count($player->playerEffectService->getEffectsByPlayerId($player->getId())) * $result->getValue());
        }

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

    public function checkPassiveConditionsByPlayerById(Player $player, ActionPassive $passive, ConditionObject $conditionObject): bool
    {
        $conditions = $passive->getConditions();
        if(is_null($conditions)){
            return true;
        }
        // ex : {"weapon":["arc","arbalete"]}
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
        // ex : {"category":["melee-curse","melee-off"]}
        if(isset($conditions["category"])){
            return in_array($conditionObject->getAction()->getCategory(),$conditions["category"]);
        }
        return true;
    }

    public function addPassiveByPlayerId(int $playerId, int $passiveId): void
    {
        $db = new Db();
        $sql = "INSERT INTO players_passives (player_id, passive_id) VALUES (?, ?)";
    
        // On capture le résultat de l'exécution
        $res = $db->exe($sql, [$playerId, $passiveId]);

        // Si le résultat est faux ou nul, on arrête tout pour afficher l'erreur
        if (!$res) {
            exit('<div id="data">Erreur SQL : L\'insertion a échoué. Vérifiez les types de colonnes. (ID Joueur: '.$playerId.', ID Passif: '.$passiveId.')</div>');
        }
    }

    public function hasPassiveByPlayerId(int $playerId, int $passiveId): bool
    {
        $repo = $this->entityManager->getRepository(PlayerPassive::class);
    
        $passive = $this->entityManager->getReference(ActionPassive::class, $passiveId);

        $result = $repo->findOneBy([
            'playerId' => $playerId,
            'passive'  => $passive
        ]);

        return $result !== null;
    }

    public function hasPassiveByPlayerIdByName(int $playerId, string $name): bool
    {
        $repo = $this->entityManager->getRepository(PlayerPassive::class);
    
        $actionPassiveRepo = $this->entityManager->getRepository(ActionPassive::class);
        $passive = $actionPassiveRepo->findOneBy(['name' => $name]);

        if (!$passive) {
            return false;
        }

        $result = $repo->findOneBy([
            'playerId' => $playerId,
            'passive'  => $passive
        ]);

        return $result !== null;
    }

    public function removePassiveByPlayerId(int $playerId, int $passiveId): bool
    {
        $repo = $this->entityManager->getRepository(PlayerPassive::class);
    
        $passive = $this->entityManager->getReference(ActionPassive::class, $passiveId);

        $playerPassive = $repo->findOneBy([
            'playerId' => $playerId,
            'passive'  => $passive
        ]);

        if ($playerPassive !== null) {
            try {
                $this->entityManager->remove($playerPassive);
                $this->entityManager->flush();
                return true; 
            } catch (\Exception $e) {
                return false; 
            }
        }
        
            return false; 
    }

}