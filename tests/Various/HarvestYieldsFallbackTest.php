<?php

namespace Tests\Various;

use App\Service\Map\HarvestCatalogService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

/**
 * Where the game reads a plan's yields: the table first, the plan JSON after.
 *
 * The fallback is what lets the admin be the real control without the seed
 * being a prerequisite — a plan nobody has poured keeps harvesting exactly as
 * before. Same arrangement as the dialogs.
 *
 * DB-backed; skips cleanly when the database is unreachable.
 */
class HarvestYieldsFallbackTest extends TestCase
{
    private const PLAN = 'plan_test_yields';
    private const TYPE = 'gm_yields_arbre';

    private ?Connection $conn = null;
    private string $plansDir;
    private int $raceId = 0;

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
            $this->conn = \App\Entity\EntityManagerFactory::getEntityManager()->getConnection();
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
             VALUES ('GM_YIELDS', ?, 'Gm yields', '', 0, 1, 'structure', 'ressource',
                     '', '#cd7f32', 1, 1, '#8a8a8a', 'black', '', '', 10)",
            [self::TYPE]
        );

        $this->raceId = (int) $this->conn->fetchOne('SELECT id FROM races WHERE name = ?', [self::TYPE]);

        file_put_contents(
            $this->plansDir . '/' . self::PLAN . '.json',
            json_encode([
                'name' => self::PLAN,
                'biomes' => [['wall' => self::TYPE, 'ressource' => 'bois', 'exhaust' => 75, 'regrow' => 20]],
            ], JSON_PRETTY_PRINT)
        );
        json()->forget('plans', self::PLAN);
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

        @unlink($this->plansDir . '/' . self::PLAN . '.json');
        json()->forget('plans', self::PLAN);
        $this->conn->executeStatement('DELETE FROM race_harvest WHERE plan = ?', [self::PLAN]);
        $this->conn->executeStatement('DELETE FROM races WHERE name = ?', [self::TYPE]);
    }

    /** Nothing poured: the plan JSON answers, exactly as before. */
    public function testWithoutARowThePlanJsonAnswers(): void
    {
        $yields = (new HarvestCatalogService($this->conn))->yieldsFor(self::PLAN);

        $this->assertArrayHasKey(self::TYPE, $yields);
        $this->assertSame('bois', $yields[self::TYPE]['item']);
        $this->assertSame(75, $yields[self::TYPE]['exhaust']);
        $this->assertSame(20, $yields[self::TYPE]['regrow']);
    }

    /** Poured: the table answers, and it is what the admin edits. */
    public function testARowWinsOverTheJson(): void
    {
        $this->conn->executeStatement(
            'INSERT INTO race_harvest (plan, race_id, item, exhaust, regrow) VALUES (?, ?, ?, ?, ?)',
            [self::PLAN, $this->raceId, 'pierre', 10, null]
        );

        $yields = (new HarvestCatalogService($this->conn))->yieldsFor(self::PLAN);

        $this->assertSame('pierre', $yields[self::TYPE]['item'], 'la base fait foi');
        $this->assertSame(10, $yields[self::TYPE]['exhaust']);
        $this->assertNull($yields[self::TYPE]['regrow'], 'un taux vide vaut jamais');
    }

    /** What the screen saves is what the game then reads. */
    public function testWhatTheScreenSavesIsWhatTheGameReads(): void
    {
        $service = new HarvestCatalogService($this->conn);
        $service->seed();

        $service->save([
            self::PLAN . '|' . $this->raceId => ['item' => 'mana', 'exhaust' => '5', 'regrow' => ''],
        ]);

        $yields = $service->yieldsFor(self::PLAN);

        $this->assertSame('mana', $yields[self::TYPE]['item']);
        $this->assertSame(5, $yields[self::TYPE]['exhaust']);
        $this->assertNull($yields[self::TYPE]['regrow']);
    }
}
