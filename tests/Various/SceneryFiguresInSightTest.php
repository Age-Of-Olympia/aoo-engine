<?php

namespace Tests\Various;

use App\Service\Map\EntityCellService;
use App\Service\Map\EntitySpriteService;
use App\Service\Map\EntityTypeFootprintService;
use App\Service\Map\SceneryFiguresInSight;
use App\Service\Map\SceneryObjectService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

/**
 * The scenery a board window draws, one picture per figure.
 *
 * The render itself has no test — `new View(...)` appears nowhere in the
 * suite — so what the switch can break is pinned here instead: which figures
 * a window sees, how big they are, and which cells they take over from the
 * piece rows.
 *
 * DB-backed; skips cleanly when the database is unreachable.
 */
class SceneryFiguresInSightTest extends TestCase
{
    private const PLAN = 'plan_test_figures';
    private const FAMILY = 'gm_figure_tour';

    private ?Connection $conn = null;
    private string $spriteDir;

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
            $this->conn = \App\Factory\EntityManagerFactory::getEntityManager()->getConnection();
            $this->conn->fetchOne('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database unreachable: ' . $e->getMessage());
        }

        $this->spriteDir = $_SERVER['DOCUMENT_ROOT'] . '/img/foregrounds/_composed';
        $this->cleanup();

        (new EntityTypeFootprintService($this->conn))->declare(
            self::FAMILY,
            1,
            2,
            [0 => [0, 0], 1 => [0, -1]]
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

        foreach ($this->conn->fetchFirstColumn(
            'SELECT p.id FROM players p JOIN coords c ON c.id = p.coords_id WHERE c.plan = ?',
            [self::PLAN]
        ) as $id) {
            $this->conn->executeStatement('DELETE FROM entity_cells WHERE player_id = ?', [(int) $id]);
            \App\Service\BuildingService::deleteEntityRows($this->conn, (int) $id);
        }

        /* Cells and piece rows before coords: both keys are RESTRICT. */
        $this->conn->executeStatement(
            'DELETE ec FROM entity_cells ec JOIN coords c ON c.id = ec.coords_id WHERE c.plan = ?',
            [self::PLAN]
        );
        $this->conn->executeStatement(
            'DELETE f FROM map_foregrounds f JOIN coords c ON c.id = f.coords_id WHERE c.plan = ?',
            [self::PLAN]
        );
        $this->conn->executeStatement('DELETE FROM coords WHERE plan = ?', [self::PLAN]);
        $this->conn->executeStatement('DELETE FROM entity_type_footprints WHERE type_name = ?', [self::FAMILY]);
        $this->conn->executeStatement('DELETE FROM races WHERE name = ?', [self::FAMILY]);

        @unlink($this->spriteDir . '/' . self::FAMILY . '.png');

        foreach (glob($_SERVER['DOCUMENT_ROOT'] . '/img/foregrounds/' . self::FAMILY . '-*.png') ?: [] as $art) {
            @unlink($art);
        }

        EntitySpriteService::forget();
    }

    /** The per-cell art a figure is stitched from. */
    private function pieceArt(): void
    {
        $dir = $_SERVER['DOCUMENT_ROOT'] . '/img/foregrounds';

        foreach ([0, 1] as $piece) {
            $image = imagecreatetruecolor(50, 50);
            imagepng($image, $dir . '/' . self::FAMILY . '-0' . $piece . '.png');
            imagedestroy($image);
        }

        EntitySpriteService::forget();
    }

    /** The composed picture the board insists on before drawing anything whole. */
    private function composedSprite(): void
    {
        if (!is_dir($this->spriteDir)) {
            @mkdir($this->spriteDir, 0775, true);
        }

        $image = imagecreatetruecolor(50, 100);
        imagepng($image, $this->spriteDir . '/' . self::FAMILY . '.png');
        imagedestroy($image);

        EntitySpriteService::forget();
    }

    private function coordsId(int $x, int $y): int
    {
        return (int) \Classes\View::get_coords_id(
            (object) ['x' => $x, 'y' => $y, 'z' => 0, 'plan' => self::PLAN]
        );
    }

    /**
     * A two-cell figure anchored at (x, y), its second cell one south — the
     * declared offset is dy = -1.
     *
     * `placeObject` answers how many pieces it laid, so the entity is looked
     * up rather than assumed.
     */
    private function figureAt(int $x, int $y): int
    {
        (new SceneryObjectService($this->conn))->placeObject(self::FAMILY . '-00', $x, $y, 0, self::PLAN);

        $id = (int) $this->conn->fetchOne(
            "SELECT p.id FROM players p JOIN coords c ON c.id = p.coords_id
              WHERE c.plan = ? AND p.player_type = 'scenery' ORDER BY p.id DESC",
            [self::PLAN]
        );

        (new EntityCellService($this->conn))->syncCells($id);

        return $id;
    }

    public function testAFigureIsDrawnOnceOverItsWholeBox(): void
    {
        $this->composedSprite();
        $id = $this->figureAt(0, 0);

        $seen = (new SceneryFiguresInSight($this->conn))->forWindow(
            [$this->coordsId(0, 0), $this->coordsId(0, -1)]
        );

        $this->assertCount(1, $seen['figures']);
        $figure = $seen['figures'][0];

        $this->assertSame($id, $figure['id']);
        $this->assertSame(1, $figure['w']);
        $this->assertSame(2, $figure['h'], 'la figure fait deux cases de haut');
        $this->assertSame(0, $figure['x']);
        $this->assertSame(0, $figure['y'], 'coin haut-gauche : le y le plus grand des cases');
        $this->assertStringContainsString('_composed', $figure['image']);
    }

    /**
     * A figure is seen BY ITS BODY: standing beside its top cell shows it,
     * even though the anchor sits outside the window.
     */
    public function testAFigureIsSeenByItsBodyNotItsAnchor(): void
    {
        $this->composedSprite();
        $this->figureAt(0, 0);

        $seen = (new SceneryFiguresInSight($this->conn))->forWindow([$this->coordsId(0, -1)]);

        $this->assertCount(1, $seen['figures'], 'la case hors ancrage suffit à la faire voir');
        $this->assertSame(2, $seen['figures'][0]['h'], 'et elle est dessinée entière');
    }

    /** Its cells step aside, so the piece rows do not draw underneath. */
    public function testTheCellsItCoversAreReported(): void
    {
        $this->composedSprite();
        $this->figureAt(0, 0);

        $seen = (new SceneryFiguresInSight($this->conn))->forWindow([$this->coordsId(0, 0)]);

        $this->assertArrayHasKey($this->coordsId(0, 0), $seen['covered']);
        $this->assertArrayHasKey($this->coordsId(0, -1), $seen['covered'], 'toute l\'emprise, pas la seule case vue');
    }

    /**
     * A figure whose picture is missing composes it on the spot, once: `img/`
     * is not versioned, so a fresh deployment has none and nobody should have
     * to remember a console command for the decor to appear.
     */
    public function testAMissingPictureIsComposedOnDemand(): void
    {
        $this->pieceArt();
        $this->figureAt(0, 0);

        $seen = (new SceneryFiguresInSight($this->conn))->forWindow([$this->coordsId(0, 0)]);

        $this->assertCount(1, $seen['figures'], 'la figure se dessine sans commande préalable');
        $this->assertFileExists(
            $_SERVER['DOCUMENT_ROOT'] . '/' . $seen['figures'][0]['image'],
            'et son image reste sur le disque pour les affichages suivants'
        );
    }

    /**
     * Nothing to compose from either: no spanning, and no cell taken over,
     * or the decor would disappear from the board entirely.
     */
    public function testWithoutAnyArtTheFigureKeepsItsPieces(): void
    {
        $this->figureAt(0, 0); /* neither sprite nor pieces on disk */

        $seen = (new SceneryFiguresInSight($this->conn))->forWindow([$this->coordsId(0, 0)]);

        $this->assertSame([], $seen['figures']);
        $this->assertSame([], $seen['covered'], 'rien ne doit être masqué si rien ne le remplace');
    }

    /** An empty window asks the database nothing. */
    public function testAnEmptyWindowSeesNothing(): void
    {
        $seen = (new SceneryFiguresInSight($this->conn))->forWindow([]);

        $this->assertSame([], $seen['figures']);
        $this->assertSame([], $seen['covered']);
    }
}
