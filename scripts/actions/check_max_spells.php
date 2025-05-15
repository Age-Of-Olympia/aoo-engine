<?php

// sort & must be forgotten
if(!empty($actionJson->type) && $actionJson->type == "sort"){

    $spellList = $player->get_spells();

    $spellsN = count($spellList);

    $numberOfSpellsAvailable = $player->get_spells_available($spellN);

    $maxSpells = $player->get_max_spells();

    if($numberOfSpellsAvailable < 0){

        exit('<font color="red">Vous ne pouvez pas utiliser vos sorts <a href="upgrades.php?spells">(max.'. $maxSpells .')</a>.</font></th>');
    }
}
