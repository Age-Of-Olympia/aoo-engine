<?php

$itemToEquip = array();


foreach($actionJson->playerIgnore as $emp){


    if(!empty($player->emplacements->{$emp})){


        // unequip
        $player->equip($player->emplacements->{$emp}, $doNotRefresh=true);

        $itemToEquip[$emp] = $player->emplacements->{$emp};

        unset($player->emplacements->{$emp});
    }
}

// update caracs
$player->get_caracs();


// store caracs without ignored equipement
$caracsCp = clone $player->caracs;


// re equip
foreach($itemToEquip as $emp=>$item){


    $player->equip($item, $doNotRefresh=true);


    // unset again
    unset($player->emplacements->{$emp});
}


// apply caracs without ignored equipement
$player->caracs = $caracsCp;

