<?php

namespace App\Tutorial;

use Classes\Db;

/**
 * Feature flags for tutorial system
 *
 * Allows gradual rollout without breaking existing functionality.
 * Can enable new tutorial system for specific players (admins, testers)
 * while keeping old system active for everyone else.
 *
 * Settings are stored in tutorial_settings table and can be managed via admin panel.
 */
class TutorialFeatureFlag
{
    private static ?array $settingsCache = null;

    /**
     * Is new tutorial system enabled globally?
     */
    public static function isEnabled(): bool
    {
        // Check environment variable first (allows override)
        if (isset($_ENV['TUTORIAL_V2_ENABLED'])) {
            return filter_var($_ENV['TUTORIAL_V2_ENABLED'], FILTER_VALIDATE_BOOLEAN);
        }

        // Check constant (can be set in config.php, allows override)
        if (defined('TUTORIAL_V2_ENABLED')) {
            return TUTORIAL_V2_ENABLED === true;
        }

        // Check database setting
        $settings = self::getSettings();
        if (isset($settings['global_enabled'])) {
            return filter_var($settings['global_enabled'], FILTER_VALIDATE_BOOLEAN);
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

        // Allow specific whitelisted players
        $whitelistedPlayers = self::getWhitelistedPlayers();
        return in_array($playerId, $whitelistedPlayers, true);
    }

    /**
     * Get list of whitelisted players who can access tutorial
     */
    public static function getWhitelistedPlayers(): array
    {
        // Check if defined in config (allows override)
        if (defined('TUTORIAL_V2_TEST_PLAYERS')) {
            return TUTORIAL_V2_TEST_PLAYERS;
        }

        // Check database setting
        $settings = self::getSettings();
        if (isset($settings['whitelisted_players']) && $settings['whitelisted_players'] !== '') {
            $ids = array_map('intval', explode(',', $settings['whitelisted_players']));
            return array_filter($ids, fn($id) => $id > 0);
        }

        // Default test players (the 3 dev accounts)
        return [1, 2, 3];
    }

    /**
     * Check if auto-show for new players is enabled
     */
    public static function isAutoShowNewPlayersEnabled(): bool
    {
        $settings = self::getSettings();
        if (isset($settings['auto_show_new_players'])) {
            return filter_var($settings['auto_show_new_players'], FILTER_VALIDATE_BOOLEAN);
        }
        return true; // Default: enabled
    }

    /**
     * Get all settings from database
     */
    public static function getSettings(): array
    {
        if (self::$settingsCache !== null) {
            return self::$settingsCache;
        }

        try {
            $db = new Db();
            $result = $db->exe("SELECT setting_key, setting_value FROM tutorial_settings");

            self::$settingsCache = [];
            while ($row = $result->fetch_assoc()) {
                self::$settingsCache[$row['setting_key']] = $row['setting_value'];
            }
        } catch (\Exception $e) {
            error_log("[TutorialFeatureFlag] Error loading settings: " . $e->getMessage());
            self::$settingsCache = [];
        }

        return self::$settingsCache;
    }

    /**
     * Update a setting in the database
     */
    public static function updateSetting(string $key, string $value): bool
    {
        try {
            $db = new Db();
            $db->exe(
                "INSERT INTO tutorial_settings (setting_key, setting_value)
                 VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
                [$key, $value]
            );

            // Clear cache
            self::$settingsCache = null;

            return true;
        } catch (\Exception $e) {
            error_log("[TutorialFeatureFlag] Error updating setting: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Add a player to the whitelist
     */
    public static function addWhitelistedPlayer(int $playerId): bool
    {
        $current = self::getWhitelistedPlayers();
        if (!in_array($playerId, $current, true)) {
            $current[] = $playerId;
            return self::updateSetting('whitelisted_players', implode(',', $current));
        }
        return true;
    }

    /**
     * Remove a player from the whitelist
     */
    public static function removeWhitelistedPlayer(int $playerId): bool
    {
        $current = self::getWhitelistedPlayers();
        $updated = array_filter($current, fn($id) => $id !== $playerId);
        return self::updateSetting('whitelisted_players', implode(',', $updated));
    }

    /**
     * Clear the settings cache (useful after external updates)
     */
    public static function clearCache(): void
    {
        self::$settingsCache = null;
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
