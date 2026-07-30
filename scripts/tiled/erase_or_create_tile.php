<?php
use App\Service\BuildingService;
use App\Service\ResourcePaletteService;
use App\Service\CellShadeService;
use App\Service\Map\ResourceObjectService;
use App\Service\Map\SceneryObjectService;
use Classes\Db;
use Classes\View;

if($_POST['type'] == 'eraser'){
    include 'erase_map.php';
} elseif($_POST['type'] == 'buildings'){

    /* Poser un bâtiment = créer une ENTITÉ (PV du type, attaquable) — le
       service valide le type et l'occupation de la case. La cible se relit
       depuis $coordsId : en mode zone, seul lui est fiable par itération. */
    $res = (new Db())->exe('SELECT x, y, z, plan FROM coords WHERE id = ?', array($coordsId));
    $goCoords = (object) $res->fetch_assoc();

    try {
        /* Depuis l'éditeur, on pose PAR-DESSUS le décor : c'est un geste
         * d'animateur — cacher quelque chose derrière une statue — quand un
         * joueur, lui, ne bâtit pas au travers. */
        $buildingId = (new BuildingService())->place(
            $_POST['src'], $goCoords, null, '', null, overScenery: true
        );

        echo 'bâtiment #'. $buildingId .' ';
    } catch (\InvalidArgumentException $e) {

        echo 'refusé : '. $e->getMessage() .' ';
    }

} else {


    if(!in_array($_POST['type'], array('tiles','foregrounds','resources','triggers','elements','dialogs','plants','routes'))){

        exit('error type');
    }

    /* L'ombre n'est plus un decor mais une INTENSITE de case.
     *
     * Le geste de l'animateur ne change pas d'un poil : il prend le meme
     * pinceau dans la meme palette, et re-cliquer fonce davantage — c'est ce
     * qu'il faisait deja en empilant des lignes. Seul le stockage change :
     * un niveau sur la case au lieu de N lignes de decor.
     *
     * Le plafond est celui DU PLAN edite (CellShadeService : reglage de plan,
     * defaut du tableau de bord admin sinon) : au-dela, un clic reste sans
     * effet visible plutot que de gonfler un compteur sans fin. */
    if ($_POST['type'] === 'foregrounds' && $_POST['src'] === 'ombre') {
        $shadeMax = (new CellShadeService())->forPlan($player->coords->plan)['max'];

        (new Db())->exe(
            'UPDATE coords SET shade = LEAST(shade + 1, ?) WHERE id = ?',
            [$shadeMax, $coordsId]
        );

        echo 'ombre';

        /* `return` et non `exit` : ce fichier est INCLUS dans une boucle quand
           on peint une zone (tiled.php). Un exit s'arrêterait à la première
           case, et le pinceau de zone n'ombrerait qu'un carreau. */
        return;
    }

    /* Les obstacles/décor sont des entités bâtiment depuis leur conversion :
       map_resources ne reçoit plus que les ressources et les survivants
       (autels, unique_*, plans de tutoriel) */
    if($_POST['type'] == 'resources' && !ResourcePaletteService::isAuthorable($_POST['src'], $player->coords->plan)){

        exit('error: mur « '. $_POST['src'] .' » — les obstacles se posent en tant que bâtiments (admin → Bâtiments)');
    }


    $db = new Db();

    /* Un décor multi-cases se pose EN ENTIER.
     *
     * L'animateur prend un morceau dans la palette — n'importe lequel — et
     * clique : la figure se pose alignée pour que CE morceau tombe sur la
     * case visée. Le geste ne change pas, seul le résultat : il n'a plus à
     * placer quatorze morceaux à la main pour un fort, ni à se souvenir de
     * la découpe.
     *
     * Une famille sans découpe connue — décor d'une seule case, ou famille
     * que la carte ne permet pas de trancher — retombe sur la pose simple.
     * On ne devine pas une figure. */
    if ($_POST['type'] === 'foregrounds') {
        $origin = $db->exe('SELECT x, y, z, plan FROM coords WHERE id = ?', [$coordsId])->fetch_object();

        $placed = (new SceneryObjectService())->placeObject(
            $_POST['src'],
            (int) $origin->x,
            (int) $origin->y,
            (int) $origin->z,
            (string) $origin->plan
        );

        if ($placed > 0) {
            echo 'foregrounds (' . $placed . ' morceaux)';

            return;
        }
    }

    /* Une ressource est une ENTITÉ depuis sa conversion. La poser en ligne de
     * couche l'écrivait dans une table que plus personne ne lit : le pinceau
     * répondait « resources », et la partie ne voyait aucun arbre.
     *
     * Du champ « damages » de la palette il ne reste qu'un sens : -2 pose une
     * ressource déjà épuisée, qui repoussera à son heure. */
    if ($_POST['type'] === 'resources') {
        $resourceId = (new ResourceObjectService())->placeAt(
            $_POST['src'],
            (int) $coordsId,
            (int) ($_POST['params'] ?? -1) === -2
        );

        echo 'ressource #' . $resourceId;

        \Classes\View::refresh_players_svg_at((int) $coordsId);

        return;
    }

    $values = array(
        'name'=>$_POST['src'],
        'coords_id'=>$coordsId
    );

    echo $_POST['type'];

    $db->insert('map_'. $_POST['type'], $values);

    if(!empty($_POST['params'])){
        
        /* Le cas particulier des ressources (damages) a disparu avec la ligne
           de couche : la pose d'une ressource retourne plus haut, et son état
           part avec elle. */
        $lastId = $db->get_last_id('map_'. $_POST['type']);

        $sql = 'UPDATE map_'. $_POST['type'] .' SET params = ? WHERE id = ?';

        $db->exe($sql, array($_POST['params'], $lastId));

        echo '
            params: '. $_POST['params'];
    }
}

/* Idem à la pose : `BuildingService::place` rafraîchit déjà pour les
 * bâtiments, mais rien ne le faisait pour un sol, un décor ou une
 * ressource. */
\Classes\View::refresh_players_svg_at((int) $coordsId);
