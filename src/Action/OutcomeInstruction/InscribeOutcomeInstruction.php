<?php

namespace App\Action\OutcomeInstruction;

use App\Action\Condition\ConditionObject;
use App\Action\Condition\InscriptionTextCondition;
use App\Entity\OutcomeInstruction;
use App\Interface\HasParameterSchemaInterface;
use App\Action\Schema\ParameterSchema;
use App\Service\InscriptionService;
use Classes\Player;
use Doctrine\ORM\Mapping as ORM;

/**
 * Writes the posted text on the target ({@see InscriptionService},
 * which re-checks the right).
 */
#[ORM\Entity]
class InscribeOutcomeInstruction extends OutcomeInstruction implements HasParameterSchemaInterface
{
    public static function parameterSchema(): ParameterSchema
    {
        return new ParameterSchema();
    }

    public function execute(Player $actor, Player $target, ConditionObject $conditionObject): OutcomeResult
    {
        if ($actor->isSimulated()) {
            return new OutcomeResult(true, outcomeSuccessMessages: ['Écrirait sur la cible.'], outcomeFailureMessages: array());
        }

        $text = InscriptionTextCondition::requestedText() ?? '';

        try {
            (new InscriptionService())->inscribe((int) $target->id, (int) $actor->id, $text);
        } catch (\RuntimeException $e) {
            return new OutcomeResult(false, outcomeSuccessMessages: array(), outcomeFailureMessages: [$e->getMessage()]);
        }

        return new OutcomeResult(
            true,
            outcomeSuccessMessages: [trim($text) === '' ? 'Vous effacez l\'inscription.' : 'Vous écrivez sur ' . htmlspecialchars((string) $target->data->name, ENT_QUOTES, 'UTF-8') . '.'],
            outcomeFailureMessages: array()
        );
    }
}
