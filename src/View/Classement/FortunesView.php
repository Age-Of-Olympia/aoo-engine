<?php

namespace App\View\Classement;

use Classes\Db;

class FortunesView
{
    public static function renderFortunes($playerList): void
    {

        echo '<h1>Classement des Fortunes</h1>';


        $path = 'datas/public/classements/fortunes.html';

        if (file_exists($path) && CACHED_CLASSEMENTS) {


            echo file_get_contents($path);
        } else {


            ob_start();


            $db = new Db();

            $sql = '
    SELECT
    player_id, item_id, n
    FROM players_items
    WHERE
    item_id = 1

    UNION

    SELECT
    player_id, item_id, n
    FROM
    players_items_bank
    WHERE
    item_id = 1
    AND
    player_id > 0
    ';

            $res = $db->exe($sql);


            $playerGold = array();

            while ($row = $res->fetch_object()) {


                if (!isset($playerGold[$row->player_id])) {

                    $playerGold[$row->player_id] = $row->n;

                    continue;
                }

                $playerGold[$row->player_id] += $row->n;
            }


            foreach ($playerList as $k => $player) {

                if (!empty($playerGold[$player->id])) {

                    $playerList[$k]->gold = $playerGold[$player->id];
                } else {

                    $playerList[$k]->gold = 0;
                }
            }

            // Trier le tableau en utilisant la fonction de comparaison
            usort($playerList, self::compareByGold(...));


            print_players($playerList);


            $data = ob_get_clean();

            $myfile = fopen($path, "w") or die("Unable to open file!");
            fwrite($myfile, $data);
            fclose($myfile);

            echo $data;
        }
    }

    private static function compareByGold($a, $b)
    {
        return $b->gold - $a->gold; // Tri décroissant
    }
}
