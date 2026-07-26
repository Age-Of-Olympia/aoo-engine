<?php
use App\Factory\PlayerFactory;
use Classes\Db;
use Classes\View;
use Classes\Log;
use Classes\Item;
use Classes\Element;
require_once('config.php');


if(!isset($_POST['coords'])){

    exit('error coords');
}


$coords = explode(',', $_POST['coords']);

// Validate coords array has both x and y
if(count($coords) < 2){
    exit('error coords format');
}

$player = PlayerFactory::active();

if($player->getRemaining('mvt') < 1){


    echo '<script>aooAlert("Pas assez de Mouvements.").then(function(){document.location.reload();});</script>';
    exit();
}

$player->getCoords();


$goCoords = (object) array(
    'x'=>$coords[0],
    'y'=>$coords[1],
    'z'=>$player->coords->z,
    'plan'=>$player->coords->plan
);

$originalGooCoords=$goCoords;

if(!is_numeric($goCoords->x) || !is_numeric($goCoords->y)){

    exit('error coords numeric');
}


// distance
if(View::get_distance($player->coords, $goCoords) > 1){

    exit('error distance');
}


$coordsId = View::get_coords_id($goCoords);
$goCoords->coordsId=$coordsId;

$db = new Db();


// check invalid location
$inPlayerSql = '';
$values = $coordsId;

if($planJson = json()->decode('plans', $player->coords->plan)){
    // Only block occupied coordinates if player_visibility is not explicitly disabled
    // This allows tutorial players to move freely without seeing each other
    $playerVisibilityEnabled = !isset($planJson->player_visibility) || $planJson->player_visibility !== false;

    if ($playerVisibilityEnabled) {
        /* Les structures PASSABLES (table…) ne bloquent pas la case —
         * liste résolue en PHP, la jointure races/players est interdite
         * par collation (cf. BuildingService). */
        $passable = (new \App\Service\RaceService())->getPassableStructureNames();
        $passableSql = $passable !== []
            ? ' AND players.race NOT IN (' . implode(',', array_map(fn($r) => '"' . $r . '"', $passable)) . ')'
            : '';

        $inPlayerSql = '
        OR
        id IN(
            SELECT coords_id FROM players
            LEFT JOIN players_options AS po ON po.player_id = players.id AND po.name = "invisibleMode"
            WHERE coords_id = ? AND po.player_id IS NULL' . $passableSql . '
            )
        ';

        $values = array($coordsId, $coordsId);
    }
}


$sql = '
SELECT COUNT(*) AS n
FROM coords
WHERE
id IN(
    SELECT coords_id FROM map_resources WHERE coords_id = ?
    )
'. $inPlayerSql .'
';

$res = $db->exe($sql, $values);

$row = $res->fetch_object();

if($row->n){


    echo '<script>aooAlert("Quelque chose obstrue ton chemin.").then(function(){document.location.reload();});</script>';

    exit();
}


$sql = '
SELECT *, "triggers" AS whichTable FROM map_triggers WHERE coords_id = ? and name != "grow"
UNION
SELECT *, "plants" AS whichTable FROM map_plants WHERE coords_id = ?

ORDER BY id DESC
';

$res = $db->exe($sql, array($coordsId, $coordsId));
$db->beginTransaction();
if($res->num_rows){


    while($row = $res->fetch_object()){


        if($row->whichTable == 'triggers'){


            $path = 'scripts/map/triggers/'. $row->name .'.php';

            if(!file_exists($path)){

                exit('error trigger path');
            }

            $triggerId = $row->id;
            $params = $row->params;
        }

        elseif($row->whichTable == 'plants'){


            $path = 'scripts/map/plants.php';

            if(!file_exists($path)){

                exit('error plant path');
            }

            $plantId = $row->id;
            $name = $row->name;
        }


        include($path);
    }
}


// underground
if($goCoords->z < 0){


    /*$sql = '
    SELECT COUNT(*) AS n
    FROM map_tiles
    WHERE
    coords_id = ?
    AND
    name = "caverne"
    ';*/

    $sql = '
    SELECT COUNT(*) AS n
    FROM map_tiles
    WHERE
    coords_id = ?
    ';

    $res = $db->exe($sql, $coordsId);

    $row = $res->fetch_object();

    if(!$row->n){


        if(!isset($player->caracs)){


            $player->get_caracs();
        }


        /* Creuser sans Pioche inflige un malus : confirmation AVANT
         * de dépenser quoi que ce soit — annuler ne creuse pas. La
         * confirmation re-poste le même déplacement avec le drapeau. */
        if($player->emplacements->main1->data->name != 'Pioche' && empty($_POST['digConfirmed'])){

            $digCoords = addslashes($_POST['coords']);

            echo '<script>
            aooConfirm("Vous n\'avez pas de Pioche en main : creuser à mains nues fatigue (malus).\n\nCreuser quand même ?").then(function(ok){
                if(!ok){
                    /* view.js a déjà montré l\'engrenage et débranché la
                     * flèche : restaurer l\'état cliquable sans recharger. */
                    $("#go-img").attr("href", "img/ui/view/arrow.webp");
                    if(typeof window.bindMapView === "function"){ window.bindMapView(); }
                    return;
                }
                $.post("go.php", {"coords": "'. $digCoords .'", "digConfirmed": 1}, function(data){
                    if($.trim(data) !== ""){ $("#ajax-data").html(data); return; }
                    if(typeof window.hudRefreshAfterMove === "function"){ window.hudRefreshAfterMove(); }
                    else{ document.location.reload(); }
                });
            });
            </script>';
            exit();
        }


        /* Le creusement est l'ACTION `creuser` du catalogue, démarrée
         * par le déplacement (cadrage 2026-07-19 : le mouvement lance
         * des actions) : coût 1 A, pierre, malus sans Pioche et galerie
         * vivent dans ses conditions/instructions — go.php ne fait que
         * fournir la case visée et raconter le résultat. */
        $_POST['digX'] = (string) $goCoords->x;
        $_POST['digY'] = (string) $goCoords->y;

        $digAction = \App\Action\ActionFactory::getAction('creuser');
        if($digAction === null){

            exit('error creuser action missing (run migrations)');
        }

        $digResults = (new \App\Service\ActionExecutorService($digAction, $player, $player))->executeAction();

        /* Une action laisse TOUJOURS un événement, d'où qu'elle parte —
         * ici du déplacement (récap détaillé : le même rendu
         * qu'action.php, sans l'écho). */
        \App\Service\Action\ActionEventLogger::write(
            $digAction,
            $digResults,
            $player,
            $player,
            (new \App\View\ActionResultsView($digResults))->getActionResults()
        );

        if($digResults->isBlocked() || !$digResults->isSuccess()){

            $reason = 'Impossible de creuser ici.';
            foreach ($digResults->getConditionsResultsArray() as $conditionResult) {
                foreach (($conditionResult->getConditionFailureMessages() ?? []) as $message) {
                    $reason = $message;
                    break 2;
                }
            }

            echo '<script>aooAlert('. json_encode($reason) .').then(function(){document.location.reload();});</script>';
            exit();
        }

        if($player->emplacements->main1->data->name != 'Pioche'){

            echo '<script>aooAlert("Creuser sans Pioche, qu\'est-ce que ça fatigue !").then(function(){document.location.reload();});</script>';
        }
    }
}


