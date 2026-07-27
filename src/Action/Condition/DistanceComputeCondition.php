<?php
namespace App\Action\Condition;

use App\Action\Combat\CombatResolver;
use App\Action\Combat\RollDetail;
use App\Action\Combat\RollDetailView;
use App\Action\Schema\DeclaresSimulationInputs;

class DistanceComputeCondition extends ComputeCondition implements DeclaresSimulationInputs
{
    /**
     * Ligne de tir : une structure qui arrête les projectiles
     * (races.blocks_projectiles — un mur, pas une table) entre le
     * tireur et la cible fait échouer le tir. La flèche part quand
     * même : l'échec suit le drapeau blocking de la condition, comme
     * une esquive.
     */
    public function check(\App\Interface\ActorInterface $actor, ?\App\Interface\ActorInterface $target, \App\Entity\ActionCondition $condition, ConditionObject $conditionObject): ConditionResult
    {
        /* Le contrôle de ligne de tir a été retiré d'ici : il vivait en
         * double, une fois ici pour le tir à distance et une fois dans
         * ObstacleCondition pour les cinq autres types de calcul — avec deux
         * géométries et deux catalogues d'obstacles différents. Il est
         * désormais dans ObstacleCondition seule, déclarée en précondition
         * des six types. */
        return parent::check($actor, $target, $condition, $conditionObject);
    }

    public static function targetDefenseValue(int $cc, int $agi): int
    {
        return (int) floor(max(3 / 4 * $cc + 1 / 4 * $agi, 1 / 4 * $cc + 3 / 4 * $agi));
    }

    public static function simulationInputs(array $params): array
    {
        return self::physicalDefenseSimulationInputs($params);
    }

    public static function distanceMalusFor(int $distance): int
    {
        $cellCount = $distance - 1;

        return $cellCount > 2 ? ($cellCount - 2) * 3 : 0;
    }

    public static function distanceThresholdFor(int $distance): int
    {
        return (int) floor($distance * 2.5);
    }

    protected function getDistanceTreshold() : int {
        return self::distanceThresholdFor($this->distance);
    }

    protected function computeTarget($target, $dice, $conditionObject)
    {
        $trait1 = $target->caracs->cc;
        $trait2 = $target->caracs->agi;
        $targetRollTraitValue = self::targetDefenseValue((int) $trait1, (int) $trait2);
        
        if($target->playerPassiveService->hasPassiveByPlayerIdByName($target->getId(),"reflexes_fulgurants")){
            $targetRollTraitValue = floor(6/7 * $trait2 + 1/7 * $trait1);
        }
        if($target->playerPassiveService->hasPassiveByPlayerIdByName($target->getId(),"couverture")){
            $equipedItems = $target->getEquipedItems();
            foreach($equipedItems as $item){
                if(in_array($item->name, ["bouclier_parma","bouclier_clipeus","bouclier_ancile","targe","bouclier_lianes","targe_renforcee"] )){
                    $targetRollTraitValue = floor(6/7 * $trait1 + 1/7 * $trait2);
                }
            }
        }

        $targetRoll = (new CombatResolver($dice))->rollDetailed(
            (int) $targetRollTraitValue,
            (bool) $conditionObject->getTargetAdvantage(),
            (bool) $conditionObject->getTargetDisadvantage()
        );
        $bonus = (int) $conditionObject->getTargetRollBonus();
        // Modificateurs du jet de défense portés par les effets (catalogue).
        $mods = (new \App\Service\EffectService())->modifierContributions($target->getEffects(), 'getRollDefenseMod');
        $esquive = (int) ($target->caracs->esquive ?? 0);
        $malus = (int) $target->data->malus;
        $total = array_sum($targetRoll->roll) - $malus + $bonus + $mods['pos'] - $mods['neg'] + $esquive;

        $detail = new RollDetail(
            name: $target->data->name,
            rollSum: array_sum($targetRoll->roll),
            bonus: $bonus,
            positiveEffect: $mods['pos'],
            negativeEffect: $mods['neg'],
            malus: $malus,
            esquive: $esquive,
            total: $total,
            advantage: $targetRoll,
        );

        $conditionObject->setTargetRoll($total);

        return array($targetRoll->roll, $total, (new RollDetailView())->renderTarget($detail));
    }

    protected function checkDistanceCondition(int $actorTotal): bool {
        $checkAboveDistance = true;
        if($this->distance > 1){
            $distanceTreshold = $this->getDistanceTreshold();
            $checkAboveDistance = $actorTotal >= $distanceTreshold;
        }
        return $checkAboveDistance;
    }
    
    protected function getDistanceMalus(): int {
        return self::distanceMalusFor($this->distance);
    }
}