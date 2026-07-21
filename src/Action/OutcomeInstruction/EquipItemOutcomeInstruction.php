<?php

namespace App\Action\OutcomeInstruction;

use App\Action\Condition\ConditionObject;
use App\Entity\OutcomeInstruction;
use App\Action\Schema\HasParameterSchema;
use App\Action\Schema\ParameterSchema;
use App\Enum\EquipResult;
use Classes\Player;
use Doctrine\ORM\Mapping as ORM;

/**
 * Équipe/déséquipe l'objet fourni au geste (ItemPick → ConditionObject,
 * instance précise comprise) — la bascule de Player::equip, sortie du
 * flux d'inventaire (InventoryService::useItem, chemin hérité conservé).
 *
 * Le COÛT vit ici et pas en condition RequiresTraitValue : il dépend du
 * SENS de la bascule, connu seulement après equip() — équiper coûte
 * 1 Ae (annulé si insuffisant, comme le flux hérité), déséquiper est
 * gratuit. L'action est de type 'equip' : sans ligne action_type_xp,
 * elle ne rapporte aucune XP.
 */
#[ORM\Entity]
class EquipItemOutcomeInstruction extends OutcomeInstruction implements HasParameterSchema
{
    public static function parameterSchema(): ParameterSchema
    {
        return new ParameterSchema();
    }

    public function execute(Player $actor, Player $target, ConditionObject $conditionObject): OutcomeResult
    {
        $item = $conditionObject->getPickedItem();
        if ($item === null) {
            return new OutcomeResult(false, outcomeSuccessMessages: array(), outcomeFailureMessages: ['Aucun équipement fourni au geste (condition ItemPick manquante ?).']);
        }

        if ($actor->isSimulated()) {
            return new OutcomeResult(true, outcomeSuccessMessages: ['Équiperait/déséquiperait : ' . $item->data->name . '.'], outcomeFailureMessages: array());
        }

        $return = $actor->equip($item, instanceId: $conditionObject->getPickedInstanceId(),
            clickedEquippedLine: $conditionObject->getPickedEquippedLine());

        if ($return == EquipResult::Equip) {
            if ($actor->getRemaining('ae') < 1) {
                // même règle que le flux hérité : sans Ae, la bascule est
                // annulée — SANS contexte de ligne : la bascule héritée
                // par catalogue déséquipe ce qui vient d'être équipé
                $actor->equip($item, instanceId: $conditionObject->getPickedInstanceId());

                return new OutcomeResult(false, outcomeSuccessMessages: array(), outcomeFailureMessages: ['Pas assez d\'Ae pour équiper ' . htmlspecialchars($item->data->name, ENT_QUOTES, 'UTF-8') . '.']);
            }

            $actor->putBonus(array('ae' => -1));

            return new OutcomeResult(true, outcomeSuccessMessages: ['Vous équipez ' . htmlspecialchars($item->data->name, ENT_QUOTES, 'UTF-8') . '.'], outcomeFailureMessages: array());
        }

        if ($return == EquipResult::Unequip) {
            return new OutcomeResult(true, outcomeSuccessMessages: ['Vous déséquipez ' . htmlspecialchars($item->data->name, ENT_QUOTES, 'UTF-8') . '.'], outcomeFailureMessages: array());
        }

        return new OutcomeResult(false, outcomeSuccessMessages: array(), outcomeFailureMessages: [htmlspecialchars($item->data->name, ENT_QUOTES, 'UTF-8') . ' ne peut pas être équipé.']);
    }
}
