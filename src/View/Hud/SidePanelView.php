<?php

namespace App\View\Hud;

use Classes\Str;

/**
 * Panneau latéral droit du HUD : onglets Général (messages du jour) et
 * Événements (perception). Les flux sont chargés en AJAX par js/hud.js
 * depuis load_chat.php / load_events.php — voir FeedRenderer.
 *
 * Lecture seule en Phase 1 : pas de champ de saisie tant que le vrai
 * chat n'existe pas, pour ne pas laisser croire qu'on peut écrire.
 */
final class SidePanelView
{
    public static function render(): void
    {
        ob_start();

        echo '<aside id="hud-side">'
            . '<div class="hud-tabs">'
            . '<button class="hud-tab hud-tab--active" data-tab="mdj">Général</button>'
            . '<button class="hud-tab" data-tab="events">Événements'
            . '<span id="hud-events-badge" class="hud-badge" style="display:none;"></span></button>'
            . '<button id="hud-feed-refresh" title="Rafraîchir"><span class="ra ra-cycle"></span></button>'
            . '<a id="hud-feed-full" href="logs.php?light" title="Page complète des évènements (perception, messages du jour, quêtes)"><button><span class="ra ra-book"></span></button></a>'
            . '</div>'
            . '<div id="hud-feed-mdj" class="hud-feed">Chargement…</div>'
            . '<div id="hud-feed-events" class="hud-feed" style="display:none;">Chargement…</div>'
            . '</aside>';

        echo Str::minify(ob_get_clean());
    }
}
