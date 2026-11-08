<?php
use App\Factory\PlayerFactory;
use Classes\Ui;
use Classes\View;
use App\Service\AdminAuthorizationService;
use App\Service\Map\SceneryObjectService;
require_once('config.php');

$player = PlayerFactory::legacy($_SESSION['playerId']);
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


    // A copy: go() needs the player's coords to still be the cell left.
    $coords = clone $player->coords;

    $coords->x = explode(',', $_POST['coords'])[0];
    $coords->y = explode(',', $_POST['coords'])[1];

    $coordsId = View::get_coords_id($coords);


    if($_POST['type'] == 'tp'){
        
        $coords->coordsId = $coordsId;
        // Moving around the editor: no biome entry or exit for the admin's own character
        $player->go($coords, \App\Service\Map\BiomeEvents::None);
        exit('tp');
    }

    if($_POST['type'] == 'info'){
        include $_SERVER['DOCUMENT_ROOT'].'/scripts/tiled/tile_info.php';
        exit('infos');
    }

    /* Les deux gestes de l'éditeur d'objet : compléter une figure tronquée,
       ou la retirer en entier. Ils travaillent sur l'OBJET, pas sur la case —
       c'est ce qui manquait pour reprendre les 38 exemplaires incomplets. */
    if ($_POST['type'] === 'object_complete') {
        $placed = (new SceneryObjectService())->complete($coordsId, (string) $_POST['src']);
        \App\Service\Map\BoardChanges::cellId((int) $coordsId);

        exit($placed > 0 ? $placed . ' morceau(x) reposé(s)' : 'figure déjà complète');
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


/** Fewest cells shown around the editor on each side; a character who sees farther keeps their Perception */
const TILED_MIN_VIEW_RANGE = 10;
/** Extra cells of biome data past the view: a viewer wider than one cell sees farther, and a border leaving the view is followed to its edge */
const TILED_BIOME_MARGIN = 5;

$viewRange = max(TILED_MIN_VIEW_RANGE, (int) ($player->get_caracsJson()->p ?? 0));
$view = new View($player->coords, p:$viewRange, tiled:true);

$data = $view->get_view();

/* Biomes of the area on screen, sent with the view so the overlay follows an
 * AJAX refresh: the script repaints right after the board is replaced. */
$biomeReach = $viewRange + TILED_BIOME_MARGIN;
$biomeCoverage = (new \App\Service\Map\BiomeCellService())->coverage((string) $player->coords->plan, [
    'xMin' => (int) $player->coords->x - $biomeReach, 'xMax' => (int) $player->coords->x + $biomeReach,
    'yMin' => (int) $player->coords->y - $biomeReach, 'yMax' => (int) $player->coords->y + $biomeReach,
]);
$data .= '<script>window.tiledBiomes = ' . json_encode($biomeCoverage, JSON_HEX_TAG | JSON_HEX_APOS)
    . '; if (window.paintBiomes) { window.paintBiomes(); } if (window.applyTiledZoom) { window.applyTiledZoom(); }</script>';

// View-only mode: return just the map view HTML for AJAX refresh
if(!empty($_GET['view_only'])){
    echo $data;
    exit();
}


echo '
<link rel="stylesheet" href="css/modal.css" />

<style>
    /* Emprise d\'un objet de décor, entourée sur le damier.
       Elle n\'était visible nulle part : on posait et on effaçait des
       morceaux sans jamais voir la figure. Le liseré plein dit ce qui est
       posé, le liseré d\'alerte les cases où il MANQUE un morceau. */
    .case.scenery-outline {
        outline: 2px solid rgba(60, 130, 220, 0.9);
        outline-offset: -2px;
    }
    .case.scenery-hole {
        outline: 2px dashed rgba(220, 70, 70, 0.95);
        outline-offset: -2px;
    }

    /* Palette : une vignette par OBJET, pas par morceau. */
    .scenery-palette .scenery-object {
        border: 1px solid rgba(0, 0, 0, 0.15);
        margin: 1px;
        vertical-align: top;
    }

    /* Les morceaux qui composent la vignette ne reçoivent PAS le clic.
       Sans cela l\'événement a pour cible l\'image intérieure, que le
       désélecteur global ne reconnaît pas comme un outil de palette : la
       sélection était retirée aussitôt posée, et plus rien n\'était
       sélectionnable. */
    .scenery-palette .scenery-object img {
        pointer-events: none;
    }

    /* Découpe inconnue : la vignette groupe les morceaux mais la pose reste
       morceau par morceau — le liseré le dit. */
    .scenery-palette .scenery-object--unknown {
        border-style: dashed;
        border-color: rgba(180, 140, 60, 0.7);
    }

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

    /* Map container - left side. position:relative so the
       blocked-tile markers (position:absolute) anchor here and
       scroll with the map natively. */
    #map-view-container {
        position: relative;
        flex: 0 0 70%;
        overflow: auto;
        padding: 10px;
        box-sizing: border-box;
        background: rgba(255, 255, 255, 0.1);
    }

    /* The editor container scrolls a zoomed board, not the inner #view */
    #map-view-container #view {
        overflow: visible;
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
        <button id="tiled-zoom-in" type="button" title="Zoomer (Ctrl + molette)" aria-label="Zoomer">+</button>
        <button id="tiled-zoom-out" type="button" title="Dézoomer (Ctrl + molette)" aria-label="Dézoomer" disabled>-</button>
        <button id="toggle-blocked-tiles" type="button" title="Afficher les cases bloquantes (murs, joueurs, déclencheurs « interdit »)">⛔ Cases bloquantes</button>
        '. (\App\Module\Modules::on(\App\Module\Module::Biomes)
            ? '<button id="toggle-biomes" type="button" title="Colorer chaque case selon son biome (celui du dessus là où plusieurs se chevauchent ; une case de transition, rayée, appartient à chacun de ses biomes)">🗺️ Biomes</button>'
                . '<span id="biome-legend" class="biome-legend" hidden></span>'
            : '') .'
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
        <script src="js/modal.js?v=20261017"></script>
        ';

