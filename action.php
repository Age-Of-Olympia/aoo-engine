<?php

require_once('config.php');

use App\Action\ActionFactory;
use App\Service\ActionExecutorService;
use App\Service\ActionService;
use App\View\ActionResultsView;

ob_start();


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


// if($player->getRemaining('a') < 1){

//     exit('<font color="red">Pas assez d\'Actions.</font>');
// }

if(isset($actionJson->costs) && isset($actionJson->costs->pm) && $actionJson->costs->pm > $player->getRemaining('pm')){

    exit('<font color="red">Pas assez de PM.</font>');
}

$player->get_data();

$player->get_caracs();


// anti berserk
// if(!isset($actionJson->noBerserkCheck)){

//     include('scripts/actions/check_berserk.php');
// }


// target
if(!isset($_POST['targetId'])){

    exit('error targetId');
}

$target = new Player($_POST['targetId']);

$target->get_data();

$target->get_caracs();


// store target health
$targetPvBefore = $target->getRemaining('pv');

// healing a full life target
if(!empty($actionJson->playerHeal)){


    if($targetPvBefore == $target->caracs->pv){

        exit('Ce personnage n\'a pas besoin de soins.');
    }
}

// action on a dead target
if($targetPvBefore < 1){

    // exit('Ce personnage est mort.');
}


// player : ignore equipement
if(!empty($actionJson->playerIgnore)){


    include('scripts/actions/playerIgnore.php');
}

// target : ignore equipement
if(!empty($actionJson->targetIgnore)){


    include('scripts/actions/targetIgnore.php');
}


// distance
$distance = View::get_distance($player->getCoords(), $target->getCoords());


include('scripts/actions/check_distance.php');


include('scripts/actions/check_equipement.php');


View::get_walls_between($player->coords, $target->coords);


// forbid if
if(!empty($actionJson->forbidIf)){


    foreach($actionJson->forbidIf as $e){


        $who = ($e->who == 'player') ? $player : $target;

        if($e->have == 'effect' && $who->haveEffect($e->name)){

            exit('Un effet empêche cette action <span class="ra '. EFFECTS_RA_FONT[$e->name] .'"></span>');
        }
    }
}


include('scripts/actions/check_max_spells.php');


/*
 * action details
 */


echo '<style>.action-details{display: none;}</style>';

if($player->have_option('showActionDetails')){

    echo '<style>.action-details{display: block;}</style>';
}


/*
 * PERFORM ACTION
 */

// Initialisation de la fabrique avec le répertoire des actions
ActionFactory::initialize('src/Action');
$actionResultsView = null;


// log
$log = $actionJson->log;
if (isset($actionJson->targetLog)) {
    $targetLog = $actionJson->targetLog;
    $targetLog = str_replace('PLAYER', $player->data->name, $targetLog);
    $targetLog = str_replace('TARGET', $target->data->name, $targetLog);
    $targetLog = str_replace('NAME', $actionJson->name, $targetLog);
}

$log = str_replace('PLAYER', $player->data->name, $log);
$log = str_replace('TARGET', $target->data->name, $log);
$log = str_replace('NAME', $actionJson->name, $log);


