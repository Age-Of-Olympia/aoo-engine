<?php

namespace App\Service\Map;

use Classes\Db;
use Classes\Player;

/**
 * The single path for "the board changed". Code that changes what a board
 * shows says WHAT changed; this class finds WHO sees it (board_views, the
 * area each board was drawn with) and tells them through notify(), today by
 * flagging the board stale (polled by the HUD) and dropping its cached SVG.
 * A push channel (websockets…) plugs into notify() and nowhere else.
 *
 * Guarded by tests/Various/BoardChangesGuardTest.php: no other file deletes a
 * cached board or touches board_views.
 */
final class BoardChanges
{
    private static ?int $reach = null;

    /** A change on one cell (coords: x, y, z, plan). */
    public static function cell(object $coords): void
    {
        self::cells((string) $coords->plan, (int) $coords->z, (int) $coords->x, (int) $coords->x, (int) $coords->y, (int) $coords->y);
    }

    /** A change on one cell known by its coords id; an unknown id changes nothing. */
    public static function cellId(int $coordsId): void
    {
        $row = (new Db())->exe('SELECT x, y, z, plan FROM coords WHERE id = ?', array($coordsId))->fetch_object();
        if ($row) {
            self::cell($row);
        }
    }

    /**
     * Changes over a box of cells. The box is widened by the largest
     * footprint: a change reported at an anchor cell can reach that far.
     */
    public static function cells(string $plan, int $z, int $minX, int $maxX, int $minY, int $maxY): void
    {
        $reach = self::reach();
        $res = (new Db())->exe(
            'SELECT player_id FROM board_views
              WHERE plan = ? AND z = ? AND x_min <= ? AND x_max >= ? AND y_min <= ? AND y_max >= ?',
            array($plan, $z, $maxX + $reach, $minX - $reach, $maxY + $reach, $minY - $reach)
        );

        $ids = array();
        while ($row = $res->fetch_object()) {
            $ids[] = (int) $row->player_id;
        }

        self::notify($ids);
    }

    /** What this player's own board draws changed: Perception, options, effects, their entity. */
    public static function viewer(int $playerId): void
    {
        self::notify(array($playerId));
    }

    /** Every board at once: catalog changes, the console's purge. */
    public static function world(): void
    {
        (new Db())->exe('UPDATE board_views SET stale = 1');
        foreach (glob(dirname(Player::cachePath(0, '.svg')) . '/*.svg') ?: array() as $file) {
            @unlink($file);
        }
    }

    /**
     * MainView drew this board: record the area it shows, fresh.
     *
     * @param array{plan: string, z: int, x_min: int, x_max: int, y_min: int, y_max: int} $area View::area()
     */
    public static function drawn(int $playerId, array $area): void
    {
        (new Db())->exe(
            'INSERT INTO board_views (player_id, plan, z, x_min, x_max, y_min, y_max, stale)
             VALUES (?, ?, ?, ?, ?, ?, ?, 0)
             ON DUPLICATE KEY UPDATE plan = VALUES(plan), z = VALUES(z),
                x_min = VALUES(x_min), x_max = VALUES(x_max), y_min = VALUES(y_min), y_max = VALUES(y_max), stale = 0',
            array($playerId, $area['plan'], $area['z'], $area['x_min'], $area['x_max'], $area['y_min'], $area['y_max'])
        );
    }

    /** Has something changed in this player's board since it was drawn? (HUD poll) */
    public static function isStale(int $playerId): bool
    {
        $row = (new Db())->exe('SELECT stale FROM board_views WHERE player_id = ?', array($playerId))->fetch_object();

        return (bool) ($row->stale ?? false);
    }

    /** @param list<int> $playerIds */
    private static function notify(array $playerIds): void
    {
        if ($playerIds === array()) {
            return;
        }

        $marks = implode(',', array_fill(0, count($playerIds), '?'));
        (new Db())->exe('UPDATE board_views SET stale = 1 WHERE player_id IN (' . $marks . ')', $playerIds);

        foreach ($playerIds as $id) {
            @unlink(Player::cachePath($id, '.svg'));
        }
    }

    /** How far a change reported at one cell can reach: the largest footprint, minus that cell. */
    private static function reach(): int
    {
        if (self::$reach === null) {
            self::$reach = 0;
            foreach ((new EntityTypeFootprintService())->catalogue() as $footprint) {
                self::$reach = max(self::$reach, $footprint->width() - 1, $footprint->height() - 1);
            }
        }

        return self::$reach;
    }
}
