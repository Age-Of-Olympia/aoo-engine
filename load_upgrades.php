<?php
use App\Factory\PlayerFactory;
use App\View\UpgradesView;
use Classes\Str;

require_once('config.php');

/*
 * Fragments Améliorations / Sorts & Techniques pour les panneaux du
 * HUD (js/hud.js). Mêmes corps qu'upgrades.php sans l'enveloppe Ui ;
 * le tampon externe est requis par scripts/upgrades/spells.php qui le
 * vide lui-même (Str::minify(ob_get_clean())).
 */

ob_start();

$player = PlayerFactory::legacy($_SESSION['playerId']);

$player->get_data();

$player->get_row();

$player->get_caracs();

if (isset($_GET['spells'])) {

    include('scripts/upgrades/spells.php');
    exit();
}

UpgradesView::render($player);

echo Str::minify(ob_get_clean());
