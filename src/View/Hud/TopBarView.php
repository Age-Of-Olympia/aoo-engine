<?php

namespace App\View\Hud;

use Classes\Player;
use Classes\Str;

/**
 * Barre de statut persistante du nouveau HUD (option newHud).
 *
 * Remplace InfosView en mode HUD : reprend ses deux éléments porteurs
 * (#player-avatar avec data-id, hôte de la pastille missives posée par
 * js/main.js, et #next-turn-timer) et ajoute les pastilles de caracs.
 *
 * IMPORTANT : ne pas réutiliser ici les ids #mvt-counter / #action-counter.
 * Ils appartiennent au panneau de caracs (CaracsPanelRenderer) où le
 * tutoriel vient les surligner ; les dupliquer casserait ce ciblage.
 */
final class TopBarView
{
    /** Caracs consommables affichées en restant/total (cf. CARACS_RECOVER). */
    private const CONSUMABLE_PILLS = ['a', 'mvt', 'pv', 'pm'];

    public static function render(Player $player): void
    {
        $player->get_data(false);

        $caracsJson = $player->get_caracsJson();
        $turnJson = $player->get_turnJson();
        $coords = $player->getCoords();

        $raceJson = json()->decode('races', $player->data->race);
        $raceName = is_object($raceJson) ? $raceJson->name : $player->data->race;

        $planJson = json()->decode('plans', $coords->plan);
        $planName = is_object($planJson) ? $planJson->name : '?';

        ob_start();

        echo '<header id="hud-topbar">';

        /* Hamburger (mobile uniquement, masqué en CSS ≥1024px) :
         * ouvre le rail en tiroir latéral. */
        echo '<button id="hud-burger" title="Menu" aria-label="Menu">&#9776;</button>';

        echo '<div class="hud-chip">'
            . '<div id="player-avatar" data-id="' . $player->id . '">'
            . '<a href="pnjs.php"><img src="' . $player->data->avatar . '" alt="" /></a>'
            . '</div>'
            . '<div class="hud-chip-id">'
            . '<a id="hud-chip-name" href="infos.php?targetId=' . $player->id . '">' . $player->data->name . '</a>'
            . '<sup>' . $raceName . ' · Rang ' . $player->data->rank . ' · mat.' . $player->getDisplayId() . '</sup>'
            . '</div>'
            . '</div>';

        echo '<div class="hud-pills">';
        foreach (self::CONSUMABLE_PILLS as $k) {
            $value = isset($turnJson->$k)
                ? $turnJson->$k . '/' . $caracsJson->$k
                : (string) $caracsJson->$k;
            echo self::pill($k, CARACS[$k], $value, CARACS_TXT[$k]);
        }
        echo self::pill('pf', 'PF', (string) $player->data->pf, 'Points de Foi');
        echo self::pill('en', 'EN', (string) $player->data->energie, 'Énergie');
        if ($player->data->malus > 0) {
            echo self::pill(
                'malus',
                'Malus',
                (string) $player->data->malus,
                'Malus : -' . $player->data->malus . ' aux jets de défense',
                ' hud-pill--warn'
            );
        }
        if (!empty($caracsJson->esquive)) {
            echo self::pill(
                'esquive',
                'Esq',
                (string) $caracsJson->esquive,
                'Esquive',
                $caracsJson->esquive < 0 ? ' hud-pill--warn' : ' hud-pill--bonus'
            );
        }
        echo '</div>';

        echo '<div class="hud-place">'
            . '<span id="hud-location" title="Position actuelle">'
            . $planName . ' — (' . $coords->x . ', ' . $coords->y . ', ' . $coords->z . ')'
            . '</span>'
            . '<sup>' . self::nextTurn($player) . '</sup>'
            . '</div>';

        echo '<div class="hud-quick">';

        /* Panneau d'administration (/admin : actions, tutoriel,
         * joueurs…) pour les super-administrateurs. La console texte
         * reste sur la touche ² et la page Profil. */
        if ($player->have_option('isSuperAdmin')) {
            echo '<a href="admin/" title="Panneau d\'administration">'
                . '<button class="hud-quick-icon"><span class="ra ra-cog"></span></button></a>';
        }

        /* Badge orange : sujets de forum non lus (les missives ont déjà
         * leurs pastilles rouge — personnage courant — et bleue —
         * autres personnages). */
        $forumUnread = self::unreadForumCount($player);
        $forumBadge = $forumUnread > 0
            ? '<span id="forum-unread-badge" class="cartouche bulle">' . $forumUnread . '</span>'
            : '';

        echo '<a href="classements.php" title="Classements"><button class="hud-quick-icon"><span class="ra ra-trophy"></span></button></a>'
            /* Le bouton mène à l'ACCUEIL du forum (catégories) ; les
             * derniers messages y restent à un clic. */
            . '<a href="forum.php" title="' . self::lastPostTitle($player) . '"><button>Forum' . $forumBadge . '</button></a>'
            . '<a href="index.php?menu" title="Menu principal"><button><span class="ra ra-castle-flag"></span></button></a>'
            . '<a href="index.php?logout" title="Se déconnecter"><button>Déconnexion</button></a>'
            . '</div>';

        echo '</header>';

        echo Str::minify(ob_get_clean());
    }

