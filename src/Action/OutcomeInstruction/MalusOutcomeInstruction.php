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
        
        
        $difference = null;
        $params = $this->getParameters();

        if(!empty($this->getParameters()['rollDivisor'])){
            $difference = max(0,floor((array_sum($rollsArray[0]) - array_sum($rollsArray[1]))/$params['rollDivisor']));
        }

        $to = $param["to"] ?? "target";
        $malus = $difference ?? random_int(1,3);

        $malusAtt = 0;
        $malusAttText = null;
        if (isset($params['addBonusMalus']) && $params['addBonusMalus']) {
            $malusAtt = random_int(1,3);
            $malusAttText = $malusAtt . ' + ';
        }

        if ($to == "target") {
            $target->put_malus($malus+$malusAtt);
        } else if ($to == "actor") {
            $actor->put_malus($$malus+$malusAtt);
        }

        $malusTotalTxt = ($malusAttText) ? $malusAttText . $malus . ' = ' . $malusAtt+$malus : $malus;
        $outcomeMalusMessages = array();
        $outcomeMalusMessages[0] = 'Votre action inflige '. $malusTotalTxt .' malus à ' . $target->data->name . '.';

        return new OutcomeResult(true, $outcomeMalusMessages, $outcomeMalusMessages);
    }

}
