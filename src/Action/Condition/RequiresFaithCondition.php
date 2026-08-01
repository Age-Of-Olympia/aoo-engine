<?php

namespace App\Action\Condition;

use App\Action\Schema\FieldType;
use App\Action\Schema\HasParameterSchemaInterface;
use App\Action\Schema\ParameterField;
use App\Action\Schema\ParameterSchema;
use App\Entity\ActionCondition;
use App\Interface\ActorInterface;

/**
 * Faith points as the price of an action.
 *
 * `pf` is not a turn characteristic: `putBonus` ignores it, so the generic
 * cost path charges nothing at all. Hence its own condition.
 */
class RequiresFaithCondition extends BaseCondition implements HasParameterSchemaInterface
{
    public static function parameterSchema(): ParameterSchema
    {
        return new ParameterSchema(
            new ParameterField('pf', FieldType::INT, 'Points de foi requis', default: 50),
        );
    }

    public static function parameterCost(ActionCondition $condition): int
    {
        return max(0, (int) ($condition->getParameters()['pf'] ?? 0));
    }

    public function check(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition, ConditionObject $conditionObject): ConditionResult
    {
        $price = self::parameterCost($condition);

        if ($price === 0) {
            return new ConditionResult(true, array(), array());
        }

        $held = (int) ($actor->data->pf ?? 0);

        if ($held >= $price) {
            return new ConditionResult(true, array(), array());
        }

        $condition->setBlocking(true);

        return new ConditionResult(false, array(), [
            'Il vous faut ' . $price . ' points de foi (vous en avez ' . $held . ').',
        ]);
    }

    public function applyCosts(ActorInterface $actor, ?ActorInterface $target, ActionCondition $conditionToPay): array
    {
        $price = self::parameterCost($conditionToPay);

        if ($price === 0) {
            return array();
        }

        // `put_pf` lives on the legacy Player, not on the interface.
        if (!$actor instanceof \Classes\Player) {
            return array();
        }

        $actor->put_pf(-$price);

        return ['Vous avez dépensé ' . $price . ' points de foi.'];
    }
}
