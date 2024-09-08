<?php

require_once('config.php');


ob_start();


$player = new Player($_SESSION['playerId']);

$player->get_data();

$caracsJson = $player->get_caracsJson();

$turnJson = $player->get_turnJson();


echo '
<table border="1" align="center" class="marbre" id="caracs-menu">
    ';

    echo '
    <tr>
        ';

        foreach(CARACS as $k=>$e){


            if($k == 'spd'){

                continue;
            }


            echo '<th width="30">'. $e .'</th>';
        }

        echo '<th>Foi</th>';

        echo '
    </tr>
    ';

    echo '
    <tr>
        ';

        foreach(CARACS as $k=>$e){


            if($k == 'spd'){

                continue;
            }


            $left = '';
            if(isset($turnJson->$k)){

                $left = $turnJson->$k .'/';
            }

            echo '<td>'. $left . $caracsJson->$k .'</td>';
        }

        echo '<td>'. $player->data->pf .'</td>';

        echo '
    </tr>
    ';


    $pct = Str::calculate_xp_percentage($player->data->xp, $player->data->rank);


    echo '<tr>';

        echo '<td colspan="'. count(CARACS) - 8 .'">
        <div class="progress-bar">
            <div class="bar" style="width: '. $pct .'%;">&nbsp;</div>
            <div class="text">Xp: '. $player->data->xp .'/'. Str::get_next_xp($player->data->rank) .'</div>
        </div>
        </td>';


        echo '<td colspan="2"><div style="white-space: nowrap;">Pi: '. $player->data->pi .'</div></td>';
        echo '<td colspan="6"><div style="white-space: nowrap;"><a href="upgrades.php"><button>Améliorer mes caractéristiques</button></a></div></td>';


    echo '</tr>';

    echo '<tr>';

        // if($player->data->malus){

            echo '<td colspan="'. count(CARACS) .'">Malus ('. $player->data->malus .'): -'. $player->data->malus .' aux jets de défense.</td>';
        // }

    echo '</tr>';

    echo '<tr>';

        // if($player->data->fatigue >= FAT_EVERY){


            $fatMalus = floor($player->data->fatigue / FAT_EVERY);

            echo '<td colspan="'. count(CARACS) .'">Fatigue ('. $player->data->fatigue .'): -'. $fatMalus .' à tous les jets.</td>';
        // }

    echo '</tr>';


    echo '
</table>
';


echo Str::minify(ob_get_clean());
