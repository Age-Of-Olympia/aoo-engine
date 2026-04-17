<?php
namespace App\Action\Condition;

use Classes\View;

class DistancePureComputeCondition extends ComputePureCondition
{
    public function __construct()
    {
        parent::__construct();
        array_push($this->preConditions, new ObstacleCondition());
    }

    protected function getDistanceTreshold() : int {
        return floor(($this->distance) * 2.5);
    }

    protected function computeTarget($target, $dice, $conditionObject)
    {
        $trait1 = $target->caracs->cc;
        $trait2 = $target->caracs->agi;
        $targetRollTraitValue = floor(max(3/4 * $trait1 + 1/4 * $trait2, 1/4 * $trait1 + 3/4 * $trait2));

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
        
        $targetRoll = $dice->roll($targetRollTraitValue);
        if($conditionObject->getTargetAdvantage() && $conditionObject->getTargetDisadvantage()){
            // Do nothing if advantage and disadvantage
        }
        elseif($conditionObject->getTargetAdvantage() || $conditionObject->getTargetDisadvantage()){
            $targetRoll2 = $dice->roll($targetRollTraitValue);
            if($conditionObject->getTargetAdvantage()){
                $targetRoll = max($targetRoll,$targetRoll2);
            }   
            else{
                $targetRoll = min($targetRoll,$targetRoll2);
            }
        }
        $bonus = $conditionObject->getTargetRollBonus();
        $targetTotal = array_sum($targetRoll) + $bonus;
        $tooltipOtherTxt = !empty($bonus) ? 'Bonus de compétence : ' . $conditionObject->getTargetRollBonus() . ' ' : '';
        $targetOtherTxt = ($bonus != 0) ? ($bonus < 0 ? ' - '.abs($bonus) : $bonus) . ' (<span style="text-decoration: underline;" flow="up" tooltip="' . $tooltipOtherTxt+$bonus . '">Autre</span>) = ' . array_sum($targetRoll)+$bonus . ' (Jet pur)' : ' (Jet pur)';
        $targetTxt = 'Jet '. $target->data->name .' = '. array_sum($targetRoll) . $targetOtherTxt;

        $conditionObject->setTargetRoll($targetTotal);
        
        return array($targetRoll, $targetTotal, $targetTxt);
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
        $distanceMalus = 0;
        $cellCount = $this->distance - 1;
        if($cellCount > 2){
            $distanceMalus = ($cellCount - 2) * 3;
        }
        return $distanceMalus;
    }
}