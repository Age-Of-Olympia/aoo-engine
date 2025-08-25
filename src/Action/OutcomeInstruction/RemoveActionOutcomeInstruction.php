<?php

namespace App\Action\OutcomeInstruction;

use App\Entity\OutcomeInstruction;
use Doctrine\ORM\Mapping as ORM;
use Classes\ActorInterface;
use Classes\Str;

#[ORM\Entity]
class RemoveActionOutcomeInstruction extends OutcomeInstruction
{
    public function execute(ActorInterface $actor, ActorInterface $target): OutcomeResult {
        $params =$this->getParameters();
        // e.g. { "action": "name" }
        $actionToRemove = $params['action'] ?? null;

        $actor->end_action($actionToRemove);

        return new OutcomeResult(true, array(), array());
    }

}
