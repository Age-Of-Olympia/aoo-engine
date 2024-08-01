<?php

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

$coords = $player->get_coords();


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


    while($row = $res->fetch_object()){


        $target = new Player($row->id);

        $target->get_data();

        $target->get_caracs();


        $dataName = '<a href="infos.php?targetId='. $target->id .'">'. $target->data->name .'</a>';

        $dataName .= '<div class="effects">';

        foreach($target->get_effects() as $e){


            if(in_array($e, EFFECTS_HIDDEN)){

                continue;
            }

            $dataName .= ' <a href="infos.php?targetId='. $target->id .'"><span class="ra '. EFFECTS_RA_FONT[$e] .'"></span></a>';
        }

        $dataName .= '</div>';


        $dataImg = '';


        $actions = $player->get_actions();

        $basics = array(
            "attaquer",
            "courir",
            "entrainement",
            "fouiller",
            "prier",
            "repos"
        );

        function custom_compare($a, $b) {

            global $basics;

            if (in_array($a, $basics)) {
                return -1;
            }

            return 1; // Si l'élément $b n'est pas dans l'ordre, il est considéré plus petit
        }

        // Trier le tableau en utilisant la fonction de comparaison personnalisée
        usort($actions, 'custom_compare');

        foreach($actions as $e){


            $actionJson = json()->decode('actions', $e);


            if(!empty($actionJson->targetType) && $actionJson->targetType == 'none'){

                continue;
            }


            if($player->id == $target->id){

                if(!isset($actionJson->targetType) || $actionJson->targetType != 'self'){

                    continue;
                }
            }
            elseif(!isset($actionJson->targetType) || $actionJson->targetType == 'self'){

                continue;
            }


            $dataImg .= '<button
                class="action"

                data-target-id="'. $target->id .'"
                data-action="'. $e .'"
                >
                <span class="ra '. $actionJson->raFont .'"></span>
                <span class="action-name">'. $actionJson->name .'</span>
                </button><br/>';
        }


        if($target->have_option('isMerchant')){

            $dataImg .= '<a href="merchant.php?targetId='. $target->id .'"><button class="action"><span class="ra ra-ammo-bag"></span> <span class="action-name">Marchander</span></button></a>';
        }


        $raceJson = json()->decode('races', $target->data->race);

        $dataType = $raceJson->name;


        $text = $target->data->text;

        if(
            $target->data->race != $player->data->race
            &&
            $target->data->secretFaction != $player->data->secretFaction
        ){

            $text = '<i>Parle dans une langue qui vous est inconnue.</i>';
        }


        $pvPct = floor($target->get_left('pv') / $target->caracs->pv * 100);


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

        $card = Ui::get_card($data);
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
        <script src="js/observe_destroy.js"></script>
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


// coords
echo '<div id="case-coords"><button OnClick="copyToClipboard(this);">x'. $x .',y'. $y .'</button></div>';


if(!empty($card)){

    echo $card;

    ?>
    <script src="js/observe.js"></script>
    <?php
}


echo Str::minify(ob_get_clean());
