<?php

namespace App\Action\OutcomeInstruction;

use App\Action\Condition\ConditionObject;
use App\Entity\OutcomeInstruction;
use App\Interface\HasParameterSchemaInterface;
use App\Action\Schema\ParameterSchema;
use App\Service\ContainerService;
use Classes\Player;
use Doctrine\ORM\Mapping as ORM;

/**
 * Turns the target's lock through the container gateway —
 * {@see ContainerService::toggleOpen()} re-checks control server-side,
 * so a stale button costs a refusal, never a wrong state.
 *
 * Parameters: {"open": 0|1} — the state the gesture produces.
 */
#[ORM\Entity]
class TurnLockOutcomeInstruction extends OutcomeInstruction implements HasParameterSchemaInterface
{
    public static function parameterSchema(): ParameterSchema
    {
        return new ParameterSchema();
    }

    public function execute(Player $actor, Player $target, ConditionObject $conditionObject): OutcomeResult
    {
        $open = !empty($this->getParameters()['open']);

        if ($actor->isSimulated()) {
            return new OutcomeResult(true, outcomeSuccessMessages: [$open ? 'Ouvrirait la serrure.' : 'Fermerait la serrure.'], outcomeFailureMessages: array());
        }

        try {
            (new ContainerService())->toggleOpen((int) $target->id, (int) $actor->id, $open);
        } catch (\RuntimeException | \InvalidArgumentException $e) {
            return new OutcomeResult(false, outcomeSuccessMessages: array(), outcomeFailureMessages: [$e->getMessage()]);
        }

        return new OutcomeResult(
            true,
            outcomeSuccessMessages: [$open ? 'Vous ouvrez.' : 'Vous fermez.'],
            outcomeFailureMessages: array()
        );
    }
}
