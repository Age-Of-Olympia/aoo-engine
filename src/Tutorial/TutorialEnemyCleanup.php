<?php

namespace App\Tutorial;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Tutorial Enemy Cleanup Service
 *
 * Centralized logic for removing tutorial enemy NPCs and their related data.
 * Prevents code duplication and ensures consistent cleanup across all code paths.
 *
 * This service handles:
 * - Querying tutorial_enemies table for session-specific enemies
 * - Deleting foreign key references from 9 related tables
 * - Removing enemy player records
 * - Removing enemy coordinates
 * - Cleaning up tracking records
 */
class TutorialEnemyCleanup
{
    private Connection $conn;
    private LoggerInterface $logger;

    /**
     * Tables with foreign key references to players.id (enemy_player_id)
     * ORDER MATTERS: Must delete child tables before parent
     */
    private const FOREIGN_KEY_TABLES = [
        // Player activity logs (both as actor and target)
        ['table' => 'players_logs', 'columns' => ['player_id', 'target_id']],

        // Player actions
        ['table' => 'players_actions', 'columns' => ['player_id']],

        // Player inventory
        ['table' => 'players_items', 'columns' => ['player_id']],

        // Player effects/buffs
        ['table' => 'players_effects', 'columns' => ['player_id']],

        // Player bonus/characteristics (CRITICAL: enemy gets PV bonus on spawn)
        ['table' => 'players_bonus', 'columns' => ['player_id']],

        // Player options
        ['table' => 'players_options', 'columns' => ['player_id']],

        // Combat statistics (both as killer and victim)
        ['table' => 'players_kills', 'columns' => ['player_id', 'target_id']],
        ['table' => 'players_assists', 'columns' => ['player_id', 'target_id']],

        // Additional tables that might reference players
        // Add more as needed for complete cleanup
    ];

    public function __construct(Connection $conn, ?LoggerInterface $logger = null)
    {
        $this->conn = $conn;
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Remove all tutorial enemies for a specific session
     *
     * @param string $sessionId Tutorial session UUID
     * @return int Number of enemies removed
     * @throws TutorialEnemyCleanupException If cleanup fails
     */
    public function removeBySessionId(string $sessionId): int
    {
        try {
            // Query all enemies for this session
            $stmt = $this->conn->prepare("
                SELECT enemy_player_id, enemy_coords_id
                FROM tutorial_enemies
                WHERE tutorial_session_id = ?
            ");
            $stmt->bindValue(1, $sessionId);
            $result = $stmt->executeQuery();

            $cleanedCount = 0;

            // Process each enemy
            while ($row = $result->fetchAssociative()) {
                $enemyId = (int) $row['enemy_player_id'];

                // Delete enemy and related data
                $this->deleteEnemyPlayer($enemyId);

                // NOTE: Do NOT delete coordinates here
                // Coords are part of the map instance and will be deleted
                // when TutorialMapInstance.deleteInstance() is called
                // Deleting coords here causes foreign key violations if the
                // tutorial player still exists and references nearby coords

                $cleanedCount++;
            }

            // Delete tracking records from tutorial_enemies table
            $this->conn->delete('tutorial_enemies', ['tutorial_session_id' => $sessionId]);

            if ($cleanedCount > 0) {
                $this->logger->info("Removed {$cleanedCount} tutorial enemy/enemies for session {$sessionId}");
            }

            return $cleanedCount;

        } catch (\Exception $e) {
            $this->logger->error("Failed to remove tutorial enemies for session {$sessionId}: " . $e->getMessage());
            throw new TutorialEnemyCleanupException(
                "Failed to remove tutorial enemies for session {$sessionId}",
                0,
                $e
            );
        }
    }

    /**
     * Remove a single enemy player and all related data
     *
     * @param int $enemyId Enemy player ID (should be negative for NPCs)
     * @throws TutorialEnemyCleanupException If deletion fails
     */
    private function deleteEnemyPlayer(int $enemyId): void
    {
        if ($enemyId === 0) {
            return; // Nothing to delete
        }

        if ($enemyId > 0) {
            $this->logger->warning("Deleting tutorial enemy with positive ID {$enemyId} - expected negative ID for NPC");
        }

        try {
            // Delete foreign key references first to avoid constraint violations
            $this->deleteForeignKeyReferences($enemyId);

            // Now safe to delete the enemy player record
            $deleted = $this->conn->delete('players', ['id' => $enemyId]);

            if ($deleted > 0) {
                $this->logger->debug("Deleted enemy player {$enemyId}");
            }

        } catch (\Exception $e) {
            throw new TutorialEnemyCleanupException(
                "Failed to delete enemy player {$enemyId}",
                0,
                $e
            );
        }
    }

    /**
     * Delete all foreign key references to an enemy player
     *
     * This ensures no orphaned records remain in related tables.
     * Deletes from both 'player_id' and 'target_id' columns where applicable
     * (e.g., logs where enemy was actor OR target).
     *
     * @param int $enemyId Enemy player ID
     */
    private function deleteForeignKeyReferences(int $enemyId): void
    {
        foreach (self::FOREIGN_KEY_TABLES as $tableConfig) {
            $table = $tableConfig['table'];
            $columns = $tableConfig['columns'];

            foreach ($columns as $column) {
                try {
                    $deleted = $this->conn->delete($table, [$column => $enemyId]);

                    if ($deleted > 0) {
                        $this->logger->debug("Deleted {$deleted} row(s) from {$table}.{$column} for enemy {$enemyId}");
                    }
                } catch (\Exception $e) {
                    // Log but don't fail - table might not exist or column might be missing
                    $this->logger->warning("Failed to delete from {$table}.{$column}: " . $e->getMessage());
                }
            }
        }
    }

    /**
     * Remove orphaned tutorial enemies (enemies without valid session)
     *
     * Useful for cleanup after crashes or incomplete tutorial cancellations.
     *
     * @return int Number of orphaned enemies removed
     */
    public function removeOrphanedEnemies(): int
    {
        try {
            // Find enemies whose sessions no longer exist or are completed
            $stmt = $this->conn->prepare("
                SELECT te.enemy_player_id, te.enemy_coords_id, te.tutorial_session_id
                FROM tutorial_enemies te
                LEFT JOIN tutorial_progress tp ON te.tutorial_session_id = tp.tutorial_session_id
                WHERE tp.id IS NULL OR tp.completed = 1
            ");
            $result = $stmt->executeQuery();

            $cleanedCount = 0;

            while ($row = $result->fetchAssociative()) {
                $enemyId = (int) $row['enemy_player_id'];
                $sessionId = $row['tutorial_session_id'];

                $this->deleteEnemyPlayer($enemyId);

                // NOTE: Do NOT delete coordinates here
                // Coords are part of the map instance and should be cleaned up
                // by TutorialMapInstance.deleteInstance() to avoid foreign key violations

                $this->conn->delete('tutorial_enemies', ['tutorial_session_id' => $sessionId]);

                $cleanedCount++;
            }

            if ($cleanedCount > 0) {
                $this->logger->info("Removed {$cleanedCount} orphaned tutorial enemies");
            }

            return $cleanedCount;

        } catch (\Exception $e) {
            $this->logger->error("Failed to remove orphaned enemies: " . $e->getMessage());
            throw new TutorialEnemyCleanupException(
                "Failed to remove orphaned tutorial enemies",
                0,
                $e
            );
        }
    }
}

/**
 * Exception thrown when tutorial enemy cleanup fails
 */
class TutorialEnemyCleanupException extends \Exception
{
}
