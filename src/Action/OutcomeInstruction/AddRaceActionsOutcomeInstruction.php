<?php

namespace App\Action\OutcomeInstruction;

use App\Entity\OutcomeInstruction;
use App\Interface\ActorInterface;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity]
class AddRaceActionsOutcomeInstruction extends OutcomeInstruction
{
    public function execute(ActorInterface $actor, ActorInterface $target): OutcomeResult {

        $raceJson = json()->decode('races', $actor->data->race);

        foreach($raceJson->actions as $e){
            $actor->add_action($e);
        }

        return new OutcomeResult(true, array(), array());
    }

}
