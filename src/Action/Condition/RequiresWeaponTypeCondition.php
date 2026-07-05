<?php
namespace App\Action\Condition;

use App\Entity\ActionCondition;
use App\Interface\ActorInterface;
use App\Action\Condition\ConditionObject;
use App\Action\Schema\DeclaresSimulationInputs;
use App\Action\Schema\FieldType;
use App\Action\Schema\HasParameterSchema;
use App\Action\Schema\ParameterField;
use App\Action\Schema\ParameterSchema;
use App\Action\Schema\SimulationField;

//add enum to display correctly the weapon type names (melee, distance, multipurpose, etc)

class RequiresWeaponTypeCondition extends BaseCondition implements HasParameterSchema, DeclaresSimulationInputs
{
    public static function parameterSchema(): ParameterSchema
    {
        return new ParameterSchema(
            new ParameterField('type', FieldType::WEAPON_TYPE, "Types d'arme", multiple: true),
            new ParameterField('location', FieldType::EMPLACEMENT, 'Emplacements', multiple: true),
        );
    }

    public static function simulationInputs(array $params): array
    {
        $types = (array) ($params['type'] ?? []);
        $label = 'Arme acteur' . ($types ? ' (' . implode('/', $types) . ')' : '');
        $default = $types[0] ?? null;

        return [new SimulationField(SimulationField::KIND_WEAPON, SimulationField::SIDE_ACTOR, 'weapon', $label, $default)];
    }

    public function check(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition, ConditionObject $conditionObject): ConditionResult
    {
        $result = new ConditionResult(true, array(), array());
        $params = $condition->getParameters(); // e.g. { "type": ["melee"] } { "type": ["tir","jet"] } { "type": ["bouclier"], "location": ["main2"] }
        $weaponTypes = $params['type'] ?? array();
        $locationArray = $params['location'] ?? ['main1'];
        $weaponTypeOk = false;
        $weaponTypesKo = array();
        foreach ($locationArray as $location) {
            if (!isset($actor->emplacements->{$location}) || $actor->emplacements->{$location} === null) {
                continue;
            }
            foreach ($weaponTypes as $weaponType) {
                if ($actor->emplacements->{$location}->data->subtype == $weaponType) {
                    $weaponTypeOk = true;
                    break 2;
                } else {
                    array_push($weaponTypesKo, $weaponType);
                }
            }
        }

        if (!$weaponTypeOk) {
            $errorMessage[0] = 'Vous n\'êtes pas équipé d\'une arme de type '. join("/",$weaponTypesKo). '.';
            $result = new ConditionResult(false, array(), $errorMessage);
        }
        

        return $result;
    }
}
