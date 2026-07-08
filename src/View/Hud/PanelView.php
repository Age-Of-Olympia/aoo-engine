<?php

namespace App\View\Hud;

use Classes\Str;

/**
 * Panneau glissant du HUD (Phase 2) : un slot unique dans lequel le
 * routeur (js/hud.js) charge les sous-pages en fragments — ouvrir une
 * autre entrée REMPLACE le panneau courant (décision UX : deux
 * panneaux côte à côte étaient pénibles à manipuler). Le markup garde
 * la forme slot/data-slot pour pouvoir rouvrir la question plus tard.
 */
final class PanelView
{
    private const SLOTS = 1;

    public static function render(): void
    {
        ob_start();

        for ($slot = 0; $slot < self::SLOTS; $slot++) {
            echo '<aside class="hud-panel" id="hud-panel-' . $slot . '" data-slot="' . $slot . '" aria-hidden="true">'
                . '<div class="hud-panel-head">'
                /* Retour au panneau remplacé (Inventaire → Artisanat…) :
                 * js/hud.js tient la pile et masque la flèche sans
                 * historique. */
                . '<button class="hud-panel-back" title="Panneau précédent" style="display:none;"><span class="ra ra-sideswipe"></span></button>'
                . '<span class="hud-panel-title"></span>'
                . '<button class="hud-panel-close" title="Fermer"><span class="ra ra-cancel"></span></button>'
                . '</div>'
                . '<div class="hud-panel-content"></div>'
                . '</aside>';
        }

        echo Str::minify(ob_get_clean());
    }
}
