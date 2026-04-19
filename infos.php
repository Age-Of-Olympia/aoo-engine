<?php
use App\Factory\PlayerFactory;
use Classes\Item;
use Classes\Ui;
use Classes\View;
use Classes\Str;

require_once('config.php');


if(!isset($_GET['targetId']) || !is_numeric($_GET['targetId'])){

    exit('error target id');
}

$player = PlayerFactory::active();
$player->get_data();


$target = PlayerFactory::legacy($_GET['targetId']);
$target->get_data();


if(isset($_GET['reputation'])){

    include('scripts/infos/reputation.php');

    exit();
}
if(isset($_GET['rewards'])){

    include('scripts/infos/rewards.php');

    exit();
}


// Phase 4.3d — hydrate an entity alongside the legacy $target for
// read paths. The legacy object stays for anything downstream code
// or the included sub-scripts still rely on (->coords, ->get_caracs,
// Item::get_equiped_list). The entity powers every pure data read.
$targetEntity = PlayerFactory::entity((int) $target->id);
if ($targetEntity === null) {
    exit('error target id');
}

$ui = new Ui($targetEntity->getName());
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

        // Target coords via entity (Phase 4.3d). Shape-compatible with
        // View::get_distance which only reads ->x / ->y / ->plan.
        $conn = \App\Entity\EntityManagerFactory::getEntityManager()->getConnection();
        $targetCoords = $targetEntity->getCoords($conn);

        $distance = View::get_distance($player->coords, $targetCoords);

        if(
            $player->id == $targetEntity->getId()
            ||
            $distance <= $caracsJson->p
        ){

            echo '<div class="infos-effects">';

                $playerEffects = $playerEffectService->getEffectsByPlayerId($targetEntity->getId());

                foreach ($playerEffects as $effect){

                    if(in_array($effect->getName(), EFFECTS_HIDDEN)){

                        continue;
                    }

                    if(
                        $targetEntity->getId() == $player->id
                        ||
                        $targetEntity->getFaction() == $player->data->faction
                        ||
                        $targetEntity->getSecretFaction() == $player->data->secretFaction
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

                    echo '<a href="https://age-of-olympia.net/wiki/doku.php?id=regles:effets#'. $effect->getName() .'"><span class="ra '. EFFECTS_RA_FONT[$effect->getName()] .'"></span><span style="font-size: 88%;">(' . $effect->getValue() . ') '.$endTime .'</span></a><br />';
                }

            echo '</div>';
        }


        echo '<img src="'. $targetEntity->getPortrait() .'" height="330" />';


        echo '
    </td>
    <td valign="top">
        ';


        echo '
        <div id="infos-player">
            ';


            echo '<h1>'. $targetEntity->getName() .'</h1>';


            $raceJson = json()->decode('races', $targetEntity->getRace());

            $pnjText = $targetEntity->getId() < 0 ? ' - PNJ' : '';
            // isInactive is runtime-computed (RealPlayer domain method from !384).
            // Only meaningful for real players.
            $isInactive = ($targetEntity instanceof \App\Entity\RealPlayer)
                && $targetEntity->isInactive(new \App\Service\PlayerService($targetEntity->getId()));
            $inactifText = ($targetEntity->getId() > 0 && $isInactive) ? ' (inactif)' : '';

            echo '<div>'. $raceJson->name . $pnjText . $inactifText .' - <a href="infos.php?targetId='. $targetEntity->getId() .'&reputation">'. Str::get_reput(floor($targetEntity->getPr()/COEFFICIENT_PR)) .'</a> Rang '. $targetEntity->getRank() .'</div>';


            $factionJson = json()->decode('factions', $targetEntity->getFaction());

            echo '<div><a href="faction.php?faction='. $targetEntity->getFaction() .'">'. $factionJson->name .'</a> <span style="font-size: 1.3em" class="ra '. $factionJson->raFont .'"></span> (<i>'.$factionJson->role[$targetEntity->getFactionRole()]->name.'</i>) </div>';

            $targetSecretFaction = $targetEntity->getSecretFaction();
            if (!empty($targetSecretFaction) && ($player->data->secretFaction == $targetSecretFaction || $player->have_option('isAdmin'))) {
                $secretFactionJson = json()->decode('factions', $targetSecretFaction);

                echo '<div class="secret-faction"><a href="faction.php?faction='. $targetSecretFaction .'">'. $secretFactionJson->name .'</a> <span style="font-size: 1.3em" class="ra '. $secretFactionJson->raFont .'"></span> (<i>'.$secretFactionJson->role[$targetEntity->getSecretFactionRole()]->name.'</i>) </div>';
            }

            echo '<img src="'. $targetEntity->getAvatar() .'" />';


            $text = nl2br($targetEntity->getText());

            if($player->id != $targetEntity->getId() && $distance > $caracsJson->p){

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


if($player->coords->plan == $targetCoords->plan && $distance <= $caracsJson->p){


    echo '
    <tr>
        <td colspan="2">
            ';

            echo '
            <table align="center" border="1" class="marbre" cellspacing="0">
                <tr>
                    ';

                    // Item::get_item_list already accepts int id (legacy signature branches on is_numeric),
                    // so pass the entity's id directly instead of the legacy object.
                    $itemList = Item::get_equiped_list($targetEntity->getId());

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

        '. nl2br($targetEntity->getStory()) .'
    </td>
</tr>
</table>
';

echo Str::minify(ob_get_clean());

?>
<script src="js/infos.js?v=20250529"></script>
