<?php

namespace App\Action\Condition;

use App\Action\Schema\ParameterField;
use App\Action\Schema\ParameterSchema;
use App\Enum\FieldType;
use App\Entity\ActionCondition;
use App\Interface\ActorInterface;
use App\Interface\HasParameterSchemaInterface;
use App\Service\TypeRepairService;

/**
 * One repair gesture is one dose of the target type's repair recipe in the
 * row's mode (materials or gold): its
 * costs paid together, its share of the max PV restored, capped at the
 * damage. Refuses when the bag or purse does not hold the whole dose, so a
 * refused repair costs no A and earns no XP; the dose is left on the
 * ConditionObject for the repair outcome, which charges it.
 */
class RepairBillCondition extends BaseCondition implements HasParameterSchemaInterface
{
    public static function parameterSchema(): ParameterSchema
    {
        return new ParameterSchema(
            new ParameterField('mode', FieldType::ENUM, 'Recette de réparation', default: TypeRepairService::MATERIALS, options: TypeRepairService::MODES),
        );
    }

    public function check(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition, ConditionObject $conditionObject): ConditionResult
    {
        // Runs after RequiresRepairableTarget and RequiresDamagedTarget: a target, damaged.
        assert($target !== null);
        $repairs = new TypeRepairService();
        $recipe = $repairs->recipeOf((string) ($target->data->race ?? ''), RequiresRepairableTargetCondition::modeOf($condition));
        if ($recipe === null) {
            return new ConditionResult(false, array(), array('Cela ne se répare pas.'));
        }

        $target->get_caracs();
        $maxPv = (int) ($target->caracs->pv ?? 0);
        $missingPv = $maxPv - $target->getRemaining('pv');

        $lacking = $repairs->missing($actor->getId(), $recipe['costs']);
        if ($lacking !== []) {
            return new ConditionResult(false, array(), array(
                'Réparer demande ' . $repairs->label($recipe['costs']) . ' ; il vous manque ' . $repairs->label($lacking) . '.',
            ));
        }

        $conditionObject->setRepairBill([
            'amount' => TypeRepairService::doseAmount($recipe['percent'], $maxPv, $missingPv),
            'costs' => $recipe['costs'],
        ]);

        return new ConditionResult(true, array(), array());
    }
}
