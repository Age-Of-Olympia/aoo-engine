<?php

namespace App\Tutorial;

/**
 * Tutorial System Constants
 *
 * Centralized constants for the tutorial system to avoid magic numbers
 * and improve code readability.
 */
class TutorialConstants
{
    // ========================================================================
    // MAP INSTANCE CONSTANTS
    // ========================================================================

    /**
     * Maximum length for plan name prefix
     *
     * Tutorial instance plans are named 'tut_XXXXXXXXXX' where X is part of the session UUID.
     * This constant defines how many characters of the UUID to use.
     *
     * Calculation:
     * - Database column: plan VARCHAR(50)
     * - Prefix 'tut_' = 4 characters
     * - UUID prefix = 10 characters
     * - Total = 14 characters (well under 50 limit with safety margin)
     *
     * @var int
     */
    const INSTANCE_PLAN_PREFIX_LENGTH = 10;

    /**
     * Template plan name for tutorial map
     *
     * This is the base plan that gets copied for each tutorial instance.
     * It contains the tutorial map layout, resources, NPCs, etc.
     *
     * @var string
     */
    const TEMPLATE_PLAN_NAME = 'tutorial';

    // ========================================================================
    // ENEMY NPC CONSTANTS
    // ========================================================================

    /**
     * Minimum ID for tutorial enemy NPCs
     *
     * Tutorial enemies use negative IDs to distinguish them from real players.
     * This range avoids collision with:
     * - Real players: positive IDs (1, 2, 3, ...)
     * - Regular NPCs: -1 to -99999
     * - Tutorial enemies: -100000 to -999999 (900,000 possible IDs)
     *
     * @var int
     */
    const TUTORIAL_ENEMY_ID_MIN = -100000;

    /**
     * Range size for tutorial enemy IDs
     *
     * This defines how many unique enemy IDs are available.
     * Range: -100000 to (-100000 - 899999) = -100000 to -999999
     *
     * @var int
     */
    const TUTORIAL_ENEMY_ID_RANGE = 899999;

    /**
     * Enemy spawn position relative to player
     *
     * When spawning a tutorial enemy, it's placed at this offset from the player's position.
     * (X offset, Y offset) = (2, 1) means 2 tiles east, 1 tile south from player
     *
     * @var array{int, int}
     */
    const ENEMY_SPAWN_OFFSET_X = 2;
    const ENEMY_SPAWN_OFFSET_Y = 1;

    // ========================================================================
    // TUTORIAL PROGRESSION CONSTANTS
    // ========================================================================

    /**
     * XP required per level
     *
     * Simple level-up formula: every 100 XP = 1 level
     * Used in TutorialContext for level calculation
     *
     * @var int
     */
    const XP_PER_LEVEL = 100;

    /**
     * PI (Points d'Influence) awarded per level-up
     *
     * When tutorial character levels up, they gain this many PI points
     *
     * @var int
     */
    const PI_PER_LEVEL = 5;

    /**
     * Default starting level for tutorial characters
     *
     * @var int
     */
    const DEFAULT_STARTING_LEVEL = 1;

    /**
     * Default starting energie (energy/health) for tutorial characters
     *
     * @var int
     */
    const DEFAULT_STARTING_ENERGIE = 100;

    // ========================================================================
    // TIMEOUT AND RETRY CONSTANTS
    // ========================================================================

    /**
     * Maximum retries for UI element detection (JavaScript)
     *
     * When waiting for UI elements to appear, this is the maximum number
     * of retry attempts before giving up.
     *
     * @var int
     */
    const MAX_UI_RETRIES = 50;

    /**
     * Delay between UI retry attempts (milliseconds)
     *
     * How long to wait between each retry when checking for UI elements
     *
     * @var int
     */
    const UI_RETRY_DELAY_MS = 100;

    /**
     * Total UI detection timeout (milliseconds)
     *
     * Calculated as: MAX_UI_RETRIES × UI_RETRY_DELAY_MS = 5000ms = 5 seconds
     *
     * @var int
     */
    const UI_DETECTION_TIMEOUT_MS = self::MAX_UI_RETRIES * self::UI_RETRY_DELAY_MS;

