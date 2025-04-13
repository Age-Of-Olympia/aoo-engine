<?php

use App\Enum\EquipResult;

$player->get_data();


$item = new Item($_POST['itemId']);
$item->get_data();


$text = $player->data->name .' a utilisé '. $item->data->name .'.';


if(!empty($item->data->emplacement)){


    $return = $player->equip($item);

    if($return == EquipResult::Equip){

        if($player->getRemaining('ae') < 1){


            // undo equip
            $player->equip($item);

            exit('error ae');
        }

        $text = $player->data->name .' a équipé '. $item->data->name .'.';

        $ae = 1;
    }

    elseif($return == EquipResult::Unequip){

        $text = $player->data->name .' a déséquipé '. $item->data->name .'.';
    }
}

elseif($item->row->spell != ''){


    if($player->getRemaining('ae') < 1){

        exit('error ae');
    }

    $raceJson = json()->decode('races', $player->data->race);

    $charges = false;

    if(!in_array($item->row->spell, $raceJson->spells)){

        $charges = 1;
    }


    if(!$item->add_item($player, -1)){

        exit('error add item');
    }

    $player->add_action($item->row->spell, $charges);


    $text = $player->data->name .' a lu '. $item->data->name .'.';

    $ae = 1;
}


// use ae
if(!empty($ae)){

    $player->putBonus(array('ae'=>-$ae));
}


Log::put($player, $player, $text, $type='use');
