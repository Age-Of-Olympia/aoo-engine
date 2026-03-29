<?php

namespace App\Action\OutcomeInstruction;

use App\Entity\OutcomeInstruction;
use App\Action\Condition\ConditionObject;
use App\Interface\ActorInterface;
use Doctrine\ORM\Mapping as ORM;
use Classes\Player;

#[ORM\Entity]
class ManaLossOutcomeInstruction extends OutcomeInstruction
{
    public function execute(Player $actor, Player $target, ConditionObject $conditionObject): OutcomeResult {

        // e.g. { "lossType": "carac", "value":"m", "typeDivisor":2 }
        // e.g. { "lossType": "fixed", "value":5 }
        // e.g. { "lossType": "difference" }
        $lossType = $this->getParameters()['lossType'] ?? '';
        $value = $this->getParameters()['value'] ?? 0;
        $typeDivisor = $this->getParameters()['typeDivisor'] ?? 1;
        $outcomeSuccessMessages = array();
        $outcomeFailureMessages = array();
        $backfire = false;

        switch ($lossType) {
            case "carac":
                $manaloss = floor($actor->caracs->{$value} / $typeDivisor);
                break;
            case "fixed":
                $manaloss = $value;
                break;
            case "difference":
                $manaloss = $conditionObject->getActorRoll() - $conditionObject->getTargetRoll();
                if($manaloss < 0){
                    $backfire = true;
                    $manaloss = abs($manaloss);
                    $outcomeFailureMessages[sizeof($outcomeFailureMessages)] = 'Aïe... votre sort se retourne contre vous.';
                }
                break;
            default:
                $manaloss = 0;
        }
        
        $finalTarget = $backfire ? $actor : $target;
        $remainingPM = $finalTarget->getRemaining("pm");
        if($remainingPM < $manaloss){
            $lifeloss = floor(($manaloss - $remainingPM)/2);
            $finalTarget->putBonus(array('pm'=>-$remainingPM));
            $outcomeSuccessMessages[sizeof($outcomeSuccessMessages)] = 'Vous faites perdre ' . $remainingPM . ' PM à ' . $finalTarget->data->name . '.';
            $finalTarget->putBonus(array('pv'=>-$lifeloss));
            $outcomeSuccessMessages[sizeof($outcomeSuccessMessages)] = $finalTarget->data->name . ' ne supporte pas l\'invasion psychique et perd ' . $lifeloss . ' PV.';
            $recoverMalus = floor($lifeloss/2);
            $finalTarget->put_malus(-$recoverMalus);
            $outcomeSuccessMessages[sizeof($outcomeSuccessMessages)] = $finalTarget->data->name . ' récupère ' . $recoverMalus . ' Malus.';

            if($backfire){
                $outcomeFailureMessages[sizeof($outcomeFailureMessages)] = 'Vous perdez ' . $remainingPM . ' PM.';
                $outcomeFailureMessages[sizeof($outcomeFailureMessages)] = 'Vous ne supportez pas l\'invasion psychique et perdez ' . $lifeloss . ' PV.';
                $outcomeFailureMessages[sizeof($outcomeFailureMessages)] = 'Vous récupèrez ' . $recoverMalus . ' Malus.';  
            }
        }
        else{
            $finalTarget->putBonus(array('pm'=>-$manaloss));
            $outcomeSuccessMessages[sizeof($outcomeSuccessMessages)] = 'Vous faites perdre ' . $manaloss . ' PM à ' . $target->data->name . '.';
            if($backfire){
                $outcomeFailureMessages[sizeof($outcomeFailureMessages)] = 'Vous perdez ' . $remainingPM . ' PM.';
            }
        }

        return new OutcomeResult(true, outcomeSuccessMessages:$outcomeSuccessMessages, outcomeFailureMessages:$outcomeFailureMessages);
    }

}

