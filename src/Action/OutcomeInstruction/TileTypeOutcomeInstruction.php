<?php

namespace App\Action\OutcomeInstruction;

use App\Entity\OutcomeInstruction;
use App\Action\Condition\ConditionObject;
use App\Action\Schema\FieldType;
use App\Action\Schema\HasParameterSchema;
use App\Action\Schema\ParameterField;
use App\Action\Schema\ParameterSchema;
use App\Service\MapService;
use Doctrine\ORM\Mapping as ORM;
use Classes\Player;

#[ORM\Entity]
class TileTypeOutcomeInstruction extends OutcomeInstruction implements HasParameterSchema
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

        $mapService = new MapService();

        $outcomeSuccessMessages = array();

        $params =$this->getParameters();
        // e.g. { "type": "routes" }

        $tileType = $params['type'] ?? "routes";
        $carac = $params['carac'] ?? "mvt";
        $value = $params['value'] ?? 1;

        $row = $mapService->getTileTypeAtCoord($tileType, $actor->data->coords_id);

        if($row->n){
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