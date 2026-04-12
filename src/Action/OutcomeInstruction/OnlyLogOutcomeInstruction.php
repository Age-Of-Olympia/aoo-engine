<?php

namespace App\Action\OutcomeInstruction;

use App\Entity\OutcomeInstruction;
use App\Action\Condition\ConditionObject;
use Doctrine\ORM\Mapping as ORM;
use Classes\Player;

#[ORM\Entity]
class OnlyLogOutcomeInstruction extends OutcomeInstruction
{
    public function execute(Player $actor, Player $target, ConditionObject $conditionObject): OutcomeResult {
        $actorRank = $actor->data->rank;
        $targetRank = $target->data->rank;

        $outcomeSuccessMessages = array();
        $outcomeSuccessMessages[sizeof($outcomeSuccessMessages)] = $actor->data->name .' (rang '. $actorRank .')';
        $outcomeSuccessMessages[sizeof($outcomeSuccessMessages)] = $target->data->name .' (rang '. $targetRank .')';

        return new OutcomeResult(true, outcomeSuccessMessages:$outcomeSuccessMessages, outcomeFailureMessages: array());

    }

}
