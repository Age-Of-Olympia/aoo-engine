<?php
namespace App\Action\Condition;

//use App\Entity\Player;

include '../../classes/player.php';
use Player;

use App\Entity\ActionCondition;


interface ConditionInterface
{
    /**
     * Return true if the condition is satisfied, false otherwise.
     */
    public function check(Player $actor, ?Player $target, ActionCondition $condition): bool;
    
    /**
     * Return an error message or reason if desired.
     */
    public function getErrorMessage(): ?string;
}
