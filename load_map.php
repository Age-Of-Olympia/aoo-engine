<?php

use App\Factory\PlayerFactory;
use App\Service\RaceService;
use App\Service\ViewService;
use App\Tutorial\TutorialHelper;
use Classes\Db;
use Classes\Str;

require_once('config.php');

/*
 * Map panel of the HUD (js/hud.js): the world map, or the local map of
 * the territory the player stands in, as pre-generated PNG layers
 * stacked in one box with the position marker. Everything the legacy
 * map.php offered lives here: the Monde / territory tabs, the layer
 * toggles (client side — a layer is one element of the stack, shown or
 * hidden), the hell and unmapped-territory notices, the territory's PNJ
 * and the admin link.
 */

$player = PlayerFactory::legacy(TutorialHelper::getActivePlayerId());
$coords = $player->getCoords();

$planJson = plans()->read($coords->plan);
$planId = $coords->plan;
$planName = is_object($planJson) ? $planJson->name : $coords->plan;

$zName = '';
if (is_object($planJson) && count($planJson->z_levels ?? []) > 1) {
    foreach ($planJson->z_levels as $zLevel) {
        if ($zLevel->z === $coords->z) {
            $zName = ' - ' . ($zLevel->{'z-name'} ?? 'Niveau ' . $coords->z);
            break;
        }
    }
}

$viewService = new ViewService(new Db(), $coords->x, $coords->y, $coords->z, $player->id, $planId, $player->traitBonus('vue'));

$onWorld = $viewService->isWorldPlan();
$showWorld = $onWorld || isset($_GET['world']);

ob_start();

echo '<div class="hud-map-fragment">';

/* Tabs: the world, and the territory the player stands in */
if ($onWorld) {
    $head = '<h2 class="hud-panel-topic-title">' . $planName . '</h2>';
} else {
    $tab = static fn (string $href, string $label, bool $current): string =>
        '<a href="' . $href . '"' . ($current ? ' class="hud-tab--current" aria-current="page"' : '') . '>'
        . '<button>' . $label . '</button></a>';

    $head = '<div class="hud-tabs-row">'
        . $tab('map.php?world', 'Monde', $showWorld)
        . $tab('map.php?local=1', $planName . $zName, !$showWorld)
        . '</div>';
}

$notice = null;
if ($planId === plans()->deathPlan()) {
    $notice = 'On ne va pas vous faire un dessin, vous êtes bien dans le royaume des morts.<br>'
        . 'Les cartographes ne s\'aventurent pas ici. Vous devrez trouver votre chemin seul.<br>'
        . 'On sait quand même que la sortie est en 0,0. Une prière ne serait peut-être pas de trop.';
} elseif (!$showWorld && !$viewService->isLocalMapAvailable()) {
    $notice = 'Ces lieux n\'ont pas été cartographiés. Avancez à l\'aveugle… ou faites demi-tour.';
}

if ($notice !== null) {
    echo $head . '<p class="hud-map-notice"><em>' . $notice . '</em></p></div>';
    echo Str::minify(ob_get_clean());
    exit();
}

/* Static layers in drawing order: label, and whether it shows by default.
 * Buildings, grid and border follow the player's board options. */
$hideBuildings = (bool) $player->have_option('hideBuildingsLayer');
$hideGrid = (bool) $player->have_option('hideGrid');
$hideBoardCoords = (bool) $player->have_option('hideBoardCoords');
$layers = $showWorld
    ? [
        'tiles' => ['Terrain', true],
        'elements' => ['Éléments', true],
        'routes' => ['Routes', true],
        'buildings' => ['Bâtiments', !$hideBuildings],
        /* Labels last, so routes and buildings never hide them */
        'locations' => ['Lieux', true],
        'coordinates' => ['Grille', !$hideGrid],
    ]
    : [
        'tiles' => ['Terrain', true],
        'elements' => ['Éléments', true],
        'foregrounds' => ['Décor', true],
        'resources' => ['Ressources', true],
        'routes' => ['Routes', true],
        'buildings' => ['Bâtiments', !$hideBuildings],
        'coordinates' => ['Grille', !$hideGrid],
    ];

