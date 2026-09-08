<?php

namespace Tests\Tutorial;

use App\Tutorial\TutorialPlayerCleanup;
use PHPUnit\Framework\Attributes\Group;
use Tests\Tutorial\Mock\TutorialIntegrationTestCase;

/**
 * D4 Phase C — integration test for App\Tutorial\TutorialPlayerCleanup.
 *
 * The cleanup service does two things the unit tests can't verify:
 *
 *   1. Two-phase deletion — soft-delete in `tutorial_players` (sets
 *      `is_active=0` and `deleted_at`) THEN hard-delete from `players`
 *      plus ~25 foreign-key tables. Any reordering risks leaving
 *      orphan rows.
 *
 *   2. `cleanupOrphanedTutorialPlayers` discovers every active tutorial
 *      player for a given real player. If the SELECT misses inactive
 *      rows or picks up the wrong real_player_id, the bug would not
 *      surface until production data diverges.
 *
 * Uses the TutorialIntegrationTestCase harness: transactional rollback
 * isolates every test, and markTestSkipped fires when the test DB is
 * unreachable (keeps the phpunit stage green in CI, where no mariadb
 * service is attached).
 *
 * Seeds a throwaway real player + tutorial player + one FK row
 * (`players_options`) inside the transaction. After deletion the FK
 * row must be gone too — that's the proof the cleanup honours its
 * ~25-table cascade list.
 */
#[Group('tutorial-cleanup-integration')]
class TutorialPlayerCleanupIntegrationTest extends TutorialIntegrationTestCase
{
    public function testDeleteTutorialPlayerSoftDeletesAndHardDeletesWithCascade(): void
    {
        [$realPlayerId, $tutPlayerId, $tutPlayersRowId] = $this->seedTutorialPlayerWithFkRow();

        $cleanup = new TutorialPlayerCleanup($this->conn);
        $cleanup->deleteTutorialPlayer($tutPlayersRowId, $tutPlayerId);

        // Phase 1: tutorial_players row soft-deleted, not removed.
        $tutRow = $this->conn->fetchAssociative(
            'SELECT is_active, deleted_at FROM tutorial_players WHERE id = ?',
            [$tutPlayersRowId]
        );
        $this->assertNotFalse($tutRow, 'tutorial_players row should still exist (soft delete)');
        $this->assertSame(0, (int) $tutRow['is_active']);
        $this->assertNotNull($tutRow['deleted_at']);

        // Phase 2: players row hard-deleted.
        $playerRow = $this->conn->fetchAssociative(
            'SELECT id FROM players WHERE id = ?',
            [$tutPlayerId]
        );
        $this->assertFalse($playerRow, 'players row should be hard-deleted');

        // FK cascade: the players_options row we seeded must be gone.
        $optionCount = (int) $this->conn->fetchOne(
            'SELECT COUNT(*) FROM players_options WHERE player_id = ?',
            [$tutPlayerId]
        );
        $this->assertSame(0, $optionCount, 'players_options FK rows must cascade-delete');
    }

    public function testCleanupOrphanedTutorialPlayersReturnsZeroWhenNoneActive(): void
    {
        $realPlayerId = $this->seedRealPlayer();

        $cleanup = new TutorialPlayerCleanup($this->conn);
        $cleaned = $cleanup->cleanupOrphanedTutorialPlayers($realPlayerId);

        $this->assertSame(0, $cleaned);
    }

    public function testCleanupOrphanedTutorialPlayersCleansAllActiveForRealPlayer(): void
    {
        // Two active tutorial players for the same real player (e.g.
        // from two interrupted tutorial attempts). Both must go.
        $realPlayerId = $this->seedRealPlayer();
        [$tutPlayerId1, $tutRowId1] = $this->seedTutorialPlayer($realPlayerId);
        [$tutPlayerId2, $tutRowId2] = $this->seedTutorialPlayer($realPlayerId);

        $cleanup = new TutorialPlayerCleanup($this->conn);
        $cleaned = $cleanup->cleanupOrphanedTutorialPlayers($realPlayerId);

        $this->assertSame(2, $cleaned);

        // Both players rows gone, both tutorial_players rows soft-deleted.
        foreach ([$tutPlayerId1, $tutPlayerId2] as $pid) {
            $this->assertFalse(
                $this->conn->fetchAssociative('SELECT id FROM players WHERE id = ?', [$pid]),
                "player row {$pid} should be hard-deleted"
            );
        }
        foreach ([$tutRowId1, $tutRowId2] as $rowId) {
            $active = (int) $this->conn->fetchOne(
                'SELECT is_active FROM tutorial_players WHERE id = ?',
                [$rowId]
            );
            $this->assertSame(0, $active, "tutorial_players row {$rowId} should be soft-deleted");
        }
    }

    public function testHardDeleteSkipsWhenPlayerIdIsInvalid(): void
    {
        // The service guards against accidental destructive calls on
        // invalid IDs (<= 0). The tutorial_players row still soft-deletes
        // — the guard is specifically for the players-table phase.
        [, , $tutPlayersRowId] = $this->seedTutorialPlayerWithFkRow();

        $cleanup = new TutorialPlayerCleanup($this->conn);
        $cleanup->deleteTutorialPlayer($tutPlayersRowId, -1);

        $tutRow = $this->conn->fetchAssociative(
            'SELECT is_active FROM tutorial_players WHERE id = ?',
            [$tutPlayersRowId]
        );
        $this->assertSame(0, (int) $tutRow['is_active'], 'soft delete should still run');
    }

    /**
     * Seed a full fixture: real player + tutorial player + one FK row
     * in players_options (to prove the ~25-table cascade ran).
     *
     * @return array{0: int, 1: int, 2: int} [realPlayerId, tutPlayerId, tutPlayersRowId]
     */
    private function seedTutorialPlayerWithFkRow(): array
    {
        $realPlayerId = $this->seedRealPlayer();
        [$tutPlayerId, $tutRowId] = $this->seedTutorialPlayer($realPlayerId);

        $this->conn->insert('players_options', [
            'player_id' => $tutPlayerId,
            'name'      => 'phaseCCascadeProbe',
        ]);

        return [$realPlayerId, $tutPlayerId, $tutRowId];
    }
}
