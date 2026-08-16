<?php

namespace App\View;

use App\Service\PlayerCaracsService;
use Classes\Player;
use Classes\Str;

/**
 * The carac upgrade table, rendered either as a full page (upgrades.php)
 * or as a fragment inside a HUD panel (load_upgrades.php).
 *
 * Reassignment (war school) reads the SAME table: same rows, same
 * equipped values, same cost grid — only the buying column changes,
 * selling one more rank or buying one back. Add a mode here rather than
 * a copy: whatever touches the caracs table then reaches both screens.
 * The cost grid itself lives in PlayerCaracsService, with the endpoints
 * that charge it.
 */
final class UpgradesView
{
    /** Buy the next rank, with Pi. */
    public const MODE_UPGRADE = 'upgrade';

    /** Forget the last rank bought: the Pi come back, the gold goes. */
    public const MODE_REASSIGN = 'reassign';

    /**
     * @param bool $hudPanel HUD panel rendering: the « Reste » column
     *                       goes (the top band pills already show it) and
     *                       « Coût » merges with the +1 into an
     *                       « Améliorer (X Pi) » button. The legacy page
     *                       (upgrades.php) keeps the six columns.
     * @param string $mode   self::MODE_UPGRADE or self::MODE_REASSIGN
     */
    public static function render(Player $player, bool $hudPanel = false, string $mode = self::MODE_UPGRADE): void
    {
        $caracsService = new PlayerCaracsService();
        $reassign = ($mode === self::MODE_REASSIGN);

        /* Reassignment is paid in gold: the purse decides which rows are
         * playable, as the Pi do for the rows one upgrades. */
        $gold = $reassign ? (int) $player->get_gold() : 0;

        ob_start();

        /* A title and the purse line already hold the top of the
         * reassignment screen; on upgrades.php the table arrives alone. */
        $topMargin = $reassign ? '20px' : '60px';

        echo '
        <table class="box-shadow marbre" border="1" align="center" style="position:relative; margin-top: ' . $topMargin . ';">';

        if ($reassign) {

            echo '<tr><th>Carac.</th><th>Valeur</th><th>Équipé</th><th>Rangs</th><th><span class="ra ra-archery-target"></span></th></tr>';
        } elseif ($hudPanel) {

            echo '<tr><th>Carac.</th><th>Valeur</th><th>Équipé</th><th><span class="ra ra-archery-target"></span></th></tr>';
        } else {

            echo '<tr><th>Carac.</th><th>Valeur</th><th>Équipé</th><th>Reste</th><th>Coût</th><th><span class="ra ra-archery-target"></span></th></tr>';
        }

        foreach (CARACS as $k => $e) {

            if ($k == 'ae' || $k == 'spd') {

                continue;
            }

            $carac = '';

            if ($player->caracs->$k > $player->nude->$k) {

                $carac = '<font color="blue">' . $player->caracs->$k . '</font>';
            } elseif ($player->caracs->$k < $player->nude->$k) {

                $carac = '<font color="red">' . $player->caracs->$k . '</font>';
            }

            $debuff = '';

            if (!empty($player->debuffs->$k)) {

                $debuff = '<span class="ra ' . $player->effectService->getIcon($player->debuffs->$k) . '"></span>';
            }

            if ($reassign) {

                $ranks = (int) ($player->upgrades->$k ?? 0);

                /* Buying a rank back costs what that rank cost: the Pi it
                 * took come back, its price is paid in gold. No rank
                 * bought, nothing to give back. */
                $cost = $ranks > 0 ? $caracsService->returnCost($k, $ranks - 1) : 0;
                $color = ($cost > $gold) ? 'red' : 'green';
                $disabled = ($ranks < 1 || $cost > $gold) ? 'disabled' : '';
                $label = $ranks > 0
                    ? 'Réassigner (<font color="' . $color . '">' . $cost . ' Po</font>)'
                    : 'Réassigner';

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
                        ' . $ranks . '
                    </td>
                    <td>
                        <button
                            data-carac="' . $k . '"
                            data-carac-name="' . CARACS[$k] . '"
                            ' . $disabled . '
                            class="reassign"
                            >
                            ' . $label . '
                        </button>
                    </td>
                </tr>
                ';

                continue;
            }

            $cost = $caracsService->returnCost($k, $player->upgrades->$k);

            $color = 'green';
            $disabled = '';

            if ($cost > $player->row->pi) {

                $color = 'red';
                $disabled = 'disabled';
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

            if ($hudPanel) {

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
                        <button
                            data-carac="' . $k . '"
                            data-carac-name="' . CARACS[$k] . '"
                            ' . $disabled . '
                            class="upgrade"
                            >
                            Améliorer (<font color="' . $color . '">' . $cost . ' Pi</font>)
                        </button>
                    </td>
                </tr>
                ';

                continue;
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

        if (!$reassign) {

            echo $player->row->pi . ' Points d\'investissement (Pi)<br/>';
            echo 'Familiarisez-vous avec les règles du jeu dans le <a href="https://age-of-olympia.net/wiki/doku.php?id=regles:combat" target="_blank">wiki</a>.<br/>';
        }

        echo Str::minify(ob_get_clean());

        if ($reassign) {

            echo '<script src="js/reassign.js?v=20260816"></script>';

            return;
        }

        echo '<script src="js/upgrades.js?v=20260715"></script>';
    }

    private static function getTooltip(string $key): string
    {
        if (!isset(CARACS_TXT_LONG[$key])) {

            return '';
        }

        return 'tooltip="' . CARACS_TXT_LONG[$key] . '"';
    }
}
