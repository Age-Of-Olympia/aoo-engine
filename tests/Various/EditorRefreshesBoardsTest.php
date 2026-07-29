<?php

namespace Tests\Various;

use App\Service\TiledMapService;
use Classes\View;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

/**
 * What an animator changes, the people looking at it see.
 *
 * A board is cached per viewer with no expiry, and the map editors wrote
 * straight to the `map_*` tables without telling anyone. So a building
 * dropped from Tiled into someone's field of view simply did not appear —
 * not until that player moved, which is the one thing they had no reason to
 * do. Only `BuildingService::place` purged anything, so the gap was invisible
 * on exactly the layer most often tested.
 *
 * DB-backed; skips cleanly when the database is unreachable.
 */
class EditorRefreshesBoardsTest extends TestCase
{
    private const PLAN = 'plan_test_refresh_edit';

    private ?Connection $conn = null;
    private int $watcherId = 0;

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
        $this->watcherId = $this->watcherAt(0, 0);
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

        if ($this->watcherId !== 0) {
            @unlink($this->boardOf($this->watcherId));
        }

        foreach ($this->conn->fetchFirstColumn(
            'SELECT p.id FROM players p JOIN coords c ON c.id = p.coords_id WHERE c.plan = ?',
            [self::PLAN]
        ) as $id) {
            $this->conn->executeStatement('DELETE FROM entity_cells WHERE player_id = ?', [(int) $id]);
            \App\Service\BuildingService::deleteEntityRows($this->conn, (int) $id);
        }

        $this->conn->executeStatement(
            'DELETE f FROM map_foregrounds f JOIN coords c ON c.id = f.coords_id WHERE c.plan = ?',
            [self::PLAN]
        );
        $this->conn->executeStatement(
            'DELETE t FROM map_tiles t JOIN coords c ON c.id = t.coords_id WHERE c.plan = ?',
            [self::PLAN]
        );
        $this->conn->executeStatement('DELETE FROM coords WHERE plan = ?', [self::PLAN]);

        @unlink($_SERVER['DOCUMENT_ROOT'] . '/datas/private/plans/' . self::PLAN . '.json');
        json()->forget('plans', self::PLAN);
    }

    private function coordsId(int $x, int $y): int
    {
        return (int) View::get_coords_id(
            (object) ['x' => $x, 'y' => $y, 'z' => 0, 'plan' => self::PLAN]
        );
    }

    /** Someone standing on the plan, with a board already drawn. */
    private function watcherAt(int $x, int $y): int
    {
        $coordsId = $this->coordsId($x, $y);

        $this->conn->executeStatement(
            "INSERT INTO players (name, race, coords_id, player_type) VALUES (?, 'nain', ?, 'real')",
            ['GmRegardeur', $coordsId]
        );

        return (int) $this->conn->lastInsertId();
    }

    private function boardOf(int $playerId): string
    {
        return $_SERVER['DOCUMENT_ROOT'] . '/datas/private/players/' . $playerId . '.svg';
    }

    private function boardIsCached(int $playerId): void
    {
        file_put_contents($this->boardOf($playerId), '<svg class="case"/>');
        clearstatcache(true, $this->boardOf($playerId));
    }

    /** What the in-game editor holds is a cell id, and nothing else. */
    public function testEditingACellRedrawsTheBoardsThatSawIt(): void
    {
        $this->boardIsCached($this->watcherId);

        View::refresh_players_svg_at($this->coordsId(2, 2));

        $this->assertFileDoesNotExist($this->boardOf($this->watcherId));
    }

    /** Far away is far away: nobody else pays for an edit they cannot see. */
    public function testAnEditOutOfRangeLeavesABoardAlone(): void
    {
        $this->boardIsCached($this->watcherId);

        View::refresh_players_svg_at($this->coordsId(500, 500));

        $this->assertFileExists($this->boardOf($this->watcherId));
    }

    /** An unknown cell is not a reason to blow up mid-edit. */
    public function testAnUnknownCellIsHarmless(): void
    {
        $this->boardIsCached($this->watcherId);

        View::refresh_players_svg_at(0);

        $this->assertFileExists($this->boardOf($this->watcherId));
    }

    /** And the push from Tiled, which is the whole point. */
    public function testAPushFromTiledRedrawsTheBoardsAround(): void
    {
        $this->coordsId(0, 0);
        $service = new TiledMapService();
        $export = $service->exportPlan(self::PLAN, 0);

        $this->assertNotNull($export, 'le plan de test doit être exportable');

        $this->boardIsCached($this->watcherId);

        $service->applyPush(
            self::PLAN,
            0,
            ['tiles' => [['x' => 3, 'y' => 3, 'name' => 'gm_refresh_sol']]],
            $export['version'],
            null,
            null
        );

        $this->assertFileDoesNotExist(
            $this->boardOf($this->watcherId),
            'un bâtiment posé sous les yeux de quelqu\'un doit se voir'
        );
    }
}
