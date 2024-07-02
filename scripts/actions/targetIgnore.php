<?php

$itemToEquip = array();


foreach($actionJson->targetIgnore as $e){


    if(!empty($target->$e)){


        // unequip
        $target->equip($target->$e, $doNotRefresh=true);

        $itemToEquip[$e] = $target->$e;

        unset($target->$e);
    }
}

// update caracs
$target->get_caracs();


// store caracs without ignored equipement
$caracsCp = clone $target->caracs;


// re equip
foreach($itemToEquip as $k=>$e){


    $target->equip($e, $doNotRefresh=true);


    // unset again
    unset($target->$k);
}


// apply caracs without ignored equipement
$target->caracs = $caracsCp;

