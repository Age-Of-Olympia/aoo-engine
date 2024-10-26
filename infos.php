<?php


require_once('config.php');


if(!isset($_GET['targetId']) || !is_numeric($_GET['targetId'])){

    exit('error target id');
}


$player = new Player($_SESSION['playerId']);
$player->get_data();


$target = new Player($_GET['targetId']);
$target->get_data();


if(isset($_GET['reputation'])){

    include('scripts/infos/reputation.php');

    exit();
}
if(isset($_GET['rewards'])){

    include('scripts/infos/rewards.php');

    exit();
}


$ui = new Ui($target->data->name);

ob_start();

echo '<div><a href="index.php"><button><span class="ra ra-sideswipe"></span> Retour</button></a></div>';


echo '
<table border="1" align="center" cellspacing="0" class="marbre" style="width: 100%;">
<tr>
    <td width="210" class="infos-portrait" valign="top">
        ';


        $caracsJson = $player->get_caracsJson();

        $player->get_coords();
        $target->get_coords();

        $distance = View::get_distance($player->coords, $target->coords);

        if(
            ($player->id == $target->id || $distance)
            &&
            $distance <= $caracsJson->p
        ){


            $sql = '
            SELECT
            name, endTime
            FROM
            players_effects
            WHERE
            player_id = ?
            ';

            $db = new Db();

            $res = $db->exe($sql, $target->id);

            echo '<div class="infos-effects">';

                while($row = $res->fetch_object()){


                    if(in_array($row->name, EFFECTS_HIDDEN)){

                        continue;
                    }


                    if(
                        $target->id == $player->id
                        ||
                        $target->data->faction == $player->data->faction
                        ||
                        $target->data->secretFaction == $player->data->secretFaction
                    ){

                        $endTime = '(reposez-vous)';

                        if(time() < $row->endTime){

                            $endTime = Str::convert_time($row->endTime - time());
                        }


                        if(!$row->endTime){

                            $endTime = '∞';
                        }
                    }
                    else{

                        $endTime = '';
                    }

                    echo '<a href="https://age-of-olympia.net/wiki/doku.php?id=regles:effets#'. $row->name .'"><span class="ra '. EFFECTS_RA_FONT[$row->name] .'"></span><span style="font-size: 88%;">'. $endTime .'</span></a><br />';
                }

            echo '</div>';
        }


        echo '<img src="'. $target->data->portrait .'" height="330" />';


        echo '
    </td>
    <td valign="top">
        ';


        echo '
        <div id="infos-player">
            ';


            echo '<h1>'. $target->data->name .'</h1>';


            $raceJson = json()->decode('races', $target->data->race);


            echo '<div>'. $raceJson->name .' <a href="infos.php?targetId='. $target->id .'&reputation">'. Str::get_reput($target->data->pr) .'</a> Rang '. $target->data->rank .'</div>';


            $factionJson = json()->decode('factions', $target->data->faction);

            echo '<div><a href="faction.php?faction='. $target->data->faction .'">'. $factionJson->name .'</a> <span style="font-size: 1.3em" class="ra '. $factionJson->raFont .'"></span> (<i>'.$factionJson->role[$target->data->factionRole]->name.'</i>) </div>';

            if (isset($target->data->secretFaction) && !empty($target->data->secretFaction) && ($player->data->secretFaction == $target->data->secretFaction || $player->have_option('isAdmin'))) {
                $secretFactionJson = json()->decode('factions', $target->data->secretFaction);

                echo '<div class="secret-faction"><a href="faction.php?faction='. $target->data->secretFaction .'">'. $secretFactionJson->name .'</a> <span style="font-size: 1.3em" class="ra '. $secretFactionJson->raFont .'"></span> (<i>'.$secretFactionJson->role[$target->data->secretFactionRole]->name.'</i>) </div>';
            }
            
            echo '<img src="'. $target->data->avatar .'" />';


            $text = nl2br($target->data->text);

            if(
                ($player->id != $target->id && !$distance)
                ||
                $distance > $caracsJson->p
            ){

                $text = '<i>Ce personnage est trop éloigné pour l\'entendre parler.</i>';
            }


            echo '<div class="infos-text">'. $text .'</div>';

            echo '
        </div>
        ';


        echo '
        <div id="preview-item" style="display: none;">
            <h1></h1>
            <div class="preview-img">
                <img src="img/ui/fillers/150.png" />
            </div>
            <p class="preview-text"></p>
        </div>
        ';


        echo '
    </td>
</tr>
';


if($player->coords->plan == $target->coords->plan){


    echo '
    <tr>
        <td colspan="2">
            ';

            echo '
            <table align="center" border="1" class="marbre" cellspacing="0">
                <tr>
                    ';

                    $itemList = Item::get_equiped_list($target);

                    foreach($itemList as $row){


                        $item = new Item($row->id, $row);
                        $item->get_data();


                        $itemName = Item::get_formatted_name(ucfirst($item->data->name), $row);

                        $type = (!empty($item->data->type)) ? $item->data->type : '';


                            echo '<td><img
                                class="infos-item"
                                data-id="'. $row->id .'"
                                data-name="'. $itemName .'"
                                data-n="'. $row->n .'"
                                data-text="'. $item->data->text .'"
                                data-price="'. $item->data->price .'"
                                data-type="'. $type .'"
                                data-img="img/items/'. $item->row->name .'.webp"
                                src="'. $item->data->mini .'" /></td>';
                    }

                    echo '
                </tr>
            </table>
            ';

            echo '
        </td>
    </tr>
    ';
}

echo '
<tr>
    <td colspan="2" align="left">

        <h2>Histoire:</h2>

        '. nl2br($target->data->story) .'
    </td>
</tr>
</table>
';

echo Str::minify(ob_get_clean());

?>
<script src="js/infos.js"></script>
