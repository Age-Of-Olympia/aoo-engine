<?php
use App\Factory\PlayerFactory;
use App\View\AccountView;

require_once('config.php');

/*
 * Fragment Options du profil pour le panneau du HUD (js/hud.js).
 * Même contenu qu'account.php sans l'enveloppe Ui ; les bascules
 * d'options (js/account.js) continuent de POSTer vers account.php,
 * les sous-pages (portraits, mdj, histoire…) restent plein-page.
 */

$player = PlayerFactory::legacy($_SESSION['playerId']);

$player->get_data();

AccountView::render($player, AccountView::buildOptions($player));
