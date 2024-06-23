<?php


$item = new Item($_POST['itemId']);
$item->get_data();

$player->drop($item, $_POST['n']);


if($item->data->type == 'graine' && $_POST['n'] == 1){


    include('scripts/crons/daily/grow_crops.php');
}
