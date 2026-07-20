<?php
use App\Service\BuildingService;
use Classes\Db;
//delete anything at coords given.
$mapTypes = array('tiles','resources','triggers','elements','dialogs','plants','foregrounds','routes');

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
     WHERE p.coords_id = ? AND b.owner_id IS NULL AND b.faction = '' AND b.build_state = 'built'",
    array($coordsId)
);

$buildingService = new BuildingService();

while($building = $res->fetch_assoc()){

    $buildingService->remove((int) $building['player_id']);
}

