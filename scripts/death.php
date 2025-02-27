<?php


if($target->get_left('pv') > 0){

    exit('error not dead');
}


$timestamp = time();
$text = $player->data->name .' a tué '. $target->data->name .'.';

Log::put($player, $target, $text, $type="kill",'',$timestamp);

$text = $target->data->name .' a été tué par '. $player->data->name .'.';

Log::put($target, $player, $text, $type="kill",'',$timestamp);


echo '<b><font color="red">Vous tuez votre adversaire.</font></b>';


echo '
<div class="action-details">
    ';

    $distributedXp = $assistXp = $target->distribute_xp();


    foreach($distributedXp as $k=>$e){


        if($k == 'xp_to_distribute'){

            if($e == 0 && $target->data->isInactive) {
                echo 'Partage de '. $e .'Xp (joueur inactif):<br />';
            } else {
                echo 'Partage de '. $e .'Xp:<br />';
            }

            continue;
        }

        if($k == 'remaining_xp'){

            echo $player->data->name .' +'. $e .'Xp bonus<br />';

            $player->put_xp($e);

            continue;
        }

        if(is_numeric($k)){

            $assistant = new Player($k);

            $assistant->get_data();

            $assistant->put_xp($e);


            $assist = ($assistant->id == $player->id) ? 0 : 1;

            $assistant->put_kill($target, $e, $assist);


            echo $assistant->data->name .' +'. $e .'Xp<br />';
        }
    }

    echo '
</div>
';


$target->death();


include('scripts/actions/on_hide_reload_view.php');
