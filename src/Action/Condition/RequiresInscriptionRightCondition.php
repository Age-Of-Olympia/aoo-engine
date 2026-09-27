<?php

namespace App\Action\Condition;

use App\Entity\ActionCondition;
use App\Interface\ActorInterface;
use App\Interface\HasParameterSchemaInterface;
use App\Action\Schema\ParameterSchema;
use App\Service\InscriptionService;

/**
 * The target carries an inscription the actor may write
 * ({@see InscriptionService::mayInscribe()}). Declare it
 * `display_context`: the gesture shows only where it can serve.
 */
class RequiresInscriptionRightCondition extends BaseCondition implements HasParameterSchemaInterface
{
    public static function parameterSchema(): ParameterSchema
    {
        return new ParameterSchema();
    }

    public function check(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition, ConditionObject $conditionObject): ConditionResult
    {
        if ($target === null || !(new InscriptionService())->mayInscribe((int) $target->getId(), (int) $actor->getId())) {
            return new ConditionResult(false, array(), array('Vous ne pouvez pas écrire ici.'));
        }

        return new ConditionResult(true, array(), array());
    }
}
