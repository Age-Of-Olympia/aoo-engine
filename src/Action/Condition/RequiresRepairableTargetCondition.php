<?php

namespace App\Action\Condition;

use App\Entity\ActionCondition;
use App\Entity\StructureType;
use App\Interface\ActorInterface;
use App\Interface\HasParameterSchemaInterface;
use App\Action\Schema\ParameterSchema;
use App\Service\ItemInstanceService;
use App\Service\RaceService;

/**
 * Only what mends may be repaired, and the TYPE decides — `races.repairable`,
 * nullable, falling back to the family default (built and scenery yes, what
 * grows no). Adding a repairable building type is therefore a catalogue edit,
 * with no list to update anywhere else.
 *
 * Pairs with {@see TargetTypeCondition}, which answers a different question:
 * that one says which KINDS an action reaches, this one whether a given type
 * mends. Keep `reparer` on the wide envelope (`structure`) — narrowing it to
 * families there would make `repairable` unsettable on the excluded ones.
 *
 * Declare it `display_context` so the button disappears from what cannot be
 * mended instead of appearing and failing on click.
 */
class RequiresRepairableTargetCondition extends BaseCondition implements HasParameterSchemaInterface
{
    public static function parameterSchema(): ParameterSchema
    {
        return new ParameterSchema();
    }

    public function check(
        ActorInterface $actor,
        ?ActorInterface $target,
        ActionCondition $condition,
        ConditionObject $conditionObject
    ): ConditionResult {
        $preConditionResult = parent::check($actor, $target, $condition, $conditionObject);

        if (!$preConditionResult->isSuccess()) {
            return $preConditionResult;
        }

        if ($target === null) {
            return new ConditionResult(false, array(), array("Il n'y a rien à réparer ici."));
        }

        /* An exemplar's type lives in `items`, which getRaceByName() never
         * finds: answer here, or every chest stops being repairable. Anything
         * manufactured mends; give `items` its own `repairable` column the day
         * one of them must refuse. */
        if ((string) ($target->data->player_type ?? '') === ItemInstanceService::ENTITY_TYPE) {
            return new ConditionResult(true, array(), array());
        }

        $race = (string) ($target->data->race ?? '');
        $type = $race === '' ? null : (new RaceService())->getRaceByName($race);

        /* A type the catalogue does not know refuses: better than making
         * everything unreadable repairable by default. */
        if (!$type instanceof StructureType || !$type->isRepairable()) {
            return new ConditionResult(
                false,
                array(),
                array('Cela ne se répare pas.')
            );
        }

        return new ConditionResult(true, array(), array());
    }
}
