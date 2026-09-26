<?php

use App\Entity\BuildingDetails;
use App\Factory\EntityManagerFactory;
use App\Interface\ActionInterface;
use App\Interface\ActorInterface;
use App\Service\ActionService;
use App\Service\BuildingService;
use App\Service\FactionService;
use App\Service\MapElementService;
use App\Service\RaceService;
use App\Service\Action\ActionTargeting;
use App\Factory\PlayerFactory;
use Classes\Str;
use Classes\Ui;
use Classes\Db;

require_once('config.php');


if(!isset($_POST['coords'])){

    exit('error coords');
}


ob_start();


$coords = explode(',', $_POST['coords']);

$x = $coords[0];
$y = $coords[1];


if(!is_numeric($x) || !is_numeric($y)){

    exit('error coords numeric');
}


$player = PlayerFactory::active();

$player->get_data();

$coords = $player->getCoords();


$db = new Db();


$sql = '
SELECT
p.id AS id,
name,
rotation
FROM
map_elements AS p
INNER JOIN
coords AS c
ON
p.coords_id = c.id
WHERE
c.x = ?
AND
c.y = ?
AND
c.z = ?
AND
c.plan = ?
';

$res = $db->exe($sql, array($x, $y, $coords->z, $coords->plan));


if($res->num_rows){


    $elements = new MapElementService();

    while($row = $res->fetch_object()){

        echo '
        <div class="case-infos">
            ';


            echo '<img src="'. $elements->imagePath($row->name) .'"'
                . ($row->rotation ? ' style="transform: rotate('. (int) $row->rotation .'deg)"' : '') .' />';

            echo '
            <div class="text">
                Élement ('. $row->name .')<br />
                ';

                if((new \App\Service\EffectService())->exists($row->name)){

                    echo 'Effet: <span class="ra '. (new \App\Service\EffectService())->getIcon($row->name) .'"></span>';
                }
                else{

                    echo 'Aucun effet.';
                }

                echo '
            </div>
        </div>
        ';
    }

}


/* An entity answers from EVERY cell it holds, not only the one it stands on:
 * without this, only the top-left corner of a 3×3 library called itself a
 * library. `players.coords_id` stays in the lookup, so an entity whose cells
 * have not kept up is still reachable where it stands.
 *
 * Both lookups go through an index and the UNION is materialised once; the
 * former `JOIN players ON (coords_id = c.id OR EXISTS …)` scanned players. */
$cellId = '(SELECT id FROM coords WHERE x = ? AND y = ? AND z = ? AND plan = ?)';
$onCell = '(SELECT id FROM players WHERE coords_id = ' . $cellId
    . ' UNION SELECT player_id FROM entity_cells WHERE coords_id = ' . $cellId . ')';
$cellParams = [$x, $y, $coords->z, $coords->plan, $x, $y, $coords->z, $coords->plan];

/* Plans with player_visibility off (tutorial) and unknown plans show only
 * oneself plus what is not another player: PNJ, buildings, unique objects.
 * Structures have positive ids since the wall conversion, hence the type test. */
$planJson = plans()->read($player->coords->plan);
$othersHidden = !$planJson || (isset($planJson->player_visibility) && $planJson->player_visibility === false);

$sql = '
SELECT p.id AS id, p.name
  FROM ' . $onCell . ' AS occ
  JOIN players AS p ON p.id = occ.id
  LEFT JOIN players_options AS po ON po.player_id = p.id AND po.name = "invisibleMode"
 WHERE (p.id = ? OR po.player_id IS NULL)'
    . ($othersHidden ? ' AND (p.id = ? OR p.player_type NOT IN ("real", "tutorial"))' : '');

$res = $db->exe($sql, array_merge($cellParams, $othersHidden ? [$player->id, $player->id] : [$player->id]));


