<?php

namespace Tests\Various;

use App\Service\Map\HarvestCatalogService;
use App\Service\Map\ResourceStateService;
use App\Service\ResourceService;
use Classes\View;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

/**
 * Harvesting works whether a resource is a legacy row or already an entity.
 *
 * This is what lets the conversion happen at all: a deploy runs migrations
 * BEFORE code, so for a moment the world holds both shapes. Tolerance ships
 * first — inert the day it lands, since no entity wears the type yet — and the
 * conversion follows without a window where fouiller breaks.
 *
 * DB-backed; skips cleanly when the database is unreachable.
 */
class HarvestToleratesBothSourcesTest extends TestCase
{
    private const PLAN = 'plan_test_tolerance';
    private const TYPE = 'gm_tol_arbre';
    private const ENTITY = 50990101;

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

        if (empty($_SERVER['DOCUMENT_ROOT'])) {
            $_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);
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
             VALUES ('GM_TOL', ?, 'Gm tol', '', 0, 1, 'structure', 'ressource',
                     '', '#cd7f32', 1, 1, '#8a8a8a', 'black', '', '', 100)",
            [self::TYPE]
        );

        file_put_contents(
            $this->plansDir . '/' . self::PLAN . '.json',
            json_encode([
                'name' => self::PLAN,
                'biomes' => [['wall' => self::TYPE, 'ressource' => 'bois', 'exhaust' => 100, 'regrow' => 1000]],
            ], JSON_PRETTY_PRINT)
        );
        json()->forget('plans', self::PLAN);
        (new HarvestCatalogService($this->conn))->seed();
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

        $this->conn->executeStatement('DELETE FROM resources WHERE player_id = ?', [self::ENTITY]);
        $this->conn->executeStatement('DELETE FROM entity_cells WHERE player_id = ?', [self::ENTITY]);
        \App\Service\BuildingService::deleteEntityRows($this->conn, self::ENTITY);
        $this->conn->executeStatement('DELETE FROM race_harvest WHERE plan = ?', [self::PLAN]);
        $this->conn->executeStatement(
            'DELETE m FROM map_resources m JOIN coords c ON c.id = m.coords_id WHERE c.plan = ?',
            [self::PLAN]
        );
        $this->conn->executeStatement(
            'DELETE ec FROM entity_cells ec JOIN coords c ON c.id = ec.coords_id WHERE c.plan = ?',
            [self::PLAN]
        );
        $this->conn->executeStatement('DELETE FROM coords WHERE plan = ?', [self::PLAN]);
        $this->conn->executeStatement('DELETE FROM races WHERE name = ?', [self::TYPE]);
    }

    private function coordsId(int $x, int $y): int
    {
        return (int) View::get_coords_id((object) ['x' => $x, 'y' => $y, 'z' => 0, 'plan' => self::PLAN]);
    }

    /** A harvester standing at the origin of the test plan. */
    private function harvester(): \Classes\Player
    {
        $player = $this->createMock(\Classes\Player::class);
        $player->method('getCoords')->willReturn(
            (object) ['id' => $this->coordsId(0, 0), 'x' => 0, 'y' => 0, 'z' => 0, 'plan' => self::PLAN]
        );

        return $player;
    }

    private function legacyRow(int $x, int $y, int $damages = -1): void
    {
        $this->conn->executeStatement(
            'INSERT INTO map_resources (coords_id, name, damages) VALUES (?, ?, ?)',
            [$this->coordsId($x, $y), self::TYPE, $damages]
        );
    }

    private function entityOn(int $x, int $y): void
    {
        $coordsId = $this->coordsId($x, $y);

        $this->conn->executeStatement(
            "INSERT INTO players (id, name, race, coords_id, player_type)
             VALUES (?, 'Gm arbre', ?, ?, 'resource')",
            [self::ENTITY, self::TYPE, $coordsId]
        );
        $this->conn->executeStatement(
            "INSERT INTO entity_cells (player_id, coords_id, plan, z, x, y, piece, role)
             VALUES (?, ?, ?, 0, ?, ?, 0, 'block')",
            [self::ENTITY, $coordsId, self::PLAN, $x, $y]
        );
    }

    /** @return array<string, int> type => how many harvestable neighbours */
    private function counted(): array
    {
        $res = ResourceService::findResourcesAround($this->harvester());
        $out = [];

        while ($row = $res->fetch_object()) {
            $out[(string) $row->name] = (int) $row->max;
        }

        return $out;
    }

    public function testALegacyRowIsStillFound(): void
    {
        $this->legacyRow(1, 0);

        $this->assertSame([self::TYPE => 1], $this->counted());
    }

    /** The point of the whole MR: an entity counts as a harvestable neighbour. */
    public function testAnEntityIsFoundToo(): void
    {
        $this->entityOn(1, 0);

        $this->assertSame([self::TYPE => 1], $this->counted());
    }

    /** And the two add up, which is the state a deploy passes through. */
    public function testBothSourcesAddUp(): void
    {
        $this->legacyRow(1, 0);
        $this->entityOn(0, 1);

        $this->assertSame([self::TYPE => 2], $this->counted());
    }

    /** An exhausted entity is ignored, as a -2 row always was. */
    public function testAnExhaustedEntityIsIgnored(): void
    {
        $this->entityOn(1, 0);
        (new ResourceStateService($this->conn))->exhaust([self::ENTITY]);

        $this->assertSame([], $this->counted());
    }

    /** Exhaustion reaches the right place for each source. */
    public function testExhaustingWritesToTheRightPlace(): void
    {
        $this->legacyRow(1, 0);
        $this->entityOn(0, 1);

        $rows = ResourceService::getResourcesAround($this->harvester());
        $handles = [];

        while ($row = $rows->fetch_object()) {
            $handles[] = ($row->src ?? '?') . ':' . (int) $row->id;
        }

        ResourceService::exhaustResources($handles);

        $this->assertSame(
            -2,
            (int) $this->conn->fetchOne(
                'SELECT m.damages FROM map_resources m JOIN coords c ON c.id = m.coords_id WHERE c.plan = ?',
                [self::PLAN]
            ),
            'la ligne héritée passe à -2'
        );
        $this->assertTrue(
            (new ResourceStateService($this->conn))->isExhausted(self::ENTITY),
            'l\'entité prend son état, pas un damages qui n\'existe pas'
        );
    }
}
