<?php

namespace Tests\Service\Monitoring;

use App\Service\Monitoring\MonitoringResult;
use App\Service\Monitoring\StuckPlayersMonitor;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyBootstrapTrait;

/**
 * « Écran de mort coincé » dates the death from the last kill AND from the
 * last death with no killer. Rolled-back transaction; skips without a DB.
 */
class StuckPlayersMonitorTest extends TestCase
{
    use LegacyBootstrapTrait;

    private const PLAYER = 990731;

    private int $playerId = self::PLAYER;

    protected function setUp(): void
    {
        $this->bootstrapLegacyOrSkip();
        $this->link->beginTransaction();

        $id = $this->playerId;
        $this->link->executeStatement(
            "INSERT INTO players (id, name, race, player_type, lastLoginTime) VALUES (?, 'GmMort', 'nain', 'real', UNIX_TIMESTAMP())",
            [$id]
        );
        $this->link->insert('players_options', ['player_id' => $id, 'name' => 'deathScreenPending']);
        $this->link->insert('players_kills', ['player_id' => $id, 'target_id' => $id, 'time' => time() - 10 * 86400, 'plan' => 'gaia']);
    }

    protected function tearDown(): void
    {
        if ($this->link !== null && $this->link->isTransactionActive()) {
            $this->link->rollBack();
        }
        $this->link = null;
    }

    public function testAnOldKillWithTheScreenStillArmedIsFlagged(): void
    {
        $this->assertTrue($this->flagged());
    }

    public function testARecentDeathWithNoKillerIsNotFlagged(): void
    {
        $this->link->insert('players_logs', [
            'player_id' => $this->playerId,
            'target_id' => $this->playerId,
            'text' => 'X a succombé aux éléments.',
            'type' => 'kill',
            'plan' => 'gaia',
            'coords_id' => (int) $this->link->fetchOne('SELECT MIN(id) FROM coords'),
            'time' => time() - 3600,
        ]);

        $this->assertFalse($this->flagged());
    }

    private function flagged(): bool
    {
        /** @var MonitoringResult $result */
        $result = (new \ReflectionMethod(StuckPlayersMonitor::class, 'deathScreens'))
            ->invoke(new StuckPlayersMonitor($this->link));

        foreach ($result->lines as $line) {
            if (str_contains($line['text'], '(#' . $this->playerId . ')')) {
                return true;
            }
        }

        return false;
    }
}
