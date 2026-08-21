<?php

namespace App\View\Hud;

use App\Service\PlayerService;
use Classes\Log;
use Classes\Player;
use Classes\Str;

/**
 * Rendu des flux du panneau latéral du HUD (option newHud).
 *
 * Lecture seule, aucune nouvelle donnée — le flux « Général »
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
            echo '<div class="hud-feed-item hud-feed-item--mdj">'
                . '<strong>' . self::authorName($playerService, (int) $e->player_id) . '</strong>'
                . '<div class="hud-mdj-text">' . self::mdjBody((string) $e->hiddenText) . '</div>'
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
            $planJson = plans()->read($e->plan);
            $planName = is_object($planJson) ? $planJson->name : '?';

            /* data-time : js/hud.js s'en sert pour le compteur d'évènements
             * non lus (comparaison au dernier passage, localStorage).
             * data-own : nos propres actions ne comptent pas comme non
             * lues — seul ce que les autres nous font mérite le badge. */
            $isOwn = ((int) $e->player_id === (int) $player->id);
            $own = $isOwn ? ' data-own="1"' : '';

            echo '<div class="hud-feed-item' . self::outcomeClass((string) $e->hiddenText) . '" data-time="' . (int) $e->time . '"' . $own . '>'
                . '<span class="log-' . $e->type . '">' . $e->text . '</span>'
                . self::renderDetail($isOwn, (string) $e->hiddenText)
                . '<div class="hud-feed-meta">'
                . self::authorName($playerService, (int) $e->player_id)
                . ' · ' . self::humanDate((int) $e->time)
                . ' · ' . $planName
                . '</div>'
                . '</div>';
        }

        /* Le seul accès à la page complète était une icône de livre
         * sans libellé, coincée dans la barre d'onglets : son intitulé
         * ne vivait que dans un title, invisible au tactile. Un pied de
         * flux le dit en toutes lettres. */
        echo '<a class="hud-feed-all" href="logs.php?light">Tout voir</a>';

        return Str::minify(ob_get_clean());
    }

    /**
     * Le texte d'un message du jour, sorti de son enveloppe de journal.
     *
     * Il est stocké dans hiddenText, concaténé brut dans un
     * div.action-details au moment de la publication
     * (scripts/account/mdj.php) : c'est de la saisie joueur, elle doit
     * passer par la liste blanche avant d'atteindre l'écran de qui que
     * ce soit. L'enveloppe, elle, est de notre fait — on la retire pour
     * n'assainir que ce que le joueur a écrit, puis on la remplace par
     * la classe visible du flux.
     */
    private static function mdjBody(string $hiddenText): string
    {
        if (preg_match('#^<div class="action-details">(.*)</div>$#s', $hiddenText, $m) === 1) {
            return Str::richText($m[1]);
        }

        /* Enveloppe inattendue (journal ancien, autre forme) : on ne
         * devine pas, on retire tout le balisage avant d'afficher. */
        return Str::richText(strip_tags($hiddenText));
    }

    /**
     * Détail d'une action (verdict, jets de dés, coûts), repliable.
     *
     * Le flux simplifié n'en montrait rien : le joueur croyait
     * l'information supprimée et devait ouvrir la page complète pour
     * la retrouver — c'est ce qui lui faisait juger la fenêtre
     * « perfectible ». Le détail revient donc là où il est lu, replié
     * par défaut pour ne pas rendre le flux bavard.
     *
     * Réservé à son auteur : hiddenText est renvoyé par Log::get pour
     * TOUTES les lignes visibles, y compris celles des autres, et les
     * jets de dés d'autrui ne nous regardent pas.
     */
    private static function renderDetail(bool $isOwn, string $hiddenText): string
    {
        if (!$isOwn || $hiddenText === '') {
            return '';
        }

        /* Le <style> embarqué masque .action-details partout où le
         * texte est réinjecté : on le retire et on renomme la classe,
         * comme le fait déjà renderMdj(). */
        $body = str_replace(
            ['<style>.action-details{display: none;}</style>', 'class="action-details"'],
            ['', 'class="hud-feed-detail-body"'],
            $hiddenText
        );

        return '<details class="hud-feed-detail">'
            . '<summary>Détails</summary>'
            . $body
            . '</details>';
    }

    /**
     * Teinte réussite / échec : le détail d'action (hiddenText)
     * commence par le verdict — l'entrée du flux prend un lavis aux
     * couleurs de l'affichage historique (bleu réussite, rouge échec,
     * orangé impossible).
     */
    private static function outcomeClass(string $hiddenText): string
    {
        if ($hiddenText === '') {
            return '';
        }
        if (strpos($hiddenText, 'Réussite') !== false) {
            return ' hud-feed-item--ok';
        }
        if (strpos($hiddenText, 'Echec') !== false || strpos($hiddenText, 'Échec') !== false) {
            return ' hud-feed-item--ko';
        }
        if (strpos($hiddenText, 'Impossible') !== false) {
            return ' hud-feed-item--na';
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
