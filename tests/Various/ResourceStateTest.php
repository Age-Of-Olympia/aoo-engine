<?php

namespace Tests\Various;

use App\Service\Map\ResourceStateService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

/**
 * A resource entity's own state: dry, or standing.
 *
 * Arbitrated: an exhausted resource STAYS — it keeps barring the way and the
 * cron regrows it in place. So the state has to live somewhere, and an absent
 * row means standing: a resource is harvestable until something says otherwise.
 *
 * DB-backed; skips cleanly when the database is unreachable.
 */
class ResourceStateTest extends TestCase
{
    private const A = 50990001;
    private const B = 50990002;

    private ?Connection $conn = null;

    protected function setUp(): void
    {
        try {
            require_once __DIR__ . '/../../config/bootstrap.php';
            require_once __DIR__ . '/../../config/functions.php';
            require_once __DIR__ . '/../../config/constants.php';
        } catch (\Throwable $e) {
            $this->markTestSkipped('Legacy bootstrap failed: ' . $e->getMessage());
        }

        try {
            $this->conn = \App\Factory\EntityManagerFactory::getEntityManager()->getConnection();
            $this->conn->fetchOne('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database unreachable: ' . $e->getMessage());
        }

        $this->cleanup();
    }

    protected function tearDown(): void
    {
        $this->cleanup();
    }

    private function cleanup(): void
    {
        $this->conn?->executeStatement(
            'DELETE FROM resources WHERE player_id IN (?, ?)',
            [self::A, self::B]
        );
    }

    /** Nothing recorded means standing — no row to write when placing one. */
    public function testUnknownIsStanding(): void
    {
        $this->assertFalse((new ResourceStateService($this->conn))->isExhausted(self::A));
    }

    public function testExhaustingThenRegrowing(): void
    {
        $service = new ResourceStateService($this->conn);

        $service->exhaust([self::A]);
        $this->assertTrue($service->isExhausted(self::A));

        $service->regrow([self::A]);
        $this->assertFalse($service->isExhausted(self::A), 'la repousse la remet debout, sur place');
    }

    /** Exhausting twice is not an error, and does not duplicate the row. */
    public function testExhaustingTwiceKeepsOneRow(): void
    {
        $service = new ResourceStateService($this->conn);
        $service->exhaust([self::A]);
        $service->exhaust([self::A]);

        $this->assertSame(
            1,
            (int) $this->conn->fetchOne('SELECT COUNT(*) FROM resources WHERE player_id = ?', [self::A])
        );
    }

    /** A whole neighbourhood filtered in one query, not one per cell. */
    public function testTheDryOnesAmongManyAreFoundAtOnce(): void
    {
        $service = new ResourceStateService($this->conn);
        $service->exhaust([self::A]);

        $dry = $service->exhaustedAmong([self::A, self::B]);

        $this->assertArrayHasKey(self::A, $dry);
        $this->assertArrayNotHasKey(self::B, $dry, 'celle qui n\'a pas de ligne est debout');
    }

    /** When it ran dry is kept: a rate that never fires must be tellable. */
    public function testWhenItRanDryIsRecorded(): void
    {
        (new ResourceStateService($this->conn))->exhaust([self::A]);

        $this->assertNotNull(
            $this->conn->fetchOne('SELECT exhausted_at FROM resources WHERE player_id = ?', [self::A])
        );
    }
}
