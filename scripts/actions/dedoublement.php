<?php

use App\View\OnHideReloadView;
use Classes\Db;
use Classes\File;

$data = file_get_contents($player->data->avatar);

$path = 'img/foregrounds/doubles/';

if(!file_exists($path)){

    mkdir($path, 0775);
}

$fileName = $player->id .'.png';

$url = $path . $fileName;

if(!file_exists($url)){


    File::write($url, $data);

    File::changeOpacityAndShift($url, opacity:0.3, shiftX:10);

    $values = array(
        'coords_id'=>$player->data->coords_id,
        'name'=>'doubles/'. $player->id
    );

    $db = new Db();

    $db->insert('map_foregrounds', $values);

    $lastId = $db->get_last_id('map_foregrounds');

    $values = array(
        'player_id'=>$player->id,
        'foreground_id'=>$lastId,
        'params'=>'on'
    );

    $db->insert('players_followers', $values);


    OnHideReloadView::render($player);
}
