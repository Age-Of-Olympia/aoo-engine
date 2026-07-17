<?php

use App\Entity\BuildingDetails;
use App\Entity\EntityManagerFactory;
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

                if(!empty(EFFECTS_RA_FONT[$row->name])){

                    echo 'Effet: <span class="ra '. EFFECTS_RA_FONT[$row->name] .'"></span>';
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
        p.coords_id = c.id
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
        p.coords_id = c.id
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
            p.id < 0
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
    p.coords_id = c.id
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
        p.id < 0
    )
    AND
    (p.id = ? OR po.player_id IS NULL)
    ';

    $res = $db->exe($sql, array($x, $y, $coords->z, $coords->plan, $player->id, $player->id));
}


if($res->num_rows){

    /**
     * Trie les actions par catégorie pour regrouper soins et offensives.
     * Ordre: bases -> offensives (melee, distance, spell, technique) -> soins (heal) -> utilitaires
     */
    function sortActionsByCategory(array $actions, ActionService $actionService): array {
        $basics = array(
            "attaquer",
            "courir",
            "entrainement",
            "fouiller",
            "prier",
            "repos",
            "vol_a_la_tire"
        );
        $offensiveTypes = array('melee', 'distance', 'spell', 'technique');
        $healType = 'heal';

        $byCategory = array(
            'basics' => array(),
            'offensive' => array(),
            'heal' => array(),
            'utility' => array()
        );

        foreach ($actions as $actionName) {
            if (in_array($actionName, $basics)) {
                $byCategory['basics'][$actionName] = array_search($actionName, $basics);
                continue;
            }
            $actionData = $actionService->getActionByName($actionName);
            if ($actionData === null) {
                $byCategory['utility'][] = $actionName;
                continue;
            }
            $ormType = $actionData->getOrmType();
            if ($ormType === $healType) {
                $byCategory['heal'][] = $actionName;
            } elseif (in_array($ormType, $offensiveTypes)) {
                $byCategory['offensive'][] = $actionName;
            } else {
                $byCategory['utility'][] = $actionName;
            }
        }

        $result = array();
        foreach ($basics as $b) {
            if (isset($byCategory['basics'][$b])) {
                $result[] = $b;
            }
        }
        sort($byCategory['offensive']);
        sort($byCategory['heal']);
        sort($byCategory['utility']);
        return array_merge($result, $byCategory['offensive'], $byCategory['heal'], $byCategory['utility']);
    }

    $card="";
    $equipStrip="";
    $raceService = new RaceService();
    while($row = $res->fetch_object()){


        $target = PlayerFactory::legacy($row->id);

        $target->get_data();

        $target->get_caracs();
        if(!empty($card)){
            echo ' <div class="case-infos">  <div class="text"> autre joueur:  <a href="infos.php?targetId='. $target->id .'">'. $target->data->name .'</a> ['.$target->getDisplayId().']</div> </div>';
           continue;
        }

        $dataName = '<a href="infos.php?targetId='. $target->id .'">'. $target->data->name .'</a>';

        $dataName .= '<div class="effects">';

        foreach($target->getEffects() as $effect){


            if(in_array($effect->getName(), EFFECTS_HIDDEN)){

                continue;
            }

            $dataName .= ' <a href="infos.php?targetId='. $target->id .'"><span class="ra '. EFFECTS_RA_FONT[$effect->getName()] .'"></span></a>';
        }

        $dataName .= '</div>';


        $dataImg = '';


        if($player->check_missive_permission($target)){

            $dataImg .= '<a href="forum.php?newTopic=Missives&targetId='. $target->id .'"><button
                    class="action">
                    <span class="ra ra-quill-ink"></span>
                    <span class="action-name">Missive</span>
                    </button></a><br/>';
        }


        $actions = $player->get_actions();
        $actionService = new ActionService();
        $actions = sortActionsByCategory($actions, $actionService);
        $actionTargeting = new ActionTargeting();

        foreach($actions as $actionName){
            $entityManager = EntityManagerFactory::getEntityManager();
            if ($actionName == "attaquer") {
                if ($player->id != $target->id) {
                    $actionData = $actionService->getActionByName("melee");
                    if ($actionData == null) {
                        continue;
                    }
                    $dataImg .= buildActionToDisplay($target, $actionData, $actionService, "attaquer");
                }
                continue;
            }

            $actionData = $actionService->getActionByName($actionName);
            if ($actionData == null) {
                continue;
            }

            // Show the action button only in the context its scope allows:
            // self on yourself, target on someone else, both in either, none
            // nowhere (a no-outcome action — e.g. a technique modifier — has no
            // button here, as the old loop did).
            $observingSelf = ($player->id == $target->id);
            $allowed = $observingSelf
                ? $actionTargeting->canTargetSelf($actionData)
                : $actionTargeting->canTargetOther($actionData);

            // And only when the action's TargetType accepts the entity branch
            // of the selection — no Barbier button on a palissade, no Réparer
            // button on a character (the executor would block them anyway).
            $targetCategory = \App\Enum\EntityCategory::fromPlayerType($target->data->player_type ?? 'real');
            $allowed = $allowed && $actionTargeting->canTargetCategory($actionData, $targetCategory);

            if ($allowed) {
                $dataImg .= buildActionToDisplay($target, $actionData, $actionService);
            }
        }


        /* class="action" comme Missive : sans elle, la grille d'actions
         * du HUD ignore ces boutons (nom toujours affiché, taille libre). */
        if($target->have_option('isMerchant')){

            $dataImg .= '<a href="merchant.php?targetId='. $target->id .'"><button class="action"><span class="ra ra-ammo-bag"></span> <span class="action-name">Marchander</span></button></a>';
        }

        if($target->have_option('isTrainer')){

            $dataImg .= '<a href="warschool.php?targetId='. $target->id .'"><button class="action"><span class="ra ra-axe"></span> <span class="action-name">Apprendre</span></button></a>';
        }


        $raceJson = $raceService->getRaceData($target->data->race);

        $pnjText = $target->id<0 ? ' - PNJ' : '';

        // Handle missing race data
        if (!$raceJson || !is_object($raceJson)) {
            $dataType = ucfirst($target->data->race ?? 'inconnu') . $pnjText;
        } else {
            $dataType = $raceJson->name . $pnjText;
        }

        if ($target->id > 0 && !empty($target->data->isInactive)) {
            $dataType .= ' (inactif)';
        }

        $text = $target->data->text;


        $pvPct = ($target->caracs->pv > 0)
            ? floor($target->getRemaining('pv') / $target->caracs->pv * 100)
            : 100;


        /* Bâtiment : satellite + raison de fermeture calculés ICI, avant
         * la carte — le bouton « Parler » (ouvre la fiche, comme
         * « Marchander » chez les PNJ) doit entrer dans $dataImg, et la
         * pastille d'état après la carte réutilise les mêmes valeurs. */
        $buildingDetails = null;
        $buildingClosure = null;
        if (($target->data->player_type ?? '') === 'building') {

            $buildingService = new BuildingService();
            $buildingDetails = $buildingService->getDetails($target->id);

            if ($buildingDetails !== null) {

                $buildingClosure = $buildingService->closureReason($buildingDetails, (int) $pvPct);

                if ($buildingDetails->getDialog() !== '' && $buildingClosure === null) {

                    $dataImg .= '<a href="infos.php?targetId='. $target->id .'"><button class="action"><span class="ra ra-speech-bubble"></span> <span class="action-name">Parler</span></button></a>';
                }
            }
        }


        $factionJson = (new FactionService())->getFactionData($target->data->faction);

        $faction = '';
        if ($factionJson && isset($factionJson->raFont)) {
            $faction = '<a href="faction.php?faction='. $target->data->faction .'"><span class="ra '. $factionJson->raFont .'"></span></a>';
        }

        if(
            $target->data->secretFaction != ''
            &&
            $target->data->secretFaction == $player->data->secretFaction
        ){

            $secretJson = (new FactionService())->getFactionData($target->data->secretFaction);

            if ($secretJson) {
                $faction .= '<a href="faction.php?faction='. $target->data->secretFaction .'"><span class="ra '. $secretJson->raFont .'"></span></a>';
            }
        }

        $data = (object) array(
            'bg'=>$target->data->portrait,
            'name'=>$dataName,
            'img'=>$dataImg,
            'pvPct'=>$pvPct,
            'type'=>$dataType,
            'text'=>$text,
            'race'=>$target->data->race,
            'faction'=>$faction
        );

        $card .= Ui::get_card($data);

        /* Bâtiment sélectionné : pastille d'ÉTAT (toujours), porte
         * Ouvert/Fermé pour tout ÉDIFICE (races.structure_nature — un
         * mur construit n'a pas de porte ; son is_open signifiera un
         * jour la passabilité). La CONVERSATION vit dans la fiche
         * (StructureSheetView, façon marchand, garde d'adjacence côté
         * serveur) — le bouton « Parler » ci-dessus l'ouvre. */
        if ($buildingDetails !== null) {

            $stateLabels = array(
                BuildingDetails::STATE_BUILT => 'Construit',
                BuildingDetails::STATE_CONSTRUCTION => 'En construction',
                BuildingDetails::STATE_RUIN => 'Ruine',
            );
            $stateLabel = $stateLabels[$buildingDetails->getBuildState()] ?? ucfirst($buildingDetails->getBuildState());

            $isEdifice = (bool) $raceService->getRaceByName((string) $target->data->race)?->isEdifice();

            $door = '';
            if ($isEdifice) {
                $door = $buildingClosure === null
                    ? '<span class="building-status-door building-status-door--open">Ouvert</span>'
                    : '<span class="building-status-door building-status-door--closed">Fermé'
                        . ($buildingClosure !== 'fermé volontairement' ? ' (' . $buildingClosure . ')' : '') . '</span>';
            }

            $card .= '<div class="building-status'
                . ($isEdifice && $buildingClosure !== null ? ' building-status--closed' : '') . '">'
                . $door
                . '<span class="building-status-state">' . $stateLabel . ' · PV ' . (int) $pvPct . '%</span>'
                . '</div>';
        }

        /* Équipement porté par le personnage observé — alvéoles pour
         * la vue de sélection du HUD papier, visibles sur écrans
         * larges seulement (js/hud.js + css/hud.css). L'habillage
         * hérité garde sa carte telle quelle. */
        if (Ui::usesPaperTheme()) {

            $equipStrip = \App\View\EquipmentSlotsView::render($target->id);
        }
    }
}

