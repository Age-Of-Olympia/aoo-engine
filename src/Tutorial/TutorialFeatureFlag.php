<?php

namespace App\Tutorial;

/**
 * Feature flags for tutorial system
 *
 * Allows gradual rollout without breaking existing functionality.
 * Can enable new tutorial system for specific players (admins, testers)
 * while keeping old system active for everyone else.
 */
class TutorialFeatureFlag
{
    /**
     * Is new tutorial system enabled globally?
     */
    public static function isEnabled(): bool
    {
        // Check environment variable first
        if (isset($_ENV['TUTORIAL_V2_ENABLED'])) {
            return filter_var($_ENV['TUTORIAL_V2_ENABLED'], FILTER_VALIDATE_BOOLEAN);
        }

        // Check constant (can be set in config.php)
        if (defined('TUTORIAL_V2_ENABLED')) {
            return TUTORIAL_V2_ENABLED === true;
        }

        // Default: disabled (safe default)
        return false;
    }

    /**
     * Force enable for specific player (for testing)
     *
     * Allows admin/test players to use new tutorial even when globally disabled
     */
    public static function isEnabledForPlayer(int $playerId): bool
    {
        // If globally enabled, everyone gets it
        if (self::isEnabled()) {
            return true;
        }

        // Allow specific test players (admins)
        $testPlayers = self::getTestPlayers();
        return in_array($playerId, $testPlayers);
    }

    /**
     * Get list of test players who can access new tutorial
     *
     * Default: Cradek (1), Dorna (2), Thyrias (3)
     */
    private static function getTestPlayers(): array
    {
        // Check if defined in config
        if (defined('TUTORIAL_V2_TEST_PLAYERS')) {
            return TUTORIAL_V2_TEST_PLAYERS;
        }

        // Default test players (the 3 dev accounts)
        return [1, 2, 3];
    }

    /**
     * Check if player should see tutorial option
     *
     * @param int $playerId
     * @param bool $hasCompletedBefore Has player completed tutorial before?
     * @return bool
     */
    public static function shouldShowTutorial(int $playerId, bool $hasCompletedBefore): bool
    {
        // If tutorial not enabled for this player, don't show
        if (!self::isEnabledForPlayer($playerId)) {
            return false;
        }

        // Always show for new players
        if (!$hasCompletedBefore) {
            return true;
        }

        // For players who completed before, show "replay" option
        return true;
    }

    /**
     * Get tutorial mode for player
     *
     * @param int $playerId
     * @param bool $hasCompletedBefore
     * @return string|null 'first_time', 'replay', or null if not available
     */
    public static function getTutorialMode(int $playerId, bool $hasCompletedBefore): ?string
    {
        if (!self::isEnabledForPlayer($playerId)) {
            return null;
        }

        return $hasCompletedBefore ? 'replay' : 'first_time';
    }
}
