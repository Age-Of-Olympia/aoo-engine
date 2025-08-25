<?php

namespace App\Action\OutcomeInstruction;

use App\Entity\OutcomeInstruction;
use App\Interface\ActorInterface;
use Doctrine\ORM\Mapping as ORM;

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
