<?php

require_once('config.php');

use App\Action\ActionFactory;
use App\Factory\PlayerFactory;
use App\Service\ActionExecutorService;
use App\Service\ActionService;
use App\Service\PlayerService;
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

// target — absent : action sur soi-même (constructibles depuis
// l'inventaire, et toute action self déclenchée hors panneau de case)
$selfDefaulted = !isset($_POST['targetId']);
if($selfDefaulted){

    $_POST['targetId'] = $player->id;
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

// Garde anti-cible-déplacée : une action sur SOI depuis l'inventaire
// (constructibles) n'a pas de coordonnées observées — soi-même ne
// s'enfuit pas. Pour toute VRAIE cible, les coordonnées restent
// obligatoires : les omettre ne contourne pas la garde.
if (!$selfDefaulted && !isset($_POST['coordsX'])) {

    exit('error coords');
}
if (isset($_POST['coordsX'])
    && (($_POST['coordsX'] != $target->getCoords()->x)
    ||($_POST['coordsY'] != $target->getCoords(refresh:false)->y)
    ||($_POST['coordsZ'] != $target->getCoords(refresh:false)->z)
    ||($_POST['coordsPlan'] != $target->getCoords(refresh:false)->plan))) {
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

/* Un nom d'action inconnu est REFUSÉ.
 *
 * Un repli choisissait ici l'action à la place du client : cible
 * adjacente → mêlée, cible plus loin → tir. Il existait pour le nom
 * fantôme « attaquer », qui n'avait pas de ligne au catalogue et se
 * résolvait par la distance — c'est la scission en melee + distance
 * (Version20260725110000) qui l'a rendu inutile.
 *
 * Il ne testait pourtant PAS « attaquer » : il se déclenchait pour
 * n'importe quel nom inconnu. Une faute de frappe ou un bouton mal
 * câblé produisait donc une attaque de mêlée au lieu d'une erreur —
 * un fourre-tout silencieux, et le contraire de ce qu'on veut d'un
 * point d'entrée qui exécute des actions de jeu. */
if ($action == null) {

    exit('Action inconnue.');
}

try {
    $actionExecutor = new ActionExecutorService($action, $player, $target);
    $actionResults = $actionExecutor->executeAction();
    $actionResultsView = new ActionResultsView($actionResults);
    // this make a "echo" needed while the huge action.php file exists
    $actionResultsView->displayActionResults();

    /* Événement de l'action : écriture mutualisée (ActionEventLogger) —
     * la même que pour les actions démarrées par le déplacement. */
    \App\Service\Action\ActionEventLogger::write(
        $action,
        $actionResults,
        $player,
        $target,
        $actionResultsView->getActionResults()
    );

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

    /* Structure encore debout : son sprite a pu basculer (blessé/réparé,
     * refreshWoundSprite) — patcher son <image> du damier en place, relu
     * en base car le memo $target->data date d'avant le coup. À la mort,
     * OnHideReloadView redessine déjà tout le plateau. */
    $boardSpritePatch = '';
    if ($targetPvAfter >= 1
        && \App\Enum\EntityCategory::fromPlayerType($target->data->player_type ?? 'real') === \App\Enum\EntityCategory::Structure) {

        $freshAvatar = (string) ((new Classes\Db())->exe(
            'SELECT avatar FROM players WHERE id = ?', $target->id
        )->fetch_object()->avatar ?? '');

        if ($freshAvatar !== '') {
            $boardSpritePatch = 'window.aooUpdateBoardSprite && aooUpdateBoardSprite('
                . (int) $target->id . ', ' . json_encode($freshAvatar) . ');';
        }
    }

    ?>
    <script>
    $(document).ready(function(){
        <?php echo $boardSpritePatch ?>

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

