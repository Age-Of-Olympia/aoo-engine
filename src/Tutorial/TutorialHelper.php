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

            // Stale session detected: the tutorial_player_id in $_SESSION
            // points at a row that no longer exists in `players`. This is
            // the TOCTOU signal D1 in docs/tutorial-p0-deferred-design.md
            // wants to quantify before deciding on a locking strategy.
            // Emit one structured line per occurrence so the divergence
            // rate can be estimated by grepping production logs:
            //   grep '"event":"tutorial_session_stale"' apache_error.log | wc -l
            self::logTelemetry('tutorial_session_stale', [
                'tutorial_player_id'  => $tutorialPlayerId,
                'main_player_id'      => (int) ($_SESSION['playerId'] ?? 0),
                'tutorial_session_id' => $_SESSION['tutorial_session_id'] ?? null,
            ]);
            self::exitTutorialMode();
        }

        // Otherwise use main player ID
        return (int) ($_SESSION['playerId'] ?? 0);
    }

    /**
     * Emit a single JSON line of structured telemetry to error_log.
     *
     * Format: `{"event":"...","ts":"...","...":...}` — one line, no trailing
     * newline (error_log adds it). Designed to be grep-friendly:
     *   grep '"event":"<name>"' apache_error.log
     *
     * @param string               $event   Discriminator key for log scrapers
     * @param array<string, mixed> $context Additional fields to include
     */
    private static function logTelemetry(string $event, array $context): void
    {
        $line = json_encode(array_merge(
            ['event' => $event, 'ts' => date('c')],
            $context
        ));
        if ($line !== false) {
            error_log($line);
        }
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

    /**
     * Load player with full data and validation
     *
     * Loads the active player (tutorial or main) with all necessary data.
     * This is a centralized method to avoid duplicate player loading code.
     *
     * @param bool $loadCaracs Whether to load characteristics (turn data)
     * @param bool $throwOnFailure Whether to throw exception if data load fails
     * @return \Classes\Player Loaded player instance
     * @throws \RuntimeException If player data fails to load and throwOnFailure is true
     */
    public static function loadActivePlayer(bool $loadCaracs = false, bool $throwOnFailure = false): \Classes\Player
    {
        $activePlayerId = self::getActivePlayerId();
        $player = new \Classes\Player($activePlayerId);

        // Load player data
        $player->get_data();

        // Validate data loaded successfully
        if (!$player->data || $player->data === false) {
            $errorMsg = "Failed to load player data for player {$activePlayerId}";

            if ($throwOnFailure) {
                throw new \RuntimeException($errorMsg);
            }
        }

        // Load characteristics if requested
        if ($loadCaracs) {
            $player->get_caracs();
        }

        return $player;
    }

    /**
     * Load specific player with full data and validation
     *
     * @param int $playerId Player ID to load
     * @param bool $loadCaracs Whether to load characteristics (turn data)
     * @param bool $throwOnFailure Whether to throw exception if data load fails
     * @return \Classes\Player Loaded player instance
     * @throws \RuntimeException If player data fails to load and throwOnFailure is true
     */
    public static function loadPlayer(int $playerId, bool $loadCaracs = false, bool $throwOnFailure = false): \Classes\Player
    {
        $player = new \Classes\Player($playerId);

        // Load player data
        $player->get_data();

        // Validate data loaded successfully
        if (!$player->data || $player->data === false) {
            $errorMsg = "Failed to load player data for player {$playerId}";

            if ($throwOnFailure) {
                throw new \RuntimeException($errorMsg);
            }
        }

        // Load characteristics if requested
        if ($loadCaracs) {
            $player->get_caracs();
        }

        return $player;
    }
}
