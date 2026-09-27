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

/* Static layers in drawing order: label, and whether it shows by default */
$hideBuildings = (bool) $player->have_option('hideBuildingsLayer');
$layers = $showWorld
    ? [
        'tiles' => ['Terrain', true],
        'elements' => ['Éléments', true],
        'coordinates' => ['Coordonnées', false],
        'locations' => ['Lieux', true],
        'routes' => ['Routes', true],
        'buildings' => ['Bâtiments', !$hideBuildings],
    ]
    : [
        'tiles' => ['Terrain', true],
        'elements' => ['Éléments', true],
        'foregrounds' => ['Décor', false],
        'resources' => ['Ressources', true],
        'routes' => ['Routes', true],
        'buildings' => ['Bâtiments', !$hideBuildings],
    ];

$mapResult = $showWorld ? $viewService->getGlobalMap() : $viewService->getLocalMap();

/* Layers never generated yet (fresh environment, new layer kind):
 * generated once, later hits read them from disk */
$missing = array_values(array_diff(array_keys($layers), array_keys($mapResult)));
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

/* Other players: a layer drawn per viewer (visibility rules) */
$showWorld ? $viewService->generateWorldPlayersLayer() : $viewService->generateLocalPlayersLayer();
$playersPath = ViewService::playerLayerPath($showWorld ? 'global' : 'local', (int) $player->id, 'players_layer.png');
if (file_exists($_SERVER['DOCUMENT_ROOT'] . $playersPath)) {
    $imgs .= '<img src="' . $playersPath . '?t=' . time() . '" alt="" data-layer="players" hidden />';
    $toggles .= '<label><input type="checkbox" data-layer="players"> Tous les joueurs</label>';
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

if ($player->have_option('isAdmin')) {
    echo '<p class="hud-map-admin"><a href="admin/' . ($showWorld ? 'world_map.php' : 'local_maps.php') . '">Gérer les cartes (admin)</a></p>';
}

echo '</div>';
?>
<script>
    /* A layer is one element of the stack: toggling it needs no reload */
    $('.hud-map-layers input').on('change', function () {
        $('.hud-map-stack [data-layer="' + $(this).data('layer') + '"]').prop('hidden', !this.checked);
    });
</script>
<?php

echo Str::minify(ob_get_clean());
