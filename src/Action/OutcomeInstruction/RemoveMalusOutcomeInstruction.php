<?php

namespace App\Action\OutcomeInstruction;

use App\Entity\OutcomeInstruction;
use App\Action\Condition\ConditionObject;
use Doctrine\ORM\Mapping as ORM;
use Classes\Player;

#[ORM\Entity]
class RemoveMalusOutcomeInstruction extends OutcomeInstruction
{
    public function execute(Player $actor, Player $target, ConditionObject $conditionObject): OutcomeResult {
        
        $malus = 0;
        $params = $this->getParameters();
        $actorCarac = $params['actorCarac'] ?? 0;
        $divisor =  $params['divisor'] ?? 1;

        $to = $param["to"] ?? "target";

        if (isset($params['fixedMalus']) && $params['fixedMalus']) {
            $malus = $params['fixedMalus'];
        }

        if(!empty($actorCarac) || !empty($actorCarac)){
            $malus = floor($actor->caracs->{$actorCarac}/$divisor);
        }

        if ($to == "target") {
            $target->put_malus(-$malus);
        } else if ($to == "actor") {
            $actor->put_malus(-$malus);
        }

        $outcomeMalusMessages = array();
        $outcomeMalusMessages[0] = 'Votre action retire '. $malus .' malus à ' . $target->data->name . '.';

        return new OutcomeResult(true, $outcomeMalusMessages, $outcomeMalusMessages);
    }

}
