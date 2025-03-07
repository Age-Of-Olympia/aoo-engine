<?php
namespace App\Service;

use App\Action\Effect\EffectResult;
use Player;
use App\Entity\EffectInstruction;

class EffectInstructionExecutorService
{
    /**
     * Execute a single instruction (one row from `effect_instructions`)
     *
     * @param Player $actor  The player who has made the action
     * @param Player $target  The player who will be affected
     * @param EffectInstruction $instruction The DB entity describing the operation
     */
    public function executeInstruction(Player $actor, Player $target, EffectInstruction $instruction): EffectResult
    {
        $operation = $instruction->getOperation();
        $params    = $instruction->getParameters() ?? [];

        switch ($operation) {
            case 'MODIFY_STAT': // DROP AMMO ?
                $res = $this->executeModifyStat($actor, $target, $params);
                break;

            case 'APPLY_STATUS':
                $res = $this->executeApplyStatus($actor, $target, $params);
                break;

            case 'DAMAGE_OBJECT':

            // Add more operation cases as needed

            default:
                // Unrecognized operation
                // Could log or ignore
                break;
        }
        return $res;
    }

    private function executeModifyStat(Player $actor, Player $target, array $params): EffectResult
    {
        // e.g. { "actorDamages": "f", "targetDamages": "e" } (bonusDamage?)
        $actorTraitDamages = $params['actorDamages'];
        $targetTraitDamagesTaken = $params['targetDamages'];

        if(!empty($actorTraitDamages) && !empty($targetTraitDamagesTaken)){
            $actorDamages = (is_numeric($actorTraitDamages)) ? $actorTraitDamages : $target->caracs->{$actorTraitDamages};
            $targetDamages = (is_numeric($targetTraitDamagesTaken)) ? $targetTraitDamagesTaken : $target->caracs->{$targetTraitDamagesTaken};
            $totalDamages = $actorDamages - $targetDamages;
            if($totalDamages < 1){
                $totalDamages = 1;
            }

            //CRIT ? (devrait dépendre du scores des dés ?)
            //ESQUIVE ? (géré dans les conditions ?)
            //TANK ?
            $target->put_bonus(array('pv'=>-$totalDamages));
            $effectSuccessMessages[0] = 'Vous infligez '. $totalDamages .' dégâts à '. $target->data->name.'.';
            $effectSuccessMessages[1] = CARACS[$actorTraitDamages] .' - '. CARACS[$targetTraitDamagesTaken] .' = '. $actorDamages .' - '. $targetDamages .' = '. $totalDamages .' dégâts';

            // put assist
            $actor->put_assist($target, $totalDamages);

            //BREAK WEAPON ?
            
        } {
            //handle not working case
        }

        return new EffectResult(true, effectSuccessMessages:$effectSuccessMessages, effectFailureMessages: array(), totalDamages:$totalDamages);
        
    }

    private function executeApplyStatus(Player $actor, Player $target, array $params): EffectResult
    {
        // e.g. { "adrenaline": true, "duration": 86400 }
        $status = array_key_first($params);
        $duration = $params['duration'] ?? 0;
        $player = $params['player'] ?? 'BOTH';
        switch ($player) {
            case 'ACTOR':
                $this->applyEffect($params[$status], $status, $duration, $actor);
                $effectSuccessMessages[0] = 'L\'effet '.$status.' est appliqué pour ' . $duration . ' TBD à ' . $actor->data->name;
                break;
            case 'TARGET':
                $this->applyEffect($params[$status], $status, $duration, $target);
                $effectSuccessMessages[0] = 'L\'effet '.$status.' est appliqué pour ' . $duration . ' TBD à ' . $target->data->name;
                break;
            default:
            $this->applyEffect($params[$status], $status, $duration, $actor);
            $this->applyEffect($params[$status], $status, $duration, $target);
            $effectSuccessMessages[0] = 'L\'effet '.$status.' est appliqué pour ' . $duration . ' TBD à ' . $actor->data->name;
            $effectSuccessMessages[1] = 'L\'effet '.$status.' est appliqué pour ' . $duration . ' TBD à ' . $target->data->name;
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