else{


    // no player

    $sql = '
    SELECT
    p.id AS id,
    coords_id,
    name,
    damages
    FROM
    map_walls AS p
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


        // structures

        while($row = $res->fetch_object()){


            $wallId = $row->id;

            /* Le bloc altar réassigne $row plus bas : figer les champs du
             * mur pour la carte mutualisée construite après le bloc. */
            $wallName = $row->name;
            $wallDamages = (int) $row->damages;


            echo '
            <div class="case-infos">
                <img src="img/walls/'. $row->name .'.png" title="#'. $row->id .'"/>

                <div class="text">
                    Structure non-passable.<br />
                    ';

                    if(!empty(WALLS_PV[$row->name]) && WALLS_PV[$row->name] > 0){

                        echo 'Destructible ('. Str::get_status($row->damages, WALLS_PV[$row->name]) .').';
                    }
                    else{

                        echo 'Indestructible.';
                    }

                    echo '<br />';

                    // Affichage si la ressource est épuisée ou non
                    if($row->damages == -1){
                        echo '<br /><span class="resource-status resource-harvestable" style="color:green;"><b>Récoltable.</b></span> <br />';
                    }
                    if($row->damages == -2){
                        echo '<br /><span class="resource-status resource-exhausted" style="color:red;"><b>Épuisée.</b></span> <br />';
                    }

                    // altar

                    $sql = 'SELECT * FROM map_triggers WHERE name = "altar" AND coords_id= ?';

                    $res = $db->exe($sql, $row->coords_id);

                    if($res->num_rows){

                        $row = $res->fetch_object();

                        $god = PlayerFactory::legacy($row->params);

                        $god->get_data();

                        echo 'Altar du Dieu '. $god->data->name .'.';

                        $actions = '';

                        $dataText = "Vous vénérez déjà ce Dieu.";

                        if($god->id != $player->data->godId){

                            $actions = '
                            <button
                                class="action"
                                data-url="worship.php"
                                data-action="worship"
                                data-target-id="'. $row->id .'"
                            ><span class="ra ra-candle"></span>
                            <span class="action-name">Vénérer</span>
                            </button><br/>';

                            $dataText = "Vénérez ce Dieu pour pouvoir lui adresser vos prières.";
                        }

                        $dataName = '<a href="infos.php?targetId='. $god->id .'">Altar du Dieu '. $god->data->name .'</a>';

                        $data = (object) array(
                            'bg'=>$god->data->portrait,
                            'name'=>$dataName,
                            'img'=>$actions,
                            'type'=>'Altar',
                            'race'=>'dieu',
                            'text'=>$dataText
                        );

                        $card = Ui::get_card($data);
                    }

                    echo '
                </div>
            </div>
            ';

            /* Carte mutualisée (Ui::get_card — LE composant de la palissade
             * et de l'autel) : nom du catalogue, portrait avec voile de
             * dégâts, état brisé, description. L'autel garde la priorité
             * quand il a déjà posé sa carte. */
            if(empty($card)){

                $wallBaseName = str_replace('_broken', '', $wallName);
                $isBroken = strpos($wallName, '_broken') !== false;

                $wallLabel = ucfirst(str_replace('_', ' ', $wallBaseName));
                $wallText = '';
                $wallCatalogItem = \Classes\Item::get_item_by_name($wallBaseName);
                if($wallCatalogItem){

                    $wallCatalogItem->get_data();
                    $wallLabel = ucfirst(str_replace('_', ' ', $wallCatalogItem->data->name));
                    $wallText = (string) ($wallCatalogItem->data->text ?? '');
                }

                $wallPvMax = (!empty(WALLS_PV[$wallName]) && WALLS_PV[$wallName] > 0) ? (int) WALLS_PV[$wallName] : 0;

                $wallStatus = ($wallPvMax > 0)
                    ? 'Destructible ('. Str::get_status($wallDamages, $wallPvMax) .').'
                    : 'Indestructible.';

                $data = (object) array(
                    'bg' => 'img/walls/'. $wallName .'.png',
                    'name' => $wallLabel . ($isBroken ? ' — <font color="red">brisé</font>' : ''),
                    'img' => '',
                    'type' => 'Structure',
                    'race' => 'common',
                    'text' => $wallStatus . ($wallText !== '' ? '<br /><sup>'. $wallText .'</sup>' : ''),
                );

                if($wallPvMax > 0){

                    $data->pvPct = max(0, (int) floor(($wallPvMax - $wallDamages) / $wallPvMax * 100));
                }

                $card = Ui::get_card($data);
            }
        }


        // show destroy button
        ?>
        <script>
        var $wall = $('#walls<?php echo $wallId ?>');
        var x = <?php echo $x ?>;
        var y = <?php echo $y ?>;
        </script>
        <script src="js/observe_destroy.js?v=20260715"></script>
        <?php

    }
    else{


        /*
         * go button is now printed in js in scripts/view.php
         */
    }


    // dialogs
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

    if($res->num_rows){


        $row = $res->fetch_object();


        if($row->params[0] == '"'){


            $alert = str_replace('"', '', $row->params);

            echo '<script>alert("'. $alert .'");</script>';
        }

        else{


            $paramsTbl = explode(',', $row->params);


            if(count($paramsTbl) == 1){

                $paramsTbl[] = $paramsTbl[0];
                $paramsTbl[] = $paramsTbl[0];
                $paramsTbl[] = $paramsTbl[0];
            }


            $options = array(
            'name'=>$paramsTbl[0],
            'avatar'=>'img/dialogs/bg/'. $paramsTbl[1] .'.webp',
            'dialog'=>$paramsTbl[2],
            'text'=>''
            );

            echo '<div class="view-dialog">'. Ui::get_dialog($player, $options) .'</div>';
        }
    }
}


