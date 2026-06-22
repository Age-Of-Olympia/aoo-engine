<?php
namespace App\Action\Condition;

use App\Action\Combat\CombatResolver;
use App\Action\Combat\RollDetail;
use App\Action\Combat\RollDetailView;

class DistanceComputeCondition extends ComputeCondition
{
    public static function targetDefenseValue(int $cc, int $agi): int
    {
        return (int) floor(max(3 / 4 * $cc + 1 / 4 * $agi, 1 / 4 * $cc + 3 / 4 * $agi));
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

        $targetRoll = (new CombatResolver($dice))->roll(
            (int) $targetRollTraitValue,
            (bool) $conditionObject->getTargetAdvantage(),
            (bool) $conditionObject->getTargetDisadvantage()
        );
        $bonus = (int) $conditionObject->getTargetRollBonus();
        $protection = (int) ($target->getEffectValue("protection") ?: 0);
        $vulnerabilite = (int) ($target->getEffectValue("vulnerabilite") ?: 0);
        $esquive = (int) ($target->caracs->esquive ?? 0);
        $malus = (int) $target->data->malus;
        $total = array_sum($targetRoll) - $malus + $bonus + $protection - $vulnerabilite + $esquive;

        $detail = new RollDetail(
            name: $target->data->name,
            rollSum: array_sum($targetRoll),
            bonus: $bonus,
            positiveEffect: $protection,
            negativeEffect: $vulnerabilite,
            malus: $malus,
            esquive: $esquive,
            total: $total,
        );

        $conditionObject->setTargetRoll($total);

        return array($targetRoll, $total, (new RollDetailView())->renderTarget($detail));
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