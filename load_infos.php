<?php
use App\Factory\PlayerFactory;
use App\View\InfosSheetView;

require_once('config.php');

/*
 * Fragment fiche de personnage pour le panneau glissant du HUD
 * (js/hud.js). Même contenu qu'infos.php sans l'enveloppe Ui ;
 * les sous-vues réputation/récompenses restent des pages complètes.
 */

if (!isset($_GET['targetId']) || !is_numeric($_GET['targetId'])) {

    exit('error target id');
}

$player = PlayerFactory::active();
$player->get_data();

$targetEntity = PlayerFactory::entity((int) $_GET['targetId']);
if ($targetEntity === null) {
    exit('error target id');
}

InfosSheetView::render($player, $targetEntity, hudPanel: true);
