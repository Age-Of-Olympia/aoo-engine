<?php
use Classes\ActorInterface;

require_once('config.php');


$player = new ActorInterface($_SESSION['playerId']);

if(!$player->have_option('isAdmin')){

    exit('error admin');
}


$crafts = json()->decode('', 'crafts');

foreach($crafts as $k=>$e){

    echo '<h1>'. $k .'</h1>';


    foreach($e as $item){


        echo $item->name;


        // $private = 0; // public
        //
        //
        // $lastId = Item::put_item($item->name, $private);
        //
        //
        // $dir = ($private) ? 'private' : 'public';
        //
        // $race = ($k != 'ressource') ? $k : 'common';
        //
        //
        // $data = (object) array(
        //     'id'=>$lastId,
        //     'name'=>$item->name,
        //     "private"=>$private,
        //     'price'=>1,
        //     'race'=>$race,
        //     'type'=>'',
        //     'text'=>"Description de l'objet."
        // );
        //
        //
        // Json::write_json('datas/'. $dir .'/items/'. $item->name . '.json', Json::encode($data));

        echo ' Imported<br />';
    }
}
