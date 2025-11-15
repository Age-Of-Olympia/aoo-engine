<?php
use Classes\Player;
use Classes\Item;
use Classes\Ui;
use Classes\View;
use Classes\Str;
use App\Tutorial\TutorialHelper;

require_once('config.php');


if(!isset($_GET['targetId']) || !is_numeric($_GET['targetId'])){

    exit('error target id');
}

// Get active player ID (tutorial player if in tutorial mode, otherwise main player)
$playerId = TutorialHelper::getActivePlayerId();

$player = new Player($playerId);
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
Use App\Service\PlayerEffectService;
$playerEffectService = new PlayerEffectService();

ob_start();

echo '<div><a href="index.php"><button><span class="ra ra-sideswipe"></span> Retour</button></a></div>';


echo '
<table border="1" align="center" cellspacing="0" class="marbre" style="width: 100%;">
<tr>
    <td width="210" class="infos-portrait" valign="top">
        ';


        $caracsJson = $player->get_caracsJson();

        $player->getCoords();
        $target->getCoords();

        $distance = View::get_distance($player->coords, $target->coords);

        if(
            $player->id == $target->id
            ||
            $distance <= $caracsJson->p
        ){

            echo '<div class="infos-effects">';

                $playerEffects = $playerEffectService->getEffectsByPlayerId($target->id);

                foreach ($playerEffects as $effect){

                    if(in_array($effect->getName(), EFFECTS_HIDDEN)){

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

                        if(time() < $effect->getEndTime()){

                            $endTime = Str::convert_time($effect->getEndTime() - time());
                        }


                        if(!$effect->getEndTime()){

                            $endTime = '∞';
                        }
                    }
                    else{

                        $endTime = '';
                    }

                    echo '<a href="https://age-of-olympia.net/wiki/doku.php?id=regles:effets#'. $effect->getName() .'"><span class="ra '. EFFECTS_RA_FONT[$effect->getName()] .'"></span><span style="font-size: 88%;">'. $endTime .'</span></a><br />';
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

            $pnjText = $target->id<0 ? ' - PNJ' : '';

            echo '<div>'. $raceJson->name . $pnjText .' - <a href="infos.php?targetId='. $target->id .'&reputation">'. Str::get_reput(floor($target->data->pr/COEFFICIENT_PR)) .'</a> Rang '. $target->data->rank .'</div>';


            $factionJson = json()->decode('factions', $target->data->faction);

            echo '<div><a href="faction.php?faction='. $target->data->faction .'">'. $factionJson->name .'</a> <span style="font-size: 1.3em" class="ra '. $factionJson->raFont .'"></span> (<i>'.$factionJson->role[$target->data->factionRole]->name.'</i>) </div>';

            if (isset($target->data->secretFaction) && !empty($target->data->secretFaction) && ($player->data->secretFaction == $target->data->secretFaction || $player->have_option('isAdmin'))) {
                $secretFactionJson = json()->decode('factions', $target->data->secretFaction);

                echo '<div class="secret-faction"><a href="faction.php?faction='. $target->data->secretFaction .'">'. $secretFactionJson->name .'</a> <span style="font-size: 1.3em" class="ra '. $secretFactionJson->raFont .'"></span> (<i>'.$secretFactionJson->role[$target->data->secretFactionRole]->name.'</i>) </div>';
            }
            
            echo '<img src="'. $target->data->avatar .'" />';


            $text = nl2br($target->data->text);

            if($player->id != $target->id && $distance > $caracsJson->p){

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
            <p class="preview-caracs"></p>
        </div>
        ';


        echo '
    </td>
</tr>
';


if($player->coords->plan == $target->coords->plan && $distance <= $caracsJson->p){


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
                        $caracs = implode(', ',Item::get_item_carac($item->data));

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
                                data-caracs="' . htmlspecialchars($caracs, ENT_QUOTES) .'"
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
<script src="js/infos.js?v=20250529"></script>