$mapResult = $showWorld ? $viewService->getGlobalMap() : $viewService->getLocalMap();

/* Layers never generated yet (fresh environment, new layer kind):
 * generated once, later hits read them from disk */
$missing = array_values(array_diff(array_keys($layers), array_keys($mapResult)));
$rulers = $viewService->getMapRulers($showWorld);
/* World layers drawn before the canvas followed the plan's proportions
 * (700x466): redrawn once, or they no longer line up with the players */
$tilesPath = $mapResult['tiles']['imagePath'] ?? null;
if ($showWorld && $rulers !== null && $tilesPath && file_exists($_SERVER['DOCUMENT_ROOT'] . $tilesPath)) {
    [$tilesW, $tilesH] = getimagesize($_SERVER['DOCUMENT_ROOT'] . $tilesPath);
    if ($tilesW !== $rulers['width'] || $tilesH !== $rulers['height']) {
        $missing = array_keys($layers);
    }
}
if ($missing) {
    $showWorld ? $viewService->generateGlobalMap($missing) : $viewService->generateLocalMap($missing);
    $mapResult = $showWorld ? $viewService->getGlobalMap() : $viewService->getLocalMap();
}

$imgs = '';
$toggles = '';
$aspect = '';
foreach ($layers as $layer => [$label, $shown]) {
    $imagePath = $mapResult[$layer]['imagePath'] ?? null;
    if (!$imagePath || !file_exists($_SERVER['DOCUMENT_ROOT'] . $imagePath)) {
        continue;
    }
    if ($aspect === '') {
        [$w, $h] = getimagesize($_SERVER['DOCUMENT_ROOT'] . $imagePath);
        $aspect = (string) round($w / max(1, $h), 4);
    }
    $imgs .= '<img src="' . $imagePath . '" alt="" data-layer="' . $layer . '"' . ($shown ? '' : ' hidden') . ' />';
    /* The terrain is the map itself: never switched off */
    if ($layer !== 'tiles') {
        $toggles .= '<label><input type="checkbox" data-layer="' . $layer . '"' . ($shown ? ' checked' : '') . '> ' . $label . '</label>';
    }
}

if ($imgs === '') {
    echo $head . '<p class="hud-feed-empty">La carte n\'est pas encore générée.</p></div>';
    echo Str::minify(ob_get_clean());
    exit();
}

/* The numbered border, drawn by the board's own code (js/hud.js) */
if ($rulers !== null) {
    $imgs .= '<svg class="hud-map-rulers" data-layer="rulers"'
        . ' data-rulers="' . htmlspecialchars((string) json_encode($rulers, JSON_FORCE_OBJECT), ENT_QUOTES) . '"'
        . ($hideBoardCoords ? ' hidden' : '') . '></svg>';
    $toggles .= '<label><input type="checkbox" data-layer="rulers"' . ($hideBoardCoords ? '' : ' checked') . '> Coordonnées du bord</label>';
}

/* Other players: a layer drawn per viewer (visibility rules) */
$showWorld ? $viewService->generateWorldPlayersLayer() : $viewService->generateLocalPlayersLayer();
$playersPath = ViewService::playerLayerPath($showWorld ? 'global' : 'local', (int) $player->id, 'players_layer.png');
if (file_exists($_SERVER['DOCUMENT_ROOT'] . $playersPath)) {
    $imgs .= '<img src="' . $playersPath . '?t=' . time() . '" alt="" data-layer="players" />';
    $toggles .= '<label><input type="checkbox" data-layer="players" checked> Tous les joueurs</label>';
}

/* The marker only on the map of the plan the player stands in */
$pos = $showWorld === $onWorld ? $viewService->getPositionPercent() : null;
if ($pos !== null) {
    $imgs .= '<span class="hud-minimap-me" data-layer="player" title="Vous êtes ici" style="left: '
        . round($pos['x'], 2) . '%; top: ' . round($pos['y'], 2) . '%;"></span>';
    $toggles .= '<label><input type="checkbox" data-layer="player" checked> Ma position</label>';
}

/* One bar: the tabs (or the plan name), then the layers menu — a
 * native <details>, the switches drop down over the map's corner */
