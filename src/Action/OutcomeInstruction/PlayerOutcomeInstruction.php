<?php

namespace App\Action\OutcomeInstruction;

use App\Entity\OutcomeInstruction;
use Doctrine\ORM\Mapping as ORM;
use Player;
use Str;

#[ORM\Entity]
class PlayerOutcomeInstruction extends OutcomeInstruction
{
    public function execute(Player $actor, Player $target): OutcomeResult {
        $params =$this->getParameters();
        // e.g. {"carac": "fatigue", "value" : 4, "player": "actor"}
        // {"carac": "mvt", "value" : 1, "player": "actor"}
        // {"carac" : "fatigue", "value": 1, "player" : "target"}
        
        $player = $params['player'] ?? 'both';
        $carac = $params['carac'] ?? null;
        $value = $params['value'] ?? 0;
        $outcomeSuccessMessages = array();
        switch ($player) {
            case "actor":
                if ($carac != null) {
                    if ($carac == "fatigue") {
                        if($actor->data->fatigue){
                            $actor->putFat(-$value);
                            $fatigue = ($actor->data->fatigue > $value) ? $value : $actor->data->fatigue;
                            $outcomeSuccessMessages[0] = $fatigue .' Fatigues enlevées.';
                        } else {
                            $outcomeSuccessMessages[0] = 'Vous n\'aviez pas de fatigue.';
                        }
                    } else if ($carac == "foi") {
                        $god = new Player($actor->data->godId);
                        $god->get_data();
                        $pf = rand(1,3);
                        $actor->put_pf($pf);
                        $outcomeSuccessMessages[0] = 'Vous priez '. $god->data->name .' et gagnez '. $pf .' Points de Foi (total '. $actor->data->pf .'Pf).';
                        $outcomeSuccessMessages[1] = '1d3 = '. $pf;
                    } else {
                        $bonus = array($carac=>$value);
                        $actor->putBonus($bonus);
                        $outcomeSuccessMessages[0] = 'Vous courez ! (+'.$value.' mouvement !)';
                    }

                }
                break;
            case "target":
                if ($carac == "fatigue") {
                    $target->putFat($value);
                }
                break;
            default:
            break;
        }

        return new OutcomeResult(true, outcomeSuccessMessages:$outcomeSuccessMessages, outcomeFailureMessages: array());
    }

}
