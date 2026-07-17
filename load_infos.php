<?php
use App\Entity\Structure;
use App\Factory\PlayerFactory;
use App\View\InfosSheetView;
use App\View\StructureSheetView;

require_once('config.php');

/*
 * Fragment fiche de personnage pour le panneau glissant du HUD
 * (js/hud.js) : la fiche de base et les sous-vues réputation et
 * récompenses, sans enveloppe Ui.
 */

if (!isset($_GET['targetId']) || !is_numeric($_GET['targetId'])) {

    exit('error target id');
}

$player = PlayerFactory::active();
$player->get_data();

if (isset($_GET['reputation']) || isset($_GET['rewards'])) {

    /* Les corps réputation et récompenses (scripts/infos/*.php)
     * lisent l'objet legacy $target — même contrat qu'infos.php. */
    $target = PlayerFactory::legacy($_GET['targetId']);
    $target->get_data();

    include(isset($_GET['reputation'])
        ? 'scripts/infos/reputation.php'
        : 'scripts/infos/rewards.php');

    exit();
}

// Racine STI : personnage OU structure — chacun sa fiche.
$targetEntity = PlayerFactory::gameEntity((int) $_GET['targetId']);
if ($targetEntity === null) {
    exit('error target id');
}

if ($targetEntity instanceof Structure) {
    StructureSheetView::render($player, $targetEntity, hudPanel: true);
} else {
    InfosSheetView::render($player, $targetEntity, hudPanel: true);
}
