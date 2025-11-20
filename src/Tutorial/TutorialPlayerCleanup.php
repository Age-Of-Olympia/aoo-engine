<?php

namespace App\Tutorial;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Tutorial Player Cleanup Service
 *
 * Centralized logic for removing tutorial players and their related data.
 * Prevents code duplication and ensures consistent cleanup across all code paths.
 *
 * This service handles:
 * - Soft-deleting tutorial_players table entries (marking inactive)
 * - Hard-deleting player records from players table
 * - Deleting foreign key references from 25+ related tables
 * - Proper dependency order to avoid constraint violations
 */
class TutorialPlayerCleanup
{
    private Connection $conn;
    private LoggerInterface $logger;

    /**
     * Tables with foreign key references to players.id
     * ORDER MATTERS: Must delete child tables before parent
     *
     * This list is comprehensive to handle all possible player data,
     * even though tutorial players shouldn't have most of these records.
     */
    private const FOREIGN_KEY_TABLES = [
        // Player activity and state
        ['table' => 'players_logs', 'columns' => ['player_id', 'target_id']],
        ['table' => 'players_actions', 'columns' => ['player_id']],
        ['table' => 'players_items', 'columns' => ['player_id']],
        ['table' => 'players_effects', 'columns' => ['player_id']],
        ['table' => 'players_options', 'columns' => ['player_id']],
        ['table' => 'players_connections', 'columns' => ['player_id']],
        ['table' => 'players_bonus', 'columns' => ['player_id']],

        // Combat statistics
        ['table' => 'players_assists', 'columns' => ['player_id', 'target_id']],
        ['table' => 'players_kills', 'columns' => ['player_id', 'target_id']],

        // Character progression
        ['table' => 'players_upgrades', 'columns' => ['player_id']],

        // Quests
        ['table' => 'players_quests_steps', 'columns' => ['player_id']],
        ['table' => 'players_quests', 'columns' => ['player_id']],

        // Forum/social
        ['table' => 'players_forum_missives', 'columns' => ['player_id']],
        ['table' => 'players_forum_rewards', 'columns' => ['from_player_id', 'to_player_id']],
        ['table' => 'players_followers', 'columns' => ['player_id']],

        // Administration
        ['table' => 'players_banned', 'columns' => ['player_id']],

        // NPCs (if tutorial player somehow has NPCs)
        ['table' => 'players_pnjs', 'columns' => ['player_id', 'pnj_id']],

        // Map interactions (tutorial players shouldn't have these, but comprehensive cleanup)
        ['table' => 'map_walls', 'columns' => ['player_id']],
        ['table' => 'map_tiles', 'columns' => ['player_id']],
        ['table' => 'map_routes', 'columns' => ['player_id']],

        // Trading/economy (tutorial players shouldn't have these)
        ['table' => 'items_asks', 'columns' => ['player_id']],
        ['table' => 'items_bids', 'columns' => ['player_id']],
        ['table' => 'items_exchanges', 'columns' => ['player_id', 'target_id']],
        ['table' => 'players_items_exchanges', 'columns' => ['player_id', 'target_id']],
    ];

