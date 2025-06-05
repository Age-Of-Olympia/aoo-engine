<?php
use Classes\Item;
use Classes\Log;

$item = new Item($_POST['itemId']);
$item->get_data();

if($item->row->cursed){

    echo '<div id="data">Objet Maudit!</div>';
    exit();
}

$player->drop($item, $_POST['n']);


$text = $player->data->name .' a déposé '. $item->data->name .' x'. $_POST['n'] .'.';

Log::put($player, $player, $text, type:'use');


if(AUTO_GROW && $item->data->type == 'graine' && $_POST['n'] == 1){


    include('scripts/crons/daily/grow_crops.php');
}
