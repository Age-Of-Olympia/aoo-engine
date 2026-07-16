<?php
namespace App\Action\Condition;

use App\Entity\ActionCondition;
use App\Interface\ActorInterface;
use App\Action\Schema\FieldType;
use App\Action\Schema\HasParameterSchema;
use App\Action\Schema\ParameterField;
use App\Action\Schema\ParameterSchema;

/**
 * Restricts an action to target categories of the GameEntity tree
 * (docs/design-buildings-entities.md §4.4) — the data-driven
 * replacement for id-sign conventions:
 *
 *   'character' — real players, tutorial players, NPCs
 *   'structure' — buildings, unique objects
 *
 * A heal keeps ['character']; an attack that may also raze a palisade
 * declares ['character', 'structure']; a future repair action declares
 * ['structure']. Rows predating the param keep today's behavior via
 * the ['character'] default.
 *
 * Reads the player_type discriminator from the target's data (absent
 * on simulated targets, which default to 'real' — simulations always
 * model characters).
 */
class TargetTypeCondition extends BaseCondition implements HasParameterSchema
{
    private const CATEGORY_BY_PLAYER_TYPE = [
        'real'     => 'character',
        'tutorial' => 'character',
        'npc'      => 'character',
        'building' => 'structure',
        'unique'   => 'structure',
    ];

    public static function parameterSchema(): ParameterSchema
    {
        return new ParameterSchema(
            new ParameterField(
                'allowed',
                FieldType::ENUM,
                'Catégories de cibles autorisées',
                default: ['character'],
                multiple: true,
                options: ['character' => 'Personnage', 'structure' => 'Structure'],
            ),
        );
    }

    public function check(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition, ConditionObject $conditionObject): ConditionResult
    {
        $params = $condition->getParameters();
        $allowed = is_array($params['allowed'] ?? null) && $params['allowed'] !== []
            ? $params['allowed']
            : ['character'];

        $playerType = (string) ($target->data->player_type ?? 'real');
        $category = self::CATEGORY_BY_PLAYER_TYPE[$playerType] ?? 'character';

        if (in_array($category, $allowed, true)) {
            return new ConditionResult(true, array(), array());
        }

        $condition->setBlocking(true);

        $errorMessage = $category === 'structure'
            ? ['Cette action ne peut pas viser une structure.']
            : ['Cette action ne peut viser qu\'une structure.'];

        return new ConditionResult(false, array(), $errorMessage);
    }
}
