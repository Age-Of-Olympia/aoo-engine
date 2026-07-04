<?php

namespace App\View\Hud;

use Classes\Str;

/**
 * Panneaux glissants du HUD (Phase 2, multi-panneaux) : deux slots
 * identiques dans lesquels le routeur (js/hud.js) charge les
 * sous-pages en fragments. Desktop : jusqu'à deux panneaux côte à
 * côte (ex. Inventaire + Banque pour les dépôts) ; mobile : un seul,
 * en bottom-sheet. L'attribution des slots est gérée par le routeur.
 */
final class PanelView
{
    private const SLOTS = 2;

    public static function render(): void
    {
        ob_start();

        for ($slot = 0; $slot < self::SLOTS; $slot++) {
            echo '<aside class="hud-panel" id="hud-panel-' . $slot . '" data-slot="' . $slot . '" aria-hidden="true">'
                . '<div class="hud-panel-head">'
                . '<span class="hud-panel-title"></span>'
                . '<button class="hud-panel-close" title="Fermer"><span class="ra ra-cancel"></span></button>'
                . '</div>'
                . '<div class="hud-panel-content"></div>'
                . '</aside>';
        }

        echo Str::minify(ob_get_clean());
    }
}
