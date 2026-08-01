<?php

namespace Tests\Various;

use App\Service\Map\HarvestDefaultsService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

/**
 * How much life a harvestable resource is created with.
 *
 * A balance dial — how many blows it takes to fell a tree — so it belongs in
 * the admin rather than in a migration constant. It applies at CREATION only:
 * raising it must never silently re-balance types already placed.
 *
 * DB-backed; skips cleanly when the database is unreachable.
 */
class HarvestDefaultsTest extends TestCase
{
    private ?Connection $conn = null;
    private string $previous = '';

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

        $this->previous = (string) ($this->conn->fetchOne(
            'SELECT value FROM admin_settings WHERE name = ?',
            [HarvestDefaultsService::SETTING]
        ) ?: '');
    }

    protected function tearDown(): void
    {
        if ($this->conn === null) {
            return;
        }

        if ($this->previous === '') {
            $this->conn->executeStatement('DELETE FROM admin_settings WHERE name = ?', [HarvestDefaultsService::SETTING]);

            return;
        }

        (new HarvestDefaultsService())->setPv((int) $this->previous);
    }

    public function testItRoundTripsThroughTheSettings(): void
    {
        $service = new HarvestDefaultsService();
        $service->setPv(250);

        $this->assertSame(250, (new HarvestDefaultsService())->pv(), 'relu depuis la base');
    }

    /** Zero life would make a resource fall to any breath. */
    public function testAnAbsurdValueFallsBackOnTheDefault(): void
    {
        $service = new HarvestDefaultsService();
        $service->setPv(0);

        $this->assertGreaterThanOrEqual(1, $service->pv());
    }

    /** Unset, it is the hundred the arbitration chose. */
    public function testUnsetItIsAHundred(): void
    {
        $this->conn->executeStatement('DELETE FROM admin_settings WHERE name = ?', [HarvestDefaultsService::SETTING]);

        $this->assertSame(100, (new HarvestDefaultsService())->pv());
    }

    /** Every harvestable type carries it: none left fellable in one blow. */
    public function testNoHarvestableTypeIsLeftAtTheOldDefault(): void
    {
        $weak = $this->conn->fetchFirstColumn(
            "SELECT name FROM races WHERE structure_nature = 'ressource' AND pv < 100"
        );

        $this->assertSame([], $weak, 'un type à moins de 100 PV tomberait en quelques coups');
    }
}
