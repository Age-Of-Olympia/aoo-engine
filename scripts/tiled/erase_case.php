<?php
use App\Service\BuildingService;
use App\Service\Map\SceneryObjectService;
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

        /* SHELVE, do not delete. `remove()` drops the `players` row, which
         * forces `players_logs` to be cleared on both sides of its foreign
         * key — so taking a building off the map erased the history of
         * everything done to it. The editor removes from the board; it does
         * not rewrite the past. */
        $buildingService->vanish((int) $building['player_id']);
    }

} elseif ($type === 'ombre') {
    /* Retirer UN cran d'ombre, pas toute l'ombre.
     *
     * L'assombrissement se pose cran par cran — on re-clique pour foncer —,
     * il doit donc se retirer de la même façon : celui qui est allé trop loin
     * revient d'un pas, sans tout reprendre. Le pinceau de gomme sur les
     * décors, lui, remet la case à zéro d'un coup. */
    (new Db())->exe('UPDATE coords SET shade = GREATEST(shade - 1, 0) WHERE id = ?', [$coordsId]);
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
        $names = $db->exe('SELECT name FROM map_foregrounds WHERE coords_id = ?', [$coordsId]);
        $toErase = [(int) $coordsId];
        $objects = new SceneryObjectService();

        while ($row = $names->fetch_object()) {
            $toErase = array_merge($toErase, $objects->objectCellsAt((int) $coordsId, $row->name));
        }

        /* Une seule requête : la boucle de DELETE faisait un aller-retour par
         * case, soit quatorze pour un fort. */
        $cells = array_map('intval', array_unique($toErase));
        $ids = implode(',', $cells);
        $db->exe("DELETE FROM map_foregrounds WHERE coords_id IN ({$ids})");

        /* The entity goes with its pieces: left behind, a decor drawn
         * nowhere would still block. */
        $objects->removeEntitiesOn($cells);
    } else {
        $db->exe('DELETE FROM ' . $type . ' WHERE coords_id = ?', $coordsId);
    }

    /* Effacer le décor d'une case efface aussi son assombrissement : c'est ce
     * que faisait le DELETE quand l'ombre était une ligne de décor. */
    if ($type === 'map_foregrounds') {
        $db->exe('UPDATE coords SET shade = 0 WHERE id = ?', [$coordsId]);
    }
}

/* Ceux qui regardaient la case revoient la carte. Le damier est en cache par
 * joueur : sans ça, un joueur immobile gardait sous les yeux ce qui vient
 * d'être retiré. */
\Classes\View::refresh_players_svg_at((int) $coordsId);
