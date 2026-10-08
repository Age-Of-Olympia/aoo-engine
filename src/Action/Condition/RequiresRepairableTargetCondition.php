<?php

namespace App\Action\Condition;

use App\Entity\ActionCondition;
use App\Interface\ActorInterface;
use App\Interface\HasParameterSchemaInterface;
use App\Action\Schema\ParameterField;
use App\Action\Schema\ParameterSchema;
use App\Enum\FieldType;
use App\Service\TypeRepairService;

/**
 * Only what mends may be repaired, and the TYPE decides: a type mends when
 * it carries a repair recipe (entity_type_repairs) in the row's mode — one
 * action per mode, so each button hides on its own. Keyed by players.race,
 * so a placed exemplar answers through its item's name like a building
 * through its type. Adding a repairable type is a catalogue edit.
 *
 * Pairs with {@see TargetTypeCondition}, which answers a different question:
 * that one says which KINDS an action reaches, this one whether a given type
 * mends. Keep `reparer` on the wide envelope (`structure`).
 *
 * Declare it `display_context` so the button disappears from what cannot be
 * mended instead of appearing and failing on click.
 */
class RequiresRepairableTargetCondition extends BaseCondition implements HasParameterSchemaInterface
{
    public static function parameterSchema(): ParameterSchema
    {
        return new ParameterSchema(
            new ParameterField('mode', FieldType::ENUM, 'Recette de réparation', default: TypeRepairService::MATERIALS, options: TypeRepairService::MODES),
        );
    }

    public function check(
        ActorInterface $actor,
        ?ActorInterface $target,
        ActionCondition $condition,
        ConditionObject $conditionObject
    ): ConditionResult {
        if ($target === null) {
            return new ConditionResult(false, array(), array("Il n'y a rien à réparer ici."));
        }

        if ((new TypeRepairService())->recipeOf((string) ($target->data->race ?? ''), self::modeOf($condition)) === null) {
            return new ConditionResult(false, array(), array('Cela ne se répare pas.'));
        }

        return new ConditionResult(true, array(), array());
    }

    /** The recipe mode the action row asks for, materials by default. */
    public static function modeOf(ActionCondition $condition): string
    {
        $mode = (string) ($condition->getParameters()['mode'] ?? '');

        return isset(TypeRepairService::MODES[$mode]) ? $mode : TypeRepairService::MATERIALS;
    }
}
