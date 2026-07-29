<?php

namespace Tests\Various;

use App\Service\Map\EntityTypeFootprintService;
use App\Service\Map\MapForegroundsRetirement;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

/**
 * The notice that tells an admin whether `map_foregrounds` can go.
 *
 * A hand-written note would rot: it would still say "wait" long after the
 * wait ended, or worse say "go ahead" while a family's only record of its
 * shape is the pieces standing on the map. So the verdict is computed, and
 * these tests hold it to the things that actually hold the table.
 *
 * DB-backed; skips cleanly when the database is unreachable.
 */
class MapForegroundsRetirementTest extends TestCase
{
    private const PLAN = 'plan_test_retirement';
    private const FAMILY = 'gm_retire_famille';

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

        $this->conn->executeStatement(
            'DELETE f FROM map_foregrounds f JOIN coords c ON c.id = f.coords_id WHERE c.plan = ?',
            [self::PLAN]
        );
        $this->conn->executeStatement('DELETE FROM coords WHERE plan = ?', [self::PLAN]);
        $this->conn->executeStatement('DELETE FROM entity_type_footprints WHERE type_name = ?', [self::FAMILY]);
    }

    /** Lays a two-piece figure on the board, the way an animator would have. */
    private function layTheFigure(): void
    {
        foreach ([[3, 3, '-00'], [3, 4, '-01']] as [$x, $y, $suffix]) {
            $coordsId = \Classes\View::get_coords_id(
                (object) ['x' => $x, 'y' => $y, 'z' => 0, 'plan' => self::PLAN]
            );

            $this->conn->executeStatement(
                'INSERT INTO map_foregrounds (name, coords_id) VALUES (?, ?)',
                [self::FAMILY . $suffix, (int) $coordsId]
            );
        }
    }

    /** A fresh service each time: both cache, and this is what a page load says. */
    private function verdict(?bool $rendererReadsTable = null): array
    {
        return (new MapForegroundsRetirement(
            $this->conn,
            new EntityTypeFootprintService($this->conn),
            $rendererReadsTable
        ))->status();
    }

    /** While the renderer reads the table, the answer is no, whatever else holds. */
    public function testTheRendererAloneHoldsTheTable(): void
    {
        $status = $this->verdict(true);

        $this->assertFalse($status['droppable']);
        $this->assertContains('le rendu de la carte y lit encore le décor', $status['blockers']);
    }

    /**
     * And once it no longer does — the day the flip lands — the notice turns
     * green by itself on a board with nothing else holding the table.
     */
    public function testTheNoticeClearsOnceNothingHoldsTheTable(): void
    {
        $status = $this->verdict(false);

        $this->assertNotContains('le rendu de la carte y lit encore le décor', $status['blockers']);
        $this->assertSame($status['blockers'] === [], $status['droppable']);
    }

    /**
     * A shape known only from the map is the reason to keep the table: once
     * the pieces are gone, nothing can tell that figure again.
     */
    public function testAShapeKnownOnlyFromTheMapIsABlocker(): void
    {
        $this->layTheFigure();

        $this->assertSame(
            'map',
            (new EntityTypeFootprintService($this->conn))->sourceOf(self::FAMILY),
            'la figure posée doit être la seule source de sa forme'
        );

        $status = $this->verdict();

        $this->assertContains(self::FAMILY, $status['shapesFromMap']);
        $this->assertFalse($status['droppable']);
        $this->assertNotEmpty(array_filter(
            $status['blockers'],
            static fn(string $b): bool => str_contains($b, 'tient sa forme')
                || str_contains($b, 'tiennent leur forme')
        ));
    }

    /** Settling the shape here puts it out of reach of the table. */
    public function testASettledShapeStopsBeingABlocker(): void
    {
        $this->layTheFigure();

        (new EntityTypeFootprintService($this->conn))->declare(
            self::FAMILY,
            1,
            2,
            [0 => [0, 0], 1 => [0, -1]]
        );

        $this->assertNotContains(self::FAMILY, $this->verdict()['shapesFromMap']);
    }

    /** Decor that no entity claims would simply vanish from the board. */
    public function testDecorNoEntityClaimsIsCounted(): void
    {
        $before = $this->verdict()['orphanRows'];

        $this->layTheFigure();

        $after = $this->verdict();

        $this->assertSame($before + 2, $after['orphanRows']);
        $this->assertFalse($after['droppable']);
        $this->assertNotEmpty(array_filter(
            $after['blockers'],
            static fn(string $b): bool => str_contains($b, 'disparaîtrait')
        ));
    }
}
