<?php

// sort & must be forgotten
if(!empty($actionJson->type) && $actionJson->type == "sort"){

    $spellList = $player->get_spells();

    $spellsN = count($spellList);

    $maxSpells = $player->get_max_spells(count($spellList));

    if($maxSpells < 0){

        $max = $maxSpells + $spellsN;

        exit('<font color="red">Vous ne pouvez pas utiliser vos sorts <a href="upgrades.php?spells">(max.'. $max .')</a>.</font></th>');
    }
}
