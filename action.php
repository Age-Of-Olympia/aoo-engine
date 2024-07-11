<?php

require_once('config.php');


/*
 * ACTION CHECK
 */


// action
if(!isset($_POST['action'])){

    exit('error action');
}

$actionJson = json()->decode('actions', $_POST['action']);


// special no target
if($actionJson->targetType == 'none'){

    exit('Ce sort ne peut être lancé.');
}


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


// player : ignore equipement
if(!empty($actionJson->playerIgnore)){


    include('scripts/actions/playerIgnore.php');
}

// target : ignore equipement
if(!empty($actionJson->targetIgnore)){


    include('scripts/actions/targetIgnore.php');
}


// distance
$distance = View::get_distance($player->get_coords(), $target->get_coords());


include('scripts/actions/check_distance.php');


include('scripts/actions/check_equipement.php');


View::get_walls_between($player->coords, $target->coords);


// forbid if
if(!empty($actionJson->forbidIf)){


    foreach($actionJson->forbidIf as $e){


        $who = ($e->who == 'player') ? $player : $target;

        if($e->have == 'effect' && $who->have_effect($e->name)){

            exit('Un effet empêche cette action <span class="ra '. EFFECTS_RA_FONT[$e->name] .'"></span>');
        }
    }
}


/*
 * PERFORM ACTION
 */


// log
$log = $actionJson->log;

$log = str_replace('PLAYER', $player->data->name, $log);
$log = str_replace('TARGET', $target->data->name, $log);
$log = str_replace('NAME', $actionJson->name, $log);


if(!empty($emplacement)){

    $log = str_replace('WEAPON', $player->$emplacement->data->name, $log);
}


if($actionJson->targetType != 'self'){


    if($target->id == $player->id){

        exit('error not self');
    }


    // action
    $dice = new Dice(3);


    $checkAboveDistance = true;
    $distanceMalus = 0;


    // special attack cc/ct
    if($actionJson->playerJet == 'cc/ct'){

        if($distance == 1){


            // melee

            $actionJson->playerJet = 'cc';
        }

        elseif($distance > 1){


            // tir / jet

            $actionJson->playerJet = 'ct';
        }
    }

    // special defense cc/agi
    if($actionJson->targetJet == 'cc/agi'){


        if($distance == 1){


            // melee

            $actionJson->targetJet = max($target->caracs->cc, $target->caracs->agi);
        }

        elseif($distance > 1){


            // tir


            // distanceMalus
            if($distance > 2){

                $distanceMalus = ($distance - 2) * 3;
            }


            $option1 = floor( (3/4*$target->caracs->cc) + (1/4*$target->caracs->agi) );
            $option2 = floor( (1/4*$target->caracs->cc) + (3/4*$target->caracs->agi) );


            $actionJson->targetJet = max($option1, $option2);
        }
    }


    $playerJet = (is_numeric($actionJson->playerJet)) ? $dice->roll($actionJson->playerJet) : $dice->roll($player->caracs->{$actionJson->playerJet});

    $targetJet = (is_numeric($actionJson->targetJet)) ? $dice->roll($actionJson->targetJet) : $dice->roll($target->caracs->{$actionJson->targetJet});


    $playerFat = floor($player->data->fatigue / FAT_EVERY);
    $targetFat = floor($target->data->fatigue / FAT_EVERY);


    $playerTotal = array_sum($playerJet) - $playerFat - $distanceMalus;
    $targetTotal = array_sum($targetJet) - $targetFat - $target->data->malus;


    // tir & too far
    if($distanceMalus){


        $distanceTreshold = floor(($distance) * 2.5);
        $checkAboveDistance = $playerTotal >= $distanceTreshold;
    }


    // spell & too far
    if($actionJson->playerJet == 'fm'){


        $distanceTreshold = 4 * ($distance - 1);
        $checkAboveDistance = $playerTotal >= $distanceTreshold;
    }


    // success
    if(
        $checkAboveDistance
        &&
        (
            $actionJson->targetJet == 0
            ||
            $playerTotal >= $targetTotal
        )
        ){

        $success = true;
    }
    else{


        if(!$checkAboveDistance){


            echo '<div style="color: red;">Votre action ne porte pas aussi loin.</div>';
        }
        else{


            echo '<div style="color: red;">Échec.</div>';

            // target malus
            $target->put_malus(1);
        }
    }
}
elseif($actionJson->targetType == 'self'){


    if($target->id != $player->id){

        exit('error self');
    }

    $success = true;
}


