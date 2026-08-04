<?php

namespace App\Action\OutcomeInstruction;

use App\Action\Condition\ConditionObject;
use App\Entity\OutcomeInstruction;
use App\Enum\FieldType;
use App\Interface\HasParameterSchemaInterface;
use App\Action\Schema\ParameterField;
use App\Action\Schema\ParameterSchema;
use App\Service\ConstructionSiteService;
use Classes\Player;
use Doctrine\ORM\Mapping as ORM;

/**
 * One work gesture on a construction site (STI key 'advanceconstructionsite'): +units
 * toward the type's total, PV rising with the fabric, and the last
 * stone turns the site into the building. RequiresConstructionSite upstream
 * answered WHO may and ON WHAT; here only the progress happens.
 */
#[ORM\Entity]
class AdvanceConstructionSiteOutcomeInstruction extends OutcomeInstruction implements HasParameterSchemaInterface
{
    public static function parameterSchema(): ParameterSchema
    {
        return new ParameterSchema(
            new ParameterField('units', FieldType::INT, 'Unités de travail par geste', default: 1),
        );
    }

    public function execute(Player $actor, Player $target, ConditionObject $conditionObject): OutcomeResult
    {
        $units = max(1, (int) (($this->getParameters() ?? [])['units'] ?? 1));

        if ($actor->isSimulated()) {
            return new OutcomeResult(true, outcomeSuccessMessages: ['Ferait avancer le chantier de ' . $units . '.'], outcomeFailureMessages: array());
        }

        $progress = (new ConstructionSiteService())->advance((int) $target->id, $units);
        if ($progress === null) {
            return new OutcomeResult(false, outcomeSuccessMessages: array(), outcomeFailureMessages: ["Ce n'est pas un chantier."]);
        }

        if ($progress['completed']) {
            $this->getOutcome()?->getAction()?->setRefreshScreen(true);
            $name = htmlspecialchars((string) ($target->data->name ?? ''), ENT_QUOTES, 'UTF-8');

            return new OutcomeResult(true, outcomeSuccessMessages: ["Le chantier s'achève : {$name} est construit !"], outcomeFailureMessages: array());
        }

        return new OutcomeResult(true, outcomeSuccessMessages: ["Le chantier avance ({$progress['done']}/{$progress['total']})."], outcomeFailureMessages: array());
    }
}
