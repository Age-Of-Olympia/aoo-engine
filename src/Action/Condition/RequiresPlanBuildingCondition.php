<?php

namespace App\Action\Condition;

use App\Entity\ActionCondition;
use App\Interface\ActorInterface;
use App\Interface\HasParameterSchemaInterface;
use App\Action\Schema\ParameterSchema;
use App\Service\BuildingService;
use App\Service\RaceService;

/**
 * The required building: an object type may declare that it can only
 * be built when a FINISHED building of a given type stands somewhere
 * on the actor's plan (items.requires_building, a races.name —
 * 'banque' for the chests). The type carries the requirement, so
 * activating it for a new object is a catalog value, not code. No
 * declaration = nothing to check.
 *
 * Only a finished building counts: neither a construction site nor a
 * ruin does.
 */
class RequiresPlanBuildingCondition extends BaseCondition implements HasParameterSchemaInterface
{
    public static function parameterSchema(): ParameterSchema
    {
        return new ParameterSchema();
    }

    public function check(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition, ConditionObject $conditionObject): ConditionResult
    {
        $preConditionResult = parent::check($actor, $target, $condition, $conditionObject);
        if (!$preConditionResult->isSuccess()) {
            return $preConditionResult;
        }

        $required = trim((string) ($conditionObject->getPickedItem()?->row->requires_building ?? ''));
        if ($required === '') {
            return new ConditionResult(true, array(), array());
        }

        // The workbench simulation stands on no board.
        if ($actor->isSimulated()) {
            return new ConditionResult(true, array(), array());
        }

        $coords = $actor->getCoords();
        if ($coords === null || (new BuildingService())->builtBuildingInPlan((string) $coords->plan, [$required]) === null) {
            $race = (new RaceService())->getRaceByName($required);
            $label = $race !== null ? $race->getLabel() : $required;

            return new ConditionResult(false, array(), ['Il faut d\'abord construire « ' . $label . ' » sur ce plan.']);
        }

        return new ConditionResult(true, array(), array());
    }
}
