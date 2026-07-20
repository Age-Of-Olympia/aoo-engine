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