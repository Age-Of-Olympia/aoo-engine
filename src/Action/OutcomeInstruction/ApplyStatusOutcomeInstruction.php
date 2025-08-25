<?php

namespace App\Action\OutcomeInstruction;

use App\Entity\OutcomeInstruction;
use App\Interface\ActorInterface;
use Doctrine\ORM\Mapping as ORM;
use Classes\Str;

#[ORM\Entity]
class ApplyStatusOutcomeInstruction extends OutcomeInstruction
{
    public function execute(ActorInterface $actor, ActorInterface $target): OutcomeResult {
        $params =$this->getParameters();
        // e.g. { "adrenaline": true, "duration": 86400 }
        // e.g. { "adrenaline": true, "player": "actor" , "duration": 86400 }
        // e.g. { "finished": true, "player": "actor" }
        $status = array_key_first($params);
        if (in_array($status, EFFECTS_HIDDEN)) {
            $this->getOutcome()->getAction()->setHideOnSuccess(true);
        }
        $duration = $params['duration'] ?? 0;
        $timeMessage = 'pour ' . Str::displaySeconds($duration);
        if ($duration == 0) {
            $timeMessage = 'jusqu\'au prochain tour';
        }
        $player = $params['player'] ?? 'both';
        $outcomeSuccessMessages = array();
        switch ($player) {
            case 'actor':
                if ($status == "finished") {
                    $res = $actor->purge_effects();
                    if ($res > 0) {
                        $outcomeSuccessMessages[0] = $res .' effet(s) terminé(s).';
                    }
                } else {
                    $this->applyEffect($params[$status], $status, $duration, $actor);
                    $outcomeSuccessMessages[0] = 'L\'effet '.$status.' <span class="ra '. EFFECTS_RA_FONT[$status] .'"></span> est appliqué '. $timeMessage.' à ' . $actor->data->name;
                }
                break;
            case 'target':
                $this->applyEffect($params[$status], $status, $duration, $target);
                $outcomeSuccessMessages[0] = 'L\'effet '.$status.' <span class="ra '. EFFECTS_RA_FONT[$status] .'"></span> est appliqué '. $timeMessage. ' à ' . $target->data->name;
                break;
            default:
            $this->applyEffect($params[$status], $status, $duration, $actor);
            $outcomeSuccessMessages[0] = 'L\'effet '.$status.' <span class="ra '. EFFECTS_RA_FONT[$status] .'"></span> est appliqué '. $timeMessage. ' à ' . $actor->data->name;

            if ($target->data->name !== $actor->data->name) {
                $this->applyEffect($params[$status], $status, $duration, $target);
                $outcomeSuccessMessages[1] = 'L\'effet '.$status.' <span class="ra '. EFFECTS_RA_FONT[$status] .'"></span> est appliqué '. $timeMessage. ' à ' . $target->data->name;
            }
            break;
        }

        return new OutcomeResult(true, outcomeSuccessMessages:$outcomeSuccessMessages, outcomeFailureMessages: $outcomeSuccessMessages);
    }

    private function applyEffect (bool $apply, string $effectName, int $duration, ActorInterface $player){
        if ($apply) {
            $player->addEffect($effectName, $duration);
        } else {
            $player->endEffect($effectName);
        } 
    }
}
