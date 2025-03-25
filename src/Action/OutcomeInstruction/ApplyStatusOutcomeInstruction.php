<?php

namespace App\Action\OutcomeInstruction;

use App\Entity\OutcomeInstruction;
use Doctrine\ORM\Mapping as ORM;
use Player;
use Str;

#[ORM\Entity]
class ApplyStatusOutcomeInstruction extends OutcomeInstruction
{
    public function execute(Player $actor, Player $target): OutcomeResult {
        $params =$this->getParameters();
        // e.g. { "adrenaline": true, "duration": 86400 }
        // e.g. { "adrenaline": true, "player": "actor", , "duration": 86400 }
        // e.g. { "finished": true, "player": "actor" }
        $status = array_key_first($params);
        if (isset(EFFECTS_HIDDEN[$status])) {
            $this->getOutcome()->getAction()->setHideOnSuccess(true);
        }
        $duration = $params['duration'] ?? 0;
        $player = $params['player'] ?? 'both';
        $outcomeSuccessMessages = array();
        switch ($player) {
            case 'actor':
                if ($status == "finished") {
                    $res = $actor->purge_effects();
                    if ($res > 0) {
                        $outcomeSuccessMessages[0] = $res .' effets terminés.';
                    }
                } else {
                    $this->applyEffect($params[$status], $status, $duration, $actor);
                    $outcomeSuccessMessages[0] = 'L\'effet '.$status.' est appliqué pour ' . Str::displaySeconds($duration) . ' à ' . $actor->data->name;
                }
                break;
            case 'target':
                $this->applyEffect($params[$status], $status, $duration, $target);
                $outcomeSuccessMessages[0] = 'L\'effet '.$status.' est appliqué pour ' . Str::displaySeconds($duration) . ' à ' . $target->data->name;
                break;
            default:
            $this->applyEffect($params[$status], $status, $duration, $actor);
            $this->applyEffect($params[$status], $status, $duration, $target);
            $outcomeSuccessMessages[0] = 'L\'effet '.$status.' est appliqué pour ' . Str::displaySeconds($duration) . ' à ' . $actor->data->name;
            $outcomeSuccessMessages[1] = 'L\'effet '.$status.' est appliqué pour ' . Str::displaySeconds($duration) . ' à ' . $target->data->name;
            break;
        }

        return new OutcomeResult(true, outcomeSuccessMessages:$outcomeSuccessMessages, outcomeFailureMessages: array());
    }

    private function applyEffect (bool $apply, string $effectName, int $duration, Player $player){
        if ($apply) {
            $player->addEffect($effectName, $duration);
        } else {
            $player->endEffect($effectName);
        } 
    }
}
