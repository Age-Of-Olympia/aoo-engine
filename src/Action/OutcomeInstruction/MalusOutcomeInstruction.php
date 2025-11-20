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
    public function execute(Player $actor, Player $target, array $rollsArray): OutcomeResult {
        
        $malus = random_int(1,3);
        $params = $this->getParameters();

        if(!empty($this->getParameters()['rollDivisor'])){
            $difference = max(0,floor((array_sum($rollsArray[0]) - array_sum($rollsArray[1]))/$params['rollDivisor']));
            $malusText = $malus . ' + ' . $difference . ' (Jet)';
        }

        $malusTot = (isset($difference)) ? $malus+$difference : $malus;

        $to = $param["to"] ?? "target";

        if ($to == "target") {
            $target->put_malus($malusTot);
        } else if ($to == "actor") {
            $actor->put_malus($malusTot);
        }

        $malusTotalTxt = ($malusText) ? $malusText . ' = ' . $malusTot : $malus;
        $outcomeMalusMessages = array();
        $outcomeMalusMessages[0] = 'Votre action inflige '. $malusTotalTxt .' malus à ' . $target->data->name . '.';

        return new OutcomeResult(true, $outcomeMalusMessages, $outcomeMalusMessages);
    }


}