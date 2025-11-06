<?php

namespace App\Action\OutcomeInstruction;

use App\Entity\OutcomeInstruction;
use Doctrine\ORM\Mapping as ORM;
use Classes\Item;
use Classes\Player;
use Classes\View;

#[ORM\Entity]
class ObjectOutcomeInstruction extends OutcomeInstruction
{
    public function execute(Player $actor, Player $target, array $rollsArray): OutcomeResult {

        // e.g. {"action":"steal", "object": 1 }
        $action = $this->getParameters()['action'] ?? '';
        $object = $this->getParameters()['object'] ?? 1;

        $outcomeSuccessMessages = array();
        if(!empty($action)){
            $gold = new Item($object);
            $goldInTargetInventory = $gold->get_n($target);
            $takenFromInventory = floor($goldInTargetInventory * 0.1);

            if($takenFromInventory < 1){
                $takenFromInventory = 1;
            }

            // Here we take from the target inventory. 
            $res = $gold->give_item($target, $actor, $takenFromInventory);

            $gain = $takenFromInventory;
            // if we took less than MIN_GOLD_STOLEN po from target player we add the difference to the inventory and to the gain
            if ($takenFromInventory < MIN_GOLD_STOLEN) {
                // If target had enough gold in his pockets
                if ($res) {
                    $goldAddedToComplete = MIN_GOLD_STOLEN - $takenFromInventory;
                    $gold->add_item($actor, $goldAddedToComplete);
                    $gain += $goldAddedToComplete; 
                }
                // Pockets were empty or the gold could not be given
                else {
                    $gold->add_item($actor, MIN_GOLD_STOLEN);
                    $gain = MIN_GOLD_STOLEN;
                } 
            }

            $outcomeSuccessMessages[0] = 'Vous obtenez '. $gain .' Po grâce à votre larcin sur '. $target->data->name .'.';
        
        } {
            //handle not working case
        }

        return new OutcomeResult(true, outcomeSuccessMessages:$outcomeSuccessMessages, outcomeFailureMessages: array(), totalDamages:$gain);
    }
}
