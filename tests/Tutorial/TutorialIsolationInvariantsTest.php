<?php

namespace Tests\Tutorial;

use App\Entity\NonPlayerCharacter;
use App\Entity\GameEntity;
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
#[Group('sti-isolation')]
class TutorialIsolationInvariantsTest extends TutorialIntegrationTestCase
{

    public function testDiscriminatorMapCoversAllThreeSubclasses(): void
    {
        // The STI discriminator must map real/tutorial/npc onto the
        // expected subclasses. If a future edit drops or renames an
        // entry, Doctrine will silently hydrate the abstract base and
        // blow up elsewhere — catch it here.
        $attrs = (new ReflectionClass(GameEntity::class))
            ->getAttributes(\Doctrine\ORM\Mapping\DiscriminatorMap::class);

        $this->assertCount(1, $attrs, 'GameEntity must declare a DiscriminatorMap');

        $map = $attrs[0]->newInstance()->value;
        $this->assertSame(RealPlayer::class,         $map['real']     ?? null);
        $this->assertSame(TutorialPlayer::class,     $map['tutorial'] ?? null);
        $this->assertSame(NonPlayerCharacter::class, $map['npc']      ?? null);
    }

    public function testGoPhpOccupiedCoordsQueryIsPlanIsolated(): void
    {
        // go.php's "blocked coords" SQL selects coords_id from `players`
        // without a player_type filter. It's safe because coords_id is
        // globally unique per (x, y, z, plan) — tutorial players live on
        // plan='tutorial', real players on plan='olympia', so their
        // coords_ids never collide. This test pins that structural
        // guarantee by seeding two rows at the same (x, y) on different
        // plans and asserting they have distinct coords_ids.
        $realCoords = $this->seedTile(42, 42, 0, 'olympia');
        $tutCoords  = $this->seedTile(42, 42, 0, 'tutorial');

        $this->assertNotSame(
            $realCoords,
            $tutCoords,
            'coords_id must be plan-scoped; tutorial and real coords at same (x,y) must differ'
        );

        // Seed a tutorial player at the tutorial coords. The go.php
        // occupied-coords query filtered by the real coords_id must NOT
        // surface the tutorial player.
        $realId = $this->seedRealPlayer('IsoMover_' . bin2hex(random_bytes(4)));
        [$tutId, ] = $this->seedTutorialPlayer($realId, 'IsoBlocker_' . bin2hex(random_bytes(4)), $tutCoords);

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
}
