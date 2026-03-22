<?php
namespace App\Action\Condition;

class MeleeComputeCondition extends ComputeCondition
{
  protected function computeTarget($target, $dice, $conditionObject)
    {
        $option1 = $target->caracs->cc;
        $option2 = $target->caracs->agi;
        $targetRollTraitValue = max($option1, $option2);
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

        return array($targetRoll, $targetTotal, $targetTxt);
    }
}