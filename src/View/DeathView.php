<?php

namespace App\View;

use App\Service\PlayerOptionsService;
use App\Service\PlayerService;
use App\Tutorial\TutorialHelper;
use App\View\Hud\FeedRenderer;
use App\View\Hud\HudLayoutView;
use Classes\Log;
use Classes\Player;

/**
 * "Vous êtes mort" page: interstitial shown when a character got killed,
 * on the NewTurnView model (full-page render then exit). The page
 * mutates nothing: the button only closes it. The character stays put —
 * the way out of the underworld is walked.
 *
 * Being killed is what shows the page, not standing in the underworld:
 * Player::death() arms a persistent option, the player's dismissal
 * disarms it. A living visitor who walked into the underworld through
 * one of its worldly entrances sees nothing. An opened (admin) session
 * closes the page for itself only, in session — the real player must
 * still discover their own.
 */
class DeathView
{
    private const SEEN_KEY = 'deathScreenSeen';
    private const PENDING_OPTION = 'deathScreenPending';
    public const DISMISS_PARAM = 'enterHell';

    /** How many recent events show under the announcement. */
    private const MAX_EVENTS = 10;

    /**
     * POST-then-redirect dismissal, called from index.php BEFORE any
     * output: the Ui header would make the redirect impossible, and the
     * redirect is what keeps a refresh from dismissing a later death.
     */
    public static function handleDismissRequest(): void
    {
        if (!isset($_POST[self::DISMISS_PARAM]) || empty($_SESSION['playerId'])) {
            return;
        }

        if (self::isOpenedSession()) {
            $_SESSION[self::SEEN_KEY] = true;
        } else {
            (new PlayerOptionsService())->endOption((int) $_SESSION['playerId'], self::PENDING_OPTION);
        }

        header('Location: index.php');
        exit();
    }

    public static function renderDeathScreen(Player $player): void
    {
        if (TutorialHelper::isInTutorial()) {
            return;
        }

        if (!$player->have_option(self::PENDING_OPTION)) {
            return;
        }

        $player->getCoords();
        $currentPlan = $player->coords->plan ?? null;
        $deathPlan = plans()->deathPlan();

        if (!self::shouldDisplay(true, $currentPlan, $deathPlan, !empty($_SESSION[self::SEEN_KEY]))) {
            if ($currentPlan !== $deathPlan) {
                // Out of the underworld with the page still armed (e.g. walked
                // out by an admin session): the announcement is stale, drop it.
                $player->end_option(self::PENDING_OPTION);
            }

            return;
        }

        $buttonLabel = self::dismissLabel(self::isOpenedSession(), empty($_SESSION['nonewturn']));

        echo '<link rel="stylesheet" href="css/hud.min.css?v=' . HudLayoutView::VERSION . '" />';
        echo '<link rel="stylesheet" href="css/interstitial.css?v=20260821b" />';

        echo '<div class="aoo-notice aoo-notice--death">';

        echo '<h1>Vous êtes mort</h1>';
        echo '<img class="aoo-notice-flourish" src="img/ui/paper/flourish-sep.png" alt="" />';
        echo '<img class="aoo-notice-medallion" src="img/ui/illustrations/enfers.webp" alt="" />';

        echo '<p class="aoo-notice-lead">Votre dernière aventure s\'est mal terminée :'
            . ' le Styx vous a recraché quelque part dans le royaume de Hadès.<br />'
            . 'N\'hésitez pas à le saluer si vous le croisez.<br />'
            . 'On sait que la sortie est en 0,0. Le chemin, lui, est votre affaire.'
            . ' Une prière ne serait peut-être pas de trop.</p>';

        // The button comes before the events: no scrolling to reach it.
        if ($buttonLabel !== null) {
            echo '<form method="post" action="index.php">'
                . '<button name="' . self::DISMISS_PARAM . '" value="1">' . $buttonLabel . '</button></form>';
        } else {
            echo '<p class="aoo-notice-meta">Session ouverte sans -reactive : rouvrez-la avec'
                . ' <code>session open ' . (int) ($_SESSION['playerId'] ?? 0) . ' -reactive</code>'
                . ' pour incarner ce personnage.</p>';
        }

        self::renderLastEvents($player);

        echo '</div>';

        exit();
    }

    /** Arm the page for a fresh death. NPCs and structures never log in. */
    public static function armFor(Player $player): void
    {
        if ((int) $player->id <= 0) {
            return;
        }

        if (!$player->have_option(self::PENDING_OPTION)) {
            $player->add_option(self::PENDING_OPTION);
        }
    }

    /** The page only shows to the freshly killed, still in the underworld, and not twice. */
    public static function shouldDisplay(bool $armed, ?string $currentPlan, string $deathPlan, bool $alreadySeen): bool
    {
        return $armed && !$alreadySeen && $currentPlan === $deathPlan;
    }

    /**
     * Label of the exit button, depending on who is looking. An opened
     * session without -reactive gets no button: an admin does not revive
     * a character without meaning to.
     */
    public static function dismissLabel(bool $openedSession, bool $reactive): ?string
    {
        if (!$openedSession) {
            return 'Bienvenue aux Enfers';
        }

        return $reactive ? 'Ressusciter' : null;
    }

    /** A session opened on another character through the admin console. */
    private static function isOpenedSession(): bool
    {
        return isset($_SESSION['originalPlayerId'])
            && (int) $_SESSION['originalPlayerId'] !== (int) ($_SESSION['playerId'] ?? 0);
    }

    /**
     * The character's last events — enough to understand how one died.
     * Same "Du personnage" filter as logs.php?self: the dead is actor
     * or target of every row.
     */
    private static function renderLastEvents(Player $player): void
    {
        $logs = array_slice(
            self::aboutPlayer(Log::get($player, THREE_DAYS, 'light'), (int) $player->id),
            0,
            self::MAX_EVENTS
        );

        if ($logs === []) {
            echo '<p class="aoo-notice-empty">Aucun événement récent.</p>';

            return;
        }

        // Same rendering as the HUD events feed, detail folds included.
        echo '<h2>Vos derniers instants</h2>';
        echo '<div class="aoo-notice-events">';

        $playerService = new PlayerService($player->id);

        foreach ($logs as $e) {
            echo FeedRenderer::renderEventItem($playerService, $player, $e);
        }

        echo '</div>';
    }

    /**
     * @param object[] $logs
     * @return object[]
     */
    public static function aboutPlayer(array $logs, int $playerId): array
    {
        return array_values(array_filter(
            $logs,
            fn (object $e): bool => (int) $e->player_id === $playerId || (int) $e->target_id === $playerId
        ));
    }
}
