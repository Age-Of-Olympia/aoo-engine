<?php

namespace App\Action\OutcomeInstruction;

use App\Entity\OutcomeInstruction;
use App\Action\Condition\ConditionObject;
use Doctrine\ORM\Mapping as ORM;
use Classes\Player;

#[ORM\Entity]
class RefreshScreenOutcomeInstruction extends OutcomeInstruction
{
    public function execute(Player $actor, Player $target, ConditionObject $conditionObject): OutcomeResult {

        $this->getOutcome()->getAction()->setRefreshScreen(true);

        return new OutcomeResult(true, outcomeSuccessMessages:array(), outcomeFailureMessages: array());
    }

}
