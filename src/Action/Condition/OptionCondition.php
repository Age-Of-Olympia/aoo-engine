<?php
namespace App\Action\Condition;

use App\Entity\ActionCondition;
use App\Interface\ActorInterface;
use App\Action\Condition\ConditionObject;
use App\Enum\FieldType;
use App\Interface\HasParameterSchemaInterface;
use App\Action\Schema\ParameterField;
use App\Action\Schema\ParameterSchema;

class OptionCondition extends BaseCondition implements HasParameterSchemaInterface
{
    public static function parameterSchema(): ParameterSchema
    {
        return new ParameterSchema(
            new ParameterField('option', FieldType::STRING, 'Option du personnage cible', help: "Option qui, si activée sur la cible, bloque l'action."),
        );
    }

    public function check(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition, ConditionObject $conditionObject): ConditionResult
    {
        $result = new ConditionResult(true, array(), array());

        $params = $condition->getParameters();
        $option = $params["option"]??"";

        if($target->have_option($option)){
            //devrait être un switch avec les options possibles
            $errorMessage[0] = "Ce personnage n\'autorise pas les entraînements.";
            return new ConditionResult(false, array(), $errorMessage);
        }

        return $result;
    }

}
