<?php

require_once('config.php');

use App\Action\ActionFactory;
use App\Service\ActionExecutorService;
use App\Service\ActionService;
use App\Service\PlayerService;
use App\View\ActionResultsView;

ob_start();

/*
 * ACTION CHECK
 */

// action
if(!isset($_POST['action'])){
    exit('error action');
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

// store target health
$targetPvBefore = $target->getRemaining('pv');

// healing a full life target
if ($_POST['action'] != 'attaquer') {
    $actionService = new ActionService();
    $action = $actionService->getActionByName($_POST['action']);
    if($action != null && $action->getOrmType() == 'heal') {
        if($targetPvBefore == $target->caracs->pv){
            exit('Ce personnage n\'a pas besoin de soins.');
        }
    }
}


// distance
$distance = View::get_distance($player->getCoords(), $target->getCoords());

$playerService = new PlayerService($player->id);
$numberOfSpellAvailable = $playerService->getNumberOfSpellAvailable();

// ToDo : should a condition
if ($numberOfSpellAvailable < 0) {
    exit('<font color="red">Vous ne pouvez pas utiliser vos sorts <a href="upgrades.php?spells">(max.'. $maxSpells .')</a>.</font></th>');
}

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

$action = ActionFactory::getAction($_POST["action"]);

if ($action == null) {
    if($distance == 1){
        try {
            $action = ActionFactory::getAction('melee'); // Crée une instance de MeleeAction
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }
    elseif($distance > 1){
        try {
            $action = ActionFactory::getAction('distance'); // Crée une instance de DistanceAction
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
    if($target->id != $player->id) {
        $targetMainLog = $actionResults->getLogsArray()["target"];
    }
    
    $logTime = time();
    $hideLogsCondition = ($actionResults->isSuccess() && $action->hideOnSuccess()) || $actionResults->isBlocked();
    if(!empty($actorMainLog)) {
        if ($hideLogsCondition) {
            $type = "hidden_action";
        } else {
            $type = "action";
        }
        Log::put($player, $target, $actorMainLog, $type, $logDetails, $logTime);
    }

    if($target->id != $player->id) {
        if(!empty($targetMainLog)){
            if ($hideLogsCondition) {
                $type = "hidden_action_other_player";
            } else {
                $type = "action_other_player";
            }
            Log::put($target, $player, $targetMainLog, $type, $logDetails, $logTime);
        }
    }

    if ($action->refreshScreen()) {
        @unlink('datas/private/players/'. $_SESSION['playerId'] .'.svg');
        include('scripts/actions/on_hide_reload_view.php');
    }
    
    
} catch (Exception $e) {
    echo $e->getMessage();
}

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
        } else {
            $('#red-filter').css({'height':height +'px'});
        }
        $('body').append('<div class="clicked-cases-reseter" data-coords="<?php echo $target->coords->x .','. $target->coords->y ?>"></div>');
    });
    </script>
    <?php
}

