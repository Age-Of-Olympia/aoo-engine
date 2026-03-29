<?php

namespace App\Action\OutcomeInstruction;

use App\Entity\OutcomeInstruction;
use App\Action\Condition\ConditionObject;
use Doctrine\ORM\Mapping as ORM;
use Classes\Player;

#[ORM\Entity]
class RestOutcomeInstruction extends OutcomeInstruction
{
    public function execute(Player $actor, Player $target, ConditionObject $conditionObject): OutcomeResult {
        
        $recupPV = floor($actor->getRemaining("a")*$actor->caracs->r/4);
        $recupPM = floor($actor->getRemaining("a")*$actor->caracs->rm/4);
        $recupMalus = floor($actor->getRemaining("mvt")/3);

        $actor->put_malus(-$recupMalus/3);

        $outcomeMalusMessages = array();
        $outcomeMalusMessages[] = 'Votre repos vous retire '. $recupMalus .' malus.';
        $outcomeMalusMessages[] = 'Votre repos vous rend '. $recupPV .' PV.';
        $outcomeMalusMessages[] = 'Votre repos vous rend '. $recupPM .' PM.';

        return new OutcomeResult(true, $outcomeMalusMessages, $outcomeMalusMessages);
    }

}
