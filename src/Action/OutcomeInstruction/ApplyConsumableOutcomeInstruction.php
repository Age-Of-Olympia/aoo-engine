<?php

namespace App\Action\OutcomeInstruction;

use App\Action\Condition\ConditionObject;
use App\Entity\OutcomeInstruction;
use App\Action\Schema\HasParameterSchema;
use App\Action\Schema\ParameterSchema;
use App\Service\InventoryService;
use Classes\Player;
use Doctrine\ORM\Mapping as ORM;

/**
 * Applique la charge du CONSOMMABLE fourni au geste (condition ItemPick
 * → ConditionObject) : bonus, malus, PR/PF, effets ± — la source unique
 * InventoryService::applyConsumablePayload, partagée avec le geste
 * d'inventaire historique. Le coût (1 A, RequiresTraitValue) et le
 * retrait de l'exemplaire (RequiresItem consume) sont des CONDITIONS de
 * l'action générique « consommer » — l'instruction n'applique que les
 * effets. PAS de Log::put ici : le moteur d'actions journalise déjà le
 * geste avec son détail (hiddenText au seul acteur) — un log de plus
 * ferait doublon aux événements.
 */
#[ORM\Entity]
class ApplyConsumableOutcomeInstruction extends OutcomeInstruction implements HasParameterSchema
{
    public static function parameterSchema(): ParameterSchema
    {
        return new ParameterSchema();
    }

    public function execute(Player $actor, Player $target, ConditionObject $conditionObject): OutcomeResult
    {
        $item = $conditionObject->getPickedItem();
        if ($item === null) {
            return new OutcomeResult(false, outcomeSuccessMessages: array(), outcomeFailureMessages: ['Aucun consommable fourni au geste (condition ItemPick manquante ?).']);
        }

        if ($actor->isSimulated()) {
            return new OutcomeResult(true, outcomeSuccessMessages: ['Consommerait : ' . $item->data->name . '.'], outcomeFailureMessages: array());
        }

        $details = InventoryService::applyConsumablePayload($actor, $item);

        $message = 'Vous consommez ' . htmlspecialchars($item->data->name, ENT_QUOTES, 'UTF-8') . '.'
            . ($details !== [] ? ' <i>' . htmlspecialchars(implode(', ', $details), ENT_QUOTES, 'UTF-8') . '</i>' : '');

        return new OutcomeResult(true, outcomeSuccessMessages: [$message], outcomeFailureMessages: array());
    }
}
