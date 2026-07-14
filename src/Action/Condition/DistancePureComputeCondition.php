<?php
namespace App\Action\Condition;

use App\Action\Combat\CombatResolver;
use App\Action\Combat\RollDetailView;
use Classes\View;

class DistancePureComputeCondition extends ComputePureCondition
{

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
        
        $targetRoll = (new CombatResolver($dice))->rollDetailed(
            (int) $targetRollTraitValue,
            (bool) $conditionObject->getTargetAdvantage(),
            (bool) $conditionObject->getTargetDisadvantage()
        );
        $bonus = $conditionObject->getTargetRollBonus();
        $targetTotal = array_sum($targetRoll->roll) + $bonus;
        $tooltipOtherTxt = !empty($bonus) ? 'Bonus de compétence : ' . $conditionObject->getTargetRollBonus() . ' ' : '';
        $targetOtherTxt = ($bonus != 0) ? ($bonus < 0 ? ' - '.abs($bonus) : $bonus) . ' (<span style="text-decoration: underline;" flow="up" tooltip="' . $tooltipOtherTxt . '">Autre</span>) = ' . (array_sum($targetRoll->roll) + $bonus) . ' (Jet pur)' : ' (Jet pur)';
        $advantage = RollDetailView::advantageTooltip($targetRoll);
        $rollSumTxt = $advantage === ''
            ? (string) array_sum($targetRoll->roll)
            : '<span style="text-decoration: underline;" flow="up" tooltip="' . $advantage . '">' . array_sum($targetRoll->roll) . '</span>';
        $targetTxt = 'Jet '. $target->data->name .' = '. $rollSumTxt . $targetOtherTxt;

        $conditionObject->setTargetRoll($targetTotal);

        return array($targetRoll->roll, $targetTotal, $targetTxt);
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