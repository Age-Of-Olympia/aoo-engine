<?php

namespace App\Action\EffectInstruction;

use App\Entity\EffectInstruction;
use Doctrine\ORM\Mapping as ORM;
use Player;
use Str;

#[ORM\Entity]
class PlayerEffectInstruction extends EffectInstruction
{
    public function execute(Player $actor, Player $target): EffectResult {
        $params =$this->getParameters();
        // e.g. {"carac": "fatigue", "value" : 4, "player": "actor"}
        // {"carac": "mvt", "value" : 1, "player": "actor"}
        
        $player = $params['player'] ?? 'both';
        $carac = $params['carac'] ?? null;
        $value = $params['value'] ?? 0;
        switch ($player) {
            case "actor":
                if ($carac != null) {
                    if ($carac == "fatigue") {
                        if($actor->data->fatigue){
                            $actor->put_fat(-$value);
                            $fat = ($actor->data->fatigue > $value) ? $value : $actor->data->fatigue;
                            $effectSuccessMessages[0] = $fat .' Fatigues enlevées.';
                        } else {
                            $effectSuccessMessages[0] = 'Vous n\'aviez pas de fatigue.';
                        }
                    } else {
                        $bonus = array($carac=>$value);
                        $actor->putBonus($bonus);
                        $effectSuccessMessages[0] = 'Vous courrez ! (+'.$value.' mouvement !)';
                    }

                }
                break;
            default:
            break;
        }

        return new EffectResult(true, effectSuccessMessages:$effectSuccessMessages, effectFailureMessages: array());
    }

}
