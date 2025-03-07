<?php

function addEffect($e, $player, $target){


    $duration = 0;
    $hidden = 0;


    if($e->name == 'adrenaline' || !empty(ELE_CONTROLS[$e->name])){

        $duration = ONE_DAY * 2;
    }

    elseif(!empty($e->duration)){

        $duration = $e->duration;
    }


    if(!empty($e->hidden)){

        $hidden = 1;
    }


    if($e->on == 'player'){

        $player->addEffect($e->name, $duration, $hidden);
    }

    elseif($e->on == 'target'){

        $target->addEffect($e->name, $duration, $hidden);
    }



    if(!empty($e->text)){

        echo '<div>'. $e->text .'</div>';
    }
}


if(!empty($actionJson->addEffects)){

    foreach($actionJson->addEffects as $e){



        if(
            $e->when == 'always'
            ||
            ($e->when == 'win' && (!empty($success) && $success == true))
            ||
            ($e->when == 'fail' && (!isset($success) || $success == false))
        ){

            addEffect($e, $player, $target);
        }
    }
}

// item add effect
if(!empty($actionJson->useEmplacement)){


    $emplacement = $actionJson->useEmplacement;


    if(!empty($player->emplacements->{$emplacement}->data->addEffects)){


        foreach($player->emplacements->{$emplacement}->data->addEffects as $e){


            if(
            $e->when == 'always'
            ||
            ($e->when == 'win' && (!empty($success) && $success == true))
            ||
            ($e->when == 'fail' && (!isset($success) || $success == false))
            ){

                addEffect($e, $player, $target);
            }
        }
    }
}
