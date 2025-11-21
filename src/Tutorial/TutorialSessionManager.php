<?php

namespace App\Tutorial;

use Classes\Db;
use App\Tutorial\Exceptions\TutorialSessionException;

/**
 * Tutorial Session Manager
 *
 * Handles tutorial session lifecycle:
 * - Creating new sessions
 * - Loading/resuming existing sessions
 * - Persisting session state
 * - Session validation
 * - Generating session identifiers
 *
 * This service is focused solely on session management and does not handle
 * step progression, validation, or resource management.
 */
class TutorialSessionManager
{
    private Db $db;

    public function __construct(?Db $db = null)
    {
        $this->db = $db ?? new Db();
    }

    /**
     * Create a new tutorial session
     *
     * @param int $playerId Real player's ID
     * @param string $mode Tutorial mode (first_time, replay, practice)
     * @param string $version Tutorial version
     * @param int $totalSteps Total number of steps in this version
     * @param string $firstStepId ID of the first step
     * @return array Session data with session_id, player_id, mode, version
     * @throws TutorialSessionException If session creation fails
     */
    public function createSession(
        int $playerId,
        string $mode,
        string $version,
        int $totalSteps,
        string $firstStepId
    ): array {
        $sessionId = $this->generateSessionId();

        try {
            // Insert into tutorial_progress table
            $sql = 'INSERT INTO tutorial_progress
                    (player_id, tutorial_session_id, current_step, total_steps, tutorial_mode, tutorial_version, data)
                    VALUES (?, ?, ?, ?, ?, ?, ?)';

            $this->db->exe($sql, [
                $playerId,
                $sessionId,
                $firstStepId,
                $totalSteps,
                $mode,
                $version,
                json_encode([]) // Empty context data initially
            ]);

            error_log("[TutorialSessionManager] Created session {$sessionId} for player {$playerId} (mode: {$mode}, version: {$version})");

            return [
                'session_id' => $sessionId,
                'player_id' => $playerId,
                'current_step' => $firstStepId,
                'total_steps' => $totalSteps,
                'mode' => $mode,
                'version' => $version,
                'xp_earned' => 0,
                'completed' => false
            ];

        } catch (\Exception $e) {
            throw new TutorialSessionException(
                "Failed to create tutorial session for player {$playerId}",
                ['player_id' => $playerId, 'mode' => $mode, 'version' => $version],
                0,
                $e
            );
        }
    }

    /**
     * Load an existing tutorial session
     *
     * @param string $sessionId Tutorial session UUID
     * @return array|null Session data or null if not found
     * @throws TutorialSessionException If session load fails
     */
    public function loadSession(string $sessionId): ?array
    {
        try {
            $sql = 'SELECT tp.*, tpl.player_id as tutorial_player_id
                    FROM tutorial_progress tp
                    LEFT JOIN tutorial_players tpl ON tp.tutorial_session_id = tpl.tutorial_session_id
                        AND tpl.is_active = 1
                    WHERE tp.tutorial_session_id = ?';

            $result = $this->db->exe($sql, [$sessionId]);

            if (!$result || $result->num_rows === 0) {
                return null;
            }

            $session = $result->fetch_assoc();

            return [
                'session_id' => $session['tutorial_session_id'],
                'player_id' => (int) $session['player_id'],
                'tutorial_player_id' => isset($session['tutorial_player_id']) ? (int) $session['tutorial_player_id'] : null,
                'current_step' => $session['current_step'],
                'total_steps' => (int) $session['total_steps'],
                'mode' => $session['tutorial_mode'],
                'version' => $session['tutorial_version'],
                'xp_earned' => (int) $session['xp_earned'],
                'completed' => (bool) $session['completed'],
                'started_at' => $session['started_at'],
                'completed_at' => $session['completed_at'],
                'data' => json_decode($session['data'], true) ?? []
            ];

        } catch (\Exception $e) {
            throw new TutorialSessionException(
                "Failed to load tutorial session {$sessionId}",
                ['session_id' => $sessionId],
                0,
                $e
            );
        }
    }

    /**
     * Get active session for a player
     *
     * @param int $playerId Real player's ID
     * @return array|null Session data or null if no active session
     */
    public function getActiveSession(int $playerId): ?array
    {
        $sql = 'SELECT tutorial_session_id FROM tutorial_progress
                WHERE player_id = ? AND completed = 0
                ORDER BY started_at DESC
                LIMIT 1';

        $result = $this->db->exe($sql, [$playerId]);

        if (!$result || $result->num_rows === 0) {
            return null;
        }

        $row = $result->fetch_assoc();
        return $this->loadSession($row['tutorial_session_id']);
    }

