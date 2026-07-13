<?php
use App\Factory\PlayerFactory;
use App\View\AccountView;

require_once('config.php');

/*
 * Fragments Profil pour le panneau du HUD (js/hud.js) : mêmes corps
 * qu'account.php sans l'enveloppe Ui — la racine (options) et les
 * sous-pages (mot de passe, mail, galeries, histoire). Les scripts
 * inclus gèrent aussi leur POST : le routeur de formulaires de
 * js/hud.js envoie les soumissions ici et affiche la réponse dans le
 * panneau. Le mdj reste hors panneau (formulaire du panneau latéral).
 */

$player = PlayerFactory::legacy($_SESSION['playerId']);

$player->get_data();

if (isset($_GET['portraits'])) {

    include('scripts/account/portraits.php');
    exit();
}

if (isset($_GET['avatars'])) {

    include('scripts/account/avatars.php');
    exit();
}

if (isset($_GET['story'])) {

    include('scripts/account/story.php');
    exit();
}

if (isset($_GET['changeMail'])) {

    include('scripts/account/change_mail.php');
    exit();
}

if (isset($_GET['changePsw'])) {

    include('scripts/account/change_psw.php');
    exit();
}

AccountView::render($player, AccountView::buildOptions($player), hudPanel: true);