    public function __construct(Connection $conn, ?LoggerInterface $logger = null)
    {
        $this->conn = $conn;
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Delete a tutorial player and all related data
     *
     * Performs two-phase deletion:
     * 1. Soft delete in tutorial_players table (marks inactive)
     * 2. Hard delete from players table and all foreign key references
     *
     * @param int $tutorialPlayersId ID in tutorial_players table
     * @param int $actualPlayerId ID in players table (positive ID)
     * @throws TutorialPlayerCleanupException If deletion fails
     */
    public function deleteTutorialPlayer(int $tutorialPlayersId, int $actualPlayerId): void
    {
        try {
            // Phase 1: Soft delete in tutorial_players table
            $this->softDeleteInTutorialPlayersTable($tutorialPlayersId);

            // Phase 2: Hard delete from players table and related tables
            $this->hardDeleteFromPlayersTable($actualPlayerId);

            $this->logger->info("Tutorial player deleted successfully", [
                'tutorial_players_id' => $tutorialPlayersId,
                'actual_player_id' => $actualPlayerId
            ]);

        } catch (\Exception $e) {
            $this->logger->error("Failed to delete tutorial player", [
                'tutorial_players_id' => $tutorialPlayersId,
                'actual_player_id' => $actualPlayerId,
                'error' => $e->getMessage()
            ]);
            throw new TutorialPlayerCleanupException(
                "Failed to delete tutorial player {$tutorialPlayersId} (actual ID: {$actualPlayerId})",
                0,
                $e
            );
        }
    }

    /**
     * Soft delete in tutorial_players table
     *
     * Marks the record as inactive without physically deleting it.
     * This preserves the audit trail for completed tutorials.
     *
     * @param int $tutorialPlayersId ID in tutorial_players table
     */
    private function softDeleteInTutorialPlayersTable(int $tutorialPlayersId): void
    {
        $updated = $this->conn->update('tutorial_players', [
            'is_active' => 0,
            'deleted_at' => date('Y-m-d H:i:s')
        ], [
            'id' => $tutorialPlayersId
        ]);

        if ($updated > 0) {
            $this->logger->debug("Soft-deleted tutorial_players entry {$tutorialPlayersId}");
        }
    }

    /**
     * Hard delete from players table and all related tables
     *
     * Deletes the actual player record and all foreign key references.
     * MUST delete child tables first to avoid constraint violations.
     *
     * @param int $actualPlayerId ID in players table
     * @throws TutorialPlayerCleanupException If deletion fails
     */
    private function hardDeleteFromPlayersTable(int $actualPlayerId): void
    {
        if ($actualPlayerId <= 0) {
            $this->logger->warning("Skipping deletion of invalid player ID: {$actualPlayerId}");
            return;
        }

        $this->logger->debug("Deleting player {$actualPlayerId} and all related records");

        // Delete all foreign key references first
        $this->deleteForeignKeyReferences($actualPlayerId);

        // Now safe to delete the player record
        $deleted = $this->conn->executeStatement(
            'DELETE FROM players WHERE id = ?',
            [$actualPlayerId]
        );

        if ($deleted > 0) {
            $this->logger->debug("Deleted player record {$actualPlayerId}");
        } else {
            $this->logger->warning("Player {$actualPlayerId} not found in players table (already deleted?)");
        }
    }

    /**
     * Delete all foreign key references to a player
     *
     * Iterates through all tables that reference players.id and deletes
     * records where the player is referenced (either as actor or target).
     *
     * @param int $playerId Player ID to delete references for
     */
    private function deleteForeignKeyReferences(int $playerId): void
    {
        $totalDeleted = 0;

        foreach (self::FOREIGN_KEY_TABLES as $tableConfig) {
            $table = $tableConfig['table'];
            $columns = $tableConfig['columns'];

            foreach ($columns as $column) {
                try {
                    $deleted = $this->conn->executeStatement(
                        "DELETE FROM `{$table}` WHERE `{$column}` = ?",
                        [$playerId]
                    );

                    if ($deleted > 0) {
                        $this->logger->debug("Deleted {$deleted} records from {$table}.{$column} for player {$playerId}");
                        $totalDeleted += $deleted;
                    }
                } catch (\Exception $e) {
                    // Log warning but don't fail - table might not exist or column might be missing
                    $this->logger->warning("Failed to delete from {$table}.{$column}: " . $e->getMessage());
                }
            }
        }

        if ($totalDeleted > 0) {
            $this->logger->info("Deleted {$totalDeleted} total foreign key references for player {$playerId}");
        }
    }

    /**
     * Clean up orphaned tutorial players for a real player
     *
     * Finds and deletes all active tutorial players for a given real player.
     * This is useful when starting a new tutorial to ensure clean state.
     *
     * @param int $realPlayerId Real player's ID
     * @return int Number of tutorial players cleaned up
     * @throws TutorialPlayerCleanupException If cleanup fails
     */
    public function cleanupOrphanedTutorialPlayers(int $realPlayerId): int
    {
        try {
            // Find all active tutorial players for this real player
            $sql = 'SELECT id, player_id FROM tutorial_players
                    WHERE real_player_id = ? AND is_active = 1 AND deleted_at IS NULL';
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(1, $realPlayerId);
            $result = $stmt->executeQuery();

            $cleanedCount = 0;

            while ($row = $result->fetchAssociative()) {
                $tutorialPlayersId = (int) $row['id'];
                $actualPlayerId = (int) $row['player_id'];

                if ($actualPlayerId > 0) {
                    $this->deleteTutorialPlayer($tutorialPlayersId, $actualPlayerId);
                    $cleanedCount++;
                }
            }

            if ($cleanedCount > 0) {
                $this->logger->info("Cleaned up {$cleanedCount} orphaned tutorial player(s) for real player {$realPlayerId}");
            }

            return $cleanedCount;

        } catch (\Exception $e) {
            $this->logger->error("Failed to cleanup orphaned tutorial players for real player {$realPlayerId}: " . $e->getMessage());
            throw new TutorialPlayerCleanupException(
                "Failed to cleanup orphaned tutorial players for real player {$realPlayerId}",
                0,
                $e
            );
        }
    }

    /**
     * Get list of foreign key tables (for testing/debugging)
     *
     * @return array List of tables that reference players.id
     */
    public static function getForeignKeyTables(): array
    {
        return self::FOREIGN_KEY_TABLES;
    }
}

/**
 * Exception thrown when tutorial player cleanup fails
 */
class TutorialPlayerCleanupException extends \Exception
{
}
