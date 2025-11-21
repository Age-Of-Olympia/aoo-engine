<?php

namespace App\Tutorial;

/**
 * Tutorial Helper - Centralized utilities for tutorial mode
 *
 * Provides static methods to handle common tutorial operations like:
 * - Getting the correct active player ID (tutorial vs main player)
 * - Checking if currently in tutorial mode
 * - Managing tutorial session state
 *
 * This eliminates scattered manual checks across the codebase.
 */
class TutorialHelper
{
    /**
     * Get the active player ID
     *
     * Returns tutorial player ID if in tutorial mode, otherwise main player ID.
     * Use this instead of manually checking $_SESSION['playerId'].
     *
     * @return int Active player ID
     */
    public static function getActivePlayerId(): int
    {
        // If in tutorial mode, use tutorial player ID
        if (!empty($_SESSION['in_tutorial']) && !empty($_SESSION['tutorial_player_id'])) {
            $tutorialPlayerId = (int) $_SESSION['tutorial_player_id'];

            // Validate that the tutorial player still exists
            if (self::validateTutorialPlayer($tutorialPlayerId)) {
                return $tutorialPlayerId;
            }

            // Tutorial player was deleted/cleaned up - clear stale session
            error_log("[TutorialHelper] Tutorial player {$tutorialPlayerId} no longer exists - clearing stale session");
            self::exitTutorialMode();
        }

        // Otherwise use main player ID
        return (int) ($_SESSION['playerId'] ?? 0);
    }

    /**
     * Validate that a tutorial player exists in the database
     *
     * @param int $tutorialPlayerId Tutorial player ID to validate
     * @return bool True if player exists, false otherwise
     */
    private static function validateTutorialPlayer(int $tutorialPlayerId): bool
    {
        try {
            $db = new \Classes\Db();
            $result = $db->exe("SELECT id FROM players WHERE id = ?", [$tutorialPlayerId]);
            return $result && $result->num_rows > 0;
        } catch (\Exception $e) {
            error_log("[TutorialHelper] Error validating tutorial player: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if currently in tutorial mode
     *
     * @return bool True if in tutorial, false otherwise
     */
    public static function isInTutorial(): bool
    {
        return !empty($_SESSION['in_tutorial']) && !empty($_SESSION['tutorial_session_id']);
    }

    /**
     * Get tutorial session ID if in tutorial
     *
     * @return string|null Session ID or null if not in tutorial
     */
    public static function getSessionId(): ?string
    {
        if (self::isInTutorial()) {
            return $_SESSION['tutorial_session_id'] ?? null;
        }
        return null;
    }

    /**
     * Get tutorial player ID if in tutorial
     *
     * @return int|null Tutorial player ID or null if not in tutorial
     */
    public static function getTutorialPlayerId(): ?int
    {
        if (self::isInTutorial()) {
            return (int) $_SESSION['tutorial_player_id'];
        }
        return null;
    }

    /**
     * Start tutorial mode
     *
     * Sets session variables for tutorial mode
     *
     * @param string $sessionId Tutorial session ID
     * @param int $tutorialPlayerId Tutorial player ID
     */
    public static function startTutorialMode(string $sessionId, int $tutorialPlayerId): void
    {
        $_SESSION['in_tutorial'] = true;
        $_SESSION['tutorial_session_id'] = $sessionId;
        $_SESSION['tutorial_player_id'] = $tutorialPlayerId;
    }

    /**
     * Exit tutorial mode
     *
     * Clears tutorial session variables
     */
    public static function exitTutorialMode(): void
    {
        unset($_SESSION['in_tutorial']);
        unset($_SESSION['tutorial_session_id']);
        unset($_SESSION['tutorial_player_id']);
    }

    /**
     * Get main player ID (ignoring tutorial mode)
     *
     * @return int Main player ID
     */
    public static function getMainPlayerId(): int
    {
        return (int) ($_SESSION['playerId'] ?? 0);
    }
}
