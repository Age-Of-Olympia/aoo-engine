<?php

namespace Tests\Tutorial;

use App\Tutorial\TutorialPlayerCleanup;
use PHPUnit\Framework\Attributes\Group;
use Tests\Tutorial\Mock\TutorialIntegrationTestCase;

/**
 * Phase 4.5 — characterization test pinning the contract that every
 * reader of the tutorial → real-player link still resolves the correct
 * real player once `tutorial_players.real_player_id` is collapsed into
 * `players.real_player_id_ref`.
 *
 * The test seeds rows where `players.real_player_id_ref` is the sole
 * source of truth. `tutorial_players.real_player_id` is gone after the
 * Phase 4.5 migration.
 *
 * Pins the behaviour of:
 *   - TutorialPlayerCleanup::cleanupOrphanedTutorialPlayers
 *   - TutorialResourceManager::cleanupPrevious (delegates to cleanup)
 */
#[Group('tutorial-link-collapse')]
class TutorialLinkColumnCollapseTest extends TutorialIntegrationTestCase
{
    public function testCleanupDiscoversTutorialPlayerViaRealPlayerIdRef(): void
    {
        $realPlayerId = $this->seedRealPlayer();
        [$tutPlayerId, $tutRowId] = $this->seedTutorialPlayer($realPlayerId);

        $cleanup = new TutorialPlayerCleanup($this->conn);
        $cleaned = $cleanup->cleanupOrphanedTutorialPlayers($realPlayerId);

        $this->assertSame(1, $cleaned, 'cleanup must find the seeded tutorial player by real_player_id_ref');

        $this->assertFalse(
            $this->conn->fetchAssociative('SELECT id FROM players WHERE id = ?', [$tutPlayerId]),
            'players row hard-delete must have run'
        );
        $this->assertSame(
            0,
            (int) $this->conn->fetchOne('SELECT is_active FROM tutorial_players WHERE id = ?', [$tutRowId]),
            'tutorial_players row soft-delete must have run'
        );
    }

    public function testCleanupIgnoresTutorialPlayersOwnedByOtherRealPlayers(): void
    {
        // Two real players, each owns a distinct tutorial player.
        // Cleaning up real A must NOT touch real B's tutorial player.
        $realA = $this->seedRealPlayer();
        $realB = $this->seedRealPlayer();

        [$tutA, ] = $this->seedTutorialPlayer($realA);
        [$tutB, $rowB] = $this->seedTutorialPlayer($realB);

        $cleanup = new TutorialPlayerCleanup($this->conn);
        $cleaned = $cleanup->cleanupOrphanedTutorialPlayers($realA);

        $this->assertSame(1, $cleaned, 'only real A\'s tutorial player should be cleaned');

        $this->assertFalse(
            $this->conn->fetchAssociative('SELECT id FROM players WHERE id = ?', [$tutA]),
            'real A\'s tutorial player row must be deleted'
        );
        $this->assertNotFalse(
            $this->conn->fetchAssociative('SELECT id FROM players WHERE id = ?', [$tutB]),
            'real B\'s tutorial player row must survive'
        );
        $this->assertSame(
            1,
            (int) $this->conn->fetchOne('SELECT is_active FROM tutorial_players WHERE id = ?', [$rowB]),
            'real B\'s tutorial_players row must remain active'
        );
    }

    /*
     * ----------------- seed helpers -----------------
     * Populate `players.real_player_id_ref` only — the old
     * `tutorial_players.real_player_id` column is dropped by Phase 4.5's
     * migration, so seeding it would fail against a migrated schema.
     */
}
