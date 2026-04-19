<?php

namespace Tests\Various;

use App\Factory\PlayerFactory;
use Classes\Player;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Characterization smoke test pinning the Phase 1 migration invariant.
 *
 * The Classes\Player dismantling roadmap (docs/player-dismantling-roadmap.md)
 * mechanically migrates ~50 call sites of `new Player($id)` to
 * `PlayerFactory::legacy($id)` / `::active()` in Phase 1. This test pins
 * the invariant the search-and-replace relies on: both construction paths
 * must produce objects indistinguishable on the property-heavy public
 * surface callers lean on (->id, ->data->X, ->caracs->X, ->coords->X).
 *
 * Today `PlayerFactory::legacy()` is literally `return new Player($id)`.
 * This test catches any future change that silently routes the factory
 * through extra initialization while `new Player()` stays bare — the kind
 * of divergence PHPStan cannot see on dynamic property access.
 *
 * Skips cleanly when the DB is unreachable or the sample player row is
 * missing, so `make test` stays green in fresh checkouts and CI jobs that
 * do not provision `aoo4` (the phpunit stage has no mariadb service).
 */
class PlayerFactoryLegacyEquivalenceTest extends TestCase
{
    private int $sampleId = 0;

    protected function setUp(): void
    {
        $this->sampleId = $this->bootstrapLegacyOrSkip();
    }

    #[Group('player-factory')]
    #[Group('dismantling-phase-1')]
    public function testLegacyReturnsPlayerInstanceWithMatchingId(): void
    {
        $direct  = new Player($this->sampleId);
        $factory = PlayerFactory::legacy($this->sampleId);

        $this->assertInstanceOf(Player::class, $factory);
        $this->assertSame(get_class($direct), get_class($factory));
        $this->assertSame($this->sampleId, $factory->id);
        $this->assertSame($direct->id, $factory->id);
        $this->assertSame($direct->getId(), $factory->getId());
    }

    #[Group('player-factory')]
    #[Group('dismantling-phase-1')]
    public function testDataIsIdenticalAfterGetData(): void
    {
        $direct  = new Player($this->sampleId);
        $factory = PlayerFactory::legacy($this->sampleId);

        $direct->get_data();
        $factory->get_data();

        // Both calls resolve within the same tick, so any wall-clock-
        // derived field (e.g. isInactive) agrees.
        $this->assertEquals($direct->data, $factory->data);
    }

    #[Group('player-factory')]
    #[Group('dismantling-phase-1')]
    public function testCaracsIsIdenticalAfterGetCaracs(): void
    {
        $direct  = new Player($this->sampleId);
        $factory = PlayerFactory::legacy($this->sampleId);

        $direct->get_caracs();
        $factory->get_caracs();

        $this->assertEquals($direct->caracs, $factory->caracs);
    }

    #[Group('player-factory')]
    #[Group('dismantling-phase-1')]
    public function testCoordsIsIdenticalAfterGetCoords(): void
    {
        $direct  = new Player($this->sampleId);
        $factory = PlayerFactory::legacy($this->sampleId);

        $direct->getCoords();
        $factory->getCoords();

        $this->assertEquals($direct->coords, $factory->coords);
    }

    /**
     * Bootstrap the legacy environment (Doctrine connection, global $link,
     * constants, functions) and return the id of a real player suitable for
     * the equivalence checks, or markTestSkipped if anything is missing.
     */
    private function bootstrapLegacyOrSkip(): int
    {
        try {
            require_once __DIR__ . '/../../config/bootstrap.php';
            require_once __DIR__ . '/../../config/functions.php';
            require_once __DIR__ . '/../../config/constants.php';
        } catch (\Throwable $e) {
            $this->markTestSkipped('Legacy bootstrap failed: ' . $e->getMessage());
        }

        global $link;
        if (!isset($link)) {
            $this->markTestSkipped('Global $link not populated by bootstrap.');
        }

        try {
            $link->executeQuery('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('Legacy DB unreachable: ' . $e->getMessage());
        }

        try {
            $row = $link->fetchAssociative(
                "SELECT id FROM players WHERE id > 0 AND (player_type IS NULL OR player_type = 'real') ORDER BY id ASC LIMIT 1"
            );
        } catch (\Throwable $e) {
            $this->markTestSkipped('players table unreadable: ' . $e->getMessage());
        }

        if (empty($row['id'])) {
            $this->markTestSkipped(
                'No real player row available — run scripts/testing/reset_test_database.sh.'
            );
        }

        return (int) $row['id'];
    }
}
