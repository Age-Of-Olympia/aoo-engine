<?php

namespace App\View\Hud;

use App\View\MainView;
use App\View\MenuView;
use Classes\Player;
use Classes\Ui;

/**
 * Orchestrateur du nouveau HUD (option newHud).
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
    private const VERSION = '20260722g';

    public static function render(Player $player): void
    {
        echo '<link rel="stylesheet" href="css/hud.min.css?v=' . self::VERSION . '" />';

        /* Papier filigrané « aootest » hors prod (même mécanisme que le
         * fond hérité, cf. aoo_app_background) : seule l'image change,
         * cadrage et calques du CSS conservés. Appliqué aussi au damier,
         * la feuille la plus visible de l'écran. */
        $paperBg = function_exists('aoo_paper_background') ? aoo_paper_background() : '/img/ui/paper/paper.jpg';
        if ($paperBg !== '/img/ui/paper/paper.jpg') {
            echo '<style>body:has(#hud),#hud #game-map{background-image:url(\'' . $paperBg . '\')}</style>';
        }

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
        /* Artisanat en sommeil (CRAFT_ENABLED) : l'entrée reviendra
         * portée par un bâtiment dédié — le code reste en place. */
        echo (Ui::craftEnabled() ? '<a href="inventory.php?craft" id="show-craft" title="Artisanat"><button><span class="ra ra-forging"></span></button></a>' : '')
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

        /* Calques d'affichage de la carte (façon applis de carto) :
         * médaillon boussole au-dessus du zoom, popover papier de
         * bascules. Chaque entrée bascule son option via account.php
         * (POST option) — showBlockedTiles s'applique à chaud, les
         * calques rendus côté serveur rechargent la vue (js/hud.js). */
        $mapLayers = [
            'raceHint'         => 'Indice de race',
            'raceHintMax'      => 'Indice de race maximale',
            'showBlockedTiles' => 'Cases infranchissables',
            'hideGrid'         => 'Masquer la grille',
            'noMask'           => 'Désactiver les masques météo',
            'hideBoardCoords'  => 'Masquer les coordonnées du bord',
            'hideLineOfFire'   => 'Masquer la ligne de tir',
            'hideBuildingsLayer' => 'Masquer les bâtiments (cartes)',
        ];
        echo '<div id="hud-layers">'
            . '<button id="hud-layers-btn" title="Options d\'affichage du plateau" aria-label="Options d\'affichage du plateau"></button>'
            . '<div id="hud-layers-pop" hidden>'
            . '<div class="hud-layers-title">Affichage</div>';
        foreach ($mapLayers as $option => $label) {
            $on = $player->have_option($option) ? ' hud-layer--on' : '';
            echo '<button class="hud-layer' . $on . '" data-option="' . $option . '">'
                . '<span class="hud-layer-dot"></span>' . $label . '</button>';
        }
        echo '</div></div>';

        PanelView::render();

        /* Widgets mobile (masqués ≥1024px) : pagination du carrousel
         * bas (le panneau latéral y est le volet Discussions) et fond
         * de fermeture du tiroir. */
        echo '<div id="hud-dots"></div>';
        echo '<div id="hud-backdrop"></div>';

        echo '</div>';

        echo '<script src="js/hud.js?v=' . self::VERSION . '"></script>';
    }
}
