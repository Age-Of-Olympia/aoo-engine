<?php

namespace App\View;

use Classes\Player;
use Classes\Str;

/**
 * Renders the player characteristics panel.
 *
 * Extracted from load_caracs.php so MenuView can inline the panel
 * server-side when the caracs_panel_open cookie is set, avoiding the
 * AJAX round-trip + layout shift that used to happen on every page
 * reload while the panel was "open".
 */
final class CaracsPanelRenderer
{
    public static function render(Player $player): string
    {
        $player->get_data();

        $caracsJson = $player->get_caracsJson();
        $turnJson = $player->get_turnJson();

        ob_start();

        echo '<table border="1" align="center" class="marbre" id="caracs-menu" style="position:relative;">';

        echo '<tr>';
        $i = 0;
        foreach (CARACS as $k => $e) {
            if ($k === 'spd') {
                continue;
            }
            $i++;
            echo '<th ' . self::getTooltip($k, $i) . '">' . $e . '</th>';
        }
        echo '<th ' . self::getTooltip('foi', $i + 1) . '">Foi</th>';
        echo '</tr>';

        echo '<tr>';
        foreach (CARACS as $k => $e) {
            if ($k === 'spd') {
                continue;
            }

            $left = '';
            if (isset($turnJson->$k)) {
                $left = $turnJson->$k . '/';
            }

            $idAttr = '';
            if ($k === 'mvt') {
                $idAttr = ' id="mvt-counter"';
            } elseif ($k === 'a') {
                $idAttr = ' id="action-counter"';
            }

            echo '<td' . $idAttr . '>' . $left . $caracsJson->$k . '</td>';
        }
        echo '<td>' . $player->data->pf . '</td>';
        echo '</tr>';

        $pct = Str::calculate_xp_percentage($player->data->xp, $player->data->rank);

        echo '<tr>';
        echo '<td colspan="' . (count(CARACS) - 8) . '">'
            . '<div class="progress-bar">'
            . '<div class="bar" style="width: ' . $pct . '%;">&nbsp;</div>'
            . '<div class="text">Xp: ' . $player->data->xp . '/' . Str::get_next_xp($player->data->rank) . '</div>'
            . '</div>'
            . '</td>';
        echo '<td colspan="2"><div style="white-space: nowrap;">Pi: ' . $player->data->pi . '</div></td>';
        echo '<td colspan="6"><div style="white-space: nowrap;"><a href="upgrades.php"><button>Améliorer mes caractéristiques</button></a></div></td>';
        echo '</tr>';

        echo '<tr><td colspan="' . count(CARACS) . '">Malus (' . $player->data->malus . '): -' . $player->data->malus . ' aux jets de défense.</td></tr>';
        echo '<tr><td colspan="' . count(CARACS) . '">Énergie (' . $player->data->energie . ').</td></tr>';

        echo '<tr>';
        if (!empty($caracsJson->esquive)) {
            $color = $caracsJson->esquive < 0 ? 'red' : 'blue';
            echo '<td colspan="' . count(CARACS) . '" style="color:' . $color . '">Esquive : ' . $caracsJson->esquive . '.</td>';
        }
        echo '</tr>';

        echo '</table>';

        return Str::minify(ob_get_clean());
    }

    private static function getTooltip(string $key, int $i): string
    {
        if (!isset(CARACS_TXT[$key])) {
            return '';
        }
        $flow = $i < 8 ? 'flow="right"' : 'flow="left"';
        return $flow . ' tooltip="' . CARACS_TXT[$key] . '"';
    }
}
