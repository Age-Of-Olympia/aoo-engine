<?php

namespace App\Action\OutcomeInstruction;

use App\Entity\OutcomeInstruction;
use App\Action\Condition\ConditionObject;
use App\Action\Schema\FieldType;
use App\Action\Schema\HasParameterSchemaInterface;
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
        $outcomeSuccessMessages = array();
        $outcomeFailureMessages = array();
        $params =$this->getParameters();
        $result = true;
        $location = $params["location"] ?? "";

        $itemToEnchant = $actor->emplacements->{$location};

        $outcomeSuccessMessages[sizeof($outcomeSuccessMessages)] = 'Vous enchantez l\'objet: *'. $itemToEnchant->data->name .'*. Cet objet est désormais incassable!';
        $enchantedItemId = $itemToEnchant->get_version($params=array('enchanted'=>1));

        if(!$enchantedItemId){
            $result = true;
            $outcomeFailureMessages[sizeof($outcomeFailureMessages)] = 'L\'enchantement de l\'objet: *'. $itemToEnchant->data->name .'* a échoué pour une raison technique, contactez l\'équipe technique du jeu !';
        }

        $enchantedItem = new Item($enchantedItemId);
        $itemToEnchant->add_item($actor, -1);
        $enchantedItem->add_item($actor, 1);
        $actor->equip($enchantedItem);

        return new OutcomeResult($result, outcomeSuccessMessages:$outcomeSuccessMessages, outcomeFailureMessages: $outcomeFailureMessages);
    }

}
