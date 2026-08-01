<?php

namespace Tests\Various;

use App\Service\Map\TileOccupancyService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Tests\Support\PlantsResourcesTrait;

/**
 * What a `forbidden` trigger still adds, cell by cell.
 *
 * Fences were long the only way to make something impassable: a tree or a
 * wall was a drawing, so the refusal was painted on by hand. Resources and
 * structures refuse the step by themselves now, and those fences became
 * duplicates that OUTLIVE what they doubled — remove the wall and the fence
 * keeps refusing on an empty cell.
 *
 * This decides which ones can go, so it decides what gets deleted: a
 * character standing in a doorway must never count as a permanent wall.
 *
 * DB-backed; skips cleanly when the database is unreachable.
 */
class PermanentlyBlockedTest extends TestCase
{
    use PlantsResourcesTrait;

    private const PLAN = 'plan_test_fences';

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

        if (empty($_SERVER['DOCUMENT_ROOT'])) {
            $_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);
        }

        try {
            $this->conn = \App\Entity\EntityManagerFactory::getEntityManager()->getConnection();
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
        if ($this->conn === null) {
            return;
        }

        foreach ($this->conn->fetchFirstColumn(
            'SELECT p.id FROM players p JOIN coords c ON c.id = p.coords_id WHERE c.plan = ?',
            [self::PLAN]
        ) as $id) {
            $this->conn->executeStatement('DELETE FROM entity_cells WHERE player_id = ?', [(int) $id]);
            \App\Service\BuildingService::deleteEntityRows($this->conn, (int) $id);
        }

        $this->uprootResources($this->conn, self::PLAN);

        $this->conn->executeStatement(
            'DELETE m FROM map_triggers m JOIN coords c ON c.id = m.coords_id WHERE c.plan = ?',
            [self::PLAN]
        );

        $this->conn->executeStatement(
            'DELETE ec FROM entity_cells ec JOIN coords c ON c.id = ec.coords_id WHERE c.plan = ?',
            [self::PLAN]
        );
        $this->conn->executeStatement('DELETE FROM coords WHERE plan = ?', [self::PLAN]);
    }

    private function coordsId(int $x, int $y): int
    {
        return (int) \Classes\View::get_coords_id(
            (object) ['x' => $x, 'y' => $y, 'z' => 0, 'plan' => self::PLAN]
        );
    }

    /** An entity of the given type, holding one cell in the given role. */
    private function entityOn(int $coordsId, string $playerType, string $role, string $race = 'gm_fence_mur'): int
    {
        $this->conn->executeStatement(
            'INSERT INTO players (name, race, coords_id, player_type) VALUES (?, ?, ?, ?)',
            ['GmFence', $race, $coordsId, $playerType]
        );

        $id = (int) $this->conn->lastInsertId();
        $cell = $this->conn->fetchAssociative('SELECT x, y, z, plan FROM coords WHERE id = ?', [$coordsId]);

        $this->conn->executeStatement(
            'INSERT INTO entity_cells (player_id, coords_id, plan, z, x, y, piece, role)
             VALUES (?, ?, ?, ?, ?, ?, 0, ?)',
            [$id, $coordsId, $cell['plan'], (int) $cell['z'], (int) $cell['x'], (int) $cell['y'], $role]
        );

        return $id;
    }

    private function blocked(int ...$coordsIds): array
    {
        return (new TileOccupancyService($this->conn))->permanentlyBlocked($coordsIds);
    }

    /** A resource refuses the step on its own, so a fence over it says nothing. */
    public function testAResourceBlocksPermanently(): void
    {
        $coordsId = $this->coordsId(1, 1);

        $this->plantResource($this->conn, 'gm_fence_arbre', $coordsId, self::PLAN, 1, 1);

        $this->assertArrayHasKey($coordsId, $this->blocked($coordsId));
    }

    /** A structure holding the cell as `block` does too. */
    public function testABlockingStructureBlocksPermanently(): void
    {
        $coordsId = $this->coordsId(2, 2);
        $this->entityOn($coordsId, 'building', 'block');

        $this->assertArrayHasKey($coordsId, $this->blocked($coordsId));
    }

    /** `cover` is a drawing order: one can stand there, so the fence stays. */
    public function testACoverCellIsNotBlocked(): void
    {
        $coordsId = $this->coordsId(3, 3);
        $this->entityOn($coordsId, 'scenery', 'cover');

        $this->assertSame([], $this->blocked($coordsId));
    }

    /**
     * The one that decides whether this is safe to delete: someone standing
     * in a doorway blocks it for a turn, and is no reason to take the fence
     * away.
     */
    public function testACharacterIsNeverAPermanentBlocker(): void
    {
        $coordsId = $this->coordsId(4, 4);
        $this->entityOn($coordsId, 'real', 'block', 'nain');

        $this->assertSame([], $this->blocked($coordsId), 'un personnage n\'est pas un mur');
    }

    /** An empty cell blocks nothing, so a fence on it is the only thing said. */
    public function testAnEmptyCellIsNotBlocked(): void
    {
        $this->assertSame([], $this->blocked($this->coordsId(5, 5)));
    }
}
