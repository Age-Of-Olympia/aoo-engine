<?php
use Classes\Ui;
use Classes\View;
use Classes\Player;
use App\Service\AdminAuthorizationService;
require_once('config.php');

$player = new Player($_SESSION['playerId']);
$player->getCoords();

AdminAuthorizationService::DoAdminCheck();

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
    
    if($_POST['type'] == 'harvest_mode'){
        //TODO here update to -1 if -2 or 0 update to -2 if needed
        include $_SERVER['DOCUMENT_ROOT'].'/scripts/tiled/tile_harvest_mode.php';
        exit('harvest');
    }

    include $_SERVER['DOCUMENT_ROOT'].'/scripts/tiled/erase_or_create_tile.php';

    exit();
}


$ui = new Ui(title:"Tiled");


$view = new View($player->coords, p:10, tiled:true);

$data = $view->get_view();

// View-only mode: return just the map view HTML for AJAX refresh
if(!empty($_GET['view_only'])){
    echo $data;
    exit();
}


echo '
<link rel="stylesheet" href="css/modal.css" />

<style>
    /* Tiled editor specific layout - override body centering and ensure no overflow */
    html, body {
        height: 100%;
        overflow: hidden;
    }

    body {
        max-width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    /* Top toolbar for global tools - compact layout */
    .top-toolbar {
        background: rgba(255, 255, 255, 0.3);
        border-bottom: 2px solid #333;
        padding: 5px 15px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 15px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        flex-wrap: nowrap;
    }

    .top-toolbar h3 {
        margin: 0;
        font-size: 12px;
        font-weight: bold;
    }

    /* Main layout container - perfectly fits remaining screen */
    .tiled-container {
        display: flex;
        height: calc(100vh - 50px); /* Account for compact toolbar */
        overflow: hidden;
    }

    /* Map container - left side */
    #map-view-container {
        flex: 0 0 70%;
        overflow: auto;
        padding: 10px;
        box-sizing: border-box;
        background: rgba(255, 255, 255, 0.1);
    }

    /* Tool palette - fixed right sidebar */
    .tool-sidebar {
        flex: 0 0 30%;
        overflow-y: auto;
        overflow-x: hidden;
        padding: 15px;
        background: rgba(255, 255, 255, 0.25);
        border-left: 3px solid rgba(0, 0, 0, 0.3);
        box-sizing: border-box;
    }

    .tool-sidebar h2 {
        position: sticky;
        top: 0;
        background: rgba(255, 255, 255, 0.95);
        margin: 0 0 15px 0;
        padding: 10px 0;
        z-index: 10;
        border-bottom: 2px solid #333;
        font-size: 18px;
        font-family: goudy;
        text-align: center;
        user-select: none;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .tool-sidebar details {
        margin-bottom: 15px;
    }

    .tool-sidebar summary {
        background: rgba(0, 0, 0, 0.1);
        padding: 8px;
        border-radius: 4px;
        margin-bottom: 5px;
        cursor: pointer;
    }

    .tool-sidebar summary:hover {
        background: rgba(0, 0, 0, 0.15);
    }

    .tool-sidebar img {
        max-width: 50px;
        height: auto;
    }

    #ajax-data {
        position: fixed;
        bottom: 10px;
        left: 10px;
        background: rgba(255, 255, 255, 0.95);
        padding: 10px;
        padding-top: 25px;
        border: 2px solid #333;
        border-radius: 4px;
        z-index: 100;
        box-shadow: 0 2px 8px rgba(0,0,0,0.3);
        display: none;
    }

    #ajax-data.has-content {
        display: block;
    }

    #ajax-data-close {
        position: absolute;
        top: 2px;
        right: 5px;
        background: transparent;
        border: none;
        font-size: 18px;
        cursor: pointer;
        padding: 0;
        width: 20px;
        height: 20px;
        line-height: 1;
        color: #333;
    }

    #ajax-data-close:hover {
        color: red;
    }

    .back-button {
        margin-right: 10px;
    }

    #tool-div {
        display: flex;
        gap: 5px;
        align-items: center;
        flex-wrap: nowrap;
        flex-direction: column;
    }

    #tool-div h3 {
        text-align: center;
        width: 100%;
    }

    #tool-div > div {
        display: flex;
        gap: 5px;
        align-items: center;
        justify-content: center;
    }

    .zone-tools-container {
        display: flex;
        gap: 5px;
        align-items: center;
        flex-wrap: wrap;
        max-width: 400px;
    }

    .zone-tools-container input {
        width: 60px;
        padding: 2px 4px;
        font-size: 12px;
    }

    .zone-tools-container button {
        padding: 4px 8px;
        font-size: 12px;
    }

    /* Tablet view */
    @media (max-width: 1024px) {
        .tiled-container {
            flex-direction: column;
        }

        #map-view-container {
            flex: 0 0 55%;
            height: 55vh;
        }

        .tool-sidebar {
            flex: 0 0 45%;
            height: calc(45vh - 70px);
            border-left: none;
            border-top: 3px solid rgba(0, 0, 0, 0.3);
        }

        .top-toolbar {
            flex-direction: row;
            justify-content: flex-start;
        }

        #tool-div {
            flex-direction: column;
            align-items: flex-start;
        }
    }

    /* Mobile view - optimized for touch */
    @media (max-width: 768px) {
        .back-button {
            position: fixed;
            top: 5px;
            left: 5px;
            z-index: 200;
            margin: 0;
        }

        .back-button button {
            padding: 6px 10px;
            font-size: 11px;
            min-height: 32px;
            opacity: 0.85;
        }

        .top-toolbar {
            padding: 5px;
            flex-direction: row;
            align-items: center;
            justify-content: center;
            gap: 5px;
            background: rgba(255, 255, 255, 0.9);
            border-bottom: 3px solid #333;
            flex-wrap: nowrap;
        }

        #tool-div {
            flex: 1 1 auto;
            justify-content: center;
            flex-wrap: nowrap;
            padding: 4px;
            background: rgba(0, 0, 0, 0.05);
            border-radius: 4px;
            gap: 3px;
        }

        #tool-div img {
            max-width: 32px;
            max-height: 32px;
        }

        .zone-tools-container {
            display: none !important;
        }

        .tiled-container {
            flex-direction: column;
            height: calc(100vh - 45px);
        }

        #map-view-container {
            flex: 0 0 30%;
            padding: 5px;
        }

        .tool-sidebar {
            flex: 0 0 70%;
            padding: 8px;
            -webkit-overflow-scrolling: touch;
            overflow-y: scroll;
        }

        .tool-sidebar h2 {
            font-size: 13px;
            padding: 6px 0;
            margin-bottom: 8px;
            background: rgba(255, 255, 255, 1);
            border: 1px solid #666;
            text-align: center;
        }

        .tool-sidebar img {
            max-width: 45px;
            min-height: 45px;
        }

        .tool-sidebar details {
            margin-bottom: 8px;
        }

        .tool-sidebar summary {
            padding: 10px 6px;
            font-size: 13px;
            min-height: 40px;
            display: flex;
            align-items: center;
        }

        button {
            padding: 8px 10px;
            font-size: 13px;
            min-height: 38px;
        }
    }

    /* Large phones - more balanced split */
    @media (min-width: 481px) and (max-width: 768px) {
        #map-view-container {
            flex: 0 0 45%;
        }

        .tool-sidebar {
            flex: 0 0 55%;
        }
    }

    /* Very small screens - prioritize tiles more */
    @media (max-width: 480px) {
        .top-toolbar {
            min-height: auto;
        }

        .tool-sidebar img {
            max-width: 35px;
        }

        #map-view-container {
            flex: 0 0 25%;
        }

        .tool-sidebar {
            flex: 0 0 75%;
        }
    }

    /* Very tall screens (like modern phones in portrait) - give more space to map */
    @media (min-height: 700px) and (max-width: 768px) {
        #map-view-container {
            flex: 0 0 40%;
        }

        .tool-sidebar {
            flex: 0 0 60%;
        }
    }

