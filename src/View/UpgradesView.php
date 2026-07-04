<?php

namespace App\View;

use Classes\Player;
use Classes\Str;

/**
 * Table d'amélioration des caractéristiques, extraite d'upgrades.php
 * pour être rendue soit en page complète (upgrades.php), soit en
 * fragment dans un panneau du HUD (load_upgrades.php). Contenu déplacé
 * tel quel ; la grille de coûts et son calcul vivent ici pour être
 * partagés avec scripts/upgrades/carac.php.
 */
final class UpgradesView
{
    /** Grille de coûts par carac : [coût initial, rangs 2-3, rangs 4+]. */
    public const TRIO = [
        'pv' => [4, 2, 1],
        'ct' => [110, 50, 30],
        'f' => [120, 55, 30],
        'agi' => [95, 45, 25],
        'e' => [120, 55, 30],
        'pm' => [5, 3, 1],
        'fm' => [100, 50, 30],
        'm' => [110, 55, 35],
        'a' => [800, 200, 100],
        'mvt' => [100, 50, 30],
        'r' => [40, 30, 15],
        'rm' => [50, 40, 20],
        'cc' => [100, 50, 30],
        'p' => [110, 85, 78],
        'spd' => [400, 100, 50],
    ];

    /**
     * Coût du prochain rang : plein tarif au premier, dégressif ensuite.
     *
     * @param array<int, int> $progress entrée de TRIO
     */
    public static function returnCost(array $progress, int $upgraded): int
    {
        $next = $upgraded + 1;

        $total = $progress[0];

        for ($i = 1; $i < $next; $i++) {

            if ($i < 3) {

                $total = $total + $progress[1];
            } else {

                $total = $total + $progress[2];
            }
        }

        return $total;
    }

    public static function render(Player $player): void
    {
        ob_start();

        echo '
        <table class="box-shadow marbre" border="1" align="center" style="position:relative; margin-top: 60px;">';

        echo '<tr><th>Carac.</th><th>Valeur</th><th>Équipé</th><th>Reste</th><th>Coût</th><th><span class="ra ra-archery-target"></span></th></tr>';

        foreach (CARACS as $k => $e) {

            if ($k == 'ae' || $k == 'spd') {

                continue;
            }

            $cost = self::returnCost(self::TRIO[$k], $player->upgrades->$k);

            $color = 'green';
            $disabled = '';

            if ($cost > $player->row->pi) {

                $color = 'red';
                $disabled = 'disabled';
            }

            $carac = '';

            if ($player->caracs->$k > $player->nude->$k) {

                $carac = '<font color="blue">' . $player->caracs->$k . '</font>';
            } elseif ($player->caracs->$k < $player->nude->$k) {

                $carac = '<font color="red">' . $player->caracs->$k . '</font>';
            }

            $turn = '';

            if (isset($player->turn->$k)) {

                $turn = $player->turn->$k;
            }

            if (is_numeric($turn) && $turn < 1) {

                $turn = '<font color="red">' . $turn . '</font>';
            } elseif (is_numeric($turn) && $turn == $carac) {

                $turn = '<font color="blue">' . $turn . '</font>';
            }

            $debuff = '';

            if (!empty($player->debuffs->$k)) {

                $debuff = '<span class="ra ' . EFFECTS_RA_FONT[$player->debuffs->$k] . '"></span>';
            }

            echo '
            <tr>
                <th ' . self::getTooltip($k) . '>
                    ' . $e . '
                </th>
                <td>
                    ' . $player->nude->$k . '
                </td>
                <td>
                    ' . $carac . $debuff . '
                </td>
                <td>
                    ' . $turn . '
                </td>
                <td>
                    <font color="' . $color . '">' . $cost . 'Pi</font>
                </td>
                <td>
                    <button
                        data-carac="' . $k . '"
                        data-carac-name="' . CARACS[$k] . '"
                        ' . $disabled . '
                        class="upgrade"
                        >
                        +1
                    </button>
                </td>
            </tr>
            ';
        }

        echo '
        </table><br/>
        ';

        echo $player->row->pi . ' Points d\'investissement (Pi)<br/>';
        echo 'Familiarisez-vous avec les règles du jeu dans le <a href="https://age-of-olympia.net/wiki/doku.php?id=regles:combat" target="_blank">wiki</a>.<br/>';

        echo Str::minify(ob_get_clean());

        echo '<script src="js/upgrades.js"></script>';
    }

    private static function getTooltip(string $key): string
    {
        if (!isset(CARACS_TXT_LONG[$key])) {

            return '';
        }

        return 'tooltip="' . CARACS_TXT_LONG[$key] . '"';
    }
}
