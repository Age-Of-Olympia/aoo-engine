<?php

use App\Service\ResourceService;

$sql = '
SELECT 
map_walls.id AS id,
map_walls.name as name,
map_walls.damages as damages,
coords.plan as plan
FROM `map_walls`
INNER JOIN
coords
ON
coords_id = coords.id;
';

$db = new Db();

$res = $db->exe($sql);
$resourcesIdArray[] = array();

while($row = $res->fetch_object()){
    
    $planJson = json()->decode('plans', $row->plan);
    foreach($planJson->biomes as $e){
        if($e->wall = $row->name){
            if($e->regrow < rand(1, 100))
                $resourcesIdArray[] = $row->id;
        }
    }
}

ResourceService::regrowResources($resourcesIdArray);

echo 'done';
