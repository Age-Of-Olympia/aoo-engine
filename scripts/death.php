<?php


if($target->get_left('pv') > 0){

    exit('error not dead');
}


$text = $player->data->name .' a tué '. $target->data->name .'.';


Log::put($player, $target, $text, $type="kill");


echo '<b><font color="red">Vous tuez votre adversaire.</font></b>';


$target->death();
