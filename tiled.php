<?php
use Classes\Ui;
use Classes\View;
use Classes\Player;
require_once('config.php');

$player = new Player($_SESSION['playerId']);
$player->getCoords();

if(!empty($_POST['delete'])){
    $coordsId = $_POST['coord-id'];
    $type = $_POST['type'];
    include $_SERVER['DOCUMENT_ROOT'].'/scripts/tiled/erase_case.php';
    exit();
}

if(!empty($_POST['zone']) && !empty($_POST['type']) && !empty($_POST['src'])){

    $zoneData = [
        'beginX' => intval($_POST['zone']['beginX']),
        'beginY' => intval($_POST['zone']['beginY']),
        'endX' => intval($_POST['zone']['endX']),
        'endY' => intval($_POST['zone']['endY'])
    ];
    
    $allCoords = [];
    
    for ($x = min($zoneData['beginX'], $zoneData['endX']); $x <= max($zoneData['beginX'], $zoneData['endX']); $x++) {
        for ($y = min($zoneData['beginY'], $zoneData['endY']); $y <= max($zoneData['beginY'], $zoneData['endY']); $y++) {

            $coords = $player->coords;

            $coords->x = $x;
            $coords->y = $y;
    
            $coordsId = View::get_coords_id($coords);
    
            // keep all coords ids
            $allCoords[] =  $coordsId;
        }
    }
    
    
    // Create or erase tile for each in the coords zone
    foreach ($allCoords as $coordsId) {
       include $_SERVER['DOCUMENT_ROOT'].'/scripts/tiled/erase_or_create_tile.php';
    }




  exit();
}

if(!empty($_POST['coords']) && !empty($_POST['type']) && !empty($_POST['src'])){


    $coords = $player->coords;

    $coords->x = explode(',', $_POST['coords'])[0];
    $coords->y = explode(',', $_POST['coords'])[1];

    $coordsId = View::get_coords_id($coords);


    if($_POST['type'] == 'tp'){
        $coords->coordsId = $coordsId;
        $player->go($coords);
        exit('tp');
    }

    if($_POST['type'] == 'info'){
        include $_SERVER['DOCUMENT_ROOT'].'/scripts/tiled/tile_info.php';
        exit('infos');
    }

    include $_SERVER['DOCUMENT_ROOT'].'/scripts/tiled/erase_or_create_tile.php';

    exit();
}


$ui = new Ui(title:"Tiled");


$view = new View($player->coords, p:10, tiled:true);

$data = $view->get_view();



echo '
<link rel="stylesheet" href="css/modal.css" />

<style>

    #tool-div {
        display: flex;
        justify-content: center;
        flex-direction: column;
    }

    @media (max-width: 1200px) { 
            #tool-div {
                 flex-direction: row;
            }
    }

</style>

<div style="float: left;">
<script src="js/modal.js"></script>

';

echo $data;

echo '
</div>
';

echo '<div stlye="position: absolute; top: 0; left: 0;"><a href="index.php"><button>Retour</button></a></div>
<br/>
<div id="ajax-data"></div>
<div id="tool-div" style="display:flex;justify-content: center;">
    <div>';

include $_SERVER['DOCUMENT_ROOT'].'/scripts/tiled/display_tools.php';

echo '</div><div>';

include $_SERVER['DOCUMENT_ROOT'].'/scripts/tiled/display_mass_tools.php';

echo '</div>
</div>';

include $_SERVER['DOCUMENT_ROOT'].'/scripts/tiled/display_indestructibles.php';

include $_SERVER['DOCUMENT_ROOT'].'/scripts/tiled/display_plants.php';

include $_SERVER['DOCUMENT_ROOT'].'/scripts/tiled/display_walls.php';

include $_SERVER['DOCUMENT_ROOT'].'/scripts/tiled/display_elements.php';

include $_SERVER['DOCUMENT_ROOT'].'/scripts/tiled/display_routes.php';

include $_SERVER['DOCUMENT_ROOT'].'/scripts/tiled/display_triggers.php';

include $_SERVER['DOCUMENT_ROOT'].'/scripts/tiled/display_foregrounds.php';

use App\View\ModalView;
$modalView = new ModalView();
$modalView->displayModal('tile-info','info-display');

?>


<style>
.custom-cursor {
    position: absolute;
    width: 50px;
    height: 50px;
    pointer-events: none;
    z-index: 1000;
}
</style>

<script src="js/tiled.js"></script>




