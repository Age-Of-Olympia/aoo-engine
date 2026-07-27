<?php
use App\Service\BuildingService;
use App\Service\ResourcePaletteService;
use Classes\Db;

if($_POST['type'] == 'eraser'){
    include 'erase_map.php';
} elseif($_POST['type'] == 'buildings'){

    /* Poser un bâtiment = créer une ENTITÉ (PV du type, attaquable) — le
       service valide le type et l'occupation de la case. La cible se relit
       depuis $coordsId : en mode zone, seul lui est fiable par itération. */
    $res = (new Db())->exe('SELECT x, y, z, plan FROM coords WHERE id = ?', array($coordsId));
    $goCoords = (object) $res->fetch_assoc();

    try {
        $buildingId = (new BuildingService())->place($_POST['src'], $goCoords);

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
    if($_POST['type'] == 'foregrounds' && $_POST['src'] == 'ombre'){

        $shadeMax = (new \App\Service\CellShadeService())
            ->forPlan($player->coords->plan)['max'];

        (new Db())->exe(
            'UPDATE coords SET shade = LEAST(shade + 1, ?) WHERE id = ?',
            array($shadeMax, $coordsId)
        );

        echo 'ombre';

        /* `return` et non `exit` : ce fichier est INCLUS dans une boucle
           quand on peint une zone (tiled.php). Un exit s'arreterait a la
           premiere case, et le pinceau de zone n'ombrerait qu'un carreau. */
        return;
    }

    /* Les obstacles/décor sont des entités bâtiment depuis leur conversion :
       map_resources ne reçoit plus que les ressources et les survivants
       (autels, unique_*, plans de tutoriel) */
    if($_POST['type'] == 'resources' && !ResourcePaletteService::isAuthorable($_POST['src'], $player->coords->plan)){

        exit('error: mur « '. $_POST['src'] .' » — les obstacles se posent en tant que bâtiments (admin → Bâtiments)');
    }


    $values = array(
        'name'=>$_POST['src'],
        'coords_id'=>$coordsId
    );

    $db = new Db();

    echo $_POST['type'];

    $db->insert('map_'. $_POST['type'], $values);

    if(!empty($_POST['params'])){
        
        $lastId = $db->get_last_id('map_'. $_POST['type']);

        if( $_POST['type'] == 'resources'){
            //cas particulier des ressources (damages)
    
            $sql = 'UPDATE map_resources SET damages = ? WHERE id = ?';
    
            $db->exe($sql, array($_POST['params'], $lastId));
    
        } else {
            //Autres tiles 
            $sql = 'UPDATE map_'. $_POST['type'] .' SET params = ? WHERE id = ?';
    
            $db->exe($sql, array($_POST['params'], $lastId));
    
            echo '
                params: '. $_POST['params'];
            
        }
    } 
}