if($res->num_rows){

    /* Une ENTITÉ occupe la case (personnage, PNJ, bâtiment, objet
     * unique) : la vue par type rend la carte, les boutons filtrés et
     * la pastille d'état — le contrôleur ne fait qu'assembler. */
    /* Plusieurs entités sur une case : celle qu'on demande porte la carte,
     * donc les actions. Sans ça, on lisait les autres sans jamais pouvoir
     * agir dessus. */
    [$card, $equipStrip] = \App\View\Observe\EntityCardView::render(
        $player, $res, $x, $y, $coords, (int) ($_POST['entity'] ?? 0)
    );
}

/*
 * Rien sur la case : le bouton d'avance est imprimé en js dans
 * scripts/view.php. Une seconde requête cherchait ici les murs de carte,
 * du temps où ressources et autels vivaient hors des entités.
 */

/* Dialogue de CASE : rendu QUELLE QUE SOIT l'entité présente. Ce bloc
 * vivait dans la branche « aucune entité », ce qui était sans effet
 * tant que rien n'occupait les cases — mais depuis que les structures
 * sont des entités, une pancarte masque son propre texte. Le
 * déclencheur est collé à la case, pas à ce qui s'y trouve : il se lit
 * dans les deux cas. */
$sql = '
SELECT
params
FROM
map_dialogs AS p
INNER JOIN
coords AS c
ON
p.coords_id = c.id
WHERE
c.x = ?
AND
c.y = ?
AND
c.z = ?
AND
c.plan = ?
';

$res = $db->exe($sql, array($x, $y, $coords->z, $coords->plan));

\App\View\Observe\TileDialogView::render($player, $res);



// Bourse au sol : piles + instances (GroundLootService::listAt),
// marcher sur la case ramasse (go.php) — ou le bouton sur sa propre case.
\App\View\Observe\GroundLootView::render($player, (int) $x, (int) $y, $coords);

// Le passage sous ses pieds (tp de SA case) : Monter / Descendre —
// l'arrivée d'un escalier est sa propre case, aucun pas n'y repasse.
\App\View\Observe\PassageView::render($player, (int) $x, (int) $y, $coords);

// Ce que tiennent les contenants de la case — visible seulement ouvert
// et pour les siens (règle du foyer).
\App\View\Observe\ContainerPeekView::render($player, (int) $x, (int) $y, $coords);


// forbidden trigger
$sql = '
SELECT map_triggers.id
FROM map_triggers
INNER JOIN coords AS c ON map_triggers.coords_id = c.id
WHERE c.x = ?
AND c.y = ?
AND c.z = ?
AND c.plan = ?
AND map_triggers.name = "forbidden"
';
$res = $db->exe($sql, array($x, $y, $coords->z, $coords->plan));
if($res->num_rows){
    echo '<div class="case-infos"><div class="text">⛔ Case non praticable.</div></div>';
}


/* Ligne de tir depuis le joueur vers la case observée : cases
 * traversées + premier obstacle (structure blocks_projectiles ou
 * map_resources). Le panneau garde l'info de blocage ; le TRACÉ sur le
 * damier n'est plus embarqué ici — il se demande explicitement par
 * clic droit / appui long sur la case (js/view.js →
 * api/map/line_of_fire.php), un clic gauche en dessinait trop. */
$fireReport = (new BuildingService())->lineOfFireReport(
    $coords,
    (object) ['x' => (int) $x, 'y' => (int) $y, 'z' => $coords->z, 'plan' => $coords->plan]
);

if($fireReport['tiles'] !== [] && $fireReport['blockerName'] !== null){

    echo '<div class="case-infos"><div class="text">🏹 Ligne de tir bloquée par '
        . htmlspecialchars($fireReport['blockerName'], ENT_QUOTES, 'UTF-8') .'.</div></div>';
}


// coords
echo '<div id="case-coords"><button OnClick="copyToClipboard(this);">x'. $x .',y'. $y .',z'. $coords->z .'</button></div>';


if(!empty($card)){

    echo $card;

    if(!empty($equipStrip)){

        echo $equipStrip;
    }

    ?>
    <script src="js/observe.js?v=20260926"></script>
    <?php
}


echo Str::minify(ob_get_clean());
