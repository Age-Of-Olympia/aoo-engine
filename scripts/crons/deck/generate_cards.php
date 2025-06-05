<?php
use Classes\Player;
use Classes\Json;


$list = Player::get_player_list()->list;

// enlever les pnj
foreach($list as $k=>$e){
    if($e->id < 0 || $e->lastLoginTime < time() - INACTIVE_TIME){

    }
        // unset($list[$k]);
    //Enlever les races "privées" dieux, animaux, protocols... au cas où ce ne soit pas un pnj
    if(file_exists('datas/private/races/' . $e->race . '.json'))
        unset($list[$k]);
}



foreach($list as $e){


    $cardJson = (object) array(
        'name'=>$e->name,
        'race'=>$e->race
    );

    $data = Json::encode($cardJson);
    Json::write_json('datas/private/deck/'. $e->id .'.json', $data);
    echo $e->id;
}