if(!empty($emplacement)){

    $log = str_replace('WEAPON', $player->emplacements->{$emplacement}->data->name, $log);
    if (isset($targetLog)) {
        $targetLog = str_replace('WEAPON', $player->emplacements->{$emplacement}->data->name, $targetLog);
    }
    
    if($player->data->race=='animal')
    {
        $log = str_replace('avec WEAPON', '', $log);
        $log = str_replace('WEAPON', '', $log);
        if (isset($targetLog)) {
            $targetLog = str_replace('avec WEAPON', '', $targetLog);
            $targetLog = str_replace('WEAPON', '', $targetLog);
        }
    }
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

            try {
                $action = ActionFactory::getAction('Melee'); // Crée une instance de MeleeAction
            } catch (Exception $e) {
                echo $e->getMessage();
            }
        }
        elseif($distance > 1){
            try {
                $action = ActionFactory::getAction('Distance'); // Crée une instance de MeleeAction
            } catch (Exception $e) {
                echo $e->getMessage();
            }
        }
    }

    try {
        $actionExecutor = new ActionExecutorService($action, $player, $target);
        $actionResults = $actionExecutor->executeAction();
        $actionResultsView = new ActionResultsView($actionResults);
                    // this make a "echo" needed while the huge action.php file exists
        $actionResultsView->displayActionResults();

        $logDetails = $actionResultsView->getActionResults();
        $actorMainLog = $actionResults->getLogsArray()["actor"];
        $targetMainLog = $actionResults->getLogsArray()["target"];

        $logTime = time();
        if(!empty($actorMainLog)) {
            if ($actionResults->isSuccess() && $action->hideWhenSuccess()) {
                $type = "hidden_action";
            } else {
                $type = "action";
            }
            Log::put($player, $target, $actorMainLog, $type, $logDetails, $logTime);
        }

        if(!empty($targetMainLog)){
            if ($actionResults->isSuccess() && $action->hideWhenSuccess()) {
                $type = "hidden_action_other_player";
            } else {
                $type = "action_other_player";
            }
            Log::put($target, $player, $targetMainLog, $type, $logDetails, $logTime);
        }
        
        
    } catch (Exception $e) {
        echo $e->getMessage();
    }


    // $actionExecutor = new ActionExecutorService($action, $player, $target);
    // $actionResults = $actionExecutor->executeAction();
    // $actionResultsView = new ActionResultsView($actionResults);
    //             // this make a "echo" needed while the huge action.php file exists
    // $actionResultsView->displayActionResults();

    // $logDetails = $actionResultsView->getActionResults();
    // $actorMainLog = $actionResults->getLogsArray()["actor"];
    // $targetMainLog = $actionResults->getLogsArray()["target"];

    // $logTime = time();
    // if(!empty($actorMainLog)) {
    //     if ($actionResults->isSuccess() && $action->hideWhenSuccess()) {
    //         $type = "hidden_action";
    //     } else {
    //         $type = "action";
    //     }
    //     Log::put($player, $target, $actorMainLog, $type, $logDetails, $logTime);
    // }

    // if(!empty($targetMainLog)){
    //     if ($actionResults->isSuccess() && $action->hideWhenSuccess()) {
    //         $type = "hidden_action_other_player";
    //     } else {
    //         $type = "action_other_player";
    //     }
    //     Log::put($target, $player, $targetMainLog, $type, $logDetails, $logTime);
    // }

    goto fin;

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
        !AUTO_FAIL
        &&
        (
            $checkAboveDistance
            &&
            (
                $actionJson->targetJet == 0
                ||
                $playerTotal >= $targetTotal
            )
        )
    ){

        $success = true;
    }
    else{


        if(!$checkAboveDistance){


            echo '<div style="color: red;">Votre action ne porte pas aussi loin.</div>';
        }
        else{


            // break defenses
            include('scripts/actions/break_defenses.php');


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
        $targetDamagesBonus = empty($actionJson->targetDamagesBonus)? 0 :((is_numeric($actionJson->targetDamagesBonus)) ? $actionJson->targetDamagesBonus : $target->caracs->{$actionJson->targetDamagesBonus});

        $totalDamages = $playerDamages - ($targetDamages + $targetDamagesBonus);

        // tir damages reduce and distance malus has same rules to be applied ( tir + distance > 2 )
        if($distanceMalus){


            $distanceDmgReduce = $distance - 2;

            $totalDamages -= $distanceDmgReduce;
        }


        if($totalDamages < 1){


            $totalDamages = 1;
        }

        // crit
        if(!isset($target->emplacements->tete) || !empty($actionJson->autoCrit)){


            if(rand(1,100) <= DMG_CRIT || !empty($actionJson->autoCrit)){


                $critAdd = 3;

                $totalDamages += $critAdd;

                echo '<div><font color="red">Critique! Dégâts augmentés!</font></div>';
            }
        }

        $distanceDmgReduceTxt = ($distanceDmgReduce) ? ' - '. $distanceDmgReduce .' (Distance)' : '';

        $critTxt = (!empty($critAdd)) ? ' (+ '. $critAdd .')' : '';


        include('scripts/actions/esquive.php');


        if($totalDamages){


            include('scripts/actions/tank.php');


            echo '
            Vous infligez '. $totalDamages .' dégâts à '. $target->data->name .'.

            <div class="action-details">'. CARACS[$actionJson->playerDamages] .' - '. CARACS[$actionJson->targetDamages] .' = '. $playerDamages .' - '. $targetDamages . $distanceDmgReduceTxt . $critTxt .' = '. $totalDamages .' dégâts</div>
            ';


            // put negative bonus (damages)
            $target->put_bonus(array('pv'=>-$totalDamages));


            // put assist
            $player->put_assist($target, $totalDamages);


            // weapon break
            include('scripts/actions/break_weapon.php');
        }
    }


    elseif(!empty($actionJson->playerHeal)){


        $baseHeal = (is_numeric($actionJson->playerHeal)) ? $actionJson->playerHeal : $player->caracs->{$actionJson->playerHeal};

        $bonusHeal = 0;


        if(!empty($actionJson->bonusHeal)){


            $bonusHeal = $actionJson->bonusHeal;
        }


        $playerHeal = $baseHeal + $bonusHeal;

        $playerHeal = min($playerHeal, $target->caracs->pv - $targetPvBefore);


        echo '
        <div>Vous soignez '. $target->data->name .' de '. $playerHeal .'PV.</div>
        <div class="action-details">'. CARACS[$actionJson->playerHeal] .' = '. $baseHeal .' + '. $bonusHeal .'</div>
        ';


        $target->put_bonus(array('pv'=>$playerHeal));

        $playerXp = 3;
        $targetXp = 0;
    }
}


