<?php
use App\Service\BuildingService;
use App\Service\Map\ResourceObjectService;
use Classes\Db;
//delete anything at coords given.
/* « resources » a quitté cette liste : la table ne reçoit plus rien, ses
   objets sont des entités et se retirent plus bas, avec le décor. */
/* « plants » a quitté la liste avec « resources » : ce sont des entités. */
$mapTypes = array('tiles','triggers','elements','dialogs','foregrounds','routes');

$db = new Db();

foreach ($mapTypes as $type){
    $sql = 'DELETE FROM map_'.$type.' WHERE coords_id =?';

    $db->exe($sql, $coordsId);

}

/* La gomme retire aussi le DÉCOR bâtiment (entité sans propriétaire ni
   faction, état built) — via le service, jamais en DELETE brut. Les
   bâtiments de joueurs/factions et les chantiers restent. */
$res = $db->exe(
    "SELECT b.player_id FROM buildings b JOIN players p ON p.id = b.player_id
     WHERE p.coords_id = ? AND p.owner_id IS NULL AND p.faction = '' AND b.build_state = 'built'",
    array($coordsId)
);

$buildingService = new BuildingService();

while($building = $res->fetch_assoc()){

    $buildingService->remove((int) $building['player_id']);
}

/* Les ressources aussi : elles n'ont ni propriétaire ni chantier à protéger,
   la gomme les emporte comme elle emportait leurs lignes. */
$resources = new ResourceObjectService();
$resources->removeEntities($resources->idsOn((int) $coordsId));

/* Et les plantes, pour la même raison. */
$resources->removeEntities(array_map('intval', $db->exe(
    "SELECT id FROM players WHERE player_type = 'plant' AND coords_id = ?",
    array($coordsId)
)->fetch_all(MYSQLI_COLUMN) ?: []));

