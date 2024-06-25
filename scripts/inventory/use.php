<?php


$item = new Item($_POST['itemId']);
$item->get_data();


if(!empty($item->data->emplacement)){


    $player->equip($item);
}
