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
class TutorialPlayerCleanupIntegrationTest extends TutorialIntegrationTestCase
{
    #[Group('tutorial-cleanup-integration')]
    #[Group('d4-phase-c')]
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

    #[Group('tutorial-cleanup-integration')]
    #[Group('d4-phase-c')]
    public function testCleanupOrphanedTutorialPlayersReturnsZeroWhenNoneActive(): void
    {
        $realPlayerId = $this->seedRealPlayer();

        $cleanup = new TutorialPlayerCleanup($this->conn);
        $cleaned = $cleanup->cleanupOrphanedTutorialPlayers($realPlayerId);

        $this->assertSame(0, $cleaned);
    }

    #[Group('tutorial-cleanup-integration')]
    #[Group('d4-phase-c')]
    public function testCleanupOrphanedTutorialPlayersCleansAllActiveForRealPlayer(): void
    {
        // Two active tutorial players for the same real player (e.g.
        // from two interrupted tutorial attempts). Both must go.
        $realPlayerId = $this->seedRealPlayer();
        [$tutPlayerId1, $tutRowId1] = $this->seedTutorialPlayer($realPlayerId, 'sess-a');
        [$tutPlayerId2, $tutRowId2] = $this->seedTutorialPlayer($realPlayerId, 'sess-b');

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

    #[Group('tutorial-cleanup-integration')]
    #[Group('d4-phase-c')]
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
     * Seed a minimal real player (positive id, player_type='real').
     * Most columns have defaults; we only need a unique name.
     *
     * @return int newly created player id
     */
    private function seedRealPlayer(): int
    {
        $this->conn->insert('players', [
            'name'        => 'PhaseCReal_' . bin2hex(random_bytes(4)),
            'race'        => 'Humain',
            'player_type' => 'real',
            'coords_id'   => $this->anyCoordsId(),
        ]);

        return (int) $this->conn->lastInsertId();
    }

    /**
     * Any existing coords.id so the players.coords_id FK is satisfied.
     * aoo4_test ships with ~130 rows; picking the first works.
     */
    private function anyCoordsId(): int
    {
        return (int) $this->conn->fetchOne('SELECT id FROM coords ORDER BY id ASC LIMIT 1');
    }

    /**
     * Seed a tutorial player: a `players` row (player_type='tutorial')
     * plus the matching `tutorial_players` bookkeeping row.
     *
     * @return array{0: int, 1: int} [tutorial players.id, tutorial_players.id]
     */
    private function seedTutorialPlayer(int $realPlayerId, string $sessionId): array
    {
        $this->conn->insert('players', [
            'name'        => 'PhaseCTut_' . bin2hex(random_bytes(4)),
            'race'        => 'Humain',
            'player_type' => 'tutorial',
            'coords_id'   => $this->anyCoordsId(),
        ]);
        $tutPlayerId = (int) $this->conn->lastInsertId();

        $this->conn->insert('tutorial_players', [
            'real_player_id'      => $realPlayerId,
            'tutorial_session_id' => $sessionId . '_' . bin2hex(random_bytes(4)),
            'player_id'           => $tutPlayerId,
            'name'                => 'PhaseCTutName',
            'is_active'           => 1,
        ]);
        $tutRowId = (int) $this->conn->lastInsertId();

        return [$tutPlayerId, $tutRowId];
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
        [$tutPlayerId, $tutRowId] = $this->seedTutorialPlayer($realPlayerId, 'sess-fk');

        $this->conn->insert('players_options', [
            'player_id' => $tutPlayerId,
            'name'      => 'phaseCCascadeProbe',
        ]);

        return [$realPlayerId, $tutPlayerId, $tutRowId];
    }
}
