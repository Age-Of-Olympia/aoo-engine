<?php
namespace App\Action\Condition;

class DistanceComputeCondition extends ComputeCondition
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
                    $targetRollTraitValue = floor(9/10 * $trait1 + 1/10 * $trait2);
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
        $targetEffetVulnerabilite = $target->getEffectValue("vulnerabilite");
        $targetEffetProtection = $target->getEffectValue("protection");
        $effetVulnerabilite = !empty($targetEffetVulnerabilite) ? $targetEffetVulnerabilite : 0;
        $effetProtection = !empty($targetEffetProtection) ? $targetEffetProtection : 0;
        $targetEsq = $target->caracs->esquive ?? 0;
        $bonus = $conditionObject->getTargetRollBonus();
        $totalOther = $bonus + $effetProtection - $effetVulnerabilite + $targetEsq;
        $targetTotal = array_sum($targetRoll) - $target->data->malus + $totalOther;
        $malusTxt = ($target->data->malus != 0) ? ' - '. $target->data->malus .' (Malus)' : '';
        $targetTotalTxt = $target->data->malus ? ' = '. $targetTotal : '';
        $tooltipOtherTxt = 
            (!empty($targetEffetProtection) || !empty($targetEffetVulnerabilite)
            ? 'Effets :' .
            (!empty($targetEffetProtection) ? ' ' . $effetProtection : '') .
            (!empty($targetEffetVulnerabilite) ? ' - ' . $effetVulnerabilite : '') . ' '
            : ''
            ) .
            (($targetEsq != 0) ? 'Esquive : ' . ($targetEsq < 0 ? ' - ' . abs($targetEsq) : $targetEsq) . ' ' : '') .
            (!empty($targetRollBonus) ? 'Bonus de compétence : ' . $targetRollBonus . ' ' : '');
        $targetOtherTxt = ($targetEsq != 0 || $bonus != 0 || $effetVulnerabilite != 0 || $effetProtection != 0) ? ($totalOther < 0 ? ' - '.abs($totalOther) : ' + ' . $totalOther) . ' (<span style="text-decoration: underline;" flow="up" tooltip="' . $tooltipOtherTxt . '">Autre</span>)' : '';
        $targetTxt = 'Jet '. $target->data->name .' = '. array_sum($targetRoll) . $targetOtherTxt . $malusTxt . $targetTotalTxt;

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