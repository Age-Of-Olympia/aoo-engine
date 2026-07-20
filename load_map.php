<?php

use App\Factory\PlayerFactory;
use App\Service\ViewService;
use App\Tutorial\TutorialHelper;
use Classes\Db;
use Classes\Str;

require_once('config.php');

/*
 * Fragment carte pour le panneau glissant du HUD (js/hud.js) : les
 * couches PNG pré-générées empilées (monde sur le plan monde, carte
 * locale ailleurs) avec le marqueur de position — même matière que la
 * minimap, en pleine largeur de panneau. Les options d'affichage
 * (couches, admin) restent sur la page complète map.php.
 */

$player = PlayerFactory::legacy(TutorialHelper::getActivePlayerId());
$coords = $player->getCoords();

$planJson = json()->decode('plans', $coords->plan);
$planId = is_object($planJson) ? ($planJson->id ?? $coords->plan) : $coords->plan;
$planName = is_object($planJson) ? $planJson->name : $coords->plan;

$viewService = new ViewService(new Db(), $coords->x, $coords->y, $coords->z, $player->id, $planId);

if ($viewService->isWorldPlan()) {
    $layers = ['tiles', 'elements', 'locations', 'routes'];
    $mapResult = $viewService->getGlobalMap();
    if (!isset($mapResult['tiles'])) {
        $viewService->generateGlobalMap($layers);
        $mapResult = $viewService->getGlobalMap();
    }
    $fullHref = 'map.php?world';
    $title = 'Olympia';
} else {
    $layers = ['tiles', 'elements', 'resources', 'routes'];
    $mapResult = $viewService->getLocalMap();
    if (!isset($mapResult['tiles'])) {
        $viewService->generateLocalMap($layers);
        $mapResult = $viewService->getLocalMap();
    }
    $fullHref = 'map.php?local=1';
    $title = $planName;
}

ob_start();

$imgs = '';
$aspect = '';
foreach ($layers as $layer) {
    $imagePath = $mapResult[$layer]['imagePath'] ?? null;
    if ($imagePath && file_exists($_SERVER['DOCUMENT_ROOT'] . $imagePath)) {
        if ($aspect === '') {
            [$w, $h] = getimagesize($_SERVER['DOCUMENT_ROOT'] . $imagePath);
            $aspect = (string) round($w / max(1, $h), 4);
        }
        $imgs .= '<img src="' . $imagePath . '" alt="" />';
    }
}

if ($imgs === '') {

    echo '<p class="hud-feed-empty">La carte n\'est pas encore générée.</p>';
} else {

    $marker = '';
    $pos = $viewService->getPositionPercent();
    if ($pos !== null) {
        $marker = '<span class="hud-minimap-me" title="Vous êtes ici" style="left: '
            . round($pos['x'], 2) . '%; top: ' . round($pos['y'], 2) . '%;"></span>';
    }

    $worldLink = $viewService->isWorldPlan()
        ? ''
        : '<a href="map.php?world">Carte du monde</a> · ';

    echo '<div class="hud-map-fragment">'
        . '<h2 class="hud-panel-topic-title">' . $title . '</h2>'
        . '<div class="hud-map-stack" style="aspect-ratio: ' . ($aspect !== '' ? $aspect : '1') . ';">'
        . $imgs . $marker
        . '</div>'
        . '<div class="hud-map-fragment-links">'
        . $worldLink
        . '<a href="' . $fullHref . '">Page complète &amp; options</a>'
        . '</div>'
        . '</div>';
}

echo Str::minify(ob_get_clean());
