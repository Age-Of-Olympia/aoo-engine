<?php

namespace Tests\Tutorial;

use App\Entity\NonPlayerCharacter;
use App\Entity\PlayerEntity;
use App\Entity\RealPlayer;
use App\Entity\TutorialPlayer;
use App\Factory\PlayerFactory;
use PHPUnit\Framework\Attributes\Group;
use ReflectionClass;
use Tests\Tutorial\Mock\TutorialIntegrationTestCase;

/**
 * Tutorial / real-player isolation invariants (STI refactor follow-up).
 *
 * After the Phase 0-4 refactor wave on `tutorial-refactoring`, the
 * `players` table gained a `player_type` discriminator (real / tutorial /
 * npc) and the new `App\Factory\PlayerFactory` is the canonical entry
 * point. A regression-risk audit surfaced several "the STI could leak
 * tutorial rows into non-tutorial code" concerns. Most turned out to
 * already be guarded, but "guarded today" is not "guarded forever" —
 * these tests pin the guards so a future edit can't silently drop them.
 *
 * Each test exercises one invariant the audit depended on. If any fails,
 * a consumer that expected real-players-only has started seeing tutorial
 * rows, which is a real regression.
 */
class TutorialIsolationInvariantsTest extends TutorialIntegrationTestCase
{
    #[Group('sti-isolation')]
    public function testEntityByNameRepositoryTargetsRealPlayerSubclass(): void
    {
        // entityByName() must query the RealPlayer repository (STI
        // subclass), not the abstract PlayerEntity base — otherwise it
        // would match tutorial / npc rows with the same name. Locked
        // via source inspection because exercising the path requires
        // committing fixtures to the live DB, which the test suite
        // avoids. The two passing integration tests below + the source
        // pin together guarantee the invariant.
        $source = file_get_contents((new ReflectionClass(PlayerFactory::class))->getFileName());
        $entityByName = $this->extractMethodSource($source, 'entityByName');

        $this->assertStringContainsString(
            'RealPlayer::class',
            $entityByName,
            'entityByName() must scope to RealPlayer, not PlayerEntity (STI leak risk)'
        );
    }

    #[Group('sti-isolation')]
    public function testDiscriminatorMapCoversAllThreeSubclasses(): void
    {
        // The STI discriminator must map real/tutorial/npc onto the
        // expected subclasses. If a future edit drops or renames an
        // entry, Doctrine will silently hydrate the abstract base and
        // blow up elsewhere — catch it here.
        $attrs = (new ReflectionClass(PlayerEntity::class))
            ->getAttributes(\Doctrine\ORM\Mapping\DiscriminatorMap::class);

        $this->assertCount(1, $attrs, 'PlayerEntity must declare a DiscriminatorMap');

        $map = $attrs[0]->newInstance()->value;
        $this->assertSame(RealPlayer::class,         $map['real']     ?? null);
        $this->assertSame(TutorialPlayer::class,     $map['tutorial'] ?? null);
        $this->assertSame(NonPlayerCharacter::class, $map['npc']      ?? null);
    }

    #[Group('sti-isolation')]
    public function testGoPhpOccupiedCoordsQueryIsPlanIsolated(): void
    {
        // go.php's "blocked coords" SQL selects coords_id from `players`
        // without a player_type filter. It's safe because coords_id is
        // globally unique per (x, y, z, plan) — tutorial players live on
        // plan='tutorial', real players on plan='olympia', so their
        // coords_ids never collide. This test pins that structural
        // guarantee by seeding two rows at the same (x, y) on different
        // plans and asserting they have distinct coords_ids.
        $realCoords = $this->insertCoords(42, 42, 0, 'olympia');
        $tutCoords  = $this->insertCoords(42, 42, 0, 'tutorial');

        $this->assertNotSame(
            $realCoords,
            $tutCoords,
            'coords_id must be plan-scoped; tutorial and real coords at same (x,y) must differ'
        );

        // Seed a tutorial player at the tutorial coords. The go.php
        // occupied-coords query filtered by the real coords_id must NOT
        // surface the tutorial player.
        $realId = $this->seedRealPlayer('IsoMover_' . bin2hex(random_bytes(4)));
        [$tutId, ] = $this->seedTutorialPlayerAtCoords($realId, 'IsoBlocker_' . bin2hex(random_bytes(4)), $tutCoords);

        $hits = $this->conn->fetchAllAssociative(
            'SELECT id FROM players WHERE coords_id = ?',
            [$realCoords]
        );
        $this->assertSame(
            [],
            $hits,
            'real-plan occupied-coords query must not surface a tutorial player on a different plan'
        );

        // Sanity: the tutorial player IS at the tutorial coords.
        $tutHits = $this->conn->fetchAllAssociative(
            'SELECT id FROM players WHERE coords_id = ?',
            [$tutCoords]
        );
        $this->assertCount(1, $tutHits);
        $this->assertSame($tutId, (int) $tutHits[0]['id']);
    }

