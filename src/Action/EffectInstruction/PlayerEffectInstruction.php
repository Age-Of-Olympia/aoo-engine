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
        // e.g. {"fatigue": -4, "player": "actor"}
        $fatigue = $params['fatigue'] ?? FAT_PER_REST;
        $player = $params['player'] ?? 'both';
        switch ($player) {
            default:
            if($actor->data->fatigue){
                $actor->put_fat(-$fatigue);
                $fat = ($actor->data->fatigue > $fatigue) ? $fatigue : $actor->data->fatigue;
                $effectSuccessMessages[0] = $fat .' Fatigues enlevées.';
            }
            break;
        }

        return new EffectResult(true, effectSuccessMessages:$effectSuccessMessages, effectFailureMessages: array());
    }

}
