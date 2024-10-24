<?php

if(isset($_SESSION['nonewturn']) && $_SESSION['nonewturn'] && $_SESSION['playerId'] != $_SESSION['originalPlayerId']){
   //do nothing, admin info is displayed in infos.php
}
else if(!empty($_SESSION['playerId'])){


    $time = time();

    $player = new Player($_SESSION['playerId']);
    $player->get_data();


    if($player->data->nextTurnTime <= $time){


        $player->get_coords();

        // prevent new turn if dead
        if($player->coords->plan != 'limbes'){


            $db = new Db();


            echo '<h1><font color="red">Nouveau Tour</font></h1>';


            echo '<a href="index.php"><img class="box-shadow" src="img/ui/illustrations/sunset.webp" /></a>';

            $player->get_caracs();


            // player turn
            $playerTurn = 86400 - (($player->caracs->spd-10)*3600);



            // NO dlag
            if( !$player->have_option('dlag') ){


                $nextTurnTime = $player->data->nextTurnTime + $playerTurn;
            }

            // DLAG
            else{


                $nextTurnTime = $time + $playerTurn;
            }


            // adjust time
            while( $nextTurnTime <= $time ){

                $nextTurnTime += 86400 - (($player->caracs->spd-10)*3600);
            }

            echo '<br />Prochain Tour le '. date('d/m/Y à H:i', $nextTurnTime) .'.';


            // end effects
            foreach(EFFECTS_HIDDEN as $e){

                $player->end_effect($e);
            }


            // special doubles
            $url = 'img/foregrounds/doubles/'. $player->id .'.png';
            if(file_exists($url)){

                View::delete_double($player);
            }


            echo '
            <table border="1" align="center" class="marbre">';

                // echo '<tr><td></td></tr>';


                // gain xp
                $gainXp = max(1, XP_PER_TURNS - $player->data->rank);

                echo '<tr><td>Xp</td><td align="right">+'. $gainXp .'</td></tr>';

                echo '<tr><td>Pi</td><td align="right">+'. $gainXp .'</td></tr>';


                // update malus
                $recovMalus = min($player->data->malus, MALUS_PER_TURNS);

                echo '<tr><td>Malus</td><td align="right">-'. $recovMalus .'</td></tr>';


                // update fat
                $recovFat = min($player->data->fatigue, FAT_PER_TURNS);

                echo '<tr><td>Fatigue</td><td align="right">-'. $recovFat .'</td></tr>';


                // recover carac
                foreach(CARACS_RECOVER as $k=>$e){


                    $val = $player->caracs->$e;


                    if($k == 'pm' && $player->have_effect('poison_magique')){


                        $player->end_effect('poison_magique');


                        echo '<tr><td>'. CARACS[$k] .'</td><td align="right">+0 (<span class="ra '. EFFECTS_RA_FONT['poison_magique'] .'"></span> Poison Magique)</td></tr>';

                        continue;
                    }

                    elseif($k == 'pv' && $player->have_effect('poison')){


                        $player->end_effect('poison');


                        echo '<tr><td>'. CARACS[$k] .'</td><td align="right">+ 0 (<span class="ra '. EFFECTS_RA_FONT['poison'] .'"></span> Poison)</td></tr>';

                        continue;
                    }

                    elseif($k == 'pv' && $player->have_effect('regeneration')){


                        $player->end_effect('regeneration');


                        $val += $player->caracs->rm;

                        echo '<tr><td>'. CARACS[$k] .'</td><td align="right">+'. $val .' (<span class="ra '. EFFECTS_RA_FONT['regeneration'] .'"></span> Régénération)</td></tr>';

                        continue;
                    }


                    if(!in_array($k, array('ae','a','mvt'))){

                        $player->put_bonus(array($k=>$val));
                    }

                    echo '<tr><td>'. CARACS[$k] .'</td><td align="right">+'. $val .'</td></tr>';
                }


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


                // end effects
                $sql = '
                SELECT COUNT(*) AS n
                FROM players_effects
                WHERE
                endTime <= ?
                AND
                endTime != 0
                AND
                player_id = ?
                ';


                $res = $db->exe($sql, array($time, $player->id));

                $row = $res->fetch_object();

                if($row->n){

                    $player->purge_effects();

                    echo '<tr><td>Effets terminés</td><td align="right">'. $row->n .'</td></tr>';
                }


                echo '</table>';

            echo '<br /><a href="index.php"><button>Jouer</button></a>';


            // anti berserk
            $antiBerserkTime = $player->data->lastActionTime + (0.25 * $playerTurn);


            // update
            $sql = '
            UPDATE
            players
            SET
            nextTurnTime = ?,
            lastActionTime = 0,
            antiBerserkTime = ?,
            xp = xp + ?,
            pi = pi + ?,
            malus = malus - ?,
            fatigue = fatigue - ?
            WHERE
            id = ?
            ';

            $values = array(
                $nextTurnTime,
                $antiBerserkTime,
                $gainXp,
                $gainXp,
                $recovMalus,
                $recovFat,
                $player->id
            );

            $db->exe($sql, $values);

            $player->refresh_data();
            $player->refresh_caracs();
            $player->refresh_invent(); // for Ae


            exit();
        }
    }
}
