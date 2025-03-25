<?php

require_once('config.php');

use App\Action\ActionFactory;
use App\Service\ActionExecutorService;
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

View::get_walls_between($player->coords, $target->coords);


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

$action = null;
if($actionJson->targetType != 'self'){

    if($target->id == $player->id){

        exit('error not self');
    }

    // action
    $dice = new Dice(3);

    $checkAboveDistance = true;
    $distanceMalus = 0;

    if($actionJson->playerJet == 'cc/ct'){
        if($distance == 1){
            try {
                $action = ActionFactory::getAction('melee'); // Crée une instance de MeleeAction
            } catch (Exception $e) {
                echo $e->getMessage();
            }
        }
        elseif($distance > 1 && ($_POST["action"] != 'special/attaque_sautee')){
            try {
                $action = ActionFactory::getAction('distance'); // Crée une instance de DistanceAction
            } catch (Exception $e) {
                echo $e->getMessage();
            }
        }
    } 
    
    if ($action == null) {
        try {
            $action = ActionFactory::getAction($_POST["action"]); 
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }

} elseif($actionJson->targetType == 'self'){

    if($target->id != $player->id){

        exit('error self');
    }

    if ($action == null) {
        try {
            $action = ActionFactory::getAction($_POST["action"]); 
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }

    $success = true;
}

try {
    $actionExecutor = new ActionExecutorService($action, $player, $target);
    $actionResults = $actionExecutor->executeAction();
    $actionResultsView = new ActionResultsView($actionResults);
    // this make a "echo" needed while the huge action.php file exists
    $actionResultsView->displayActionResults();

    $logDetails = $actionResultsView->getActionResults();
    $actorMainLog = $actionResults->getLogsArray()["actor"];
    if($target->id != $player->id) {
        $targetMainLog = $actionResults->getLogsArray()["target"];
    }
    
    $logTime = time();
    if(!empty($actorMainLog)) {
        if ($actionResults->isSuccess() && $action->hideOnSuccess()) {
            $type = "hidden_action";
        } else {
            $type = "action";
        }
        Log::put($player, $target, $actorMainLog, $type, $logDetails, $logTime);
    }

    if($target->id != $player->id) {
        if(!empty($targetMainLog)){
            if ($actionResults->isSuccess() && $action->hideOnSuccess()) {
                $type = "hidden_action_other_player";
            } else {
                $type = "action_other_player";
            }
            Log::put($target, $player, $targetMainLog, $type, $logDetails, $logTime);
        }
    }
    
    
} catch (Exception $e) {
    echo $e->getMessage();
}

//         if($totalDamages){
//             include('scripts/actions/tank.php');
//         }

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

