<?php

use Classes\Db;


//on recupère les triggers de type "grow" pour lesquels il n'y a pas de plants correspondant
$sql = "
SELECT
t.id AS id,
t.params,
t.coords_id,
c.z AS z
FROM
map_triggers t

INNER JOIN
coords c
ON
t.coords_id = c.id
LEFT JOIN map_plants p 
ON p.coords_id = c.id
WHERE
t.name = 'grow'
and p.id is null;
";

$db = new Db();

$res = $db->exe($sql);

while($row = $res->fetch_object()){

    $plante = $row->params;

    if(!empty(GROW_RATE[$plante])){

        $growTo = GROW_RATE[$plante];
    }

    //chance de 1/growTo
    if(AUTO_GROW || rand(1,$growTo) == 1){

        $values = array(
            'name'=>$plante,
            'coords_id'=>$row->coords_id
        );

        $db->insert('map_plants', $values);
    }
}



echo 'done';
