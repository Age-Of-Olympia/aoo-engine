<?php

use App\Service\ResourceService;
use Classes\Db;

$sql = '
SELECT 
map_resources.id AS id,
map_resources.name as name,
map_resources.damages as damages,
coords.plan as plan
FROM `map_resources`
INNER JOIN
coords
ON
coords_id = coords.id
WHERE 
map_resources.damages =-2
';

$db = new Db();

$res = $db->exe($sql);
$resourcesIdArray = array();

while ($row = $res->fetch_object()) {

    /* Un plan par passage, pas une requête par ligne : le cron balaie
       toutes les ressources épuisées du monde. */
    $yieldsByPlan[$row->plan] ??= (new App\Service\Map\HarvestCatalogService())->yieldsFor((string) $row->plan);

    if ($yieldsByPlan[$row->plan] === []) {
        echo 'Aucun rendement pour ' . $row->plan . "\n";
        continue;
    }

    ResourceService::createRegrowArray($yieldsByPlan[$row->plan], $resourcesIdArray, $row);
}
ResourceService::regrowResources($resourcesIdArray);

echo 'done';
