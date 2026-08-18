<?php

namespace App\Action\OutcomeInstruction;

use App\Entity\OutcomeInstruction;
use App\Action\Condition\ConditionObject;
use App\Interface\HasParameterSchemaInterface;
use App\Action\Schema\ParameterSchema;
use Doctrine\ORM\Mapping as ORM;
use Classes\Player;
use Classes\Str;

#[ORM\Entity]
class ObjectEffectOutcomeInstruction extends OutcomeInstruction implements HasParameterSchemaInterface
{
    public static function parameterSchema(): ParameterSchema
    {
        return new ParameterSchema();
    }

    public function execute(Player $actor, Player $target, ConditionObject $conditionObject): OutcomeResult {

        $main1 = $actor->emplacements->main1;

        $outcomeSuccessMessages = array();

        // Mise en commentaire, l'ajout d'effets est géré autrement maintenant.
        /*$itemJson = $main1->data;
        if($itemJson)
        {
            if(!empty($itemJson->addEffects)){
                foreach($itemJson->addEffects as $e){
                    // Durée en TOURS, même convention que ApplyStatus.
                    $duration = (int) ($e->duration ?? 0);
                    if (\App\Service\PlayerEffectService::isInfinite($duration)) {
                        $timeMessage = 'sans limite de durée';
                    } elseif ($duration === 0) {
                        $timeMessage = 'jusqu\'au prochain tour';
                    } else {
                        $timeMessage = 'pour ' . $duration . ' tour' . ($duration > 1 ? 's' : '');
                    }
                    switch ($e->on) {
                        case 'actor':
                            $actor->add_effect($e->name, $e->duration);
                            $outcomeSuccessMessages[0] = 'L\'effet '.$e->name.' <span class="ra '. $actor->effectService->getIcon($e->name) .'"></span> est appliqué '. $timeMessage.' à ' . $actor->data->name;
                            break;
                        case 'target':
                            $target->add_effect($e->name, $e->duration);
                            $outcomeSuccessMessages[0] = 'L\'effet '.$e->name.' <span class="ra '. $target->effectService->getIcon($e->name) .'"></span> est appliqué '. $timeMessage.' à ' . $target->data->name;
                            break;
                        
                        default:
                            $actor->add_effect($e->name, $e->duration);
                            break;
                    }
                }
            }
            
        }*/

        
    

        return new OutcomeResult(true, outcomeSuccessMessages:$outcomeSuccessMessages, outcomeFailureMessages: array());
    }

}
