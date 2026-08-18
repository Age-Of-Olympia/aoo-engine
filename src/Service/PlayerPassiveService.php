<?php

namespace App\Service;

use App\Action\Combat\PassiveValueCalculator;
use App\Action\Condition\ConditionObject;
use App\Factory\EntityManagerFactory;
use App\Entity\PlayerPassive;
use App\Entity\ActionPassive;
use Classes\Player;
use Classes\Db;

class PlayerPassiveService
{
    private $entityManager;
    private PassiveValueCalculator $passiveValueCalculator;

    public function __construct(?PassiveValueCalculator $passiveValueCalculator = null)
    {
        $this->entityManager = EntityManagerFactory::getEntityManager();
        $this->passiveValueCalculator = $passiveValueCalculator ?? new PassiveValueCalculator();
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

        if ($result === null) {
            return 0;
        }

        // "fixed" needs no player state — keep the early return so it doesn't load one.
        if ($result->getCarac() === "fixed") {
            return (int) $result->getValue();
        }

        $player = new Player($playerId);

        // The trait branch reads caracs; lostPV/effects read pv/effects directly.
        if ($result->getCarac() !== "lostPV" && $result->getCarac() !== "effects") {
            $player->get_caracs();
        }

        return $this->passiveValueCalculator->compute($result, $player);
    }

    /**
     * Appelé en fin de Player::get_caracs() : les caracs sont déjà
     * calculées (surtout ne pas rappeler get_caracs ici — récursion).
     */
    public function setEsquivePlayer(Player $player): void
    {
        $passives = $this->getPassivesByPlayerId($player->getId());
        $esquive = 0;

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
