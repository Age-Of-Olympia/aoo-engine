<?php

namespace App\Tutorial;

use Doctrine\DBAL\Connection;

/**
 * Removes what a tutorial session leaves behind: its character, its enemies
 * and every row that points at them.
 */
class TutorialCleanup
{
    /**
     * Tables that reference players.id, by column. Children go before the
     * players row; the list covers more than a tutorial character ever
     * writes so that nothing blocks the final DELETE.
     */
    private const REFERENCING_TABLES = [
        ['table' => 'players_logs', 'columns' => ['player_id', 'target_id']],
        ['table' => 'players_actions', 'columns' => ['player_id']],
        ['table' => 'players_items', 'columns' => ['player_id']],
        ['table' => 'players_effects', 'columns' => ['player_id']],
        ['table' => 'players_options', 'columns' => ['player_id']],
        ['table' => 'players_connections', 'columns' => ['player_id']],
        // An enemy gets a PV bonus when it spawns
        ['table' => 'players_bonus', 'columns' => ['player_id']],
        ['table' => 'players_assists', 'columns' => ['player_id', 'target_id']],
        ['table' => 'players_kills', 'columns' => ['player_id', 'target_id']],
        ['table' => 'players_upgrades', 'columns' => ['player_id']],
        ['table' => 'players_quests_steps', 'columns' => ['player_id']],
        ['table' => 'players_quests', 'columns' => ['player_id']],
        ['table' => 'players_forum_missives', 'columns' => ['player_id']],
        ['table' => 'players_forum_rewards', 'columns' => ['from_player_id', 'to_player_id']],
        ['table' => 'players_followers', 'columns' => ['player_id']],
        ['table' => 'players_banned', 'columns' => ['player_id']],
        ['table' => 'players_pnjs', 'columns' => ['player_id', 'pnj_id']],
        ['table' => 'map_tiles', 'columns' => ['player_id']],
        ['table' => 'items_asks', 'columns' => ['player_id']],
        ['table' => 'items_bids', 'columns' => ['player_id']],
        ['table' => 'items_exchanges', 'columns' => ['player_id', 'target_id']],
        ['table' => 'players_items_exchanges', 'columns' => ['player_id', 'target_id']],
    ];

    public function __construct(private Connection $conn)
    {
    }

    /**
     * Marks the tutorial_players row inactive (kept for the record) and
     * deletes the character itself.
     *
     * @throws TutorialCleanupException
     */
    public function deleteTutorialPlayer(int $tutorialPlayersId, int $actualPlayerId): void
    {
        try {
            $this->conn->update(
                'tutorial_players',
                ['is_active' => 0, 'deleted_at' => date('Y-m-d H:i:s')],
                ['id' => $tutorialPlayersId]
            );

            if ($actualPlayerId > 0) {
                $this->deleteCharacter($actualPlayerId);
            }
        } catch (\Exception $e) {
            throw new TutorialCleanupException(
                "Failed to delete tutorial player {$tutorialPlayersId} (actual ID: {$actualPlayerId})",
                0,
                $e
            );
        }
    }

    /**
     * Deletes every active tutorial character of a real player.
     *
     * @return int characters deleted
     * @throws TutorialCleanupException
     */
    public function cleanupOrphanedTutorialPlayers(int $realPlayerId): int
    {
        try {
            $rows = $this->conn->fetchAllAssociative(
                'SELECT tp.id, tp.player_id
                   FROM tutorial_players tp
                   JOIN players p ON p.id = tp.player_id
                  WHERE p.real_player_id_ref = ? AND tp.is_active = 1 AND tp.deleted_at IS NULL',
                [$realPlayerId]
            );
        } catch (\Exception $e) {
            throw new TutorialCleanupException(
                "Failed to cleanup orphaned tutorial players for real player {$realPlayerId}",
                0,
                $e
            );
        }

        $cleanedCount = 0;
        foreach ($rows as $row) {
            if ((int) $row['player_id'] > 0) {
                $this->deleteTutorialPlayer((int) $row['id'], (int) $row['player_id']);
                $cleanedCount++;
            }
        }

        return $cleanedCount;
    }

    /**
     * Deletes the enemies of a session and their tracking rows. Their cells
     * stay: they belong to the map instance, deleted after the character.
     *
     * @return int enemies deleted
     * @throws TutorialCleanupException
     */
    public function removeEnemiesBySession(string $sessionId): int
    {
        try {
            $enemyIds = $this->conn->fetchFirstColumn(
                'SELECT enemy_player_id FROM tutorial_enemies WHERE tutorial_session_id = ?',
                [$sessionId]
            );

            foreach ($enemyIds as $enemyId) {
                $this->deleteCharacter((int) $enemyId);
            }

            $this->conn->delete('tutorial_enemies', ['tutorial_session_id' => $sessionId]);

            return count($enemyIds);
        } catch (\Exception $e) {
            throw new TutorialCleanupException("Failed to remove tutorial enemies for session {$sessionId}", 0, $e);
        }
    }

    /**
     * Deletes the enemies whose session is gone or completed.
     *
     * @return int enemies deleted
     * @throws TutorialCleanupException
     */
    public function removeOrphanedEnemies(): int
    {
        try {
            $rows = $this->conn->fetchAllAssociative(
                'SELECT te.enemy_player_id, te.tutorial_session_id
                   FROM tutorial_enemies te
                   LEFT JOIN tutorial_progress tp ON te.tutorial_session_id = tp.tutorial_session_id
                  WHERE tp.id IS NULL OR tp.completed = 1'
            );

            foreach ($rows as $row) {
                $this->deleteCharacter((int) $row['enemy_player_id']);
                $this->conn->delete('tutorial_enemies', ['tutorial_session_id' => $row['tutorial_session_id']]);
            }

            return count($rows);
        } catch (\Exception $e) {
            throw new TutorialCleanupException('Failed to remove orphaned tutorial enemies', 0, $e);
        }
    }

    private function deleteCharacter(int $playerId): void
    {
        if ($playerId === 0) {
            return;
        }

        foreach (self::REFERENCING_TABLES as ['table' => $table, 'columns' => $columns]) {
            foreach ($columns as $column) {
                try {
                    $this->conn->delete($table, [$column => $playerId]);
                } catch (\Exception) {
                    // A table missing from an older schema must not stop the
                    // rest; a reference really left behind fails the DELETE below
                }
            }
        }

        $this->conn->delete('players', ['id' => $playerId]);
    }
}

class TutorialCleanupException extends \Exception
{
}
