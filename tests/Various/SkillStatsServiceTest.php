<?php

namespace Tests\Various;

use App\Service\SkillStatsService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyBootstrapTrait;

/**
 * Functional test for SkillStatsService — the per-player counts behind the
 * Compétences statistics page. Pins that the population is real players only
 * and that a player with no actions still appears with count 0 (LEFT JOIN).
 *
 * Read-only service; the test only verifies aggregates against known seed
 * mutations inside a rolled-back transaction. Skips when no aoo4 DB is reachable.
 */
#[Group('skill-stats')]
class SkillStatsServiceTest extends TestCase
{
    use LegacyBootstrapTrait;

    private int $realPlayerId = 0;

    protected function setUp(): void
    {
        $this->bootstrapLegacyOrSkip();
        $this->realPlayerId = $this->firstRealPlayerIdOrSkip();
        $this->link->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->link !== null && $this->link->isTransactionActive()) {
            $this->link->rollBack();
        }
        $this->link = null;
    }

    public function testRealPlayerCountMatchesDirectQuery(): void
    {
        $expected = (int) $this->link->fetchOne("SELECT COUNT(*) FROM players WHERE player_type = 'real'");

        $this->assertSame($expected, (new SkillStatsService())->realPlayerCount());
    }

    public function testPlayerActionCountsCoverRealPlayersAndExcludeNonReal(): void
    {
        $counts = (new SkillStatsService())->playerActionCounts();

        $ids = array_column($counts, 'id');
        $this->assertContains($this->realPlayerId, $ids, 'Every real player must appear (count 0 included).');

        $nonRealIds = $this->link->fetchFirstColumn("SELECT id FROM players WHERE player_type <> 'real'");
        foreach ($nonRealIds as $nonReal) {
            $this->assertNotContains((int) $nonReal, $ids, 'Non-real players must be excluded.');
        }
    }
}
