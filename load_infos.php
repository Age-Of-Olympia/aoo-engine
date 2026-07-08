<?php
use App\Factory\PlayerFactory;
use App\View\InfosSheetView;

require_once('config.php');

/*
 * Fragment fiche de personnage pour le panneau glissant du HUD
 * (js/hud.js) : la fiche de base et la sous-vue réputation, sans
 * enveloppe Ui. Les récompenses restent une page complète.
 */

if (!isset($_GET['targetId']) || !is_numeric($_GET['targetId'])) {

    exit('error target id');
}

$player = PlayerFactory::active();
$player->get_data();

if (isset($_GET['reputation'])) {

    /* Le corps réputation (scripts/infos/reputation.php) lit l'objet
     * legacy $target — même contrat qu'infos.php. */
    $target = PlayerFactory::legacy($_GET['targetId']);
    $target->get_data();

    include('scripts/infos/reputation.php');

    exit();
}

$targetEntity = PlayerFactory::entity((int) $_GET['targetId']);
if ($targetEntity === null) {
    exit('error target id');
}

InfosSheetView::render($player, $targetEntity, hudPanel: true);
