<?php

namespace Tests\Tutorial;

use PHPUnit\Framework\Attributes\Group;
use Tests\Tutorial\Mock\TutorialIntegrationTestCase;

/**
 * Phase 4.6 — pins the FK contract added by
 * `Version20260419210000_AddRealPlayerIdRefForeignKey`:
 *
 *     players.real_player_id_ref  →  players.id  ON DELETE SET NULL
 *
 * When a real player is deleted, every tutorial player that pointed
 * at them must have its `real_player_id_ref` nulled (NOT cascaded —
 * the tutorial player's own row stays, `TutorialPlayerCleanup`
 * handles the orphan).
 *
 * The test skips if the FK isn't applied to the test DB yet: fresh
 * clones from `aoo4` pick up whatever state that DB is in, and the
 * migration may not have been run locally. Once the migration runs,
 * these tests start asserting.
 */
class TutorialRealPlayerFkTest extends TutorialIntegrationTestCase
{
    #[Group('phase-4-6')]
    #[Group('tutorial-link-fk')]
    public function testFkSetsTutorialRefToNullWhenRealPlayerDeleted(): void
    {
        $this->requireFk();

        $realPlayerId = $this->seedRealPlayer();
        $tutPlayerId = $this->seedTutorialPlayer($realPlayerId);

        // Sanity: ref is set pre-delete.
        $this->assertSame(
            $realPlayerId,
            (int) $this->conn->fetchOne(
                'SELECT real_player_id_ref FROM players WHERE id = ?',
                [$tutPlayerId]
            ),
            'seed must populate real_player_id_ref'
        );

        // Delete the real player. FK should null the tutorial row's ref.
        $this->conn->executeStatement('DELETE FROM players WHERE id = ?', [$realPlayerId]);

        // Tutorial player's row itself must still exist.
        $this->assertNotFalse(
            $this->conn->fetchAssociative('SELECT id FROM players WHERE id = ?', [$tutPlayerId]),
            'tutorial players row must survive real-player delete (SET NULL, not CASCADE)'
        );

        // real_player_id_ref must be NULL.
        $ref = $this->conn->fetchOne(
            'SELECT real_player_id_ref FROM players WHERE id = ?',
            [$tutPlayerId]
        );
        $this->assertNull(
            $ref === false ? null : $ref,
            'real_player_id_ref must be SET NULL after real player delete'
        );
    }

    #[Group('phase-4-6')]
    #[Group('tutorial-link-fk')]
    public function testFkRejectsDanglingReferenceOnInsert(): void
    {
        $this->requireFk();

        $this->expectException(\Doctrine\DBAL\Exception::class);

        // Insert a tutorial player whose real_player_id_ref points at
        // a nonexistent id. FK validation must reject it.
        $this->conn->insert('players', [
            'name'               => 'DanglingTut_' . bin2hex(random_bytes(4)),
            'race'               => 'Humain',
            'player_type'        => 'tutorial',
            'coords_id'          => $this->anyCoordsId(),
            'real_player_id_ref' => 999999999,
        ]);
    }

    private function requireFk(): void
    {
        $present = (int) $this->conn->fetchOne(
            "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE()
               AND TABLE_NAME = 'players'
               AND CONSTRAINT_NAME = 'fk_players_real_player_id_ref'"
        );

        if ($present === 0) {
            $this->markTestSkipped(
                'fk_players_real_player_id_ref not present on test DB — '
                . 'run Version20260419210000 against this DB first.'
            );
        }
    }

    private function seedRealPlayer(): int
    {
        $this->conn->insert('players', [
            'name'        => 'PhaseFkReal_' . bin2hex(random_bytes(4)),
            'race'        => 'Humain',
            'player_type' => 'real',
            'coords_id'   => $this->anyCoordsId(),
        ]);

        return (int) $this->conn->lastInsertId();
    }

    private function seedTutorialPlayer(int $realPlayerId): int
    {
        $this->conn->insert('players', [
            'name'                => 'PhaseFkTut_' . bin2hex(random_bytes(4)),
            'race'                => 'Humain',
            'player_type'         => 'tutorial',
            'coords_id'           => $this->anyCoordsId(),
            'real_player_id_ref'  => $realPlayerId,
        ]);

        return (int) $this->conn->lastInsertId();
    }

    private function anyCoordsId(): int
    {
        return (int) $this->conn->fetchOne('SELECT id FROM coords ORDER BY id ASC LIMIT 1');
    }
}