// Bourse au sol : lister le contenu de la case (map_items). L'objet seul
// comme la pile s'affichent — marcher sur la case ramasse (go.php).
$sql = '
SELECT mi.n, i.id AS item_id, i.name
FROM map_items AS mi
INNER JOIN coords AS c ON mi.coords_id = c.id
INNER JOIN items AS i ON i.id = mi.item_id
WHERE c.x = ?
AND c.y = ?
AND c.z = ?
AND c.plan = ?
ORDER BY i.name
';
$res = $db->exe($sql, array($x, $y, $coords->z, $coords->plan));

// Instances au sol : même bourse, avec nom propre et état.
$sqlInstances = '
SELECT i.id, i.custom_name, i.durability, i.durability_max, it.name
FROM map_items_instances AS g
INNER JOIN coords AS c ON g.coords_id = c.id
INNER JOIN item_instances AS i ON i.id = g.instance_id
INNER JOIN items AS it ON it.id = i.item_id
WHERE c.x = ?
AND c.y = ?
AND c.z = ?
AND c.plan = ?
';
$resInstances = $db->exe($sqlInstances, array($x, $y, $coords->z, $coords->plan));

if($res->num_rows || $resInstances->num_rows){

    echo '<div class="case-infos">';
    echo '<img src="img/tiles/loot.png" title="Bourse" />';
    echo '<div class="text"><b>Au sol :</b><br />';

    while($row = $res->fetch_object()){

        $groundItem = new \Classes\Item($row->item_id);
        $groundItem->get_data();

        $mini = $groundItem->data->mini;
        if(!is_file($mini)){

            $mini = 'img/items/'. $row->name .'.webp';
        }

        echo '<img src="'. $mini .'" style="max-height:22px;vertical-align:middle;" alt="" /> '
            . $groundItem->data->name .' x'. (int) $row->n .'<br />';
    }

    while($row = $resInstances->fetch_object()){

        $label = $row->custom_name !== ''
            ? '« '. htmlspecialchars($row->custom_name, ENT_QUOTES, 'UTF-8') .' » ('. ucfirst($row->name) .')'
            : ucfirst($row->name);

        $state = ((int) $row->durability <= 0)
            ? ' — <font color="red"><b>brisé</b></font>'
            : ' — durabilité '. (int) $row->durability .'/'. (int) $row->durability_max;

        $mini = 'img/items/'. $row->name .'_mini.webp';
        if(!is_file($mini)){

            $mini = 'img/items/'. $row->name .'.webp';
        }

        echo '<img src="'. $mini .'" style="max-height:22px;vertical-align:middle;" alt="" /> '
            . $label . $state .'<br />';
    }

    /* Sa propre case : on est déjà dessus, marcher n'est pas une option —
     * bouton de ramassage direct (drop accidentel, plus besoin de sortir
     * puis revenir). Ailleurs : le rappel marche-dessus. */
    if((int) $x === (int) $player->coords->x && (int) $y === (int) $player->coords->y){

        echo '<button class="action" onclick="var b=this;b.disabled=true;'
            . 'fetch(\'pickup.php\',{method:\'POST\'}).then(function(r){return r.text();})'
            . '.then(function(t){aooAlert(t).then(function(){document.location.reload();});});">'
            . '<span class="ra ra-hand"></span> <span class="action-name">Ramasser</span></button>';
    }
    else{

        echo '<sup>Marchez sur la case pour ramasser.</sup>';
    }

    echo '</div></div>';
}


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


