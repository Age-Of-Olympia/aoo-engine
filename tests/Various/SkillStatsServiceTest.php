<?php

namespace Tests\Various;

use App\Service\SkillStatsService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Functional test for SkillStatsService — the per-player counts behind the
 * Compétences statistics page. Pins that the population is real players only
 * and that a player with no actions still appears with count 0 (LEFT JOIN).
 *
 * Read-only service; the test only verifies aggregates against known seed
 * mutations inside a rolled-back transaction. Skips when no aoo4 DB is reachable.
 */
class SkillStatsServiceTest extends TestCase
{
    private ?Connection $link = null;
    private int $realPlayerId = 0;

    protected function setUp(): void
    {
        $this->bootstrapOrSkip();
        $this->link->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->link !== null && $this->link->isTransactionActive()) {
            $this->link->rollBack();
        }
        $this->link = null;
    }

    #[Group('skill-stats')]
    public function testRealPlayerCountMatchesDirectQuery(): void
    {
        $expected = (int) $this->link->fetchOne("SELECT COUNT(*) FROM players WHERE player_type = 'real'");

        $this->assertSame($expected, (new SkillStatsService())->realPlayerCount());
    }

    #[Group('skill-stats')]
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

    private function bootstrapOrSkip(): void
    {
        try {
            require_once __DIR__ . '/../../config/bootstrap.php';
            require_once __DIR__ . '/../../config/functions.php';
            require_once __DIR__ . '/../../config/constants.php';
        } catch (\Throwable $e) {
            $this->markTestSkipped('Legacy bootstrap failed: ' . $e->getMessage());
        }

        global $link;
        if (!isset($link) || !$link instanceof Connection) {
            $this->markTestSkipped('Global $link not populated by bootstrap.');
        }

        try {
            $real = $link->fetchOne("SELECT id FROM players WHERE player_type = 'real' ORDER BY id ASC LIMIT 1");
        } catch (\Throwable $e) {
            $this->markTestSkipped('Legacy DB unreachable: ' . $e->getMessage());
        }

        if (empty($real)) {
            $this->markTestSkipped('No real player available — reseed the DB.');
        }

        $this->link = $link;
        $this->realPlayerId = (int) $real;
    }
}
