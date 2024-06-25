<?php

require_once('config.php');


// action
if(!isset($_POST['action'])){

    exit('error action');
}

$actionJson = json()->decode('actions', $_POST['action']);


// player
$player = new Player($_SESSION['playerId']);

$player->get_data();

$player->get_caracs();


// target
if(!isset($_POST['targetId'])){

    exit('error targetId');
}

$target = new Player($_POST['targetId']);

$target->get_data();

$target->get_caracs();


// distance
$distance = View::get_distance($player->get_coords(), $target->get_coords());


if($distance > $player->caracs->p){

    exit('La cible est hors de votre Perception.');
}


if(!empty($actionJson->distanceMin)){


    if($distance < $actionJson->distanceMin){

        exit('Vous n\'êtes pas à bonne distance.');
    }
}

if(!empty($actionJson->distanceMax)){


    if($distance > $actionJson->distanceMax){

        exit('Vous n\'êtes pas à bonne distance.');
    }
}


if(!empty($actionJson->useEmplacement)){


    $emplacement = $actionJson->useEmplacement;


    if($player->$emplacement->data->subtype == 'melee' && $distance > 1){

        exit('Vous n\'êtes pas à bonne distance (arme de mêlée).');
    }
    elseif($player->$emplacement->data->subtype == 'jet' && $distance < 2){

        exit('Vous n\'êtes pas à bonne distance (arme de jet).');
    }
    elseif($player->$emplacement->data->subtype == 'tir' ){


        if($distance < 2){

            exit('Vous n\'êtes pas à bonne distance (arme de tir).');
        }


        if(!$munition = $player->get_munition($player->$emplacement, $equiped=true)){

            exit('Vous devez équiper une munition.');
        }
    }
}


View::get_walls_between($player->coords, $target->coords);


// log
$log = $actionJson->log;

$log = str_replace('PLAYER', $player->data->name, $log);
$log = str_replace('TARGET', $target->data->name, $log);
$log = str_replace('NAME', $actionJson->name, $log);


if($actionJson->targetType != 'self'){


    // action
    $dice = new Dice(3);

    $playerJet = (is_numeric($actionJson->playerJet)) ? $dice->roll($actionJson->playerJet) : $dice->roll($player->caracs->{$actionJson->playerJet});

    $targetJet = (is_numeric($actionJson->targetJet)) ? $dice->roll($actionJson->targetJet) : $dice->roll($target->caracs->{$actionJson->targetJet});


    // success
    if($actionJson->targetJet == 0 || array_sum($playerJet) >= array_sum($targetJet)){

        $success = true;
    }
    else{

        echo '<div style="color: red;">Échec.</div>';
    }
}
elseif($actionJson->targetType == 'self'){


    if($target->id != $player->id){

        exit('error self');
    }

    $success = true;
}


if(!empty($success) && $success == true){


    echo '<div style="color: #66ccff;">Réussite!</div>';


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

        echo '
        Vous infligez '. $totalDamages .' dégâts à '. $target->data->name .'.

        <div>'. CARACS[$actionJson->playerDamages] .' - '. CARACS[$actionJson->targetDamages] .' = '. $playerDamages .' - '. $targetDamages .' = '. $totalDamages .'</div>';
    }

    elseif(!empty($actionJson->playerHeal)){

        $baseHeal = (is_numeric($actionJson->playerHeal)) ? $actionJson->playerHeal : $player->caracs->{$actionJson->playerHeal};

        $bonusHeal = 0;

        if(!empty($actionJson->bonusHeal)){

            $bonusHeal = $actionJson->bonusHeal;
        }


        $playerHeal = $baseHeal + $bonusHeal;

        echo '
        <div>Vous soignez '. $target->data->name .' de '. $playerHeal .'PV.</div>
        <div class="action-details">'. CARACS[$actionJson->playerHeal] .' = '. $baseHeal .' + '. $bonusHeal .'</div>
        ';
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



            if(!empty($e->text)){

                echo '<div>'. $e->text .'</div>';
            }
        }
    }
}


if(!empty($actionJson->useEmplacement)){


    if(!empty($munition) && $munition){


        $munition->get_data();

        $munition->add_item($player, -1);

        echo 'Perdu: '. $munition->data->name .'';
    }


    if($player->$emplacement->data->subtype == 'jet'){


        if($distance > 2){


            $dropCoords = clone $target->coords;

            $coordsId = View::get_free_coords_id_arround($dropCoords, $p=1);

            $values = array(
            'item_id'=>$player->$emplacement->id,
            'coords_id'=>$coordsId,
            'n'=>1
            );

            $db = new Db();

            $db->insert('map_items', $values);


            $player->$emplacement->add_item($player, -1);


            Player::refresh_views_at_z($dropCoords->z);
        }
    }
}

if(
    (!empty($actionJson->targetJet) && $actionJson->targetJet != 0)
    &&
    $actionJson->targetType != 'self'
){

    echo '<div class="action-details">Jet '. $player->data->name .' = '. implode(' + ', $playerJet) .' = '. array_sum($playerJet) .'</div>';
    echo '<div class="action-details">Jet '. $target->data->name .' = '. array_sum($targetJet) .'</div>';
}


if(!empty($log)){

    Log::put($player, $target, $log, $type="action");
}
