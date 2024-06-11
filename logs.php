<?php

require_once('config.php');

$ui = new Ui('Évènements');

$player = new Player($_SESSION['playerId']);

$player->get_coords();


echo '<div><a href="index.php"><button>Retour</button></a><button>Du lieu</button><button>Du personnage</button></div>';

echo '
<table class="box-shadow marbre" border="1" align="center">';

    foreach(Log::get($player->coords->plan) as $e){


        $playerJson = json()->decode('players', $e->player_id);

        if($e->player_id != $e->target_id){

            $targetJson = json()->decode('players', $e->target_id);
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
                '. $playerJson->name .'<br />
                (mat.'. $e->player_id .')
            </td>
            ';

            if(!empty($targetJson)){

                echo '
                <td>
                    '. $targetJson->name .'<br />
                    (mat.'. $e->target_id .')
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
