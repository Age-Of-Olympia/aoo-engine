<?php

namespace App\Action\OutcomeInstruction;

use App\Entity\OutcomeInstruction;
use App\Action\Condition\ConditionObject;
use App\Enum\FieldType;
use App\Interface\HasParameterSchemaInterface;
use App\Action\Schema\ParameterField;
use App\Action\Schema\ParameterSchema;
use Doctrine\ORM\Mapping as ORM;
use Classes\Item;
use Classes\Player;
use Classes\View;

#[ORM\Entity]
class EnchantOutcomeInstruction extends OutcomeInstruction implements HasParameterSchemaInterface
{
    public static function parameterSchema(): ParameterSchema
    {
        return new ParameterSchema(
            new ParameterField('location', FieldType::EMPLACEMENT, 'Emplacement à enchanter'),
        );
    }

    public function execute(Player $actor, Player $target, ConditionObject $conditionObject): OutcomeResult {
        $location = $this->getParameters()["location"] ?? "";
        $itemToEnchant = $actor->emplacements->{$location};

        // No variant row, no swap: the player keeps the item they had.
        $enchantedItemId = $itemToEnchant->get_version(array('enchanted' => 1));
        if (!$enchantedItemId) {
            return new OutcomeResult(false, array(), [
                'L\'enchantement de l\'objet: *' . $itemToEnchant->data->name . '* a échoué pour une raison technique, contactez l\'équipe technique du jeu !',
            ]);
        }

        $enchantedItem = new Item($enchantedItemId);
        $itemToEnchant->add_item($actor, -1);
        $enchantedItem->add_item($actor, 1);
        $actor->equip($enchantedItem);

        return new OutcomeResult(true, [
            'Vous enchantez l\'objet: *' . $itemToEnchant->data->name . '*. Cet objet est désormais incassable!',
        ], array());
    }

}
