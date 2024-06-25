<?php

$rand = rand(1,3);

$mvt = $rand;

// pouvoir divin
$pouvoir = '';

if($player->row->godId == '4'){

    if($player->row->pf > 0){

        $mvt += 1;

        $player->put_pf(-1);

        $pouvoir = '+1 (pouvoir d\'Hermès)';
    }
}


echo '
Vous courrez et gagnez '. $mvt .' Mouvements.

<div class="action-details">1d3 = '. $rand .' '. $pouvoir .'</div>
';
