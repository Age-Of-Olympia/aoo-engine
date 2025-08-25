<?php

namespace App\Action\OutcomeInstruction;

use App\Entity\OutcomeInstruction;
use App\Interface\ActorInterface;
use Doctrine\ORM\Mapping as ORM;
use Classes\Str;

#[ORM\Entity]
class ObjectEffectOutcomeInstruction extends OutcomeInstruction
{
    public function execute(ActorInterface $actor, ActorInterface $target): OutcomeResult {

        $main1 = $actor->emplacements->main1;

        $outcomeSuccessMessages = array();

        $itemJson = $main1->data;
        if($itemJson)
        {
            if(!empty($itemJson->addEffects)){
                foreach($itemJson->addEffects as $e){
                    $duration = $e->duration ?? 0;
                    $timeMessage = 'pour ' . Str::displaySeconds($duration);
                    if ($duration == 0) {
                        $timeMessage = 'jusqu\'au prochain tour';
                    }
                    switch ($e->on) {
                        case 'actor':
                            $actor->addEffect($e->name, $e->duration);
                            $outcomeSuccessMessages[0] = 'L\'effet '.$e->name.' <span class="ra '. EFFECTS_RA_FONT[$e->name] .'"></span> est appliqué '. $timeMessage.' à ' . $actor->data->name;
                            break;
                        case 'target':
                            $target->addEffect($e->name, $e->duration);
                            $outcomeSuccessMessages[0] = 'L\'effet '.$e->name.' <span class="ra '. EFFECTS_RA_FONT[$e->name] .'"></span> est appliqué '. $timeMessage.' à ' . $target->data->name;
                            break;
                        
                        default:
                            $actor->addEffect($e->name, $e->duration);
                            break;
                    }
                }
            }
            
        }

        
    

        return new OutcomeResult(true, outcomeSuccessMessages:$outcomeSuccessMessages, outcomeFailureMessages: array());
    }

}
