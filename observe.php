<?php

use App\Entity\EntityManagerFactory;
use App\Interface\ActionInterface;
use App\Interface\ActorInterface;
use App\Service\ActionService;

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


$player = new Player($_SESSION['playerId']);

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

    $sql = '
    SELECT
    p.id AS id,
    name
    FROM
    players AS p
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
}

elseif(!$planJson){

    $sql = '
    SELECT
    p.id AS id,
    name
    FROM
    players AS p
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
    AND
    (
        p.id = ?
        OR
        p.id < 0
    )
    ';

    $res = $db->exe($sql, array($x, $y, $coords->z, $coords->plan, $player->id));
}


if($res->num_rows){

    function custom_compare($a, $b) {

        global $basics;

        if (in_array($a, $basics)) {
            return -1;
        }

        return 1; // Si l'élément $b n'est pas dans l'ordre, il est considéré plus petit
    }

    $card="";
    while($row = $res->fetch_object()){


        $target = new Player($row->id);

        $target->get_data();

        $target->get_caracs();
        if(!empty($card)){
            echo ' <div class="case-infos">  <div class="text"> autre joueur:  <a href="infos.php?targetId='. $target->id .'">'. $target->data->name .'</a> ['.$target->id.']</div> </div>';
           continue;
        }

        $dataName = '<a href="infos.php?targetId='. $target->id .'">'. $target->data->name .'</a>';

        $dataName .= '<div class="effects">';

        foreach($target->get_effects() as $effect){


            if(in_array($effect, EFFECTS_HIDDEN)){

                continue;
            }

            $dataName .= ' <a href="infos.php?targetId='. $target->id .'"><span class="ra '. EFFECTS_RA_FONT[$effect] .'"></span></a>';
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

        $basics = array(
            "attaquer",
            "courir",
            "entrainement",
            "fouiller",
            "prier",
            "repos",
            "vol_a_la_tire"
        );

        // Trier le tableau en utilisant la fonction de comparaison personnalisée
        usort($actions, 'custom_compare');
        $actionService = new ActionService();
        foreach($actions as $actionName){
            $entityManager = EntityManagerFactory::getEntityManager();
            if ($actionName == "attaquer") {
                if ($player->id != $target->id) {
                    $actionData = $actionService->getActionByName("melee");
                    if ($actionData == null) {
                        continue;
                    }
                    $dataImg .= buildActionToDisplay($target, $actionData, "attaquer");
                }
                continue;
            }

            $actionData = $actionService->getActionByName($actionName);
            if ($actionData == null) {
                continue;
            }

            $actionOutcomes = $actionData->getOutcomes();
            foreach ($actionOutcomes as $actionOutcome) {
                if ($actionOutcome->getApplyToSelf() && $player->id == $target->id) {
                    $dataImg .= buildActionToDisplay($target, $actionData);
                    continue 2;
                } else if (!$actionOutcome->getApplyToSelf() && $player->id != $target->id) {
                    $dataImg .= buildActionToDisplay($target, $actionData);
                    continue 2;
                }
            }
        }


        if($target->have_option('isMerchant')){

            $dataImg .= '<a href="merchant.php?targetId='. $target->id .'"><button><span class="ra ra-ammo-bag"></span> <span class="action-name">Marchander</span></button></a>';
        }


        $raceJson = json()->decode('races', $target->data->race);

        $pnjText = $target->id<0 ? ' - PNJ' : '';

        $dataType = $raceJson->name . $pnjText;

        $text = $target->data->text;


        $pvPct = floor($target->getRemaining('pv') / $target->caracs->pv * 100);


        $factionJson = json()->decode('factions', $target->data->faction);

        $faction = '<a href="faction.php?faction='. $target->data->faction .'"><span class="ra '. $factionJson->raFont .'"></span></a>';

        if(
            $target->data->secretFaction != ''
            &&
            $target->data->secretFaction == $player->data->secretFaction
        ){

            $secretJson = json()->decode('factions', $target->data->secretFaction);

            $faction .= '<a href="faction.php?faction='. $target->data->secretFaction .'"><span class="ra '. $secretJson->raFont .'"></span></a>';
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


            echo '
            <div class="case-infos">
                <img src="img/walls/'. $row->name .'.png" title="#'. $row->id .'"/>

                <div class="text">
                    Structure non-passable.<br />
                    ';

                    if(!empty(WALLS_PV[$row->name])){

                        echo 'Destructible ('. Str::get_status($row->damages, WALLS_PV[$row->name]) .').';
                    }
                    else{

                        echo 'Indestructible.';
                    }

                    echo '<br />';

                    // Affichage si la ressource est épuisée ou non
                    if($row->damages == -1){
                        echo '<br /><span style="color:green;"><b>Récoltable.</b></span> <br />';
                    }
                    if($row->damages == -2){
                        echo '<br /><span style="color:red;"><b>Épuisée.</b></span> <br />';
                    }

                    // altar

                    $sql = 'SELECT * FROM map_triggers WHERE name = "altar" AND coords_id= ?';

                    $res = $db->exe($sql, $row->coords_id);

                    if($res->num_rows){

                        $row = $res->fetch_object();

                        $god = new Player($row->params);

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
                            ><span class="ra ra-candle"></span> Vénérer
                            </button>';

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
        }


        // show destroy button
        ?>
        <script>
        var $wall = $('#walls<?php echo $wallId ?>');
        var x = <?php echo $x ?>;
        var y = <?php echo $y ?>;
        </script>
        <script src="js/observe_destroy.js?v=31102024"></script>
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


// coords
echo '<div id="case-coords"><button OnClick="copyToClipboard(this);">x'. $x .',y'. $y .',z'. $coords->z .'</button></div>';


if(!empty($card)){

    echo $card;

    ?>
    <script src="js/observe.js?19"></script>
    <?php
}


echo Str::minify(ob_get_clean());

function buildActionToDisplay(ActorInterface $target, ActionInterface $action, ?string $nameOverride = null) : string {
        $res = '<button
                class="action"
                data-coords-x="'.$target->getCoords()->x.'"
                data-coords-y="'.$target->getCoords(refresh:false)->y.'"
                data-coords-z="'.$target->getCoords(refresh:false)->z.'"
                data-coords-plan="'.$target->getCoords(refresh:false)->plan.'"
                data-target-id="'. $target->getId() .'"
                data-action="'. $action->getName() .'"
                >
                <span class="ra '. $action->getIcon() .'"></span>
                <span class="action-name">'. $action->getDisplayName() .'</span>
                </button><br/>';

        if ($nameOverride != null) {
            $res = '<button
                class="action"
                data-coords-x="'.$target->getCoords()->x.'"
                data-coords-y="'.$target->getCoords(refresh:false)->y.'"
                data-coords-z="'.$target->getCoords(refresh:false)->z.'"
                data-coords-plan="'.$target->getCoords(refresh:false)->plan.'"
                data-target-id="'. $target->getId() .'"
                data-action="'. $nameOverride .'"
                >
                <span class="ra '. $action->getIcon() .'"></span>
                <span class="action-name">'. ucfirst($nameOverride) .'</span>
                </button><br/>';
        }

        return $res;

}
