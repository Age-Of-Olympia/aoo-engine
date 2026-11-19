<?php

namespace App\Tutorial;

use App\Module\Module;
use App\Module\Modules;
use Classes\Db;

/**
 * Who gets the tutorial, and the tutorial settings
 *
 * Who gets the tutorial comes from the tutorial module (App\Module\Modules):
 * on for everyone, or for its testers. The other settings (rewards, offer
 * to new players) stay in the tutorial_settings table.
 */
class TutorialFeatureFlag
{
    /**
     * Settings cache with TTL
     * @var array{data: array, timestamp: int}|null
     */
    private static ?array $settingsCache = null;

    /**
     * Cache TTL in seconds (5 minutes)
     * Balances performance vs. responsiveness to setting changes
     */
    private const CACHE_TTL_SECONDS = 300;

    /**
     * Does this player get the tutorial? The module decides (on for everyone,
     * or for its testers), except for whoever cannot follow it.
     */
    public static function isEnabledForPlayer(int $playerId): bool
    {
        // NPCs (negative IDs) never see the tutorial — they have no session,
        // and unsupported races (e.g. animal) crash the spawn flow.
        if ($playerId < 0) {
            return false;
        }

        // A structure never sees it either: the tutorial teaches a person.
        // A driven building lands on the map, not in a spawn flow.
        // DBAL, not Classes\Db: this runs in contexts (CI tests) where
        // the legacy global link is not set, and db() exits the process.
        $playerType = \App\Factory\EntityManagerFactory::getEntityManager()->getConnection()
            ->fetchOne('SELECT player_type FROM players WHERE id = ?', [$playerId]);
        if ($playerType !== false
            && \App\Enum\EntityCategory::fromPlayerType($playerType ?: null)->isStructure()) {
            return false;
        }

        return Modules::on(Module::Tutorial, $playerId);
    }

    /**
     * Get all settings from database (with TTL-based caching)
     *
     * Cache expires after CACHE_TTL_SECONDS to ensure settings changes
     * are reflected without requiring manual cache clear or process restart.
     *
     * @return array Settings key-value pairs
     */
    public static function getSettings(): array
    {
        // Check if cache exists and is still valid
        if (self::$settingsCache !== null) {
            $cacheAge = time() - self::$settingsCache['timestamp'];
            if ($cacheAge < self::CACHE_TTL_SECONDS) {
                // Cache still valid
                return self::$settingsCache['data'];
            }
            // Cache expired, will reload
        }

        try {
            $db = new Db();
            $result = $db->exe("SELECT setting_key, setting_value FROM tutorial_settings");

            $settings = [];
            while ($row = $result->fetch_assoc()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }

            // Store with timestamp for TTL checking
            self::$settingsCache = [
                'data' => $settings,
                'timestamp' => time()
            ];

            return $settings;
        } catch (\Exception $e) {
            error_log("[TutorialFeatureFlag] Error loading settings: " . $e->getMessage());

            // Store empty cache with timestamp to avoid repeated failed queries
            self::$settingsCache = [
                'data' => [],
                'timestamp' => time()
            ];

            return [];
        }
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
     * Clear the settings cache (useful after external updates)
     *
     * Forces immediate reload on next getSettings() call.
     * Automatically called after updateSetting().
     */
    public static function clearCache(): void
    {
        self::$settingsCache = null;
    }
}
