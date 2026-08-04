<?php

namespace Tests\Various;

use App\Service\PoolSpendService;
use App\Simulation\SimulationGuard;
use Classes\Player;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * The turn pool is SHARED the moment several hands drive one entity, so
 * every spend is one guarded statement floored at the empty pool: the
 * last point is taken once, and the answer is what was actually taken.
 */
#[Group('action')]
class PoolSpendServiceTest extends LegacyPlayerFixtureTestCase
{
    private function actorWithPool(string $prefix): Player
    {
        $player = $this->createRealPlayer($prefix);
        $player->get_caracs();

        return $player;
    }

    private function bonusRow(int $playerId, string $trait): ?int
    {
        $n = $this->link->fetchOne(
            'SELECT n FROM players_bonus WHERE player_id = ? AND name = ?',
            [$playerId, $trait]
        );

        return $n === false ? null : (int) $n;
    }

    public function testASpendTakesFromThePoolAndAnswersIt(): void
    {
        $actor = $this->actorWithPool('GmPool1');
        $max = (int) $actor->caracs->a;

        $spent = (new PoolSpendService())->spend($actor, 'a', 2);

        $this->assertSame(2, $spent);
        $this->assertSame(-2, $this->bonusRow($actor->id, 'a'));
        $this->assertSame($max - 2, $actor->getRemaining('a'), 'the in-memory turn follows the pool');
    }

    public function testAnOverdraftStopsAtTheEmptyPool(): void
    {
        $actor = $this->actorWithPool('GmPool2');
        $max = (int) $actor->caracs->a;

        $spent = (new PoolSpendService())->spend($actor, 'a', $max + 5);

        $this->assertSame($max, $spent, 'the pool yields what it held, never more');
        $this->assertSame(-$max, $this->bonusRow($actor->id, 'a'));
        $this->assertSame(0, $actor->getRemaining('a'));
    }

    public function testTheLastPointIsTakenOnce(): void
    {
        $actor = $this->actorWithPool('GmPool3');
        $max = (int) $actor->caracs->a;

        $service = new PoolSpendService();
        $service->spend($actor, 'a', $max);

        $this->assertSame(0, $service->spend($actor, 'a', 1), 'an empty pool yields nothing');
        $this->assertSame(-$max, $this->bonusRow($actor->id, 'a'), 'the floor holds');
    }

    public function testABuffSpendsBeyondTheMaximum(): void
    {
        $actor = $this->actorWithPool('GmPool4');
        $max = (int) $actor->caracs->mvt;
        $this->link->executeStatement(
            'INSERT INTO players_bonus (player_id, name, n) VALUES (?, "mvt", 2)',
            [$actor->id]
        );
        $actor->get_caracs();

        $spent = (new PoolSpendService())->spend($actor, 'mvt', $max + 2);

        $this->assertSame($max + 2, $spent, 'a buff belongs to the pool');
        $this->assertSame(-$max, $this->bonusRow($actor->id, 'mvt'));
    }

    public function testDrainEmptiesThePoolAndAnswersWhatItHeld(): void
    {
        $actor = $this->actorWithPool('GmPool5');
        $max = (int) $actor->caracs->a;

        $service = new PoolSpendService();
        $service->spend($actor, 'a', 1);
        $drained = $service->drain($actor, 'a');

        $this->assertSame($max - 1, $drained);
        $this->assertSame(0, $actor->getRemaining('a'));
    }

    public function testANonPoolTraitIsNotThisServicesJob(): void
    {
        $actor = $this->actorWithPool('GmPool6');

        $this->assertSame(0, (new PoolSpendService())->spend($actor, 'pv', 3));
        $this->assertNull($this->bonusRow($actor->id, 'pv'), 'pv keeps its own writer');
    }

    public function testAPreviewPaysNothingButWalksTheSameBranch(): void
    {
        $actor = $this->actorWithPool('GmPool7');
        $max = (int) $actor->caracs->a;

        $spent = SimulationGuard::run(
            fn (): int => (new PoolSpendService())->spend($actor, 'a', 2)
        );

        $this->assertSame(2, $spent, 'the preview reports the spend as taken');
        $this->assertNull($this->bonusRow($actor->id, 'a'), 'nothing persisted');
        $this->assertSame($max - 2, $actor->getRemaining('a'), 'the preview chain sees it in memory');
    }
}
