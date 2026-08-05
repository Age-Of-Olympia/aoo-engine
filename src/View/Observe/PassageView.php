<?php

namespace App\View\Observe;

use Classes\Db;
use Classes\Player;

/**
 * Le passage sous ses pieds (déclencheur `tp` de SA case) : un bouton
 * pour l'emprunter à nouveau.
 *
 * Un escalier se prend en marchant dessus — mais l'arrivée d'un
 * escalier EST sa case (seule case au sol creusé d'une mine vierge,
 * View::get_free_coords_id_arround) : une fois dessus, aucun pas ne
 * repasse par le déclencheur, et l'on restait coincé en bas. Le bouton
 * rejoue le même déplacement vers sa propre case (go.php, distance 0) :
 * les déclencheurs de la case se déclenchent, le tp emmène.
 */
final class PassageView
{
    public static function render(Player $player, int $x, int $y, object $coords): void
    {
        if (
            $x !== (int) $player->coords->x || $y !== (int) $player->coords->y
            || (int) $coords->z !== (int) $player->coords->z
            || (string) $coords->plan !== (string) $player->coords->plan
        ) {
            return;
        }

        $res = (new Db())->exe(
            'SELECT t.params FROM map_triggers t INNER JOIN coords c ON c.id = t.coords_id
             WHERE c.x = ? AND c.y = ? AND c.z = ? AND c.plan = ? AND t.name = "tp"',
            array($x, $y, (int) $coords->z, (string) $coords->plan)
        );
        $row = $res->fetch_object();
        if ($row === null) {
            return;
        }

        /* Le sens du voyage donne son verbe au bouton : params
         * « x,y,z,plan », z numérique quand la destination change d'étage. */
        $destZ = explode(',', (string) $row->params)[2] ?? '';
        $label = 'Emprunter le passage';
        if (is_numeric($destZ)) {
            $label = ((int) $destZ > (int) $coords->z) ? 'Monter' : 'Descendre';
        }

        echo '<div class="case-infos"><div class="text">'
            . '<button class="action action--direct" id="take-passage" data-coords="' . $x . ',' . $y . '">'
            . '<span class="ra ra-hole-ladder"></span> <span class="action-name">' . $label . '</span></button>'
            . '</div></div>';
    }
}
