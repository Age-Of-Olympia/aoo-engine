<?php

namespace Tests\Various;

use App\Service\Decay\DecayDefaultsService;
use App\Service\Decay\StructureDecayService;
use App\Service\TurnScheduleService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * Decay of player-built constructions — docs/design-decay-structures.md.
 *
 * The rule's criterion is MEMBERSHIP of `entity_decay`: what Tiled or the
 * admin placed has no row and must be untouchable. Most of what follows
 * guards that, and the arithmetic of the catch-up.
 */
#[Group('items-baseline')]
class StructureDecayTest extends LegacyPlayerFixtureTestCase
{
    private const TYPE = 'zz_mur_decrepit';

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $this->link->executeQuery('SELECT decay_from FROM entity_decay LIMIT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('decay schema unavailable (run migrations): ' . $e->getMessage());
        }

        $this->sowStructureType(self::TYPE, ['pv' => 100, 'spd' => 16]);
    }

    /** Turn length of the sown type: spd 16, so 18 h. */
    private function turn(): int
    {
        return TurnScheduleService::turnDurationSeconds(16);
    }

    private function lifeOf(int $id): int
    {
        $deficit = (int) $this->link->fetchOne(
            "SELECT COALESCE(n, 0) FROM players_bonus WHERE player_id = ? AND name = 'pv'",
            [$id]
        );

        return 100 + $deficit;
    }

    private function aWall(): int
    {
        [$x, $y] = $this->farTile();

        return $this->placeStructure(self::TYPE, $x, $y);
    }

    public function testAnUnenrolledStructureNeverDecays(): void
    {
        $id = $this->aWall();
        $service = new StructureDecayService();

        $service->run(time() + 100 * $this->turn());

        $this->assertSame(100, $this->lifeOf($id), 'what Tiled placed has no row and is never touched');
    }

    public function testEnrolledItDecaysOnceItsGraceHasLapsed(): void
    {
        $id = $this->aWall();
        $service = new StructureDecayService();
        $now = time();

        $service->enrol($id, $now);

        $grace = (new DecayDefaultsService())->graceTurns() * $this->turn();

        $service->run($now + $grace);
        $this->assertSame(100, $this->lifeOf($id), 'the grace itself costs nothing');

        $service->run($now + $grace + $this->turn());
        $this->assertSame(99, $this->lifeOf($id), 'the first turn past the grace does');
    }

    /** Catch-up: forty turns late applies forty turns, not one. */
    public function testTheCatchUpAppliesEveryTurnOwed(): void
    {
        $id = $this->aWall();
        $service = new StructureDecayService();
        $now = time();
        $service->enrol($id, $now);

        $grace = (new DecayDefaultsService())->graceTurns() * $this->turn();
        $service->run($now + $grace + 40 * $this->turn());

        $this->assertSame(60, $this->lifeOf($id));
    }

    /** Idempotent: a second run in the same turn changes nothing. */
    public function testRunningTwiceInATurnDecaysOnce(): void
    {
        $id = $this->aWall();
        $service = new StructureDecayService();
        $now = time();
        $service->enrol($id, $now);

        $at = $now + (new DecayDefaultsService())->graceTurns() * $this->turn() + $this->turn();
        $service->run($at);
        $service->run($at);

        $this->assertSame(99, $this->lifeOf($id));
    }

    /** Use pushes the horizon; it heals nothing. */
    public function testTouchPostponesButDoesNotHeal(): void
    {
        $id = $this->aWall();
        $service = new StructureDecayService();
        $now = time();
        $service->enrol($id, $now);

        $grace = (new DecayDefaultsService())->graceTurns() * $this->turn();
        $at = $now + $grace + 5 * $this->turn();
        $service->run($at);
        $this->assertSame(95, $this->lifeOf($id));

        $service->touch($id, $at);
        $this->assertSame(95, $this->lifeOf($id), 'using it does not mend it');

        $service->run($at + $grace);
        $this->assertSame(95, $this->lifeOf($id), 'and it holds through the new grace');
    }

    /** A road is walked back into shape: horizon AND wear. */
    public function testTouchAndHealMendsTheWear(): void
    {
        $id = $this->aWall();
        $service = new StructureDecayService();
        $now = time();
        $service->enrol($id, $now);

        $grace = (new DecayDefaultsService())->graceTurns() * $this->turn();
        $service->run($now + $grace + 5 * $this->turn());
        $this->assertSame(95, $this->lifeOf($id));

        $service->touchAndHeal($id, $now + $grace + 5 * $this->turn());
        $this->assertSame(100, $this->lifeOf($id), 'a step mends a road in full');
    }

    /** A construction site is not a construction yet. */
    public function testASiteDoesNotDecay(): void
    {
        [$x, $y] = $this->farTile();
        $this->link->executeStatement(
            'UPDATE races SET build_work = 10 WHERE name = ?',
            [self::TYPE]
        );
        $id = $this->placeStructure(self::TYPE, $x, $y, asConstructionSite: true);

        $service = new StructureDecayService();
        $now = time();
        $service->enrol($id, $now);

        /* A site is born at 1 PV and climbs with the work done
           (ConstructionSiteService::raisePvToFloor) — what matters is that
           the pass leaves whatever it finds untouched. */
        $before = $this->lifeOf($id);
        $service->run($now + 100 * $this->turn());

        $this->assertSame($before, $this->lifeOf($id), 'bare posts do not rot');
    }

    /** At zero it is destroyed, exactly as being torn down destroys it. */
    public function testItCollapsesAtZero(): void
    {
        $id = $this->aWall();
        $service = new StructureDecayService();
        $now = time();
        $service->enrol($id, $now);

        $grace = (new DecayDefaultsService())->graceTurns() * $this->turn();
        $result = $service->run($now + $grace + 100 * $this->turn());

        $this->assertContains($id, $result['collapsed']);
        $this->assertSame(
            0,
            (int) $this->link->fetchOne('SELECT COUNT(*) FROM players WHERE id = ?', [$id]),
            'the entity is gone'
        );
        $this->assertSame(
            0,
            (int) $this->link->fetchOne('SELECT COUNT(*) FROM entity_decay WHERE player_id = ?', [$id]),
            'and its decay row went with it'
        );
    }

    /**
     * A construction ruined by war is left standing: the game calls that
     * « en ruine », and decay has no business finishing it off.
     */
    public function testAWarRuinIsNotCollapsedByTheDecayPass(): void
    {
        $id = $this->aWall();
        $service = new StructureDecayService();
        $now = time();
        $service->enrol($id, $now);

        $this->link->executeStatement(
            "INSERT INTO players_bonus (player_id, name, n) VALUES (?, 'pv', -100)
             ON DUPLICATE KEY UPDATE n = VALUES(n)",
            [$id]
        );

        $result = $service->run($now + (new DecayDefaultsService())->graceTurns() * $this->turn() + $this->turn());

        $this->assertNotContains($id, $result['collapsed']);
        $this->assertSame(
            1,
            (int) $this->link->fetchOne('SELECT COUNT(*) FROM players WHERE id = ?', [$id]),
            'the ruin still stands'
        );
    }

    /**
     * Enrolment follows the PLAYER's gesture, not ownership.
     *
     * `asConstructionSite` is that gesture: only the build action passes it,
     * while the admin page and `buildingcmd` raise finished buildings
     * without it. This is the whole criterion, so it is pinned where it is
     * decided rather than where it is read.
     */
    public function testOnlyThePlayerGestureEnrols(): void
    {
        [$x, $y] = $this->farTile();

        $admin = $this->placeStructure(self::TYPE, $x, $y);
        $this->assertSame(
            0,
            (int) $this->link->fetchOne('SELECT COUNT(*) FROM entity_decay WHERE player_id = ?', [$admin]),
            'an admin placement enrols nothing'
        );

        $built = $this->placeStructure(self::TYPE, $x + 1, $y, asConstructionSite: true);
        $this->assertSame(
            1,
            (int) $this->link->fetchOne('SELECT COUNT(*) FROM entity_decay WHERE player_id = ?', [$built]),
            'the construire gesture enrols'
        );
    }

    /** Its horizon starts one grace away, not at zero. */
    public function testEnrolmentSetsTheHorizonOneGraceAway(): void
    {
        [$x, $y] = $this->farTile();
        $before = time();
        $id = $this->placeStructure(self::TYPE, $x, $y, asConstructionSite: true);

        $from = (int) $this->link->fetchOne('SELECT decay_from FROM entity_decay WHERE player_id = ?', [$id]);
        $grace = (new DecayDefaultsService())->graceTurns() * $this->turn();

        $this->assertGreaterThanOrEqual($before + $grace, $from);
        $this->assertLessThanOrEqual(time() + $grace, $from);
    }

    /** The type overrides the global dial. */
    public function testATypeMayCarryItsOwnRate(): void
    {
        $this->link->executeStatement('UPDATE races SET decay_rate = 5 WHERE name = ?', [self::TYPE]);

        $id = $this->aWall();
        $service = new StructureDecayService();
        $now = time();
        $service->enrol($id, $now);

        $grace = (new DecayDefaultsService())->graceTurns() * $this->turn();
        $service->run($now + $grace + 2 * $this->turn());

        $this->assertSame(90, $this->lifeOf($id), 'five a turn, twice');
    }
}
