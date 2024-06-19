<?php


if(!isset($player->data->quest)){


    exit('Vous n\'avez pas de Quête en cours.<br />Vous pouvez en demander une à un Animateur ou à un Instructeur.');
}


$questJson = json()->decode('quests', $player->data->quest);


echo '<h1>Quête: '. $questJson->name .'</h1>';


$quest = $player->get_quest($player->data->quest);


foreach($questJson->steps as $k=>$e){


    $name = $e->name;

    if($quest->step <= $k){


        echo '<div>'. $name .'<div style="font-size: 88%">'. $e->text .'</div></div>';

        break;
    }


    echo '<div><s>'. $name .'</s></div>';
}
