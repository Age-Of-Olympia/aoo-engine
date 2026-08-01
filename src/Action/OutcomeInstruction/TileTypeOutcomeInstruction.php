<?php

namespace App\Action\OutcomeInstruction;

use App\Entity\OutcomeInstruction;
use App\Action\Condition\ConditionObject;
use App\Enum\FieldType;
use App\Interface\HasParameterSchemaInterface;
use App\Action\Schema\ParameterField;
use App\Action\Schema\ParameterSchema;
use Doctrine\ORM\Mapping as ORM;
use Classes\Player;

#[ORM\Entity]
class TileTypeOutcomeInstruction extends OutcomeInstruction implements HasParameterSchemaInterface
{
    public static function parameterSchema(): ParameterSchema
    {
        return new ParameterSchema(
            new ParameterField('type', FieldType::STRING, 'Type de tuile', default: 'routes'),
            new ParameterField('carac', FieldType::TRAIT, 'Trait bonifié', default: 'mvt'),
            new ParameterField('value', FieldType::INT, 'Valeur du bonus', default: 1),
        );
    }

    public function execute(Player $actor, Player $target, ConditionObject $conditionObject): OutcomeResult {

        $outcomeSuccessMessages = array();

        $params =$this->getParameters();
        // e.g. { "type": "routes" }

        $tileType = $params['type'] ?? "routes";
        $carac = $params['carac'] ?? "mvt";
        $value = $params['value'] ?? 1;

        if($actor->isOnTileType($tileType)){
            $bonus = array($carac=>$value);
            $actor->putBonus($bonus);
            switch ($carac) {
                case 'mvt':
                    $outcomeSuccessMessages[sizeof($outcomeSuccessMessages)] = 'Vous êtes sur une route ! (+'.$value.')';
                    break;
                default:
                    break;
            }
            
        } 

        return new OutcomeResult(true,$outcomeSuccessMessages, array());
    }

}