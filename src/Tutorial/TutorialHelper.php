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
            return (int) $_SESSION['tutorial_player_id'];
        }

        // Otherwise use main player ID
        return (int) ($_SESSION['playerId'] ?? 0);
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
