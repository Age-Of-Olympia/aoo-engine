<?php

$itemToEquip = array();


foreach($actionJson->playerIgnore as $e){


    if(!empty($player->$e)){


        // unequip
        $player->equip($player->$e, $doNotRefresh=true);

        $itemToEquip[$e] = $player->$e;

        unset($player->$e);
    }
}

// update caracs
$player->get_caracs();


// store caracs without ignored equipement
$caracsCp = clone $player->caracs;


// re equip
foreach($itemToEquip as $k=>$e){


    $player->equip($e, $doNotRefresh=true);


    // unset again
    unset($player->$k);
}


// apply caracs without ignored equipement
$player->caracs = $caracsCp;

