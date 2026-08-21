<?php
namespace App\Action\Condition;

use App\Entity\ActionCondition;
use App\Interface\ActorInterface;
use App\Action\Condition\ConditionObject;
use App\Enum\FieldType;
use App\Interface\HasParameterSchemaInterface;
use App\Action\Schema\ParameterField;
use App\Action\Schema\ParameterSchema;

class PlanCondition extends BaseCondition implements HasParameterSchemaInterface
{
    public static function parameterSchema(): ParameterSchema
    {
        return new ParameterSchema(
            new ParameterField('plan', FieldType::PLAN, 'Plan interdit', default: plans()->deathPlan()),
            new ParameterField('allowed', FieldType::ACTION, 'Actions autorisées (aux Enfers)', default: ['prier'], multiple: true, help: 'Actions exemptées du blocage'),
        );
    }

    public function check(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition, ConditionObject $conditionObject): ConditionResult
    {
        $result = new ConditionResult(true, array(), array());

        $params = $condition->getParameters();
        $plan = $params["plan"] ?? plans()->deathPlan();
        // Data-driven exemption list (editable on the preconditions tab); defaults
        // to ['prier'] for any row that predates the param.
        $allowedInEnfers = is_array($params['allowed'] ?? null) ? $params['allowed'] : ['prier'];

        $actionName = $condition->getAction()?->getName();
        $isDeathPlan = $plan === plans()->deathPlan();

        if ($actor->coords->plan == $plan) {
            if ($isDeathPlan && in_array($actionName, $allowedInEnfers, true)) {
                return $result;
            }
            if ($isDeathPlan) {
                $errorMessage[0] = 'Impossible d\'agir aux Enfers.';
            } else {
                $errorMessage[0] = 'Impossible d\'agir sur ce plan : ' . $plan;
            }
            
            $condition->setBlocking(true);
            $result = new ConditionResult(false, array(), $errorMessage);
        }

        return $result;
    }

}
