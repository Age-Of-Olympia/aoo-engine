<?php

use App\Entity\BuildingDetails;
use App\Factory\EntityManagerFactory;
use App\Interface\ActionInterface;
use App\Interface\ActorInterface;
use App\Service\ActionService;
use App\Service\BuildingService;
use App\Service\FactionService;
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


/* An entity answers from EVERY cell it holds, not only the one it stands on:
 * without this, only the top-left corner of a 3×3 library called itself a
 * library.
 *
 * `players.coords_id` stays in the condition, so an entity whose cells have
 * not kept up is still reachable where it stands. */
$entityOnCell = '(p.coords_id = c.id OR EXISTS (
        SELECT 1 FROM entity_cells ec WHERE ec.player_id = p.id AND ec.coords_id = c.id
    ))';

$db = new Db();


$sql = '
SELECT
p.id AS id,
name
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


    while($row = $res->fetch_object()){

        if(str_starts_with($row->name, 'trace_pas')){
            continue;
        }

        echo '
        <div class="case-infos">
            ';


            if(!file_exists('img/elements/'. $row->name .'.png')){

                echo '<img src="img/elements/'. $row->name .'.webp" />';
            }
            else{

                echo '<img src="img/elements/'. $row->name .'.png" />';
            }

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


/* Routes aménagées (map_routes) : visibles sur la carte, elles doivent
 * aussi se lire dans le panneau de case — c'est là qu'on comprend
 * pourquoi courir est possible ici. */
$sql = '
SELECT
p.name
FROM
map_routes AS p
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

while($row = $res->fetch_object()){

    echo '
    <div class="case-infos">
        <img src="img/routes/'. $row->name .'.png" />
        <div class="text">
            '. ucfirst($row->name) .' aménagée<br />
            Courir y est possible.
        </div>
    </div>
    ';
}


/* Plantes (map_plants) : mêmes égards que les routes — le panneau de
 * case dit ce qui pousse ici et comment le récolter. */
$sql = '
SELECT
p.name
FROM
map_plants AS p
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

while($row = $res->fetch_object()){

    echo '
    <div class="case-infos">
        <img src="img/plants/'. $row->name .'.png" />
        <div class="text">
            '. ucfirst($row->name) .'<br />
            Se récolte en marchant sur la case.
        </div>
    </div>
    ';
}


// plan exceptions
$planJson = json()->decode('plans', $player->coords->plan);


if($planJson){
    // Check if player_visibility is disabled (tutorial mode)
    $playerVisibilityEnabled = !isset($planJson->player_visibility) || $planJson->player_visibility !== false;

    if ($playerVisibilityEnabled) {
        // Show all players at this location (except invisible ones)
        $sql = '
        SELECT
        p.id AS id,
        p.name
        FROM
        players AS p
        INNER JOIN
        coords AS c
        ON
        '. $entityOnCell .'
        LEFT JOIN
        players_options AS po
        ON
        po.player_id = p.id AND po.name = "invisibleMode"
        WHERE
        c.x = ?
        AND
        c.y = ?
        AND
        c.z = ?
        AND
        c.plan = ?
        AND
        (p.id = ? OR po.player_id IS NULL)
        ';

        $res = $db->exe($sql, array($x, $y, $coords->z, $coords->plan, $player->id));
    } else {
        // Player visibility disabled - only show current player and NPCs (except invisible ones)
        $sql = '
        SELECT
        p.id AS id,
        p.name
        FROM
        players AS p
        INNER JOIN
        coords AS c
        ON
        '. $entityOnCell .'
        LEFT JOIN
        players_options AS po
        ON
        po.player_id = p.id AND po.name = "invisibleMode"
        WHERE
        c.x = ?
        AND
        c.y = ?
        AND
        c.z = ?
        AND
        c.plan = ?
        AND
        (
            p.id = ?
            OR
            /* Soi-même, plus tout ce qui n\'est pas un autre joueur : PNJ,
             * bâtiments, objets uniques. « p.id < 0 » ne désignait que les
             * PNJ — depuis la conversion des murs, un bâtiment porte un id
             * POSITIF et disparaissait donc du panneau d\'observation sur
             * les plans à visibilité coupée, alors que la carte le dessine
             * (Classes/View.php : une structure fait partie du décor). */
            p.player_type NOT IN ("real", "tutorial")
        )
        AND
        (p.id = ? OR po.player_id IS NULL)
        ';

        $res = $db->exe($sql, array($x, $y, $coords->z, $coords->plan, $player->id, $player->id));
    }
}

elseif(!$planJson){

    $sql = '
    SELECT
    p.id AS id,
    p.name
    FROM
    players AS p
    INNER JOIN
    coords AS c
    ON
    '. $entityOnCell .'
    LEFT JOIN
    players_options AS po
    ON
    po.player_id = p.id AND po.name = "invisibleMode"
    WHERE
    c.x = ?
    AND
    c.y = ?
    AND
    c.z = ?
    AND
    c.plan = ?
    AND
    (
        p.id = ?
        OR
        /* Même correction que la branche ci-dessus : les structures ont un
         * id positif depuis la conversion des murs. */
        p.player_type NOT IN ("real", "tutorial")
    )
    AND
    (p.id = ? OR po.player_id IS NULL)
    ';

    $res = $db->exe($sql, array($x, $y, $coords->z, $coords->plan, $player->id, $player->id));
}


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
    <script src="js/observe.js?v=20260722a"></script>
    <?php
}


echo Str::minify(ob_get_clean());
