<?php

namespace App\View\Hud;

use Classes\Str;

/**
 * Panneau glissant du HUD (Phase 2) : conteneur unique dans lequel le
 * routeur de panneaux (js/hud.js) charge les sous-pages en fragments
 * (load_inventory.php, load_infos.php…). Glisse depuis la gauche
 * (côté rail) par-dessus la carte, chat et barre de statut restent
 * visibles — cf. wireframe desktop-panel.
 */
final class PanelView
{
    public static function render(): void
    {
        ob_start();

        echo '<aside id="hud-panel" aria-hidden="true">'
            . '<div class="hud-panel-head">'
            . '<span id="hud-panel-title"></span>'
            . '<button id="hud-panel-close" title="Fermer"><span class="ra ra-cancel"></span></button>'
            . '</div>'
            . '<div id="hud-panel-content"></div>'
            . '</aside>';

        echo Str::minify(ob_get_clean());
    }
}