echo '<div class="hud-map-bar">' . $head
    . '<details class="hud-map-layers"><summary>Couches</summary>'
    . '<div class="layer-controls hud-map-layers-pop">' . $toggles . '</div></details></div>'
    . '<div class="hud-map-frame"><div class="hud-map-stack" style="--map-ratio: ' . $aspect . ';">' . $imgs . '</div></div>';

if (!$showWorld && !empty($planJson->pnj)) {
    $pnj = PlayerFactory::legacy((int) $planJson->pnj);
    $pnj->get_data();
    $pnjRace = (new RaceService())->getRaceData($pnj->data->race);

    echo '<p class="hud-map-pnj">PNJ du territoire : <a href="infos.php?targetId=' . $pnj->id . '">' . $pnj->data->name . '</a>'
        . ' (' . ($pnjRace->name ?? $pnj->data->race) . ', rang ' . $pnj->data->rank . ')</p>';
}

if (\App\Service\AdminAuthorizationService::isAdmin()) {
    echo '<p class="hud-map-admin"><a href="admin/' . ($showWorld ? 'world_map.php' : 'local_maps.php') . '">Gérer les cartes (admin)</a></p>';
}

echo '</div>';
?>
<script>
(function () {
    /* Map border: the board's own rulers (js/hud.js drawRulers),
     * same band, font and offsets. The map is expressed in board units
     * (k = stack px per image px / board px per unit), so the border has
     * the board's on-screen size. Like the board's, it sits around the
     * map content, over the image margin, in room the stack leaves
     * through --map-ruler. The bands widen to fit the longest number.
     * The room stays when the border is hidden: the map keeps its size. */
    function drawMapRulers() {
        var boardScale = window.hudBoardScale ? window.hudBoardScale() : 0;
        if (!window.hudDrawRulers || !boardScale) {
            return;
        }
        var G = window.hudRulerBand;
        $('.hud-map-rulers').each(function () {
            var svg = this;
            var stack = svg.parentNode;
            var r = JSON.parse(svg.getAttribute('data-rulers'));
            $(svg).empty();
            /* Text is measured only when rendered */
            var hidden = svg.hasAttribute('hidden');
            svg.removeAttribute('hidden');

            var band = G;
            var probe = document.createElementNS('http://www.w3.org/2000/svg', 'text');
            probe.setAttribute('class', 'hud-svg-coord');
            svg.appendChild(probe);
            Object.keys(r.rows).forEach(function (y) {
                probe.textContent = y;
                band = Math.max(band, probe.getComputedTextLength() + 6);
            });
            svg.removeChild(probe);

            var room = band * boardScale;
            stack.style.setProperty('--map-ruler', room + 'px');
            var s = stack.getBoundingClientRect().width / r.width;
            var k = s / boardScale;
            if (!k) {
                if (hidden) {
                    svg.setAttribute('hidden', '');
                }
                return;
            }
            var inner = r.margin * s;
            svg.style.inset = (inner - room) + 'px';

            var cols = {};
            var rows = {};
            Object.keys(r.cols).forEach(function (x) { cols[x] = r.cols[x] * k; });
            Object.keys(r.rows).forEach(function (y) { rows[y] = r.rows[y] * k; });
            var p = [r.margin * k, r.margin * k, (r.width - 2 * r.margin) * k, (r.height - 2 * r.margin) * k];
            svg.setAttribute('viewBox', (p[0] - band) + ' ' + (p[1] - band) + ' ' + (p[2] + 2 * band) + ' ' + (p[3] + 2 * band));
            svg.appendChild(window.hudDrawRulers(p, band, cols, rows));
            if (hidden) {
                svg.setAttribute('hidden', '');
            }
        });
    }
    drawMapRulers();
    $(window).off('resize.hudMapRulers').on('resize.hudMapRulers', drawMapRulers);

    /* A layer is one element of the stack: toggling it needs no reload */
    $('.hud-map-layers input').on('change', function () {
        $('.hud-map-stack [data-layer="' + $(this).data('layer') + '"]').prop('hidden', !this.checked);
    });
})();
</script>
<?php

echo Str::minify(ob_get_clean());
