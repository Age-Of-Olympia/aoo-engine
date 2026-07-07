<?php

namespace App\View\Hud;

use App\Service\PlayerService;
use Classes\Log;
use Classes\Player;
use Classes\Str;

/**
 * Rendu des flux du panneau latéral du HUD (option newHud).
 *
 * Phase 1 : lecture seule, aucune nouvelle donnée — le flux « Général »
 * réutilise les messages du jour (players_logs, type mdj) et le flux
 * « Événements » la perception (type light), exactement comme logs.php.
 * Le vrai chat (saisie, transport) viendra dans une phase ultérieure.
 */
final class FeedRenderer
{
    public static function renderMdj(Player $player): string
    {
        $player->getCoords();
        $logs = Log::get($player, THREE_DAYS, 'mdj');

        if (empty($logs)) {
            return '<p class="hud-feed-empty">Aucun message du jour récent.</p>';
        }

        $playerService = new PlayerService($player->id);

        ob_start();
        foreach ($logs as $e) {
            /* Le contenu du mdj vit dans hiddenText, enrobé d'un
             * div.action-details — classe masquée globalement, donc on
             * la remplace pour afficher le texte dans le flux. */
            $text = str_replace('class="action-details"', 'class="hud-mdj-text"', (string) $e->hiddenText);

            echo '<div class="hud-feed-item hud-feed-item--mdj">'
                . '<strong>' . self::authorName($playerService, (int) $e->player_id) . '</strong>'
                . $text
                . '<div class="hud-feed-meta">' . self::humanDate((int) $e->time) . '</div>'
                . '</div>';
        }

        return Str::minify(ob_get_clean());
    }

    public static function renderEvents(Player $player): string
    {
        $player->getCoords();
        $logs = Log::get($player, THREE_DAYS, 'light');

        if (empty($logs)) {
            return '<p class="hud-feed-empty">Aucun évènement récent.</p>';
        }

        $playerService = new PlayerService($player->id);

        ob_start();
        foreach ($logs as $e) {
            $planJson = json()->decode('plans', $e->plan);
            $planName = is_object($planJson) ? $planJson->name : '?';

            /* data-time : js/hud.js s'en sert pour le compteur d'évènements
             * non lus (comparaison au dernier passage, localStorage). */
            echo '<div class="hud-feed-item" data-time="' . (int) $e->time . '">'
                . self::outcomeChip((string) $e->hiddenText)
                . '<span class="log-' . $e->type . '">' . $e->text . '</span>'
                . '<div class="hud-feed-meta">'
                . self::authorName($playerService, (int) $e->player_id)
                . ' · ' . self::humanDate((int) $e->time)
                . ' · ' . $planName
                . '</div>'
                . '</div>';
        }

        return Str::minify(ob_get_clean());
    }

    /**
     * Pastille réussite / échec : le détail d'action (hiddenText)
     * commence par le verdict — un indice visuel suffit pour lire le
     * flux d'un coup d'œil.
     */
    private static function outcomeChip(string $hiddenText): string
    {
        if ($hiddenText === '') {
            return '';
        }
        if (strpos($hiddenText, 'Réussite') !== false) {
            return '<span class="hud-feed-outcome hud-feed-outcome--ok" title="Réussite">✓</span>';
        }
        if (strpos($hiddenText, 'Echec') !== false || strpos($hiddenText, 'Échec') !== false) {
            return '<span class="hud-feed-outcome hud-feed-outcome--ko" title="Échec">✗</span>';
        }
        if (strpos($hiddenText, 'Impossible') !== false) {
            return '<span class="hud-feed-outcome hud-feed-outcome--na" title="Action impossible">•</span>';
        }

        return '';
    }

    private static function authorName(PlayerService $playerService, int $playerId): string
    {
        $author = $playerService->GetPlayer($playerId);
        $author->get_data(false);

        return '<a href="infos.php?targetId=' . $author->id . '">' . $author->data->name . '</a>';
    }

    /** Même humanisation de date que logs.php (Aujourd'hui / Hier / d/m/Y). */
    private static function humanDate(int $time): string
    {
        $date = date('d/m/Y', $time);

        if ($date == date('d/m/Y', time())) {
            $date = 'Aujourd\'hui';
        } elseif ($date == date('d/m/Y', time() - 86400)) {
            $date = 'Hier';
        }

        return $date . ' à ' . date('H:i', $time);
    }
}