    /**
     * Update session progress
     *
     * Phase 4: Now accepts both array and string (JSON) for context data
     *
     * @param string $sessionId Tutorial session UUID
     * @param string $newStepId New current step ID
     * @param int $xpEarned Total XP earned so far
     * @param array|string $contextData Context state (array or JSON string)
     * @throws TutorialSessionException If update fails
     */
    public function updateProgress(
        string $sessionId,
        string $newStepId,
        int $xpEarned,
        array|string $contextData = []
    ): void {
        try {
            // Handle both array and already-encoded JSON string
            $jsonData = is_string($contextData) ? $contextData : json_encode($contextData);

            $sql = 'UPDATE tutorial_progress
                    SET current_step = ?,
                        xp_earned = ?,
                        data = ?
                    WHERE tutorial_session_id = ?';

            $this->db->exe($sql, [
                $newStepId,
                $xpEarned,
                $jsonData,
                $sessionId
            ]);

            error_log("[TutorialSessionManager] Updated session {$sessionId} progress to step {$newStepId} (XP: {$xpEarned})");

        } catch (\Exception $e) {
            throw new TutorialSessionException(
                "Failed to update session progress for {$sessionId}",
                ['session_id' => $sessionId, 'step' => $newStepId],
                0,
                $e
            );
        }
    }

    /**
     * Mark session as completed
     *
     * @param string $sessionId Tutorial session UUID
     * @param int $finalXP Final XP earned
     * @throws TutorialSessionException If completion fails
     */
    public function completeSession(string $sessionId, int $finalXP): void
    {
        try {
            $sql = 'UPDATE tutorial_progress
                    SET completed = 1,
                        completed_at = CURRENT_TIMESTAMP,
                        xp_earned = ?
                    WHERE tutorial_session_id = ?';

            $this->db->exe($sql, [$finalXP, $sessionId]);

            error_log("[TutorialSessionManager] Completed session {$sessionId} with {$finalXP} XP");

        } catch (\Exception $e) {
            throw new TutorialSessionException(
                "Failed to mark session {$sessionId} as completed",
                ['session_id' => $sessionId, 'xp' => $finalXP],
                0,
                $e
            );
        }
    }

    /**
     * Cancel/abandon a tutorial session
     *
     * Marks the session as completed without finishing all steps.
     * Used when player cancels tutorial midway.
     *
     * @param string $sessionId Tutorial session UUID
     * @throws TutorialSessionException If cancellation fails
     */
    public function cancelSession(string $sessionId): void
    {
        try {
            $sql = 'UPDATE tutorial_progress
                    SET completed = 1,
                        completed_at = CURRENT_TIMESTAMP
                    WHERE tutorial_session_id = ?';

            $this->db->exe($sql, [$sessionId]);

            error_log("[TutorialSessionManager] Cancelled session {$sessionId}");

        } catch (\Exception $e) {
            throw new TutorialSessionException(
                "Failed to cancel session {$sessionId}",
                ['session_id' => $sessionId],
                0,
                $e
            );
        }
    }

    /**
     * Check if player has completed tutorial before
     *
     * @param int $playerId Real player's ID
     * @return bool True if player completed first_time tutorial before
     */
    public function hasCompletedBefore(int $playerId): bool
    {
        $sql = 'SELECT COUNT(*) as count FROM tutorial_progress
                WHERE player_id = ? AND completed = 1 AND tutorial_mode = "first_time"';

        $result = $this->db->exe($sql, [$playerId]);
        $row = $result->fetch_assoc();

        return (int) $row['count'] > 0;
    }

    /**
     * Validate session exists and is active
     *
     * @param string $sessionId Tutorial session UUID
     * @return bool True if session exists and is not completed
     */
    public function isSessionActive(string $sessionId): bool
    {
        $sql = 'SELECT completed FROM tutorial_progress WHERE tutorial_session_id = ?';
        $result = $this->db->exe($sql, [$sessionId]);

        if (!$result || $result->num_rows === 0) {
            return false;
        }

        $row = $result->fetch_assoc();
        return !(bool) $row['completed'];
    }

    /**
     * Validate session ID format (UUID v4)
     *
     * @param string $sessionId Session ID to validate
     * @return bool True if valid UUID v4 format
     */
    public static function validateSessionIdFormat(string $sessionId): bool
    {
        // UUID v4 format: xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx
        // where x is any hexadecimal digit and y is one of 8, 9, A, or B
        $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
        return preg_match($pattern, $sessionId) === 1;
    }

    /**
     * Generate unique session identifier (UUID v4)
     *
     * @return string Session UUID
     */
    private function generateSessionId(): string
    {
        // Generate UUID v4
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // Version 4
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // Variant

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Get session statistics for debugging
     *
     * @return array Statistics about active/completed sessions
     */
    public function getStatistics(): array
    {
        $sql = 'SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN completed = 1 THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN completed = 0 THEN 1 ELSE 0 END) as active,
                    AVG(xp_earned) as avg_xp
                FROM tutorial_progress';

        $result = $this->db->exe($sql);
        $stats = $result->fetch_assoc();

        return [
            'total_sessions' => (int) $stats['total'],
            'completed_sessions' => (int) $stats['completed'],
            'active_sessions' => (int) $stats['active'],
            'average_xp' => round((float) $stats['avg_xp'], 2)
        ];
    }
}
