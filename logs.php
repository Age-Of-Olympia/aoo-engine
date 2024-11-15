<?php
$debug=false;
if ($debug) {
    $time_start = microtime(true);
}
require_once('config.php');

$ui = new Ui('Évènements');

$player = new Player($_SESSION['playerId']);

$player->get_data();

$logAge=ONE_DAY;

if($player->have_option('isAdmin')) {
    $logAge = THREE_DAYS;
}

ob_start();


echo '<div><a href="index.php"><button><span class="ra ra-sideswipe"></span> Retour</button></a><a href="logs.php"><button>Du lieu</button></a><a href="logs.php?self"><button>Du personnage</button></a><a href="logs.php?quests"><button>Quêtes</button></a></div>';


if(isset($_GET['quests'])){


    include('scripts/logs/quests.php');

    echo Str::minify(ob_get_clean());

    exit();
}


$player->get_coords();


echo '<p>Voici les évènements qui se sont déroulés récemment<br /> du point de vue de votre personnage (max. 24h)</p>';

echo '
<table class="box-shadow marbre" border="1" align="center" style="width: 100%;">';

    echo '
    <tr>
        <th>Évènements</th>
        <th>De</th>
        <th>Avec</th>
        <th>Date</th>
        <th>Plan</th>
    </tr>
    ';

    foreach(Log::get($player,$logAge) as $e){
        if(
            isset($_GET['self'])
            &&
            $e->player_id != $_SESSION['playerId']
            &&
            $e->target_id != $_SESSION['playerId']
        ){
            continue;
        }


        $player = new Player($e->player_id);
        $player->get_data();
        $playerRaceJson = json()->decode('races', $player->data->race);


        $hiddenText = '';

        if($e->player_id == $player->id && $e->hiddenText != ''){

            $hiddenText = '<div class="logs-hidden" style="background: black; color: gray; padding: 5px; font-size: 88%;">'. str_replace('<style>.action-details{display: none;}</style>', '', $e->hiddenText) .'</div>';
        }


        $target = false;

        if($e->player_id != $e->target_id){


            $target = new Player($e->target_id);
            $target->get_data();
            $targetRaceJson = json()->decode('races', $target->data->race);
        }


        echo '
        <tr>
            <td
                align="left"
                valign="top"
                >
                    <span class="log-'. $e->type .'">'. $e->text .'</span><br />
                    '. $hiddenText .'
                </td>
            <td class="log-td" style="background-color: '. $playerRaceJson->bgColor .'; color: '. $playerRaceJson->color .';">
                '. $player->data->name .'<br />
                (<a style="color: '. $playerRaceJson->color .';" href="infos.php?targetId='. $player->id .'">mat.'. $player->id .'</a>)
            </td>
            ';

            if(!empty($target)){

                echo '
                <td  class="log-td" style="background-color: '. $targetRaceJson->bgColor .'; color: '. $targetRaceJson->color .';">
                    '. $target->data->name .'<br />
                    (<a style="color: '. $targetRaceJson->color .';" href="infos.php?targetId='. $target->id .'">mat.'. $target->id .'</a>)
                </td>
                ';
            }
            else{

                echo '
                <td class="log-td"></td>
                ';
            }


            $date = date('d/m/Y', $e->time);

            if($date == date('d/m/Y', time())){

                $date = 'Aujourd\'hui';
            }
            elseif($date == date('d/m/Y', time()-86400)){

                $date = 'Hier';
            }


            echo '
            <td class="log-td">
                '. $date .'<br />
                à '. date('H:i', $e->time) .'
            </td>
        ';

        $planJson = json()->decode('plans', $e->plan);

        
        if (is_bool($planJson)) {
            $plan = '?';
        } else {
            $plan = $planJson->name;
        }

        echo '
            <td class="log-td">
                '. $plan .'
            </td>
        </tr>
        ';
    }

echo '
</table>
';

if ($debug) {
    $time_end = microtime(true);
    $execution_time = ($time_end - $time_start);
    echo '<b>Total Execution Time:</b> '.$execution_time.' Mins';
}

echo Str::minify(ob_get_clean());
