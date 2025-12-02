<?php

namespace App\Action\OutcomeInstruction;

use App\Entity\OutcomeInstruction;
use Doctrine\ORM\Mapping as ORM;
use Classes\Player;
use Classes\Str;

#[ORM\Entity]
class ApplyStatusOutcomeInstruction extends OutcomeInstruction
{
    public function execute(Player $actor, Player $target, array $rollsArray): OutcomeResult {
        $params =$this->getParameters();
        // e.g. { "adrenaline": true, "duration": 86400 }
        // e.g. { "adrenaline": true, "player": "actor" , "duration": 86400 }
        // e.g. { "finished": true, "player": "actor" }
        $status = array_key_first($params);
        if (in_array($status, EFFECTS_HIDDEN)) {
            $this->getOutcome()->getAction()->setHideOnSuccess(true);
        }
        $duration = $params['duration'] ?? 1;
        $timeMessage = 'pour ' . Str::displaySeconds($duration);
        if ($duration == 1) {
            $timeMessage = 'jusqu\'au prochain tour';
        }
        $player = $params['player'] ?? 'both';
        $valueParam = $params['value'] ?? 1;
        if(is_array($valueParam)){
            switch ($valueParam[0]) {
                case 'rollDivisor':
                    $value = max(0,floor((array_sum($rollsArray[0]) - array_sum($rollsArray[1]))/ $valueParam[1]));
                    break;
                case 'remaining':
                    $value = $actor->getRemaining($valueParam[1]);
                    break;
                default:
                    $value = $valueParam[array_rand( $valueParam)];
            } 
        }    
        else{
            $value = $valueParam;
        }

        $stackable = $params['stackable'] ?? false;
        $outcomeSuccessMessages = array();
        switch ($player) {
            case 'actor':
                if ($status == "finished") {
                    $res = $actor->purge_effects();
                    if ($res > 0) {
                        $outcomeSuccessMessages[0] = $res .' effet(s) terminé(s).';
                    }
                } else {
                    $this->applyEffect($params[$status], $status, $duration, $value, $stackable, $actor);
                    $outcomeSuccessMessages[0] = 'L\'effet '.$status.' <span class="ra '. EFFECTS_RA_FONT[$status] .'"></span> (' . ($stackable ? '+' : 'x') . $value .') est appliqué '. $timeMessage.' à ' . $actor->data->name;
                }
                break;
            case 'target':
                $this->applyEffect($params[$status], $status, $duration, $value, $stackable, $target);
                $outcomeSuccessMessages[0] = 'L\'effet '.$status.' <span class="ra '. EFFECTS_RA_FONT[$status] .'"></span> (' . ($stackable ? '+' : 'x') . $value .') est appliqué '. $timeMessage. ' à ' . $target->data->name;
                break;
            default:
                $this->applyEffect($params[$status], $status, $duration, $value, $stackable, $actor);
                $outcomeSuccessMessages[0] = 'L\'effet '.$status.' <span class="ra '. EFFECTS_RA_FONT[$status] .'"></span> (' . ($stackable ? '+' : 'x') . $value .') est appliqué '. $timeMessage. ' à ' . $actor->data->name;

            if ($target->data->name !== $actor->data->name) {
                $this->applyEffect($params[$status], $status, $duration, $value, $stackable, $target);
                $outcomeSuccessMessages[1] = 'L\'effet '.$status.' <span class="ra '. EFFECTS_RA_FONT[$status] .'"></span> (' . ($stackable ? '+' : 'x') . $value .') est appliqué '. $timeMessage. ' à ' . $target->data->name;
            }
            break;
        }

        return new OutcomeResult(true, outcomeSuccessMessages:$outcomeSuccessMessages, outcomeFailureMessages: $outcomeSuccessMessages);
    }

    private function applyEffect (bool $apply, string $effectName, int $duration, int $value, bool $stackable, Player $player){
        if ($apply) {
            $player->addEffect($effectName, $duration, $value, $stackable);
        } else {
            $player->endEffect($effectName);
        } 
    }
}
