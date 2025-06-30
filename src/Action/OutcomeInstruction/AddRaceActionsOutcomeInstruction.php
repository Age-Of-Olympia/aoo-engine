<?php

namespace App\Action\OutcomeInstruction;

use App\Entity\OutcomeInstruction;
use Doctrine\ORM\Mapping as ORM;
use Classes\Player;


#[ORM\Entity]
class AddRaceActionsOutcomeInstruction extends OutcomeInstruction
{
    public function execute(Player $actor, Player $target, array $rollsArray): OutcomeResult {

        $raceJson = json()->decode('races', $actor->data->race);

        foreach($raceJson->actions as $e){
            $actor->add_action($e);
        }

        return new OutcomeResult(true, array(), array());
    }

}
