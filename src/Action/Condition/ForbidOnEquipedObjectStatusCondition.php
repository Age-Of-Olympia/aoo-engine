<?php
namespace App\Action\Condition;

use App\Entity\ActionCondition;
use App\Interface\ActorInterface;
use App\Action\Condition\ConditionObject;
use App\Action\Schema\FieldType;
use App\Action\Schema\HasParameterSchemaInterface;
use App\Action\Schema\ParameterField;
use App\Action\Schema\ParameterSchema;

class ForbidOnEquipedObjectStatusCondition extends BaseCondition implements HasParameterSchemaInterface
{
    public static function parameterSchema(): ParameterSchema
    {
        return new ParameterSchema(
            new ParameterField('location', FieldType::EMPLACEMENT, 'Emplacement', default: 'main1'),
        );
    }

    public function check(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition, ConditionObject $conditionObject): ConditionResult
    {
        $result = new ConditionResult(true, array(), array());

        $params = $condition->getParameters();
        $location = $params["location"]??"main1";
        $itemToEnchant = $actor->emplacements->{$location};

        if($itemToEnchant->row->enchanted != 0){
            $result = new ConditionResult(false, ['Cet objet est déjà enchanté.'], array());
        }

        return $result;
    }

}
