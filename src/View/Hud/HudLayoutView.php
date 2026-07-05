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
    private const VERSION = '20260706i';

    public static function render(Player $player): void
    {
        echo '<link rel="stylesheet" href="css/hud.min.css?v=' . self::VERSION . '" />';

        echo '<div id="hud">';

        TopBarView::render($player);

        /* Le rail réutilise la sortie de MenuView telle quelle (ids
         * #show-caracs, #show-inventory… préservés pour le tutoriel) ;
         * seul le CSS la passe en colonne d'icônes. Artisanat et Banque
         * sont des entrées propres au HUD (panneaux indépendants) —
         * js/hud.js les repositionne juste après Inventaire ; le menu
         * hérité n'est pas modifié. */
        echo '<nav id="hud-rail"><div id="menu">';
        MenuView::renderMenu();
        echo '<a href="inventory.php?craft" id="show-craft" title="Artisanat"><button><span class="ra ra-forging"></span></button></a>'
            . '<a href="inventory.php?bank" id="show-bank" title="Banque"><button><span class="ra ra-gold-bar"></span></button></a>'
            . '<a href="upgrades.php?spells" id="show-spells" title="Sorts &amp; Techniques"><button><span class="ra ra-fairy-wand"></span></button></a>';
        echo '</div></nav>';

        MinimapView::render($player);

        echo '<div id="hud-main">';
        MainView::render($player);
        echo '</div>';

        SidePanelView::render();

        /* Panneau d'actions (bandeau bas, sous le panneau latéral) :
         * js/hud.js y déplace la .card-actions injectée par observe.php
         * dans #ajax-data — la sélection reste dans #ajax-data, les
         * boutons d'action vivent ici, comme sur le wireframe. */
        echo '<div id="hud-actions"></div>';

        PanelView::render();

        /* Widgets mobile (masqués ≥1024px) : bulle de chat flottante
         * (dernier message + accès au panneau latéral en sheet),
         * pagination du carrousel bas, fond de fermeture du tiroir. */
        echo '<button id="hud-bubble" title="Chat &amp; évènements">'
            . '<span class="ra ra-speech-bubbles"></span>'
            . '<span id="hud-bubble-text"></span>'
            . '<span id="hud-bubble-badge" class="hud-badge" style="display:none;"></span>'
            . '</button>';
        echo '<div id="hud-dots"></div>';
        echo '<div id="hud-backdrop"></div>';

        echo '</div>';

        echo '<script src="js/hud.js?v=' . self::VERSION . '"></script>';
    }
}
