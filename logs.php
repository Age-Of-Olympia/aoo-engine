<?php

require_once('config.php');

$ui = new Ui('Évènements');

$player = new Player($_SESSION['playerId']);

$player->get_data();

$player->get_coords();


echo '<div><a href="index.php"><button><span class="ra ra-sideswipe"></span> Retour</button></a><a href="logs.php"><button>Du lieu</button></a><a href="logs.php?self"><button>Du personnage</button></a><a href="logs.php?quests"><button>Quêtes</button></a></div>';


if(isset($_GET['quests'])){


    include('scripts/logs/quests.php');

    exit();
}


echo '
<table class="box-shadow marbre" border="1" align="center">';

    foreach(Log::get($player->coords->plan) as $e){


        if(!isset($_GET['self']) && $e->player_id == $_SESSION['playerId']){


            continue;
        }


        $player = new Player($e->player_id);
        $player->get_data();


        if($e->player_id != $e->target_id){


            $target = new Player($e->target_id);
            $target->get_data();
        }


        echo '
        <tr>
            <td
                align="left"
                valign="top"
                >
                    '. $e->text .'
                </td>
            <td>
                '. $player->data->name .'<br />
                (mat.'. $player->id .')
            </td>
            ';

            if(!empty($target)){

                echo '
                <td>
                    '. $target->data->name .'<br />
                    (mat.'. $target->id .')
                </td>
                ';
            }
            else{

                echo '
                <td></td>
                ';
            }

            echo '
            <td>
                '. date('d/m/Y', $e->time) .'<br />
                à '. date('H:i', $e->time) .'
            </td>
        </tr>
        ';
    }

echo '
</table>
';
