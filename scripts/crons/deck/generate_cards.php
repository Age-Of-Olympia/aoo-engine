<?php



$list = json()->decode('players', 'list');


if(!$list){


    // refresh all classements (once per day, done with cron)

    Player::refresh_list();

    $list = json()->decode('players', 'list');


    @unlink('datas/public/classements/general.html');
    @unlink('datas/public/classements/bourrins.html');
    @unlink('datas/public/classements/reputation.html');
    @unlink('datas/public/classements/fortunes.html');
}

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
