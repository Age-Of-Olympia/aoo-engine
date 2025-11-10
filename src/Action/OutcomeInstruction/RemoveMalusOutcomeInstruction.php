<?php

namespace App\Action\OutcomeInstruction;

use App\Entity\OutcomeInstruction;
use Doctrine\ORM\Mapping as ORM;
use Classes\Player;

#[ORM\Entity]
class RemoveMalusOutcomeInstruction extends OutcomeInstruction
{
    public function execute(Player $actor, Player $target, array $rollsArray): OutcomeResult {
        
        $malus = 0;
        $params = $this->getParameters();

        $to = $param["to"] ?? "target";

        if (isset($params['fixedMalus']) && $params['fixedMalus']) {
            $malus = $params['fixedMalus'];
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
