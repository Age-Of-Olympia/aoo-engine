<?php

namespace App\Action\Condition;

use App\Entity\ActionCondition;
use App\Interface\ActorInterface;
use App\Interface\HasParameterSchemaInterface;
use App\Action\Schema\ParameterSchema;
use Classes\Player;

/**
 * The actor's strength beats the target's stability (Player::getPush, with
 * the poussée passives and push effects). Non-blocking on a push action: a
 * target that holds makes it a failure, its success outcomes don't run.
 */
class PushCondition extends BaseCondition implements HasParameterSchemaInterface
{
    public static function parameterSchema(): ParameterSchema
    {
        return new ParameterSchema();
    }

    public function check(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition, ConditionObject $conditionObject): ConditionResult
    {
        if (!$actor instanceof Player || !$target instanceof Player) {
            return new ConditionResult(false, array(), array('Rien à pousser.'));
        }

        if (!$actor->getPush($target, $conditionObject)) {
            return new ConditionResult(false, array(), array($target->data->name . ' reste stable.'));
        }

        return new ConditionResult(true, array(), array());
    }
}