// sky
elseif($goCoords->z > 0){


    $sql = 'SELECT COUNT(*) AS n FROM map_tiles WHERE coords_id = ?';

    $res = $db->exe($sql, $coordsId);

    $row = $res->fetch_object();

    if(!$row->n && !$player->effectService->grantsFlight($player->getEffects())){

        echo '<script>aooAlert("Il faut pouvoir voler pour accéder à ce lieu.").then(function(){document.location.reload();});</script>';

        exit();
    }
}



// loots — piles ET instances au sol, via le service partagé avec
// pickup.php (ramasser sa propre case).
(new \App\Service\GroundLootService())->collect($player, (int) $coordsId, $goCoords);



// Tutorial mode: Check if movements should be consumed for current step
$consumeMovement = false;
// Check if player is on a tutorial plan (either 'tutorial' or plans starting with 'tut_')
$isTutorial = ($player->coords->plan === 'tutorial' || strpos($player->coords->plan, 'tut_') === 0);
if ($isTutorial) {
    // Check tutorial context to see if movements should be limited
    if (!empty($_SESSION['in_tutorial']) && !empty($_SESSION['tutorial_session_id'])) {
        // Tutorial can disable movement consumption for certain steps (unlimited movement)
        // By default, tutorial does NOT consume movements (legacy behavior)
        // Steps can enable consumption via context_changes['consume_movements'] = true
        $consumeMovement = !empty($_SESSION['tutorial_consume_movements']);
        error_log("[go.php] Tutorial mode: player={$player->id}, plan={$player->coords->plan}, consume_movements=" . ($consumeMovement ? 'true' : 'false'));
    }
}

// Consume movement if:
// - Plan has JSON config (non-tutorial plans with resources) OR
// - Tutorial explicitly requests movement consumption
// Note: Tutorial plan JSON existence doesn't trigger consumption (only $consumeMovement flag does)
if(($planJson && !$isTutorial) || $consumeMovement){
    // cost (neg bonus)
    $bonus = array('mvt'=>-1);
    $player->putBonus($bonus);

    // Usure : un déplacement ARME les objets équipés à
    // déclencheur « move » (bottes…) — le décrément tombe au tour.
    (new \App\Service\WearService())->arm($player->id, 'move');

    // CRITICAL: Regenerate JSON cache after consuming movement
    // This ensures load_caracs.php shows correct movement count
    $player->get_caracs();

    if ($consumeMovement) {
        error_log("[go.php] Consumed movement for tutorial player {$player->id}, regenerated cache");
    }
}

if(!$player->have_option('incognitoMode') && !$player->have_option('invisibleMode')
    && !$player->effectService->grantsFlight($player->getEffects()))
{
    $footstep='trace_pas_';
    if($originalGooCoords->y>$player->coords->y){
        $footstep.='n';
    }
    elseif($originalGooCoords->y<$player->coords->y){
        $footstep.='s';
    }
    if($originalGooCoords->x>$player->coords->x){
        $footstep.='e';
    }
    elseif($originalGooCoords->x<$player->coords->x){
        $footstep.='o';
    }

    /* Durées en TOURS depuis le passage des effets aux tours : une trace
     * tient un tour, deux si le marcheur est couvert de boue. */
    $footstepDuration = 1;
    if ($player->have_effect("boue")) {
        $footstepDuration = 2;
    }
    if(!$player->have_effect("leger")){
        /* Pas de purge de cache ici : Player::go() ci-dessous purge déjà
         * la case d'origine (celle de la trace) et la destination. La
         * demander une seconde fois doublerait, à CHAQUE déplacement, la
         * purge la plus coûteuse du jeu — pour un résultat identique. */
        Element::put($footstep, $player->data->coords_id, $footstepDuration, refreshWatchers: false);
    }
    
}
$db->commit();
$player->go($goCoords);