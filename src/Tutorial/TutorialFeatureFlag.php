<?php

namespace App\Tutorial;

use App\Module\Module;
use App\Module\Modules;
use Classes\Db;

/**
 * Feature flags for tutorial system
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

        // The tutorial module, set in Administration → Modules
        return Modules::on(Module::Tutorial);
    }

    /**
     * Force enable for specific player (for testing)
     *
     * Allows admin/test players to use new tutorial even when globally disabled
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

        // The tutorial module's testers
        return Modules::players(Module::Tutorial);
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

    /**
     * Get cache statistics (for debugging/monitoring)
     *
     * @return array{cached: bool, age_seconds: int|null, ttl_seconds: int, expires_in: int|null}
     */
    public static function getCacheStats(): array
    {
        if (self::$settingsCache === null) {
            return [
                'cached' => false,
                'age_seconds' => null,
                'ttl_seconds' => self::CACHE_TTL_SECONDS,
                'expires_in' => null
            ];
        }

        $age = time() - self::$settingsCache['timestamp'];
        $expiresIn = self::CACHE_TTL_SECONDS - $age;

        return [
            'cached' => true,
            'age_seconds' => $age,
            'ttl_seconds' => self::CACHE_TTL_SECONDS,
            'expires_in' => max(0, $expiresIn),
            'is_expired' => $age >= self::CACHE_TTL_SECONDS
        ];
    }

}