    private static function pill(string $key, string $label, string $value, string $title, string $extraClass = ''): string
    {
        return '<span class="hud-pill' . $extraClass . '" id="hud-pill-' . $key . '" title="' . $title . '">'
            . '<span class="hud-pill-label">' . $label . '</span>'
            . '<span class="hud-pill-value">' . $value . '</span>'
            . '</span>';
    }

    /**
     * Prochain tour — horaire informatif, volontairement sans compte à
     * rebours (choix de design : la DLA laisse ~18h pour jouer, aucune
     * urgence à afficher). Logique portée d'InfosView, y compris le
     * marqueur admin ⌀ « Nouveau Tour désactivé ».
     */
    private static function nextTurn(Player $player): string
    {
        $timeToNextTurn = Str::convert_time($player->data->nextTurnTime - time());

        $adminInfos = '';
        if ($player->id == $_SESSION['originalPlayerId']) {
            $_SESSION['nonewturn'] = false;
        } elseif (isset($_SESSION['nonewturn']) && $_SESSION['nonewturn']) {
            $adminInfos = ' <a href="#" onclick="navigator.clipboard.writeText(\'session open ' . $player->id
                . ' -reactive\');" style="color: #e50000;" title="Nouveau Tour Désactivé (click to copy command)">⌀</a>';
        }

        return 'Prochain tour à <a href="#" id="next-turn-timer" title="dans ' . $timeToNextTurn . '">'
            . date('H:i', $player->data->nextTurnTime) . '</a>' . $adminInfos;
    }

    /**
     * Sujets de forum non lus (catégories RP / Privés / HRP — les
     * Missives ont leurs propres pastilles). Le décompte lit tous les
     * JSONs de sujets : cache de session, invalidé par Forum::put_view
     * dès qu'un sujet est lu, et au bout d'une minute (nouveaux posts).
     */
    private static function unreadForumCount(Player $player): int
    {
        $cache = $_SESSION['forumUnreadCache'] ?? null;

        if (is_array($cache) && $cache['playerId'] === $player->id && $cache['time'] > time() - 60) {

            return $cache['n'];
        }

        try {
            $n = count((new \App\Service\ForumService())->GetAllUnreadTopics($player));
        } catch (\Throwable $e) {
            $n = 0;
        }

        $_SESSION['forumUnreadCache'] = ['playerId' => $player->id, 'time' => time(), 'n' => $n];

        return $n;
    }

    /** Extrait du dernier message du forum (général ou faction), pour l'info-bulle. */
    private static function lastPostTitle(Player $player): string
    {
        $lastPostJson = json()->decode('forum', 'lastPosts');

        $lastPostTime = $lastPostJson->general->time ?? 0;
        $lastPost = $lastPostJson->general->text ?? '';

        foreach ([$player->data->faction, $player->data->secretFaction ?? ''] as $faction) {
            if ($faction !== '' && !empty($lastPostJson->{$faction}) && $lastPostJson->{$faction}->time > $lastPostTime) {
                $lastPostTime = $lastPostJson->{$faction}->time;
                $lastPost = $lastPostJson->{$faction}->text;
            }
        }

        /* Le texte stocké contient déjà des entités HTML (htmlentities
         * dans Forum::refresh_last_posts) : on les décode avant de ré-échapper,
         * sinon l'info-bulle affiche « D&eacute;cision » littéralement. */
        $text = html_entity_decode(strip_tags((string) $lastPost), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return htmlspecialchars($text, ENT_QUOTES);
    }
}
