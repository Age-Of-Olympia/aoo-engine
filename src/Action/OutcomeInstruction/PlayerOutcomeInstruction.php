<?php

namespace App\Action\OutcomeInstruction;

use App\Entity\OutcomeInstruction;
use Doctrine\ORM\Mapping as ORM;
use Classes\Player;
use Classes\Str;

#[ORM\Entity]
class PlayerOutcomeInstruction extends OutcomeInstruction
{
    public function execute(Player $actor, Player $target, array $rollsArray): OutcomeResult {
        $params =$this->getParameters();
        // e.g. {"carac": "energie", "value" : 4, "player": "actor"}
        // {"carac": "mvt", "value" : 1, "player": "actor"}
        // {"carac" : "energie", "value": 1, "player" : "target"}
        
        $player = $params['player'] ?? 'both';
        $carac = $params['carac'] ?? null;
        $value = $params['value'] ?? 0;
        $outcomeSuccessMessages = array();
        switch ($player) {
            case "actor":
                if ($carac != null) {
                    if ($carac == "foi") {
                        $god = new Player($actor->data->godId);
                        $god->get_data();
                        $pf = rand(1,3);
                        $actor->put_pf($pf);
                        $outcomeSuccessMessages[0] = 'Vous priez '. $god->data->name .' et gagnez '. $pf .' Points de Foi (total '. $actor->data->pf .'Pf).';
                        $outcomeSuccessMessages[1] = '1d3 = '. $pf;
                    } else {
                        $bonus = array($carac=>$value);
                        $actor->putBonus($bonus);
                        $outcomeSuccessMessages[0] = 'Vous courez ! (+'.$value.' mouvement !)' . $carac . '(valeur) !';
                    }

                }
                break;
            case "target":
                break;
            default:
            break;
        }

        return new OutcomeResult(true, outcomeSuccessMessages:$outcomeSuccessMessages, outcomeFailureMessages: array());
    }

}
