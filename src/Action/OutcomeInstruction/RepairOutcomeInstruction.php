<?php

namespace App\Action\OutcomeInstruction;

use App\Action\Condition\ConditionObject;
use App\Action\Schema\ParameterSchema;
use App\Entity\OutcomeInstruction;
use App\Interface\HasParameterSchemaInterface;
use App\Service\TypeRepairService;
use Classes\Player;
use Doctrine\ORM\Mapping as ORM;

/**
 * Mends the target by the dose RepairBillCondition checked (STI key
 * 'repair'), and charges it to the actor in the same transaction.
 */
#[ORM\Entity]
class RepairOutcomeInstruction extends OutcomeInstruction implements HasParameterSchemaInterface
{
    public static function parameterSchema(): ParameterSchema
    {
        return new ParameterSchema();
    }

    public function execute(Player $actor, Player $target, ConditionObject $conditionObject): OutcomeResult
    {
        $dose = $conditionObject->getRepairBill();
        if ($dose === null) {
            return new OutcomeResult(false, outcomeSuccessMessages: array(), outcomeFailureMessages: ['Aucune dose de réparation (condition RepairBill manquante ?).']);
        }

        $repairs = new TypeRepairService();
        $name = htmlspecialchars((string) ($target->data->name ?? ''), ENT_QUOTES, 'UTF-8');
        $message = 'Vous réparez ' . $dose['amount'] . ' PV de ' . $name . ' pour ' . $repairs->label($dose['costs']) . '.';

        if ($actor->isSimulated()) {
            return new OutcomeResult(true, outcomeSuccessMessages: [$message], outcomeFailureMessages: array());
        }

        try {
            $repairs->mend($actor, $target, $dose['amount'], $dose['costs']);
        } catch (\RuntimeException $e) {
            return new OutcomeResult(false, outcomeSuccessMessages: array(), outcomeFailureMessages: [$e->getMessage()]);
        }

        return new OutcomeResult(true, outcomeSuccessMessages: [$message], outcomeFailureMessages: array(), totalDamages: $dose['amount']);
    }
}
