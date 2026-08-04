<?php
use App\Service\BuildingService;
use App\Service\Map\ResourceObjectService;
use App\Service\Map\SceneryObjectService;
use Classes\Db;

//delete the case given on table given
$db = new Db();

if($type == 'buildings'){

    /* Une entité bâtiment se démonte par le service (satellite, logs,
       caches SVG) — jamais par un DELETE brut. Seul le décor (sans
       propriétaire ni faction, état built) se retire d'ici : le reste
       relève de admin → Bâtiments. */
    $buildingService = new BuildingService();

    /* The animator may OVERRIDE the protection knowingly: the first click
       refuses and says why, the confirm re-posts with force=1. */
    $force = !empty($_POST['force']);

    /* Par l'EMPRISE : un édifice 2×2 tient quatre cases, la gomme le
       trouve depuis chacune — la recherche vit chez le service. */
    foreach($buildingService->holdingCell((int) $coordsId) as $building){

        if(!$force
            && ($building['owner_id'] !== null || $building['faction'] !== '' || $building['build_state'] !== 'built')){

            /* The refusal must REACH the editor: the delete handler shows
               any .erase-notice, a bare echo drowned in the page. */
            echo '<div class="erase-notice">bâtiment #'. $building['player_id']
                .' protégé (propriétaire/faction/état)</div>';
            continue;
        }

        /* SHELVE, do not delete. `remove()` drops the `players` row, which
         * forces `players_logs` to be cleared on both sides of its foreign
         * key — so taking a building off the map erased the history of
         * everything done to it. The editor removes from the board; it does
         * not rewrite the past. */
        $buildingService->vanish((int) $building['player_id']);
    }

} elseif ($type === 'ressource') {

    /* Une ressource se retire comme elle se pose : c'est une entité, et le
       DELETE d'une ligne de couche frappait une table vide — la gomme passait
       sans rien effacer. Le canal porte le nom de l'objet, pas d'une table :
       c'est tile_info qui le donne au bouton, comme pour les bâtiments. */
    $resources = new ResourceObjectService();
    $resources->removeEntities($resources->idsOn((int) $coordsId));

} elseif ($type === 'plante') {

    /* Une plante se retire comme une ressource : c'est une entité. Le canal
       porte le nom de l'objet, donné par tile_info au bouton de suppression. */
    (new \App\Service\Map\ResourceObjectService())->removeEntities(
        array_map('intval', (new Db())->exe(
            "SELECT id FROM players WHERE player_type = 'plant' AND coords_id = ?",
            array($coordsId)
        )->fetch_all(MYSQLI_COLUMN) ?: [])
    );

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
    /* map_resources a quitté la liste : ses objets sont des entités et
       passent par le canal « ressource » ci-dessus. */
    if(!in_array($type, array('map_tiles','map_triggers','map_dialogs','map_elements','map_routes','map_foregrounds'))){

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