// coords
echo '<div id="case-coords"><button OnClick="copyToClipboard(this);">x'. $x .',y'. $y .',z'. $coords->z .'</button></div>';


if(!empty($card)){

    echo $card;

    if(!empty($equipStrip)){

        echo $equipStrip;
    }

    ?>
    <script src="js/observe.js?v=20260717c"></script>
    <?php
}


echo Str::minify(ob_get_clean());

function buildActionToDisplay(ActorInterface $target, ActionInterface $action, ActionService $actionService, ?string $nameOverride = null) : string {
        $icon = (new \App\View\Action\ActionIconView())->forAction($action, 'span');
        $costs = $actionService->getCostsArray(null, $action);
        if ($costs !== []) {
            $icon = '<span flow="up" tooltip="Coût : '. implode(', ', $costs) .'">'. $icon .'</span>';
        }

        $name = $nameOverride ?? $action->getName();
        $label = $nameOverride !== null ? ucfirst($nameOverride) : $action->getDisplayName();

        return '<button
                class="action"
                data-coords-x="'.$target->getCoords()->x.'"
                data-coords-y="'.$target->getCoords(refresh:false)->y.'"
                data-coords-z="'.$target->getCoords(refresh:false)->z.'"
                data-coords-plan="'.$target->getCoords(refresh:false)->plan.'"
                data-target-id="'. $target->getId() .'"
                data-action="'. $name .'"
                >
                '. $icon .'
                <span class="action-name">'. $label .'</span>
                </button><br/>';
}
