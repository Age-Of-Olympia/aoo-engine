<?php

$enchantCond = $actionJson->itemConditions[0];

$itemToEnchant = $player->emplacements->{$enchantCond->emplacement};

if(
    $itemToEnchant->data->{$enchantCond->condition} != $enchantCond->value
    ||
    $itemToEnchant->data->name == 'Poing'
){

    exit('Cette action nécessite un équipement particulier.');
}

if($itemToEnchant->row->enchanted != 0){

    exit('Cette arme est déjà enchantée.');
}

echo 'Vous enchantez l\'objet: *'. $itemToEnchant->data->name .'*. Cet objet est désormais incassable!';


$enchantedItemId = $itemToEnchant->get_version($params=array('enchanted'=>1));


if(!$enchantedItemId){

    exit('error enchanted item id');
}

$enchantedItem = new Item($enchantedItemId);

$itemToEnchant->add_item($player, -1);

$enchantedItem->add_item($player, 1);
