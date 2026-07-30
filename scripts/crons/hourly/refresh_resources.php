<?php

use App\Service\Map\HarvestCatalogService;
use App\Service\ResourceService;
use Classes\Db;

/* Les ressources épuisées repoussent — depuis leur satellite, seul endroit
   qui porte encore leur état depuis qu'elles sont des entités. Le cron lisait
   `map_resources.damages = -2` : la table vidée, rien ne repoussait plus. */
$sql = '
SELECT
p.id AS id,
p.race AS name,
ec.plan AS plan
FROM players AS p
INNER JOIN resources AS r ON r.player_id = p.id
INNER JOIN entity_cells AS ec ON ec.player_id = p.id
WHERE p.player_type = "resource"
AND r.exhausted_at IS NOT NULL
';

$db = new Db();

$res = $db->exe($sql);
$resourcesIdArray = array();
$yieldsByPlan = array();

while ($row = $res->fetch_object()) {

    /* Un plan par passage, pas une requête par ligne : le cron balaie
       toutes les ressources épuisées du monde. */
    $yieldsByPlan[$row->plan] ??= (new HarvestCatalogService())->yieldsFor((string) $row->plan);

    if ($yieldsByPlan[$row->plan] === []) {
        echo 'Aucun rendement pour ' . $row->plan . "\n";
        continue;
    }

    ResourceService::createRegrowArray($yieldsByPlan[$row->plan], $resourcesIdArray, $row);
}
ResourceService::regrowResources($resourcesIdArray);

echo 'done';
