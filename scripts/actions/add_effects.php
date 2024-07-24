<?php

if(!empty($actionJson->addEffects)){

    foreach($actionJson->addEffects as $e){

        $duration = 0;
        $hidden = 0;

        if(
            $e->when == 'always'
            ||
            ($e->when == 'win' && (!empty($success) && $success == true))
            ||
            ($e->when == 'fail' && (!isset($success) || $success == false))
        ){

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

                $player->add_effect($e->name, $duration, $hidden);
            }

            elseif($e->on == 'target'){

                $target->add_effect($e->name, $duration, $hidden);
            }



            if(!empty($e->text)){

                echo '<div>'. $e->text .'</div>';
            }
        }
    }
}
