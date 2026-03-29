<?php

namespace App\Action\OutcomeInstruction;

use App\Entity\OutcomeInstruction;
use App\Action\Condition\ConditionObject;
use Doctrine\ORM\Mapping as ORM;
use Classes\Player;

#[ORM\Entity]
class MalusOutcomeInstruction extends OutcomeInstruction
{
    public function execute(Player $actor, Player $target, ConditionObject $conditionObject): OutcomeResult {
        
        $malus = random_int(1,3);
        $params = $this->getParameters();

        if(!empty($this->getParameters()['rollDivisor'])){
            $difference = max(0,floor(($conditionObject->getActorRoll() - $conditionObject->getTargetRoll())/$params['rollDivisor']));
            $malusText = $malus . ' + ' . $difference . ' (Jet)';
        }

        $malusTot = (isset($difference)) ? $malus+$difference : $malus;

        $to = $param["to"] ?? "target";

        if ($to == "target") {
            $target->put_malus($malusTot);
        } else if ($to == "actor") {
            $actor->put_malus($malusTot);
        }

        $malusTotalTxt = isset($malusText) ? $malusText . ' = ' . $malusTot : $malus;
        $outcomeMalusMessages = array();
        $outcomeMalusMessages[0] = 'Votre action inflige '. $malusTotalTxt .' malus à ' . $target->data->name . '.';

        return new OutcomeResult(true, $outcomeMalusMessages, $outcomeMalusMessages);
    }


}