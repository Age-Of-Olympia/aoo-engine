<?php

namespace App\Action\OutcomeInstruction;

use App\Entity\OutcomeInstruction;
use Doctrine\ORM\Mapping as ORM;
use Classes\Item;
use Classes\Player;
use Classes\Str;

#[ORM\Entity]
class DropWeaponOutcomeInstruction extends OutcomeInstruction
{
    public function execute(Player $actor, Player $target, array $rollsArray): OutcomeResult {
        $outcomeSuccessMessages = array();
        $outcomeFailureMessages = array();
        $params =$this->getParameters();
        $result = false;
        $targetLocation = $params["targetLocation"] ?? "";
        $dropChance = $params["dropChance"] ?? 10;

        $item = $target->emplacements->{$targetLocation};
        
        if(rand(1,100) <= $dropChance){
            $target->drop($item, 1);
            $resultText = "L'arme de votre adversaire tombe au sol.";
            $outcomeSuccessMessages[sizeof($outcomeSuccessMessages)] = $resultText;
            $result = true;
        } else {
            $resultText = "Votre adversaire était plus vigilent que prévu, son arme reste entre ses mains !";
            $outcomeFailureMessages[sizeof($outcomeFailureMessages)] = $resultText;
        }
        
        return new OutcomeResult($result, $outcomeSuccessMessages, $outcomeFailureMessages);
    }

}
