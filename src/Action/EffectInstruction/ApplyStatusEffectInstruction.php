<?php

namespace App\Action\EffectInstruction;

use App\Entity\EffectInstruction;
use Doctrine\ORM\Mapping as ORM;
use Player;
use Str;

#[ORM\Entity]
class ApplyStatusEffectInstruction extends EffectInstruction
{
    public function execute(Player $actor, Player $target): EffectResult {
        $params =$this->getParameters();
        // e.g. { "adrenaline": true, "duration": 86400 }
        $status = array_key_first($params);
        $duration = $params['duration'] ?? 0;
        $player = $params['player'] ?? 'BOTH';
        switch ($player) {
            case 'ACTOR':
                $this->applyEffect($params[$status], $status, $duration, $actor);
                $effectSuccessMessages[0] = 'L\'effet '.$status.' est appliqué pour ' . Str::displaySeconds($duration) . ' à ' . $actor->data->name;
                break;
            case 'TARGET':
                $this->applyEffect($params[$status], $status, $duration, $target);
                $effectSuccessMessages[0] = 'L\'effet '.$status.' est appliqué pour ' . Str::displaySeconds($duration) . ' à ' . $target->data->name;
                break;
            default:
            $this->applyEffect($params[$status], $status, $duration, $actor);
            $this->applyEffect($params[$status], $status, $duration, $target);
            $effectSuccessMessages[0] = 'L\'effet '.$status.' est appliqué pour ' . Str::displaySeconds($duration) . ' à ' . $actor->data->name;
            $effectSuccessMessages[1] = 'L\'effet '.$status.' est appliqué pour ' . Str::displaySeconds($duration) . ' à ' . $target->data->name;
            break;
        }

        return new EffectResult(true, effectSuccessMessages:$effectSuccessMessages, effectFailureMessages: array());
    }

    private function applyEffect (bool $apply, string $effectName, int $duration, Player $player){
        if ($apply) {
            $player->addEffect($effectName, $duration);
        } else {
            $player->endEffect($effectName);
        } 
    }
}