/*
 * RESOLVE ACTION
 */


$db = new Db();


// default cost
$bonus = array('a'=>-1);


// add other costs (ie. PM cost for spells)
if(!empty($actionJson->costs)){

    foreach($actionJson->costs as $k=>$e){

        $bonus[$k] = -$e;
    }
}



// last action
if(!isset($actionJson->noBerserkCheck)){

    $sql = '
    UPDATE
    players
    SET
    lastActionTime = '. time() .'
    WHERE
    id = ?
    ';

    $db->exe($sql, $player->id);
}


// scripts
if(!empty($actionJson->script)){


    include($actionJson->script);
}


$player->put_bonus($bonus);


// add effects
include('scripts/actions/add_effects.php');


// drop munition or jet
include('scripts/actions/drop_ammo.php');


/*
 * XP
 */


if(!isset($playerXp)){


    // playerXp is not defined by the train.php script

    $playerXp = 1;
    $targetXp = 0;

    if(
        (!empty($success) && $success)
        &&
        ($actionJson->targetType != 'self' || (!empty($actionJson->targetJet) && $actionJson->targetJet == 0))
        &&
        !isset($actionJson->playerHeal)
    ){

        $playerXp = $player->get_action_xp($target);
    }

    if(
        (!isset($success) || $success == false)
        &&
        $player->id != $target->id
    ){

        $playerXp = 0;
        $targetXp = 2;
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

$data = ob_get_clean();

$logTime = time();
if(!empty($log)){
    if (isset($actionJson->hideWhenSuccess) && isset($success) && $success && $actionJson->hideWhenSuccess) {
        $type = "hidden_action";
    } else {
        $type = "action";
    }
    Log::put($player, $target, $log, $type, $data, $logTime);
}

if(!empty($targetLog)){
    if (isset($actionJson->hideWhenSuccess) && isset($success) && $success && $actionJson->hideWhenSuccess) {
        $type = "hidden_action_other_player";
    } else {
        $type = "action_other_player";
    }
    Log::put($target, $player, $targetLog, $type, $data, $logTime);
}

echo $data;

fin:
$targetPvAfter = $target->getRemaining('pv');

if($targetPvBefore != $targetPvAfter){


    if($targetPvAfter < 1){

        include('scripts/death.php');
    }


    // update pv red filter
    $pvPct = floor($targetPvAfter / $target->caracs->pv * 100);
    $height = floor((100 - $pvPct) * 225 / 100);
    $height = min($height, 225);

    ?>
    <script>
    $(document).ready(function(){

        var height = <?php echo $height ?>;

        if(height >= 225){

            $('.card-portrait').addClass('dead');
            $('#red-filter').hide();
        }

        else{

            $('#red-filter').css({'height':height +'px'});
        }

        $('body').append('<div class="clicked-cases-reseter" data-coords="<?php echo $target->coords->x .','. $target->coords->y ?>"></div>');
    });
    </script>
    <?php
}


// display xp
if(!empty($playerXp)){

    echo '<div>Vous gagnez '. $playerXp .'Xp.</div>';

    $player->put_xp($playerXp);
}

if(!empty($targetXp)){

    echo '<div>'. $target->data->name .' gagne '. $targetXp .'Xp.</div>';

    $target->put_xp($targetXp);
}
