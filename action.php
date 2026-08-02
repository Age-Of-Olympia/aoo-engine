<?php

require_once('config.php');

use App\Action\ActionFactory;
use App\Factory\PlayerFactory;
use App\Service\ActionExecutorService;
use App\Service\ActionService;
use App\Service\PlayerService;
use App\Service\ScreenshotService;
use App\View\ActionResultsView;
use App\View\OnHideReloadView;
use Classes\Log;
use Classes\View;

ob_start();

/*
 * ACTION CHECK
 */

// action
if(!isset($_POST['action'])){
    exit('error action');
}

// player
$player = PlayerFactory::active();
$player->get_data();
$player->get_caracs();

// target
if(!isset($_POST['targetId'])){

    exit('error targetId');
}

$target = PlayerFactory::legacy($_POST['targetId']);
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

if (($_POST['coordsX'] != $target->getCoords()->x)
    ||($_POST['coordsY'] != $target->getCoords(refresh:false)->y)
    ||($_POST['coordsZ'] != $target->getCoords(refresh:false)->z)
    ||($_POST['coordsPlan'] != $target->getCoords(refresh:false)->plan)) {
    exit('Votre cible s\'est déplacée.');
}

// distance
$distance = View::get_distance($player->getCoords(), $target->getCoords());

$playerService = new PlayerService($player->id);
//$numberOfSpellAvailable = $playerService->getNumberOfSpellAvailable();

// ToDo : should a condition
//if ($numberOfSpellAvailable < 0) {
//    exit('<font color="red">Vous ne pouvez pas utiliser vos sorts <a href="upgrades.php?spells">(max.'. $maxSpells .')</a>.</font></th>');
//}

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

    // Capture d'arène. Elle vit ici, et non dans ActionExecutorService, parce
    // qu'à ce point les logs de l'action sont écrits et leur texte disponible
    // sous la main : le fichier d'events de l'image se remplit sans requête ni
    // jointure, et sans dépendre d'un rapprochement par horodatage que les
    // tours de 18h rendent ambigu dès que les joueurs cumulent leurs actions.
    //
    // Une action BLOQUÉE est écartée : faute de PA, de portée ou de condition,
    // elle ne modifie aucun pixel de la carte. La capturer produisait une image
    // en tout point identique à la précédente. Vérifié en jeu : sur une session
    // de test, six frames sur douze venaient de tentatives refusées.
    if (!$actionResults->isBlocked()) {
        try {
            $arenaEvents = [];

            // Une action à log masqué (le vol, via hideOnSuccess) ne dit pas
            // son texte à la capture : les fichiers d'events vivent sous
            // img/arene/, que Apache sert sans restriction, et le montage est
            // public. Le jeu la range en "hidden_action" pour la soustraire aux
            // autres joueurs ; la recopier ici en "action" la leur rendrait.
            // L'image, elle, est conservée : le vol déplace bien des objets, la
            // frame est donc un état de l'arène qui a existé, simplement sans
            // réplique. Même condition que les Log::put ci-dessus, à dessein.
            if (!$hideLogsCondition && !empty($actorMainLog)) {
                $arenaEvents[] = [
                    'type'      => 'action',
                    'at'        => $logTime,
                    'player_id' => (int) $player->id,
                    'text'      => $actorMainLog,
                ];
            }

            if (!$hideLogsCondition && $target->id != $player->id && !empty($targetMainLog)) {
                $arenaEvents[] = [
                    'type'      => 'action_other_player',
                    'at'        => $logTime,
                    'player_id' => (int) $target->id,
                    'text'      => $targetMainLog,
                ];
            }

            (new ScreenshotService())->generateAutomaticScreenshot(
                $player,
                $action->getName(),
                $arenaEvents
            );
        } catch (Throwable $e) {
            error_log('Capture arene impossible : ' . $e->getMessage());
        }
    }

    if ($action->refreshScreen()) {
        $file = 'datas/private/players/'. $_SESSION['playerId'] .'.svg';
        if (file_exists($file)) {
            unlink($file); // Delete the file
        }
        OnHideReloadView::render($player);
    }
    
    
} catch (Exception $e) {
    echo $e->getMessage();
}

$targetPvAfter = $target->getRemaining('pv');

if($targetPvBefore != $targetPvAfter){
    if($targetPvAfter < 1){
        PlayerService::ProcessTargetDeath($player, $target);
    }

    // update pv red filter
    $maxPv = $target->caracs->pv ?? 0;
    if ($maxPv > 0) {
        $pvPct = floor($targetPvAfter / $maxPv * 100);
    } else {
        // If max PV is 0, target is dead or invalid
        $pvPct = 0;
    }
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

