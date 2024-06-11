<?php

require_once('config.php');


// action
if(!isset($_POST['action'])){

    exit('error action');
}

$actionJson = json()->decode('actions', $_POST['action']);


// player
$player = new Player($_SESSION['playerId']);

$player->get_caracs();


// target
if(!isset($_POST['targetId'])){

    exit('error targetId');
}

$target = new Player($_POST['targetId']);

$target->get_caracs();


// distance
$distance = Player::get_distance($player->get_coords(), $target->get_coords());


if(!empty($actionJson->distanceMin)){


    if($distance < $actionJson->distanceMin){

        exit('error distance min');
    }
}

if(!empty($actionJson->distanceMax)){


    if($distance > $actionJson->distanceMax){

        exit('error distance max');
    }
}


// log
$log = $actionJson->log;

$log = str_replace('PLAYER', $player->row->name, $log);
$log = str_replace('TARGET', $target->row->name, $log);
$log = str_replace('NAME', $actionJson->name, $log);


if($actionJson->targetType != 'self'){


    // action
    $dice = new Dice(3);

    $playerJet = (is_numeric($actionJson->playerJet)) ? $dice->roll($actionJson->playerJet) : $dice->roll($player->caracs->{$actionJson->playerJet});

    $targetJet = (is_numeric($actionJson->targetJet)) ? $dice->roll($actionJson->targetJet) : $dice->roll($target->caracs->{$actionJson->targetJet});


    echo '<div>Jet '. $player->row->name .': '. implode(' + ', $playerJet) .' = '. array_sum($playerJet) .'</div>';
    echo '<div>Jet '. $target->row->name .': '. implode(' + ', $targetJet) .' = '. array_sum($targetJet) .'</div>';



    // success
    if($playerJet >= $targetJet){

        $success = true;
    }
    else{

        echo 'Raté!';
    }
}
elseif($actionJson->targetType == 'self'){


    if($target->id != $player->id){

        exit('error self');
    }

    $success = true;
}


if(!empty($success) && $success == true){


    if(!empty($actionJson->playerDamages)){

        $playerDamages = (is_numeric($actionJson->playerDamages)) ? $actionJson->playerDamages : $player->caracs->{$actionJson->playerDamages};

        if(!empty($actionJson->bonusDamages)){

            $playerDamages += $actionJson->bonusDamages;
        }


        $targetDamages = (is_numeric($actionJson->targetDamages)) ? $actionJson->targetDamages : $player->caracs->{$actionJson->targetDamages};

        $totalDamages = $playerDamages - $targetDamages;

        if($totalDamages < 1){

            $totalDamages = 1;
        }

        echo '<div>Dégats: '. $playerDamages .' - '. $targetDamages .' = '. $totalDamages .'</div>';
    }

    elseif(!empty($actionJson->playerHeal)){

        $playerHeal = (is_numeric($actionJson->playerHeal)) ? $actionJson->playerHeal : $player->caracs->{$actionJson->playerHeal};

        if(!empty($actionJson->bonusHeal)){

            $playerHeal += $actionJson->bonusHeal;
        }

        echo '<div>Soins: '. $playerHeal .'</div>';
    }
}


// scripts
if(!empty($actionJson->script)){


    include($actionJson->script);
}


// add effects
if(!empty($actionJson->addEffects)){

    foreach($actionJson->addEffects as $e){

        $duration = 0;

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

            if($e->on == 'player'){

                $player->add_effect($e->name, $duration);
            }

            elseif($e->on == 'target'){

                $target->add_effect($e->name, $duration);
            }
        }
    }
}


if(!empty($log)){

    Log::put($player, $target, $log, $type="action");
}
