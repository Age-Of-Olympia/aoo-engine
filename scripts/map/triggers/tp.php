<?php


$coordsTbl = explode(',', $params);

$coords = (object) array();

$coords->x = (is_numeric($coordsTbl[0])) ? $coordsTbl[0] : $goCoords->x;
$coords->y = (is_numeric($coordsTbl[1])) ? $coordsTbl[1] : $goCoords->y;
$coords->z = (is_numeric($coordsTbl[2])) ? $coordsTbl[2] : $goCoords->z;
$coords->plan = ($coordsTbl[3] != 'plan') ? $coordsTbl[3] : $goCoords->plan;

$goCoords = $coords;


$p = 1;

$coordsArround = View::get_coords_arround($coords, $p);


$sql = '
SELECT
x, y
FROM
coords AS c
INNER JOIN
players AS p
ON
p.coords_id = c.id
WHERE
z = ?
AND
plan = ?

UNION

SELECT
x, y
FROM
coords AS c
INNER JOIN
map_walls AS p
ON
p.coords_id = c.id
WHERE
z = ?
AND
plan = ?

UNION

SELECT
x, y
FROM
coords AS c
INNER JOIN
map_triggers AS p
ON
p.coords_id = c.id
WHERE
name = "forbidden"
AND
z = ?
AND
plan = ?
';

$res = $db->exe($sql, array($coords->z, $coords->plan, $coords->z, $coords->plan, $coords->z, $coords->plan));

$coordsTaken = array($coords->x .','. $coords->y);

while($row = $res->fetch_object()){


    $coordsTaken[] = $row->x .','. $row->y;
}


$coordsArround = array_diff($coordsArround, $coordsTaken);


while(true){


    if(!count($coordsArround)){

        $p++;

        $coordsArround = View::get_coords_arround($coords, $p);

        $coordsArround = array_diff($coordsArround, $coordsTaken);
    }


    shuffle($coordsArround);

    $randCoords = array_pop($coordsArround);


    $goCoords->x = explode(',', $randCoords)[0];
    $goCoords->y = explode(',', $randCoords)[1];

    break;
}


$coordsId = View::get_coords_id($goCoords);
