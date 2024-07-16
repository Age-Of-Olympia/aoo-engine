<?php


$player->get_data();


$item = new Item($_POST['itemId']);
$item->get_data();


if(!empty($item->data->emplacement)){


    $player->equip($item);
}

elseif($item->row->spell != ''){


    $raceJson = json()->decode('races', $player->data->race);

    $charges = false;

    if(!in_array($item->row->spell, $raceJson->spells)){

        $charges = 1;
    }


    if(!$item->add_item($player, -1)){

        exit('error add item');
    }

    $player->add_action($item->row->spell, $charges);
}


$text = $player->data->name .' a utilisé '. $item->data->name .'.';

Log::put($player, $player, $text, $type='use');