echo $data;

echo '
    </div>

    <!-- Tool palette sidebar on the right -->
    <div class="tool-sidebar">
        <h2>🎨 Palette de Tuiles</h2>';

/* Images hidden from the palettes in admin → Tuiles & images: one query,
   then an isset() per thumbnail. Without the table, the palette stays whole. */
try {
    $hiddenTiles = (new \App\Service\TilePaletteVisibilityService())->hiddenByLayer();
} catch (Throwable $hiddenTilesError) {
    $hiddenTiles = [];
}

include $_SERVER['DOCUMENT_ROOT'].'/scripts/tiled/display_indestructibles.php';

if (\App\Module\Modules::on(\App\Module\Module::Biomes)) {
    include $_SERVER['DOCUMENT_ROOT'].'/scripts/tiled/display_biome_brush.php';
}

include $_SERVER['DOCUMENT_ROOT'].'/scripts/tiled/display_plants.php';

include $_SERVER['DOCUMENT_ROOT'].'/scripts/tiled/display_resources.php';

include $_SERVER['DOCUMENT_ROOT'].'/scripts/tiled/display_buildings.php';

include $_SERVER['DOCUMENT_ROOT'].'/scripts/tiled/display_elements.php';

foreach (['marks' => 'Marques (sans effet, au-dessus des éléments)', 'routes' => 'Route'] as $layer => $title) {
    include $_SERVER['DOCUMENT_ROOT'].'/scripts/tiled/display_palette.php';
}

include $_SERVER['DOCUMENT_ROOT'].'/scripts/tiled/display_triggers.php';

include $_SERVER['DOCUMENT_ROOT'].'/scripts/tiled/display_foregrounds.php';

echo '
    </div>
</div>

<div id="ajax-data">
    <button id="ajax-data-close" title="Fermer">✕</button>
</div>

<div id="admin-coords"></div>

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

