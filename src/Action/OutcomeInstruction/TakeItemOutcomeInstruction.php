<?php

namespace App\Action\OutcomeInstruction;

use App\Entity\OutcomeInstruction;
use App\Action\Condition\ConditionObject;
use App\Action\Schema\HasParameterSchema;
use App\Action\Schema\ParameterSchema;
use App\Service\UniqueObjectService;
use Classes\Player;
use Doctrine\ORM\Mapping as ORM;

/**
 * Pont carte des objets (docs/design-items-instances.md §3.3) : le débouché de
 * l'action « ramasser » — la cible est un UniqueObject enveloppant une
 * instance ; l'instance rejoint l'inventaire de l'acteur, l'entité de
 * carte disparaît. L'identité (usure, nom, provenance) survit à
 * l'aller-retour.
 */
#[ORM\Entity]
class TakeItemOutcomeInstruction extends OutcomeInstruction implements HasParameterSchema
{
    public static function parameterSchema(): ParameterSchema
    {
        return new ParameterSchema();
    }

    public function execute(Player $actor, Player $target, ConditionObject $conditionObject): OutcomeResult
    {
        if ($actor->isSimulated()) {
            return new OutcomeResult(true, outcomeSuccessMessages: ['Ramasserait l\'objet.'], outcomeFailureMessages: array());
        }

        $targetName = htmlspecialchars((string) ($target->data->name ?? ''), ENT_QUOTES, 'UTF-8');

        $instanceId = (new UniqueObjectService())->takeInstance((int) $target->id, (int) $actor->id);
        if ($instanceId === null) {
            return new OutcomeResult(
                false,
                outcomeSuccessMessages: array(),
                outcomeFailureMessages: ['Il n\'y a rien à ramasser ici.']
            );
        }

        $actor->refresh_invent();
        $this->getOutcome()?->getAction()?->setRefreshScreen(true);

        return new OutcomeResult(
            true,
            outcomeSuccessMessages: ['Vous ramassez ' . $targetName . '.'],
            outcomeFailureMessages: array()
        );
    }
}
