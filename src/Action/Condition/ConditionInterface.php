<?php
namespace App\Action\Condition;

use Player;

use App\Entity\ActionCondition;


interface ConditionInterface
{
    /**
     * Return true if the condition is satisfied, false otherwise.
     */
    public function check(Player $actor, ?Player $target, ActionCondition $condition): ConditionResult;

    /**
     * Do we have to remove the amount of the condition (actions, pm, etc.)
     */
    public function toRemove(): bool;
    
}
