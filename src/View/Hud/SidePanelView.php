<?php

namespace App\View\Hud;

use Classes\Str;

/**
 * Panneau latéral droit du HUD : onglets Général (messages du jour) et
 * Événements (perception). Les flux sont chargés en AJAX par js/hud.js
 * depuis load_chat.php / load_events.php — voir FeedRenderer.
 *
 * L'onglet Général propose une saisie façon chat qui change le message
 * du jour du joueur via le endpoint existant account.php?mdj — pas de
 * nouveau back-end, chaque changement alimente le flux (log mdj).
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
            . '<form id="hud-mdj-form" autocomplete="off">'
            . '<input type="text" id="hud-mdj-input" maxlength="255" placeholder="Votre message du jour…" />'
            . '<button type="submit" title="Publier"><span class="ra ra-quill-ink"></span></button>'
            . '</form>'
            . '</aside>';

        echo Str::minify(ob_get_clean());
    }
}
