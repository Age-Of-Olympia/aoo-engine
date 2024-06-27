<?php

$rand = rand(1,3);

$mvt = $rand;

// pouvoir divin
$pouvoir = '';

if($player->data->godId == '4'){

    if($player->data->pf > 0){

        $mvt += 1;

        $player->put_pf(-1);

        $pouvoir = '+1 (pouvoir d\'Hermès)';
    }
}


echo '
Vous courez et gagnez '. $mvt .' Mouvements.

<div class="action-details">1d3 = '. $rand .' '. $pouvoir .'</div>
';