/* Curseur d\'OBJET : la figure entière, à l\'échelle de la carte.
   Le curseur simple est une image de 50×50 ; il ne montrait qu\'un morceau,
   si bien qu\'on posait un fort de quatorze cases en ne voyant qu\'un pan de
   mur. Celui-ci porte les morceaux à leur place, en semi-transparence pour
   qu\'on distingue la carte dessous. */
.custom-cursor--object {
    width: auto;
    height: auto;
    opacity: 0.75;
}
</style>

<script src="js/admin-tools.js?v=20260715"></script>
<script src="js/blocked-tiles.js?v=20260727"></script>
<script src="js/tiled.js?v=20261108"></script>
<script src="js/zoom-gestures.js?v=<?= \App\View\Hud\HudLayoutView::VERSION ?>"></script>
<script>
/* Zoom of the board, as on the game board: the buttons and Ctrl+wheel or
   pinch (js/zoom-gestures.js). Zoom 1 is the board's own fit; beyond, the
   SVG gets an explicit size and the container scrolls, the point at its
   centre staying there. Applied again after each AJAX refresh of the board. */
document.addEventListener("DOMContentLoaded", function() {
    var container = document.getElementById("map-view-container");
    var ZOOM_STEP = 1.25;
    var ZOOM_MAX = 4;
    var zoom = 1;

    window.applyTiledZoom = function() {
        var svg = document.getElementById("svg-view");
        if (!svg) return;
        /* Zoom 1 fits the view's width, at most the board's own size; computed
           rather than measured, so the scroll stays put through a refresh */
        var viewBox = svg.viewBox.baseVal;
        var fitted = Math.min(document.getElementById("view").clientWidth, viewBox.width);
        svg.style.width = zoom > 1 ? fitted * zoom + "px" : "";
        svg.style.height = zoom > 1 ? fitted * zoom * viewBox.height / viewBox.width + "px" : "";
        svg.style.maxWidth = zoom > 1 ? "none" : "";
        document.getElementById("tiled-zoom-in").disabled = zoom >= ZOOM_MAX;
        document.getElementById("tiled-zoom-out").disabled = zoom <= 1;
        /* the blocked-tile markers are placed in pixels: redrawn on resize */
        window.dispatchEvent(new Event("resize"));
    };

    function setZoom(z) {
        var cx = (container.scrollLeft + container.clientWidth / 2) / container.scrollWidth;
        var cy = (container.scrollTop + container.clientHeight / 2) / container.scrollHeight;
        zoom = Math.min(ZOOM_MAX, Math.max(1, z));
        window.applyTiledZoom();
        container.scrollLeft = cx * container.scrollWidth - container.clientWidth / 2;
        container.scrollTop = cy * container.scrollHeight - container.clientHeight / 2;
    }

    document.getElementById("tiled-zoom-in").addEventListener("click", function() { setZoom(zoom * ZOOM_STEP); });
    document.getElementById("tiled-zoom-out").addEventListener("click", function() { setZoom(zoom / ZOOM_STEP); });
    window.zoomGestures(container, function() { return zoom; }, setZoom, ZOOM_STEP);
});
</script>
<script>
/* Tiled editor: toggle the "blocked tiles" overlay so admins can
   verify forbidden triggers / walls / players visually. Reuses
   the same blocked-tile-marker class as in-game and the tutorial
   so the visual is identical everywhere — tiled has no concurrent
   tutorial or showBlockedTiles caller, so no marker collision. */
document.addEventListener("DOMContentLoaded", function() {
    var btn = document.getElementById("toggle-blocked-tiles");
    if (!btn) return;
    var $container = $("#map-view-container");
    var on = false;
    function redraw() {
        if (on) window.drawBlockedTileMarkers(null, "blocked-tile-marker", $container);
    }
    btn.addEventListener("click", function() {
        on = !on;
        btn.classList.toggle("active", on);
        if (on) {
            redraw();
        } else {
            window.clearBlockedTileMarkers("blocked-tile-marker");
        }
    });
    /* Markers live inside #map-view-container (position:absolute),
       so scrolling the container moves them natively — no scroll
       listener needed. Resize still requires a redraw because the
       container dimensions change. */
    window.addEventListener("resize", redraw);
});
</script>
<script>
/* Tiled editor: tint each cell with the colour of its top biome, draw the
   borders where the top biome changes, stripe the cells under several
   biomes (transition tiles) and list the biomes on screen. The data comes with the view (window.tiledBiomes); the view
   calls paintBiomes again after each AJAX refresh of the board. */