    // ========================================================================
    // SESSION AND CLEANUP CONSTANTS
    // ========================================================================

    /**
     * Default tutorial version
     *
     * Used when no specific version is specified.
     * Allows for versioned tutorial content (A/B testing, updates, etc.)
     *
     * @var string
     */
    const DEFAULT_TUTORIAL_VERSION = '1.0.0';

    /**
     * Tutorial session ID length (UUID v4)
     *
     * Standard UUID format: 8-4-4-4-12 = 36 characters with dashes
     *
     * @var int
     */
    const SESSION_ID_LENGTH = 36;

    /**
     * Maximum age for orphaned tutorial players (seconds)
     *
     * Tutorial players older than this are considered orphaned and can be cleaned up.
     * Default: 24 hours = 86400 seconds
     *
     * @var int
     */
    const ORPHAN_CLEANUP_AGE_SECONDS = 86400;

    // ========================================================================
    // CHARACTER NAMING CONSTANTS
    // ========================================================================

    /**
     * Tutorial character name prefix
     *
     * Tutorial characters are named using this prefix + part of session ID
     * Example: "Apprenti_abc123de"
     *
     * @var string
     */
    const TUTORIAL_CHARACTER_NAME_PREFIX = 'Apprenti_';

    /**
     * Length of session ID suffix in character name
     *
     * How many characters of the session UUID to append to character name
     *
     * @var int
     */
    const CHARACTER_NAME_SUFFIX_LENGTH = 8;

    // ========================================================================
    // BASIC ACTIONS CONSTANTS
    // ========================================================================

    /**
     * Basic actions given to all tutorial characters
     *
     * These are the fundamental actions needed to complete the tutorial
     *
     * @var array<string>
     */
    const BASIC_TUTORIAL_ACTIONS = [
        'fouiller',      // Search/gather resources
        'repos',         // Rest (restore PA/MVT)
        'attaquer',      // Attack
        'courir',        // Run (fast movement)
        'prier',         // Pray (faction-specific)
        'entrainement'   // Training (gain XP)
    ];

    // ========================================================================
    // HELPER METHODS
    // ========================================================================

    /**
     * Generate tutorial enemy ID
     *
     * Creates a unique negative ID for a tutorial enemy NPC within the reserved range
     *
     * @return int Negative ID in range -100000 to -999999
     */
    public static function generateEnemyId(): int
    {
        return self::TUTORIAL_ENEMY_ID_MIN - mt_rand(1, self::TUTORIAL_ENEMY_ID_RANGE);
    }

    /**
     * Generate tutorial character name
     *
     * Creates a unique name for a tutorial character using session ID
     *
     * @param string $sessionId Tutorial session UUID
     * @return string Character name (e.g., "Apprenti_abc123de")
     */
    public static function generateCharacterName(string $sessionId): string
    {
        return self::TUTORIAL_CHARACTER_NAME_PREFIX . substr($sessionId, 0, self::CHARACTER_NAME_SUFFIX_LENGTH);
    }

    /**
     * Generate tutorial instance plan name
     *
     * Creates a unique plan name for a tutorial map instance
     *
     * @param string $sessionId Tutorial session UUID
     * @return string Plan name (e.g., "tut_abc123def0")
     */
    public static function generateInstancePlanName(string $sessionId): string
    {
        return 'tut_' . substr($sessionId, 0, self::INSTANCE_PLAN_PREFIX_LENGTH);
    }

    /**
     * Calculate level from XP
     *
     * @param int $xp Experience points
     * @return int Level (minimum 1)
     */
    public static function calculateLevel(int $xp): int
    {
        return max(1, (int) floor($xp / self::XP_PER_LEVEL) + 1);
    }

    /**
     * Calculate XP required for next level
     *
     * @param int $currentLevel Current level
     * @return int XP needed to reach next level
     */
    public static function xpForNextLevel(int $currentLevel): int
    {
        return $currentLevel * self::XP_PER_LEVEL;
    }
}
