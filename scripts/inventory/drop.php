<?php


$item = Item::get_item_by_name($_POST['item']);
$item->get_data();

$player->drop($item, $_POST['n']);


if($item->data->type == 'graine' && $_POST['n'] == 1){


    include('scripts/crons/daily/grow_crops.php');
}
