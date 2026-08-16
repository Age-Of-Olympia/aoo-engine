<?php

namespace App\View\WarSchool;

use App\View\UpgradesView;
use Classes\Player;
use Classes\Str;

/**
 * War school reassignment counter: forget a carac rank to get back the
 * Pi it cost, against gold.
 *
 * The table is the upgrades one rendered in reassignment mode
 * (UpgradesView::MODE_REASSIGN); this view carries the heading only.
 * The counter being talked to travels in the panel URL (targetId), which
 * api/warschool/reassign.php checks again: the school that teaches is
 * the school that buys back.
 */
class ReassignationView
{
    public static function render(Player $player): void
    {
        $player->get_data();
        $player->get_row();
        $player->get_caracs();

        ob_start();

        echo '<h1>Réassignation</h1>';
        echo '<h2 class="ws-info">Vous avez ' . $player->row->pi . ' Pi et ' . $player->get_gold() . ' Po</h2>';

        echo '<details class="ws-info" style="cursor: pointer; margin-bottom: 20px; background: rgba(0,0,0,0.05); padding: 10px; border-radius: 5px;">';
        echo '<summary style="cursor: pointer; font-weight: bold;">Plus d\'informations sur la Réassignation</summary>';
        echo '<h3>La <strong>réassignation</strong> permet d\'oublier des caractéristiques pour récupérer les Pi investis dans ces dernières.</h3>';
        echo '<h3>Chaque réassignation d\'une caractéristique coûte des Po à hauteur de la valeur de l\'upgrade.</h3>';
        echo '</details>';

        echo Str::minify(ob_get_clean());

        UpgradesView::render($player, false, UpgradesView::MODE_REASSIGN);
    }
}
