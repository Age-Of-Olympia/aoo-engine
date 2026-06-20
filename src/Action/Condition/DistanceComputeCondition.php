<?php
namespace App\Action\Condition;

use App\Action\Combat\CombatResolver;

class DistanceComputeCondition extends ComputeCondition
{
    public function __construct()
    {
        parent::__construct();
        array_push($this->preConditions, new ObstacleCondition());
    }

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
        return self::distanceMalusFor($this->distance);
    }
}