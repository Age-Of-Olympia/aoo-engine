<?php
use Classes\Db;

$db = new Db();

$infos ='';

$db = new Db();
$sql = "select coords_id as coords_id, 'map_tiles' as type, name as name, NULL as params from map_tiles where coords_id = ?
union
select  coords_id as coords_id, 'map_walls' as type, name as name, CONCAT('damages = ', damages) as params from map_walls where coords_id = ?
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
select  coords_id as coords_id, 'map_plants' as type, name as name, NULL as params from map_plants where coords_id = ?";
$res = $db->exe($sql, array($coordsId, $coordsId, $coordsId, $coordsId, $coordsId, $coordsId, $coordsId, $coordsId));


$results = $res->fetch_all(MYSQLI_ASSOC);


// Convertir en JSON
$json = json_encode($results, JSON_PRETTY_PRINT);

echo '<div id="tile-info">'.$json.'</div>';
