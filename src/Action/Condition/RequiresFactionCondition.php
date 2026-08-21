<?php

namespace App\Action\Condition;

use App\Entity\ActionCondition;
use App\Enum\FieldType;
use App\Interface\ActorInterface;
use App\Interface\HasParameterSchemaInterface;
use App\Action\Schema\ParameterField;
use App\Action\Schema\ParameterSchema;
use App\Service\RaceService;

/**
 * The actor must belong to a faction.
 *
 * Scope 'edifice' narrows the demand to the generic `construire`
 * action: only when the picked object builds an édifice
 * (structure_nature) — a factionless player can still build palissades
 * and walls, but not a bank or a tower. Scope 'always' is the plain
 * rule, for any action reserved to faction members.
 */
class RequiresFactionCondition extends BaseCondition implements HasParameterSchemaInterface
{
    public const SCOPE_ALWAYS = 'always';
    public const SCOPE_EDIFICE = 'edifice';

    public static function parameterSchema(): ParameterSchema
    {
        return new ParameterSchema(
            new ParameterField(
                'scope',
                FieldType::STRING,
                'Portée',
                default: self::SCOPE_ALWAYS,
                help: self::SCOPE_ALWAYS . " : l'acteur doit appartenir à une faction ; "
                    . self::SCOPE_EDIFICE . " : seulement quand l'objet construit est un édifice",
            ),
        );
    }

    public function check(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition, ConditionObject $conditionObject): ConditionResult
    {
        $preConditionResult = parent::check($actor, $target, $condition, $conditionObject);
        if (!$preConditionResult->isSuccess()) {
            return $preConditionResult;
        }

        $scope = (string) ($condition->getParameters()['scope'] ?? self::SCOPE_ALWAYS);

        if ($scope === self::SCOPE_EDIFICE) {
            // The picked object's name IS the structure type (constructible
            // convention); no object or no édifice type = nothing to demand.
            $type = (string) ($conditionObject->getPickedItem()?->row->name ?? '');
            $race = $type !== '' ? (new RaceService())->getRaceByName($type) : null;
            if ($race === null || !$race->isEdifice()) {
                return new ConditionResult(true, array(), array());
            }
        }

        // The workbench simulation has no memberships to check.
        if ($actor->isSimulated()) {
            return new ConditionResult(true, array(), array());
        }

        if ((string) ($actor->data->faction ?? '') !== '') {
            return new ConditionResult(true, array(), array());
        }

        $message = $scope === self::SCOPE_EDIFICE
            ? 'On ne peut lancer la construction d\'un bâtiment que lorsque l\'on appartient à une faction.'
            : 'Il faut appartenir à une faction.';

        return new ConditionResult(false, array(), array($message));
    }
}
