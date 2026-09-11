<?php
namespace App\Interface;

use App\Action\Condition\ConditionResult;
use App\Action\Condition\ConditionObject;

use App\Entity\ActionCondition;


/**
 * A condition declares its parameters ({@see HasParameterSchemaInterface}):
 * an empty schema means "takes none", and nothing may be read from the
 * request or the row without a field for it.
 */
interface ConditionInterface extends HasParameterSchemaInterface
{
    /**
     * Return true ConditionResult if the condition is satisfied, false otherwise.
     */
    public function check(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition, ConditionObject $conditionObject): ConditionResult;

    /**
     * Do we have to remove the amount of the condition (actions, pm, etc.)
     */
    public function toRemove(): bool;

    public function applyCosts(ActorInterface $actor, ?ActorInterface $target, ActionCondition $conditionToPay): array;
}
