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
coords_id = coords.id
WHERE 
map_walls.damages =-2
ORDER BY coords.plan , map_walls.name;
';

$db = new Db();

$res = $db->exe($sql);
$resourcesIdArray = array();

$lastPlan = '';
$lastBiome= '';
$biome = null;
$planJson = null;
while ($row = $res->fetch_object()) {
    if ($lastPlan != $row->plan) {
        $planJson = json()->decode('plans', $row->plan);
        $lastPlan = $row->plan;
    }
    if ($lastBiome != $row->name) {
        $lastBiome = $row->name;
        $biome = null;
        foreach ($planJson->biomes as $e) {
            if ($e->wall == $row->name) {
                $biome = $e;
                break;
            }
        }
        //suposement c'est des ressource car damage = -2 
        if ($biome === null) {
            echo 'Biome not found for ressource: ' . $row->name . ' in plan: ' . $row->plan . PHP_EOL;
        }
    }
    if ($biome === null) {
        continue;
    }
    $resourcesIdArray = ResourceService::createRegrowArray($biome, $resourcesIdArray, $row);
}
ResourceService::regrowResources($resourcesIdArray);

echo 'done';