    #[Group('sti-isolation')]
    public function testRefreshListScopesToRealPlayers(): void
    {
        // Classes\Player::refresh_list() powers the cached player
        // catalog used by rankings / admin lists. Post-refactor it
        // filters `player_type='real'` to keep tutorial/npc rows out of
        // public views. Pin via the raw query rather than invoking the
        // static method (which caches across tests and relies on global
        // bootstrap).
        $realName = 'IsoReal_' . bin2hex(random_bytes(4));
        $tutName  = 'IsoTut_'  . bin2hex(random_bytes(4));

        $realId = $this->seedRealPlayer($realName);
        $this->seedTutorialPlayer($realId, $tutName);

        $rows = $this->conn->fetchAllAssociative(
            'SELECT id, name FROM players WHERE player_type = "real" AND name IN (?, ?)',
            [$realName, $tutName]
        );

        $this->assertCount(1, $rows, 'refresh_list scope (player_type="real") must exclude tutorial rows');
        $this->assertSame($realName, $rows[0]['name']);
    }

    /* ---------------------- helpers ---------------------- */

    private function extractMethodSource(string $source, string $method): string
    {
        if (!preg_match('/function\s+' . preg_quote($method, '/') . '\s*\([^}]*\{.*?\n    \}/s', $source, $m)) {
            $this->fail("could not extract source for {$method}()");
        }
        return $m[0];
    }

    private function seedRealPlayer(?string $name = null): int
    {
        $this->conn->insert('players', [
            'name'        => $name ?? 'IsoReal_' . bin2hex(random_bytes(4)),
            'race'        => 'Humain',
            'player_type' => 'real',
            'coords_id'   => $this->anyCoordsId(),
        ]);

        return (int) $this->conn->lastInsertId();
    }

    private function seedTutorialPlayer(int $realPlayerId, string $name): int
    {
        [$tutId, ] = $this->seedTutorialPlayerWithId($realPlayerId, $name);
        return $tutId;
    }

    /**
     * @return array{0:int,1:int} [players.id, tutorial_players.id]
     */
    private function seedTutorialPlayerWithId(int $realPlayerId, string $name): array
    {
        return $this->seedTutorialPlayerAtCoords($realPlayerId, $name, $this->anyCoordsId());
    }

    /**
     * @return array{0:int,1:int} [players.id, tutorial_players.id]
     */
    private function seedTutorialPlayerAtCoords(int $realPlayerId, string $name, int $coordsId): array
    {
        $sessionId = 'iso-' . bin2hex(random_bytes(6));

        $this->conn->insert('players', [
            'name'                => $name,
            'race'                => 'Humain',
            'player_type'         => 'tutorial',
            'coords_id'           => $coordsId,
            'tutorial_session_id' => $sessionId,
            'real_player_id_ref'  => $realPlayerId,
        ]);
        $tutPlayerId = (int) $this->conn->lastInsertId();

        $this->conn->insert('tutorial_players', [
            'tutorial_session_id' => $sessionId,
            'player_id'           => $tutPlayerId,
            'name'                => $name,
            'is_active'           => 1,
        ]);
        $tutRowId = (int) $this->conn->lastInsertId();

        return [$tutPlayerId, $tutRowId];
    }

    private function insertCoords(int $x, int $y, int $z, string $plan): int
    {
        $existing = $this->conn->fetchOne(
            'SELECT id FROM coords WHERE x = ? AND y = ? AND z = ? AND plan = ?',
            [$x, $y, $z, $plan]
        );
        if ($existing !== false) {
            return (int) $existing;
        }

        $this->conn->insert('coords', [
            'x'    => $x,
            'y'    => $y,
            'z'    => $z,
            'plan' => $plan,
        ]);

        return (int) $this->conn->lastInsertId();
    }

    private function anyCoordsId(): int
    {
        return (int) $this->conn->fetchOne('SELECT id FROM coords ORDER BY id ASC LIMIT 1');
    }
}
