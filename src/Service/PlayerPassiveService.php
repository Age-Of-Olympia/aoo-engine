<?php

namespace App\Service;

use App\Database\QueryCache;
use App\Action\Combat\PassiveValueCalculator;
use App\Action\Condition\ConditionObject;
use App\Factory\EntityManagerFactory;
use App\Entity\PlayerPassive;
use App\Entity\ActionPassive;
use App\Interface\ActorInterface;
use Classes\Player;
use Classes\Db;
use Classes\Log;
use App\Service\Action\ActionLogResolver;

class PlayerPassiveService
{
    private $entityManager;
    private PassiveValueCalculator $passiveValueCalculator;
    /** @var array<int, ActionPassive> passive id => passive that took effect for this service's player */
    private array $triggered = [];

    public function __construct(?PassiveValueCalculator $passiveValueCalculator = null)
    {
        $this->entityManager = EntityManagerFactory::getEntityManager();
        $this->passiveValueCalculator = $passiveValueCalculator ?? new PassiveValueCalculator();
    }

    public function getPassivesByPlayerId(int $playerId): array
    {
        $repo = $this->entityManager->getRepository(PlayerPassive::class);
        $load = fn(): array => $repo->findBy(['playerId' => $playerId]);
        $results = QueryCache::remember(['players_passives'], 'passives:' . $playerId, $load);

        // Detached by an EntityManager::clear() (admin tools): read again
        if ($results !== [] && !$this->entityManager->contains($results[0])) {
            $results = $load();
        }

        $passiveArray = [];
        foreach ($results as $playerPassive) {
            $actionPassive = $playerPassive->getPassive();
            if ($actionPassive !== null) {
                $passiveArray[] = $actionPassive;
            }
    }
    
    return $passiveArray;
    }

    /**
     * A non-zero value is the passive taking effect: it is noted for the action's
     * journal unless $record is false (a value read only to test a threshold).
     */
    public function getComputedValueByPlayerIdById(int $playerId, $id, bool $record = true): int
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
            return $this->noted($result, (int) $result->getValue(), $record);
        }

        $player = new Player($playerId);

        // The trait branch reads caracs; lostPV/effects read pv/effects directly.
        if ($result->getCarac() !== "lostPV" && $result->getCarac() !== "effects") {
            $player->get_caracs();
        }

        return $this->noted($result, $this->passiveValueCalculator->compute($result, $player), $record);
    }

    private function noted(ActionPassive $passive, int $value, bool $record): int
    {
        if ($record && $value !== 0) {
            $this->markTriggered($passive);
        }

        return $value;
    }

    /** Note that $passive took effect (advantage flags, thresholds). */
    public function markTriggered(ActionPassive $passive): void
    {
        $this->triggered[$passive->getId()] = $passive;
    }

    /** Whether the player holds the passive named $name; if so it is noted as taking effect. */
    public function triggerByName(int $playerId, string $name): bool
    {
        $passive = $this->entityManager->getRepository(ActionPassive::class)->findOneBy(['name' => $name]);
        if ($passive === null || !$this->hasPassiveByPlayerId($playerId, $passive->getId())) {
            return false;
        }
        $this->markTriggered($passive);

        return true;
    }

    /**
     * The passives noted since the last call, and forget them.
     *
     * @return array<int, ActionPassive> passive id => passive
     */
    public function takeTriggered(): array
    {
        $triggered = $this->triggered;
        $this->triggered = [];

        return $triggered;
    }

    /**
     * Appelé en fin de Player::get_caracs() : les caracs sont déjà
     * calculées (surtout ne pas rappeler get_caracs ici — récursion).
     */
    public function setEsquivePlayer(Player $player): void
    {
        $passives = $this->getPassivesByPlayerId($player->getId());
        $esquive = 0;

        foreach ($passives as $passive) {
            if (in_array('esquive', $passive->getTraits(), true)) {
                $esquive += $this->passiveValueCalculator->compute($passive, $player);
            }
        }
        
        $player->caracs->esquive = $esquive;
    }

    public function checkPassiveConditionsByPlayerById(ActorInterface $player, ActionPassive $passive, ?ConditionObject $conditionObject = null): bool
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
            // Outside an action (push, sight...) a category condition cannot hold.
            return $conditionObject !== null && in_array($conditionObject->getAction()->getCategory(), $conditions["category"]);
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

    /**
     * The war-school path: the player learns the passive and reads it in their
     * own journal. Admin grants go through addPassiveByPlayerId() and stay silent.
     */
    public function learnPassive(Player $player, ActionPassive $passive): void
    {
        $this->addPassiveByPlayerId($player->getId(), $passive->getId());

        $text = (new ActionLogResolver())->renderPassive($passive->getLearnTemplate(), $passive, $player, $player);
        if ($text !== '') {
            Log::put($player, $player, $text, 'learn');
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
