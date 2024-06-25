<?php

$biomes = array();


$coords = $player->get_coords();

$planJson = json()->decode('plans', $coords->plan);


if(!empty($planJson->biomes)){


    foreach($planJson->biomes as $e){


        $biomes[$e->wall] = $e->ressource;
    }
}

$coordsArround = View::get_coords_id_arround($coords, $p=1);


$sql = '
SELECT
COUNT(*) AS max,
name
FROM
map_walls
WHERE
coords_id IN('. implode(',', $coordsArround) .')
AND
name IN ("'. implode('","', array_keys($biomes)) .'")
GROUP BY
name
';

$db = new Db();

$res = $db->exe($sql);


if(!$res->num_rows){

    exit("Il n'y a rien par ici.");
}

while($row = $res->fetch_object()){


    $max = $row->max;

    $item = Item::get_item_by_name($biomes[$row->name]);

    $item->get_data();

    $rand = rand(1, $max);

    echo '
    Vous trouvez '. ucfirst($item->data->name) .' x'. $rand .'!

    <div class="action-details">1d'. $max .' = '. $rand .'</div>
    ';

    $item->add_item($player, $max);
}


