<?php
use App\Service\BuildingService;
use Classes\Db;

//delete the case given on table given
$db = new Db();

if($type == 'buildings'){

    /* Une entité bâtiment se démonte par le service (satellite, logs,
       caches SVG) — jamais par un DELETE brut. Seul le décor (sans
       propriétaire ni faction, état built) se retire d'ici : le reste
       relève de admin → Bâtiments. */
    $res = $db->exe(
        "SELECT b.player_id, b.owner_id, b.faction, b.build_state
         FROM buildings b JOIN players p ON p.id = b.player_id
         WHERE p.coords_id = ?",
        array($coordsId)
    );

    $buildingService = new BuildingService();

    while($building = $res->fetch_assoc()){

        if($building['owner_id'] !== null || $building['faction'] !== '' || $building['build_state'] !== 'built'){

            echo 'bâtiment #'. $building['player_id'] .' protégé (propriétaire/faction/état) — passer par admin → Bâtiments';
            continue;
        }

        $buildingService->remove((int) $building['player_id']);
    }

} elseif($type === 'ombre'){

    /* Retirer UN cran d'ombre, pas toute l'ombre.
     *
     * L'assombrissement se pose cran par cran — on re-clique pour foncer —,
     * il doit donc se retirer de la meme facon : celui qui est alle trop
     * loin revient d'un pas, sans tout reprendre. Le pinceau de gomme sur
     * les decors, lui, remet la case a zero d'un coup. */
    (new Db())->exe('UPDATE coords SET shade = GREATEST(shade - 1, 0) WHERE id = ?', array($coordsId));

} else {

    /* Le nom de table vient du POST : liste blanche stricte */
    if(!in_array($type, array('map_tiles','map_resources','map_triggers','map_dialogs','map_elements','map_routes','map_foregrounds','map_plants'))){

        exit('error type');
    }

    /* Un décor multi-cases se retire EN ENTIER.
     *
     * Effacer case par case laissait des orphelins derrière : retirer la tête
     * d'un géant et lui laisser les pieds n'a jamais été un geste voulu, et
     * c'est ainsi qu'une trentaine de fragments incomplets sont arrivés sur
     * la carte. Le service borne l'objet à UN exemplaire — deux décors collés
     * sont adjacents, et les confondre ferait disparaître le voisin. */
    if ($type === 'map_foregrounds') {
        $names = $db->exe('SELECT name FROM map_foregrounds WHERE coords_id = ?', array($coordsId));
        $toErase = array((int) $coordsId);
        $objects = new \App\Service\Map\SceneryObjectService();

        while ($row = $names->fetch_object()) {
            $toErase = array_merge($toErase, $objects->objectCellsAt((int) $coordsId, $row->name));
        }

        /* Une seule requête : la boucle de DELETE faisait un aller-retour par
           case, soit quatorze pour un fort. */
        $ids = implode(',', array_map('intval', array_unique($toErase)));
        $db->exe("DELETE FROM map_foregrounds WHERE coords_id IN ({$ids})");
    } else {
        $db->exe('DELETE FROM ' . $type . ' WHERE coords_id = ?', $coordsId);
    }

    /* Effacer le décor d'une case efface aussi son assombrissement : c'est ce
       que faisait le DELETE quand l'ombre était une ligne de décor. */
    if ($type === 'map_foregrounds') {
        $db->exe('UPDATE coords SET shade = 0 WHERE id = ?', array($coordsId));
    }
}
