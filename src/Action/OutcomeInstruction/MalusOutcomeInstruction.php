<?php

namespace App\Action\OutcomeInstruction;

use App\Entity\OutcomeInstruction;
use Doctrine\ORM\Mapping as ORM;
use Classes\Item;
use Classes\Player;
use Classes\View;

#[ORM\Entity]
class MalusOutcomeInstruction extends OutcomeInstruction
{
    public function execute(Player $actor, Player $target): OutcomeResult {
        $params =$this->getParameters();
        $to = $param["to"] ?? "target";
        $malus = $params["malus"] ?? random_int(1,3);

        if ($to == "target") {
            $target->put_malus($malus);
        } else if ($to == "actor") {
            $actor->put_malus($malus);
        }

        $outcomeMalusMessages = array();
        $outcomeMalusMessages[0] = 'Votre action inflige '.$malus.' malus à ' . $target->data->name . '.';

        return new OutcomeResult(true, $outcomeMalusMessages, $outcomeMalusMessages);
    }

}
