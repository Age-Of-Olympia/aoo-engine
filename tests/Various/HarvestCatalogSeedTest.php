<?php

namespace Tests\Various;

use App\Service\Map\HarvestCatalogService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

/**
 * Pouring the per-plan yields out of the legacy plan JSONs.
 *
 * The yield belongs to the (plan, type) pair, not to the type: on the real
 * data 28 of the 39 harvestable types give something different depending on
 * where they stand.
 *
 * What matters here is what the seed does with imperfect data — and the real
 * files are imperfect: two are zero bytes, five carry an empty biome entry,
 * and one wall name is a typo repeated across five plans. None of that may
 * turn into invented rates.
 *
 * DB-backed; skips cleanly when the database is unreachable.
 */
class HarvestCatalogSeedTest extends TestCase
{
    private const PLAN_OK = 'plan_test_harvest_ok';
    private const PLAN_BROKEN = 'plan_test_harvest_casse';
    private const TYPE = 'gm_harvest_arbre';

    private ?Connection $conn = null;
    private string $plansDir;

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

        $this->plansDir = dirname(__DIR__, 2) . '/datas/private/plans';

        if (!is_dir($this->plansDir) || !is_writable($this->plansDir)) {
            $this->markTestSkipped('datas/private/plans non inscriptible.');
        }

        $this->cleanup();

        $this->conn->executeStatement(
            "INSERT IGNORE INTO races (code, name, label, description, playable, hidden, kind,
                                       structure_nature, bleeds, wound_color, blocks_passage,
                                       blocks_projectiles, bgColor, color, faction, plan, pv)
             VALUES ('GM_HARVEST', ?, 'Gm harvest', '', 0, 1, 'structure', 'obstacle',
                     '', '#cd7f32', 1, 1, '#8a8a8a', 'black', '', '', 10)",
            [self::TYPE]
        );
    }

    protected function tearDown(): void
    {
        $this->cleanup();
    }

    private function cleanup(): void
    {
        if ($this->conn === null) {
            return;
        }

        foreach ([self::PLAN_OK, self::PLAN_BROKEN] as $plan) {
            @unlink($this->plansDir . '/' . $plan . '.json');
            json()->forget('plans', $plan);
            $this->conn->executeStatement('DELETE FROM race_harvest WHERE plan = ?', [$plan]);
        }

        $this->conn->executeStatement('DELETE FROM races WHERE name = ?', [self::TYPE]);
    }

    /** @param array<int, array<string, mixed>> $biomes */
    private function writePlan(string $name, array $biomes): void
    {
        file_put_contents(
            $this->plansDir . '/' . $name . '.json',
            json_encode(['name' => $name, 'biomes' => $biomes], JSON_PRETTY_PRINT)
        );
        json()->forget('plans', $name);
    }

    /** @return array<string, mixed>|false */
    private function poured(string $plan)
    {
        return $this->conn->fetchAssociative(
            'SELECT h.item, h.exhaust, h.regrow FROM race_harvest h
               JOIN races r ON r.id = h.race_id
              WHERE h.plan = ? AND r.name = ?',
            [$plan, self::TYPE]
        );
    }

    public function testAYieldIsPouredForItsPlan(): void
    {
        $this->writePlan(self::PLAN_OK, [
            ['wall' => self::TYPE, 'ressource' => 'bois', 'exhaust' => 75, 'regrow' => 20],
        ]);

        (new HarvestCatalogService($this->conn))->seed();

        $row = $this->poured(self::PLAN_OK);

        $this->assertNotFalse($row);
        $this->assertSame('bois', $row['item']);
        $this->assertSame(75, (int) $row['exhaust']);
        $this->assertSame(20, (int) $row['regrow']);
    }

    /** A biome entry with no rates keeps them empty — never a default. */
    public function testAnEntryWithoutRatesKeepsThemEmpty(): void
    {
        $this->writePlan(self::PLAN_OK, [['wall' => self::TYPE, 'ressource' => 'bois']]);

        (new HarvestCatalogService($this->conn))->seed();

        $row = $this->poured(self::PLAN_OK);

        $this->assertNotFalse($row);
        $this->assertNull($row['exhaust'], 'aucun taux inventé');
        $this->assertNull($row['regrow']);
    }

    /**
     * An unreadable plan is NAMED and skipped. Two of the real files are zero
     * bytes; guessing rates for them would put silent numbers into the world.
     */
    public function testAnUnreadablePlanIsNamedAndNotGuessed(): void
    {
        file_put_contents($this->plansDir . '/' . self::PLAN_BROKEN . '.json', '');
        json()->forget('plans', self::PLAN_BROKEN);

        $report = (new HarvestCatalogService($this->conn))->seed();

        $this->assertContains(self::PLAN_BROKEN, $report['unreadable']);
        $this->assertFalse($this->poured(self::PLAN_BROKEN));
    }

    /** A wall the catalogue does not know is reported, not silently dropped. */
    public function testAnUnknownTypeIsReported(): void
    {
        $this->writePlan(self::PLAN_OK, [['wall' => 'gm_harvest_coquille', 'ressource' => 'bois']]);

        $report = (new HarvestCatalogService($this->conn))->seed();

        $this->assertContains('gm_harvest_coquille', $report['unknown']);
    }

    /** An empty biome entry is skipped in silence: five real plans carry one. */
    public function testAnEmptyEntryIsSkipped(): void
    {
        $this->writePlan(self::PLAN_OK, [
            ['wall' => '', 'ressource' => ''],
            ['wall' => self::TYPE, 'ressource' => 'bois'],
        ]);

        $report = (new HarvestCatalogService($this->conn))->seed();

        $this->assertSame([], $report['unknown'], 'une entrée vide n\'est pas un type inconnu');
        $this->assertNotFalse($this->poured(self::PLAN_OK));
    }

    /** Re-runnable: a corrected JSON can be poured again over the old row. */
    public function testPouringAgainUpdatesInPlace(): void
    {
        $this->writePlan(self::PLAN_OK, [['wall' => self::TYPE, 'ressource' => 'bois', 'exhaust' => 75]]);
        (new HarvestCatalogService($this->conn))->seed();

        $this->writePlan(self::PLAN_OK, [['wall' => self::TYPE, 'ressource' => 'pierre', 'exhaust' => 10]]);
        (new HarvestCatalogService($this->conn))->seed();

        $row = $this->poured(self::PLAN_OK);

        $this->assertSame('pierre', $row['item']);
        $this->assertSame(10, (int) $row['exhaust']);
        $this->assertSame(
            1,
            (int) $this->conn->fetchOne('SELECT COUNT(*) FROM race_harvest WHERE plan = ?', [self::PLAN_OK]),
            'mise à jour, pas doublon'
        );
    }
}
