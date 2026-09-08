<?php

namespace Tests\Various;

use App\Factory\PlayerFactory;
use Classes\Player;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyBootstrapTrait;

/**
 * Characterization smoke test pinning the Phase 1 migration invariant.
 *
 * The Classes\Player dismantling mechanically migrates ~50 call sites
 * of `new Player($id)` to
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
#[Group('player-factory')]
class PlayerFactoryLegacyEquivalenceTest extends TestCase
{
    use LegacyBootstrapTrait;

    private int $sampleId = 0;

    protected function setUp(): void
    {
        $this->bootstrapLegacyOrSkip();
        $this->sampleId = $this->firstRealPlayerIdOrSkip();
    }

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

    public function testCaracsIsIdenticalAfterGetCaracs(): void
    {
        $direct  = new Player($this->sampleId);
        $factory = PlayerFactory::legacy($this->sampleId);

        $direct->get_caracs();
        $factory->get_caracs();

        $this->assertEquals($direct->caracs, $factory->caracs);
    }

    public function testCoordsIsIdenticalAfterGetCoords(): void
    {
        $direct  = new Player($this->sampleId);
        $factory = PlayerFactory::legacy($this->sampleId);

        $direct->getCoords();
        $factory->getCoords();

        $this->assertEquals($direct->coords, $factory->coords);
    }
}
