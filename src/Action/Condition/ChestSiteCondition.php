<?php

namespace App\Action\Condition;

use App\Entity\ActionCondition;
use App\Interface\ActorInterface;
use App\Interface\HasParameterSchemaInterface;
use App\Action\Schema\ParameterSchema;

/**
 * The chest-specific build rules — where a lockable container may be
 * placed and who it belongs to. Anything else picked by `construire`
 * passes untouched.
 *
 * - The floor must allow chests (plan_z_levels.chests_allowed; a plan
 *   without level rows restricts nothing).
 * - The builder chooses the owner (POST buildFor): 'self' keeps
 *   today's personal chest, 'faction' gives it to their faction — and
 *   requires one. The validated choice is deposited on the
 *   ConditionObject; PlaceStructure consumes THAT result, never a
 *   re-read of the POST (BuildSitePick pattern).
 */
class ChestSiteCondition extends BaseCondition implements HasParameterSchemaInterface
{
    public const FOR_SELF = 'self';
    public const FOR_FACTION = 'faction';

    public static function parameterSchema(): ParameterSchema
    {
        return new ParameterSchema();
    }

    /** The POSTed owner choice — personal by default, null if forged. */
    public static function requestedHousehold(): ?string
    {
        $raw = (string) ($_POST['buildFor'] ?? self::FOR_SELF);

        return in_array($raw, [self::FOR_SELF, self::FOR_FACTION], true) ? $raw : null;
    }

    public function check(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition, ConditionObject $conditionObject): ConditionResult
    {
        $preConditionResult = parent::check($actor, $target, $condition, $conditionObject);
        if (!$preConditionResult->isSuccess()) {
            return $preConditionResult;
        }

        if (empty($conditionObject->getPickedItem()?->row->lockable)) {
            return new ConditionResult(true, array(), array());
        }

        // The workbench simulation stands on no board.
        if ($actor->isSimulated()) {
            return new ConditionResult(true, array(), array());
        }

        $buildFor = self::requestedHousehold();
        if ($buildFor === null) {
            return new ConditionResult(false, array(), ['Choix du propriétaire du coffre invalide.']);
        }
        if ($buildFor === self::FOR_FACTION && (string) ($actor->data->faction ?? '') === '') {
            return new ConditionResult(false, array(), ['Il faut appartenir à une faction pour poser un coffre de faction.']);
        }

        // The target cell decides the floor; automatic mode stays on the
        // builder's own level either way.
        $coords = $conditionObject->getBuildCoords() ?? $actor->getCoords();
        if ($coords !== null && !plans()->chestsAllowedAt((string) $coords->plan, (int) $coords->z)) {
            return new ConditionResult(false, array(), ['Les coffres ne peuvent pas être posés à ce niveau.']);
        }

        $conditionObject->setBuildFor($buildFor);

        return new ConditionResult(true, array(), array());
    }
}
