<?php

$itemToEquip = array();


foreach($actionJson->targetIgnore as $emp){


    if(!empty($target->emplacements->{$emp})){


        // unequip
        $target->equip($target->emplacements->{$emp}, doNotRefresh:true);

        $itemToEquip[$emp] = $target->emplacements->{$emp};

        unset($target->emplacements->{$emp});
    }
}

// update caracs & refresh equipment
$target->get_caracs();


// store caracs without ignored equipement
$caracsCp = clone $target->caracs;


// re equip
foreach($itemToEquip as $emp=>$item){


    $target->equip($item, doNotRefresh:true);

}


// apply caracs without ignored equipement. at this point if ignoring hands, "poing" is equiped in $player but not in db
$target->caracs = $caracsCp;

