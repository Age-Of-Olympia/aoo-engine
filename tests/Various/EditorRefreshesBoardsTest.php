<?php

namespace Tests\Various;

use App\Service\TiledMapService;
use Classes\View;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Tests\Support\CachedBoard;
use Tests\Support\LegacyBootstrapTrait;
use Tests\Support\PlanFixtureTrait;

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
    use LegacyBootstrapTrait;
    use PlanFixtureTrait;

    private const PLAN = 'plan_test_refresh_edit';

    private ?Connection $conn = null;
    private int $watcherId = 0;

    protected function setUp(): void
    {
        $this->bootstrapLegacyOrSkip();

        try {
            $this->conn = \App\Factory\EntityManagerFactory::getEntityManager()->getConnection();
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
        if ($this->watcherId !== 0) {
            @unlink($this->boardOf($this->watcherId));
        }

        $this->purgePlan($this->conn, self::PLAN);
    }

    /** Someone standing on the plan, with a board already drawn. */
    private function watcherAt(int $x, int $y): int
    {
        $coordsId = $this->coordsIdOn(self::PLAN, $x, $y);

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

    private function boardIsCached(int $playerId, int $perception = 10): void
    {
        CachedBoard::drawnFor($playerId, $perception, '<svg class="case"/>');
    }

    /** What the in-game editor holds is a cell id, and nothing else. */
    public function testEditingACellRedrawsTheBoardsThatSawIt(): void
    {
        $this->boardIsCached($this->watcherId);

        View::refresh_players_svg_at($this->coordsIdOn(self::PLAN, 2, 2));

        $this->assertFileDoesNotExist($this->boardOf($this->watcherId));
    }

    /** Far away is far away: nobody else pays for an edit they cannot see. */
    public function testAnEditOutOfRangeLeavesABoardAlone(): void
    {
        $this->boardIsCached($this->watcherId);

        View::refresh_players_svg_at($this->coordsIdOn(self::PLAN, 500, 500));

        $this->assertFileExists($this->boardOf($this->watcherId));
    }

    /** Whoever sees farther than the old fixed 20 tiles is refreshed from as far as they see. */
    public function testAFarSightedBoardIsRedrawnFromAsFarAsItSees(): void
    {
        $this->boardIsCached($this->watcherId, 30);

        View::refresh_players_svg_at($this->coordsIdOn(self::PLAN, 28, 0));

        $this->assertFileDoesNotExist($this->boardOf($this->watcherId));
        $this->assertSame(1, (int) $this->conn->fetchOne(
            'SELECT stale FROM board_views WHERE player_id = ?',
            [$this->watcherId]
        ), 'the HUD poll reads this flag');
    }

    /** A short-sighted board is left alone by what it cannot show. */
    public function testAShortSightedBoardIgnoresWhatItCannotSee(): void
    {
        $this->boardIsCached($this->watcherId, 3);

        View::refresh_players_svg_at($this->coordsIdOn(self::PLAN, 15, 0));

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
        $this->coordsIdOn(self::PLAN, 0, 0);
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
