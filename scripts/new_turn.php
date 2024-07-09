<?php

if(!empty($_SESSION['playerId'])){


    $player = new Player($_SESSION['playerId']);
    $player->get_data();


    if($player->data->nextTurnTime <= time()){


        $player->get_coords();

        // prevent new turn if dead
        if($player->coords->plan != 'limbes'){


            echo '<h1><font color="red">Nouveau Tour</font></h1>';


            echo '
            <table border="1" align="center" class="marbre">
                ';


                $player->get_caracs();


                // player turn
                $playerTurn = 86400 - (($player->caracs->spd-10)*3600);



                // NO dlag
                if( !$player->have_option('dlag') ){


                    $nextTurnTime = $player->data->nextTurnTime + $playerTurn;
                }

                // DLAG
                else{


                    $nextTurnTime = time() + $playerTurn;
                }


                // adjust time
                while( $nextTurnTime <= time() ){

                    $nextTurnTime += 86400 - (($player->caracs->spd-10)*3600);
                }


                // update next turn time
                $sql = '
                UPDATE
                players
                SET
                nextTurnTime = ?
                WHERE
                id = ?
                ';

                $db = new Db();

                $db->exe($sql, array($nextTurnTime, $player->id));

                echo '<tr><td>Prochain Tour</td><td>le '. date('d/m/Y à h:i', $nextTurnTime) .'</td></tr>';

                // gain xp
                $gain = max(1, XP_PER_TURNS - $player->data->rank);

                $player->put_xp($gain);

                echo '<tr><td>Xp</td><td>+ '. $gain .'</td></tr>';

                echo '<tr><td>Pi</td><td>+ '. $gain .'</td></tr>';


                // refresh data
                $player->refresh_data();


                // malus base
                $malus = MALUS_PER_TURNS;

                // update malus
                $player->put_malus(-$malus);

                echo '<tr><td>Malus</td><td>- '. $malus .'</td></tr>';


                // fat base
                $fat = FAT_PER_TURNS;

                // update fat
                $player->put_fat(-$fat);

                echo '<tr><td>Fatigue</td><td>- '. $fat .'</td></tr>';


                // recover carac
                foreach(CARACS_RECOVER as $k=>$e){


                    $val = $player->caracs->$e;


                    if($k == 'pm' && $player->have_effect('poison_magique')){


                        $player->end_effect('poison_magique');


                        echo '<tr><td>'. CARACS[$k] .'</td><td>+ 0 (<span class="ra '. EFFECTS_RA_FONT['poison_magique'] .'"></span> Poison Magique)</td></tr>';

                        continue;
                    }

                    elseif($k == 'pv' && $player->have_effect('poison')){


                        $player->end_effect('poison');


                        echo '<tr><td>'. CARACS[$k] .'</td><td>+ 0 (<span class="ra '. EFFECTS_RA_FONT['poison'] .'"></span> Poison)</td></tr>';

                        continue;
                    }

                    elseif($k == 'pv' && $player->have_effect('regeneration')){


                        $player->end_effect('regeneration');


                        $val += $player->caracs->rm;

                        echo '<tr><td>'. CARACS[$k] .'</td><td>+ '. $val .' (<span class="ra '. EFFECTS_RA_FONT['regeneration'] .'"></span> Régénération)</td></tr>';

                        continue;
                    }


                    $player->put_bonus(array($k=>$val));

                    echo '<tr><td>'. CARACS[$k] .'</td><td>+ '. $val .'</td></tr>';
                }


                // mvt left
                $mvtLeft = $player->get_left('mvt');

                // Kepp fractional mouv for next turn
                $mvtToKeep = ($mvtLeft == 0.5) ? 0.5 : 0;

                echo '<tr><td>Mouvements conservés</td><td>+ '. $mvtToKeep .'</td></tr>';


                // recover Ae, A, Mvt
                $sql = '
                DELETE FROM
                players_bonus
                WHERE
                player_id = ?
                AND
                name IN("ae","a","mvt")
                ';

                $db->exe($sql, $player->id);

                // Restore fractional mouv
                if ($mvtToKeep > 0){

                    $player->put_bonus('mvt', -$mvtToKeep);
                }


                echo '
            </table>
            ';

            echo '<a href="index.php"><button>Jouer</button></a>';

            exit();
        }
    }
}
