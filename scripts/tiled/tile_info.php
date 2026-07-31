<?php
use App\Service\Map\SceneryObjectService;
use Classes\Db;

$db = new Db();

$infos ='';

$db = new Db();
$sql = "select coords_id as coords_id, 'map_tiles' as type, name as name, NULL as params from map_tiles where coords_id = ?
union
/* Une ressource est une entité : elle se décrit comme un bâtiment, par son
   type et son état, et non plus par le signe d'un nombre. */
select p.coords_id as coords_id, 'ressource' as type, CONCAT(p.race, ' #', p.id) as name,
       IF(r.exhausted_at IS NULL, 'état = debout', CONCAT('état = épuisée depuis ', r.exhausted_at)) as params
from players p left join resources r on r.player_id = p.id
where p.player_type = 'resource' and p.coords_id = ?
union
select  coords_id as coords_id, 'map_triggers' as type, name as name, params as params from map_triggers where coords_id = ?
union
select  coords_id as coords_id, 'map_dialogs' as type, name as name, params as params  from map_dialogs where coords_id = ?
union
select  coords_id as coords_id, 'map_elements' as type, name as name, NULL as params from map_elements where coords_id = ?
union
select  coords_id as coords_id, 'map_routes' as type, name as name, NULL as params from map_routes where coords_id = ?
union
select  coords_id as coords_id, 'map_foregrounds' as type, name as name, NULL as params from map_foregrounds where coords_id = ?
union
/* Les plantes sont des entités : elles se décrivent par leur type, comme
   les ressources et les bâtiments. */
select p.coords_id as coords_id, 'plante' as type, CONCAT(p.race, ' #', p.id) as name,
       CONCAT('rend ', COALESCE(NULLIF(TRIM(r.harvest_item), ''), p.race)) as params
from players p left join races r
  on r.name COLLATE utf8mb4_general_ci = p.race COLLATE utf8mb4_general_ci and r.type_kind = 'plant'
where p.player_type = 'plant' and p.coords_id = ?
union
select  p.coords_id as coords_id, 'buildings' as type, CONCAT(p.race, ' #', p.id) as name,
        CONCAT('état = ', b.build_state,
               IF(b.owner_id IS NULL, '', CONCAT(', propriétaire #', b.owner_id)),
               IF(b.faction = '', '', CONCAT(', faction ', b.faction))) as params
from buildings b join players p on p.id = b.player_id where p.coords_id = ?
union
/* L'ombre n'est plus un décor mais une intensité portée par la case
   (coords.shade). Sans cette ligne, une case ombrée n'aurait plus rien à
   dire d'elle-même : on ne saurait ni qu'elle l'est, ni de combien. */
select id as coords_id, 'ombre' as type, CONCAT('niveau ', shade) as name,
       '« −1 niveau » éclaircit d\'un cran' as params
from coords where id = ? and shade > 0";
$res = $db->exe($sql, array($coordsId, $coordsId, $coordsId, $coordsId, $coordsId, $coordsId, $coordsId, $coordsId, $coordsId, $coordsId));


$results = $res->fetch_all(MYSQLI_ASSOC);


/* L'OBJET auquel la case appartient, quand elle porte un décor découpé.
 *
 * Une case ne disait jusqu'ici que ce qu'elle porte. Un animateur qui passe
 * sur le pied d'un géant voyait « map_foregrounds / geant_petrifie-02 » et
 * rien d'autre : ni de quelle figure il s'agit, ni s'il en manque des
 * morceaux. Sur la carte, 38 exemplaires sont incomplets. */
foreach ($results as $row) {
    if ($row['type'] !== 'map_foregrounds') {
        continue;
    }

    $object = (new SceneryObjectService())->inspect((int) $coordsId, (string) $row['name']);

    if ($object === null) {
        continue;
    }

    $objectCells = [];

    if ($object['coords_ids'] !== []) {
        $in = implode(',', array_map('intval', $object['coords_ids']));

        $cells = $db->exe("SELECT x, y FROM coords WHERE id IN ({$in})");

        while ($cell = $cells->fetch_assoc()) {
            $objectCells[] = $cell['x'] . ',' . $cell['y'];
        }
    }

    $results[] = [
        'coords_id' => $coordsId,
        'type'      => 'objet',
        'name'      => $object['family'] . ' — ' . $object['footprint']->width()
                       . '×' . $object['footprint']->height() . ', '
                       . count($object['coords_ids']) . '/' . $object['footprint']->cells()
                       . ' case(s) posée(s)',
        'params'    => $object['missing'] === []
            ? 'figure complète'
            : 'INCOMPLET : ' . count($object['missing']) . ' morceau(x) manquant(s)',
        /* Le morceau posé SUR CETTE CASE : c'est lui que le geste
           « Compléter » renvoie au serveur pour retrouver l'objet. */
        'objectPiece' => $row['name'],
        /* Les cases de l'objet, et celles où il MANQUE un morceau : l'éditeur
           les entoure sur la carte. Voir l'emprise d'un décor était jusqu'ici
           impossible — on ne voyait que des morceaux épars. */
        'objectCells'   => $objectCells,
        'objectMissing' => array_values(array_map(
            fn (array $xy): string => $xy[0] . ',' . $xy[1],
            $object['missing']
        )),
    ];

    break; /* une case n'appartient qu'à un objet */
}

// Convertir en JSON — pas dans $json : c'est le singleton du helper json()
$tileInfoJson = json_encode($results, JSON_PRETTY_PRINT);

echo '<div id="tile-info">'.$tileInfoJson.'</div>';
