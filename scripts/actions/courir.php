<?php

$rand = rand(1,3);

$mvt = $rand;

// pouvoir divin
if($player->row->godId == '4'){

    if($player->row->pf > 0){

        $mvt += 1;

        $player->put_pf(-1);
    }
}


echo "Vous courrez et gagnez $mvt Mouvements.";

echo '<div class="details">1d3 = '. $rand .' + 1 (pouvoir d\'Hermès)</div>';
