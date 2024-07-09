<?php


echo '<h1>Classement des Fortunes</h1>';


$path = 'datas/public/classements/fortunes.html';

if(file_exists($path)){


    echo file_get_contents($path);
}

else{


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
    ';

    $res = $db->exe($sql);


    $playerGold = array();

    while($row = $res->fetch_object()){


        if(!isset($playerGold[$row->player_id])){

            $playerGold[$row->player_id] = $row->n;

            continue;
        }

        $playerGold[$row->player_id] += $row->n;
    }


    foreach($list as $k=>$player){

        if(!empty($playerGold[$player->id])){

            $list[$k]->gold = $playerGold[$player->id];
        }
        else{

            $list[$k]->gold = 0;
        }
    }

    // Fonction de comparaison pour trier par "pr" (Power Rank)
    function compareByGold($a, $b) {
        return $b->gold - $a->gold; // Tri décroissant
    }

    // Trier le tableau en utilisant la fonction de comparaison
    usort($list, 'compareByGold');


    print_players($list);


    $data = ob_get_clean();

    $myfile = fopen($path, "w") or die("Unable to open file!");
    fwrite($myfile, $data);
    fclose($myfile);

    echo $data;
}

