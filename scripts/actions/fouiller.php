<?php

$biomes = array();


$coords = $player->getCoords();

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

$ressources = array();
while($row = $res->fetch_object()){

    if(array_key_exists($biomes[$row->name], $ressources))
        $ressources[$biomes[$row->name]] += $row->max;
    else
        $ressources[$biomes[$row->name]] = $row->max;

}

foreach($ressources as $k=>$v){
    $max = $v;

    $item = Item::get_item_by_name($k);

    $item->get_data();

    $rand = rand(1, $max);

    echo '
    Vous trouvez '. ucfirst($item->data->name) .' x'. $rand .'!

    <div class="action-details">1d'. $max .' = '. $rand .'</div>
    ';

    $item->add_item($player, $rand);
}


$playerXp = 1;
