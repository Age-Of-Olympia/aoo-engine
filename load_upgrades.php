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

/* Progression d'XP au-dessus de la table d'amélioration : la barre du
 * volet de caracs hérité vit désormais ici (l'entrée Caractéristiques
 * du rail ouvre directement ce panneau), avec le portefeuille de Pi
 * que les améliorations dépensent. */
$pct = Str::calculate_xp_percentage($player->data->xp, $player->data->rank);
echo '<div class="hud-xp-progress">'
    . '<div class="progress-bar">'
    . '<div class="bar" style="width: ' . $pct . '%;">&nbsp;</div>'
    . '<div class="text">Xp : ' . $player->data->xp . ' / ' . Str::get_next_xp($player->data->rank) . '</div>'
    . '</div>'
    . '<div class="hud-xp-pi">Rang ' . $player->data->rank . ' · Pi : ' . $player->data->pi . '</div>'
    . '</div>';

UpgradesView::render($player, hudPanel: true);

echo Str::minify(ob_get_clean());