</style>

<!-- Top toolbar for global/zone tools -->
<div class="top-toolbar">
    <div class="back-button">
        <a href="index.php"><button>← Retour</button></a>
    </div>

    <div id="tool-div">';

include $_SERVER['DOCUMENT_ROOT'].'/scripts/tiled/display_tools.php';

echo '</div>

    <div class="zone-tools-container">';

include $_SERVER['DOCUMENT_ROOT'].'/scripts/tiled/display_mass_tools.php';

echo '</div>
</div>

<div class="tiled-container">
    <!-- Map view on the left -->
    <div id="map-view-container">
        <script src="js/modal.js"></script>
        ';

echo $data;

echo '
    </div>

    <!-- Tool palette sidebar on the right -->
    <div class="tool-sidebar">
        <h2>🎨 Palette de Tuiles</h2>';

include $_SERVER['DOCUMENT_ROOT'].'/scripts/tiled/display_indestructibles.php';

include $_SERVER['DOCUMENT_ROOT'].'/scripts/tiled/display_plants.php';

include $_SERVER['DOCUMENT_ROOT'].'/scripts/tiled/display_walls.php';

include $_SERVER['DOCUMENT_ROOT'].'/scripts/tiled/display_elements.php';

include $_SERVER['DOCUMENT_ROOT'].'/scripts/tiled/display_routes.php';

include $_SERVER['DOCUMENT_ROOT'].'/scripts/tiled/display_triggers.php';

include $_SERVER['DOCUMENT_ROOT'].'/scripts/tiled/display_foregrounds.php';

echo '
    </div>
</div>

<div id="ajax-data">
    <button id="ajax-data-close" title="Fermer">✕</button>
</div>

<script>
// Close ajax-data box when clicking the X button
document.addEventListener("DOMContentLoaded", function() {
    const closeBtn = document.getElementById("ajax-data-close");
    const ajaxData = document.getElementById("ajax-data");

    if(closeBtn && ajaxData) {
        closeBtn.addEventListener("click", function(e) {
            e.stopPropagation();
            ajaxData.innerHTML = \'<button id="ajax-data-close" title="Fermer">✕</button>\';
            ajaxData.classList.remove("has-content");
            // Re-bind the close button
            const newCloseBtn = document.getElementById("ajax-data-close");
            if(newCloseBtn) {
                newCloseBtn.addEventListener("click", arguments.callee);
            }
        });
    }
});
</script>
';

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

<script src="js/tiled.js?v=20251111s"></script>