document.addEventListener("DOMContentLoaded", function() {
    const button = document.getElementById("toggle-biomes");
    if (!button) return;
    const legend = document.getElementById("biome-legend");
    const SVG_NS = "http://www.w3.org/2000/svg";
    const BORDER_WIDTH = 3;
    let on = false;

    const STRIPE_WIDTH = 6;

    function paintCell(rect, biomeIds, biomes) {
        if (!on || !biomeIds) {
            rect.setAttribute("fill", "transparent");
            rect.removeAttribute("fill-opacity");
            return;
        }
        rect.setAttribute("fill", biomeIds.length > 1
            ? "url(#" + stripes(rect.ownerSVGElement, biomeIds, biomes) + ")"
            : biomes[biomeIds[0]].color);
        rect.setAttribute("fill-opacity", "0.45");
    }

    /* A cell under several biomes (a transition tile) belongs to each of
       them: diagonal stripes of their colours, one pattern per set of biomes */
    function stripes(svg, biomeIds, biomes) {
        const id = "biome-stripes-" + biomeIds.join("-");
        if (svg.getElementById(id)) return id;
        const defs = svg.querySelector("defs") || svg.insertBefore(document.createElementNS(SVG_NS, "defs"), svg.firstChild);
        const pattern = document.createElementNS(SVG_NS, "pattern");
        pattern.setAttribute("id", id);
        pattern.setAttribute("patternUnits", "userSpaceOnUse");
        pattern.setAttribute("width", STRIPE_WIDTH * biomeIds.length);
        pattern.setAttribute("height", STRIPE_WIDTH);
        pattern.setAttribute("patternTransform", "rotate(45)");
        biomeIds.forEach(function(biomeId, i) {
            const band = document.createElementNS(SVG_NS, "rect");
            band.setAttribute("x", STRIPE_WIDTH * i);
            band.setAttribute("width", STRIPE_WIDTH);
            band.setAttribute("height", STRIPE_WIDTH);
            band.setAttribute("fill", biomes[biomeId].color);
            pattern.appendChild(band);
        });
        defs.appendChild(pattern);
        return id;
    }

    /* Where the top biome changes between two cells, a line on their shared
       side: the border follows the zone's outline, whatever the ground. A cell
       under several biomes (a transition tile) is striped in all their colours. */
    function drawBorders(rects, cells, biomes) {
        const axes = screenAxes(rects);
        rects.forEach(function(rect, key) {
            const ids = cells[key] || [];
            if (ids.length === 0) return;
            const [x, y] = key.split(",").map(Number);
            [[1, 0], [-1, 0], [0, 1], [0, -1]].forEach(function([dx, dy]) {
                const neighbourKey = (x + dx) + "," + (y + dy);
                const neighbourTop = (cells[neighbourKey] || [])[0];
                if (!rects.has(neighbourKey) || neighbourTop === ids[0]) return;
                const color = borderColor(biomes[ids[0]].color, biomes[neighbourTop === undefined ? ids[0] : neighbourTop].color);
                rect.parentNode.appendChild(side(rect, {x: dx * axes.x, y: dy * axes.y}, color));
            });
        });
    }

    /* Which way x and y run on screen (+1 or -1), read from two neighbouring
       cells: the board draws y upwards, the SVG counts it downwards */
    function screenAxes(rects) {
        for (const [key, rect] of rects) {
            const [x, y] = key.split(",").map(Number);
            const right = rects.get((x + 1) + "," + y);
            const above = rects.get(x + "," + (y + 1));
            if (right && above) {
                const box = boxOf(rect);
                return {x: Math.sign(boxOf(right).x - box.x), y: Math.sign(boxOf(above).y - box.y)};
            }
        }
        return {x: 1, y: -1};
    }

    /* Opposite hue of the two biomes' mix, fully saturated: it stands out
       against both tints, where a plain RGB inversion of a mid tone is grey. */
    function borderColor(colorA, colorB) {
        const rgb = hex => [1, 3, 5].map(i => parseInt(hex.slice(i, i + 2), 16));
        const [r, g, b] = rgb(colorA).map((channel, i) => (channel + rgb(colorB)[i]) / 2);
        const max = Math.max(r, g, b);
        const delta = max - Math.min(r, g, b);
        /* Hue in sixths of the circle, from whichever channel is strongest */
        let sixths = 0;
        if (delta > 0 && max === r) {
            sixths = ((g - b) / delta) % 6;
        } else if (delta > 0 && max === g) {
            sixths = (b - r) / delta + 2;
        } else if (delta > 0) {
            sixths = (r - g) / delta + 4;
        }
        return "hsl(" + Math.round(sixths * 60 + 180) + ", 100%, 50%)";
    }

    /* The cell's whole side in screen direction `toward` */
    function side(rect, toward, color) {
        const box = boxOf(rect);
        const centre = {x: box.x + box.size / 2, y: box.y + box.size / 2};
        const sideX = centre.x + toward.x * box.size / 2;
        const sideY = centre.y + toward.y * box.size / 2;
        return line(toward.x !== 0
            ? {x1: sideX, x2: sideX, y1: box.y, y2: box.y + box.size}
            : {x1: box.x, x2: box.x + box.size, y1: sideY, y2: sideY}, color);
    }

    function line(ends, color) {
        const element = document.createElementNS(SVG_NS, "line");
        Object.entries(ends).forEach(([name, value]) => element.setAttribute(name, value));
        element.setAttribute("class", "biome-border");
        element.setAttribute("stroke", color);
        element.setAttribute("stroke-width", BORDER_WIDTH);
        element.setAttribute("stroke-linecap", "round");
        element.setAttribute("pointer-events", "none");
        return element;
    }

    function boxOf(rect) {
        return {x: +rect.getAttribute("x"), y: +rect.getAttribute("y"), size: +rect.getAttribute("width")};
    }

    function fillLegend(shownIds, biomes) {
        legend.replaceChildren(...[...shownIds].map(function(id) {
            const chip = document.createElement("span");
            chip.className = "biome-chip";
            chip.style.setProperty("--biome-color", biomes[id].color);
            chip.textContent = biomes[id].name;
            return chip;
        }));
        legend.hidden = !on || shownIds.size === 0;
    }

    window.paintBiomes = function() {
        const data = window.tiledBiomes || {biomes: {}, cells: {}};
        const shownIds = new Set();
        const rects = new Map();
        document.querySelectorAll("#map-view-container .biome-border").forEach(line => line.remove());
        document.querySelectorAll("#map-view-container rect.case[data-coords]").forEach(function(rect) {
            const biomeIds = data.cells[rect.dataset.coords];
            rects.set(rect.dataset.coords, rect);
            paintCell(rect, biomeIds, data.biomes);
            if (on && biomeIds) biomeIds.forEach(id => shownIds.add(id));
        });
        if (on) drawBorders(rects, data.cells, data.biomes);
        fillLegend(shownIds, data.biomes);
    };

    button.addEventListener("click", function() {
        on = !on;
        button.classList.toggle("active", on);
        window.paintBiomes();
    });
});
</script>
<style>
#toggle-biomes.active {
    background: #3f6b4f;
    color: #fff;
}
.biome-legend {
    display: inline-flex;
    flex-wrap: wrap;
    gap: 4px;
    margin-left: 6px;
    vertical-align: middle;
}
.biome-chip {
    border-left: 12px solid var(--biome-color);
    background: rgba(255, 255, 255, 0.85);
    padding: 0 6px;
    font-size: 12px;
}
#toggle-blocked-tiles.active {
    background: #c0392b;
    color: #fff;
}
</style>