if(!empty($success) && $success == true){


    $distanceDmgReduce = 0;


    echo '<div style="color: #66ccff;">Réussite!</div>';


    if(!empty($actionJson->playerDamages)){


        $playerDamages = (is_numeric($actionJson->playerDamages)) ? $actionJson->playerDamages : $player->caracs->{$actionJson->playerDamages};


        if(!empty($actionJson->bonusDamages)){


            if(!is_numeric($actionJson->bonusDamages)){


                $actionJson->bonusDamages = $player->caracs->{$actionJson->bonusDamages};
            }


            $playerDamages += $actionJson->bonusDamages;
        }


        $targetDamages = (is_numeric($actionJson->targetDamages)) ? $actionJson->targetDamages : $target->caracs->{$actionJson->targetDamages};

        $totalDamages = $playerDamages - $targetDamages;


        // tir damages reduce
        if($distance > 2){


            $distanceDmgReduce = $distance - 2;

            $totalDamages -= $distanceDmgReduce;
        }


        if($totalDamages < 1){


            $totalDamages = 1;
        }


        // crit
        if(!isset($target->tete) || !empty($actionJson->autoCrit)){


            if(rand(1,100) <= DMG_CRIT || !empty($actionJson->autoCrit)){


                $critMultiplier = 2;

                $totalDamages *= $critMultiplier;

                echo '<div><font color="red">Critique! Dégâts doublés!</font></div>';
            }
        }


        $distanceDmgReduceTxt = ($distanceDmgReduce) ? ' - '. $distanceDmgReduce .' (Distance)' : '';

        $critTxt = (!empty($critMultiplier)) ? ' (x '. $critMultiplier .')' : '';


        echo '
        Vous infligez '. $totalDamages .' dégâts à '. $target->data->name .'.

        <div class="action-details">'. CARACS[$actionJson->playerDamages] .' - '. CARACS[$actionJson->targetDamages] .' = '. $playerDamages .' - '. $targetDamages . $distanceDmgReduceTxt . $critTxt .' = '. $totalDamages .' dégâts</div>';


        // put negative bonus (damages)
        $target->put_bonus(array('pv'=>-$totalDamages));
    }


    elseif(!empty($actionJson->playerHeal)){


        $baseHeal = (is_numeric($actionJson->playerHeal)) ? $actionJson->playerHeal : $player->caracs->{$actionJson->playerHeal};

        $bonusHeal = 0;


        if(!empty($actionJson->bonusHeal)){


            $bonusHeal = $actionJson->bonusHeal;
        }


        $playerHeal = $baseHeal + $bonusHeal;

        $playerHeal = min($playerHeal, $target->caracs->pv - $target->get_left('pv'));


        echo '
        <div>Vous soignez '. $target->data->name .' de '. $playerHeal .'PV.</div>
        <div class="action-details">'. CARACS[$actionJson->playerHeal] .' = '. $baseHeal .' + '. $bonusHeal .'</div>
        ';


        $target->put_bonus(array('pv'=>$playerHeal));
    }
}


/*
 * RESOLVE ACTION
 */


// default cost
$bonus = array('a'=>-1);


// add other costs (ie. PM cost for spells)
if(!empty($actionJson->costs)){

    foreach($actionJson->costs as $k=>$e){

        $bonus[$k] = -$e;
    }
}


// scripts
if(!empty($actionJson->script)){


    include($actionJson->script);
}


$player->put_bonus($bonus);


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


            include('scripts/actions/on_hide_reload_view.php');
        }
    }
}


/*
 * PRINT AND REQ RESULTS
 */


if(
    (!empty($actionJson->targetJet) && $actionJson->targetJet != 0)
    &&
    $actionJson->targetType != 'self'
){

    $distanceMalusTxt = ($distanceMalus) ? ' - '. $distanceMalus .' (Distance)' : '';

    $malusTxt = ($target->data->malus != 0) ? ' - '. $target->data->malus .' (Malus)' : '';

    $playerFatTxt = ($playerFat != 0) ? ' - '. $playerFat .' (Fatigue)' : '';
    $targetFatTxt = ($targetFat != 0) ? ' - '. $targetFat .' (Fatigue)' : '';

    $playerTotalTxt = ($playerFat || $distanceMalus) ? ' = '. $playerTotal : '';
    $targetTotalTxt = ($targetFat || $target->data->malus) ? ' = '. $targetTotal : '';


    echo '<div class="action-details">Jet '. $player->data->name .' = '. implode(' + ', $playerJet) .' = '. array_sum($playerJet) . $distanceMalusTxt . $playerFatTxt . $playerTotalTxt .'</div>';


    if($checkAboveDistance){


        echo '<div class="action-details">Jet '. $target->data->name .' = '. array_sum($targetJet) . $malusTxt . $targetFatTxt . $targetTotalTxt .'</div>';
    }
    else{

        echo '<div class="action-details">Le jet devait être >= à '. $distanceTreshold .'.</div>';
    }
}


if(!empty($log)){

    Log::put($player, $target, $log, $type="action");
}


// update pv red filter
$pvPct = floor($target->get_left('pv') / $target->caracs->pv * 100);
$height = floor((100 - $pvPct) * 225 / 100);
$height = min($height, 225);

?>
<script>
$(document).ready(function(){

    var height = <?php echo $height ?>;

    $('#red-filter').css({'height':height +'px'});
});
</script>
