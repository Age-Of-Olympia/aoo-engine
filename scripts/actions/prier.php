<?php


if(!$player->data->godId){

    exit('<font color="red">Vos prières ne servent à rien, car vous ne vénérez aucun Dieu!</font>');
}


$god = new Player($player->data->godId);
$god->get_data();

$pf = rand(1,3);

$player->put_pf($pf);

echo '
Vous priez '. $god->data->name .' et gagnez '. $pf .' Points de Foi (total '. $player->data->pf .'Pf).

<div class="action-details">1d3 = '. $pf .'</div>
';
