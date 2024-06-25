<?php


$item = Item::get_item_by_name($name);


$rand = rand(1,3);


$item->add_item($player, $rand);
$item->get_data();


$values = array(
    'id'=>$plantId
);


$db->delete('map_plants', $values);


$text = $player->data->name .' a récolté '. ucfirst($item->data->name) .' x'. $rand .'.
<div class="action-details">1d3 = '. $rand .'</div>';

Log::put($player, $player, $text, "harvest");
