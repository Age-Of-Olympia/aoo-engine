<?php
namespace App\Action\Condition;

use Classes\View;

use App\Entity\ActionCondition;
use App\Interface\ActorInterface;
use App\Action\Condition\ConditionObject;
use App\Action\Schema\DeclaresSimulationInputs;
use App\Action\Schema\FieldType;
use App\Action\Schema\HasParameterSchema;
use App\Action\Schema\ParameterField;
use App\Action\Schema\ParameterSchema;
use App\Action\Schema\SimulationField;

class RequiresDistanceCondition extends BaseCondition implements HasParameterSchema, DeclaresSimulationInputs
{
    public static function parameterSchema(): ParameterSchema
    {
        return new ParameterSchema(
            new ParameterField('min', FieldType::INT, 'Distance minimale', default: 0),
            new ParameterField('max', FieldType::INT, 'Distance maximale', default: 0),
        );
    }

    public static function simulationInputs(array $params): array
    {
        return [new SimulationField(SimulationField::KIND_DISTANCE, SimulationField::SIDE_SHARED, 'distance', 'Distance (cases)')];
    }

    public function check(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition, ConditionObject $conditionObject): ConditionResult
    {
        $result = new ConditionResult(true, array(), array());
        if (!$target) {
            $errorMessage[0] = "Aucune cible n'a été spécifiée.";
            return new ConditionResult(false, array(), $errorMessage);
        }

        $preConditionResult = parent::check($actor, $target, $condition, $conditionObject);
        if (!$preConditionResult->isSuccess()) {
            $condition->setBlocking(true);
            return $preConditionResult;
        }

        $params = $condition->getParameters(); // e.g. { "max": 1 }
        $maxDist = $params['max'] ?? null;
        $minDist = $params['min'] ?? null;

        $distance = View::get_distance($actor->getCoords(), $target->getCoords());

        if ($minDist == null && $distance > $maxDist) {
            $errorMessage[0] = "La cible est trop loin ! (distance $distance > max $maxDist)";
            return new ConditionResult(false, array(), $errorMessage);
        }

        if ($maxDist == null && $distance < $minDist) {
            $errorMessage[0] = "La cible est trop proche ! (distance $distance < min $minDist)";
            return new ConditionResult(false, array(), $errorMessage);
        }

        if ($maxDist != null && $minDist != null && ($distance < $minDist || $distance > $maxDist)) {
            $errorMessage[0] = "La cible n'est pas à la bonne distance ! (distance $distance < min $minDist ou distance $distance > max $maxDist)";
            return new ConditionResult(false, array(), $errorMessage);
        }

        return $result;
    }

}
