<?php

namespace App\View\Hud;

use App\View\MainView;
use App\View\MenuView;
use Classes\Player;

/**
 * Orchestrateur du nouveau HUD (option newHud) — Phase 1 desktop.
 *
 * Émet la grille CSS (css/hud.css) et y place les vues EXISTANTES sans
 * les modifier : MenuView devient le rail gauche par pur CSS, et la
 * sortie de MainView (#game-map, #ajax-data, #admin-coords, frères dans
 * le DOM) est re-parentée dans les cellules de la grille via un wrapper
 * en display:contents. observe.php, go.php et js/view.js restent
 * strictement inchangés — le clic sur une tuile continue d'injecter
 * dans #ajax-data, désormais stylé en bandeau bas sélection + actions.
 */
final class HudLayoutView
{
    /** Cache-busting des assets du HUD — à incrémenter à chaque modif CSS/JS. */
    private const VERSION = '20260704';

    public static function render(Player $player): void
    {
        echo '<link rel="stylesheet" href="css/hud.min.css?v=' . self::VERSION . '" />';

        echo '<div id="hud">';

        TopBarView::render($player);

        /* Le rail réutilise la sortie de MenuView telle quelle (ids
         * #show-caracs, #show-inventory… préservés pour le tutoriel) ;
         * seul le CSS la passe en colonne d'icônes. */
        echo '<nav id="hud-rail"><div id="menu">';
        MenuView::renderMenu();
        echo '</div></nav>';

        MinimapView::render($player);

        echo '<div id="hud-main">';
        MainView::render($player);
        echo '</div>';

        SidePanelView::render();

        echo '</div>';

        echo '<script src="js/hud.js?v=' . self::VERSION . '"></script>';
    }
}
