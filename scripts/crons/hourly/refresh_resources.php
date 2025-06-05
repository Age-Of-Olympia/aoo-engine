<?php

use App\Service\ResourceService;
use Classes\Db;

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
coords_id = coords.id
WHERE 
map_walls.damages =-2
';

$db = new Db();

$res = $db->exe($sql);
$resourcesIdArray = array();

while ($row = $res->fetch_object()) {

    $planJson = json()->decode('plans', $row->plan);
    if (!isset($planJson)) {
        echo 'Error: planJson not found ' . $row->plan . "\n";
        continue;
    }
    ResourceService::createRegrowArray($planJson, $resourcesIdArray, $row);
}
ResourceService::regrowResources($resourcesIdArray);

echo 'done';
