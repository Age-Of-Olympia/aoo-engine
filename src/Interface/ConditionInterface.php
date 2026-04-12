<?php
namespace App\Interface;

use App\Action\Condition\ConditionResult;
use App\Action\Condition\ConditionObject;

use App\Entity\ActionCondition;


interface ConditionInterface
{
    public function checkPreconditions(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition, ConditionObject $conditionObject): ConditionResult;

    /**
     * Return true ConditionResult if the condition is satisfied, false otherwise.
     */
    public function check(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition, ConditionObject $conditionObject): ConditionResult;

    /**
     * Do we have to remove the amount of the condition (actions, pm, etc.)
     */
    public function toRemove(): bool;

    public function applyCosts(ActorInterface $actor, ?ActorInterface $target, ActionCondition $conditionToPay): array;

    public function shouldRefreshUi(): bool;
    
}
