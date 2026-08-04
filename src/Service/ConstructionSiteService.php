<?php

namespace App\Service;

use App\Factory\PlayerFactory;
use Classes\Db;

/**
 * The construction site: sole writer of the `construction_sites` satellite and of the
 * build_state transitions it drives.
 *
 * A type declaring work (races.build_work > 0) is born a SITE: the
 * satellite carries the progress, buildings.build_state reads
 * 'construction' so the one closure rule shuts the place, and PV
 * follow the work — the fabric rises with it, so working also mends
 * what was damaged meanwhile, up to the progress floor. Every advance
 * is a conditional UPDATE read by its affected rows: two workers
 * cannot both lay the last stone. Writes go through Classes\Db, which
 * the simulation guard already intercepts.
 */
class ConstructionSiteService extends BaseService
{
    /**
     * Opens the site on a freshly placed entity: progress 0, PV at the floor.
     *
     * The type declares work PER CELL, and the footprint multiplies it —
     * a 3×3 keep takes nine times the gestures of its gatehouse. Cells
     * are already laid at this point (place() syncs them before opening).
     */
    public function open(int $entityId, int $workPerCell): void
    {
        $db = new Db();
        $cells = (int) ($db->exe(
            'SELECT COUNT(*) AS n FROM entity_cells WHERE player_id = ?',
            $entityId
        )->fetch_object()->n ?? 1);

        $db->exe(
            'INSERT IGNORE INTO construction_sites (player_id, work_done, work_total) VALUES (?, 0, ?)',
            [$entityId, max(1, $workPerCell) * max(1, $cells)]
        );
        $db->exe("UPDATE buildings SET build_state = 'construction' WHERE player_id = ?", $entityId);
        $this->raisePvToFloor($entityId);
    }

    public function isUnderConstruction(int $entityId): bool
    {
        return $this->progressOf($entityId) !== null;
    }

    /** @return array{done: int, total: int}|null null = not a site */
    public function progressOf(int $entityId): ?array
    {
        $row = (new Db())->exe(
            'SELECT work_done, work_total FROM construction_sites WHERE player_id = ?',
            $entityId
        )->fetch_object();

        if (!is_object($row)) {
            return null;
        }

        return ['done' => (int) $row->work_done, 'total' => (int) $row->work_total];
    }

    /**
     * One work gesture: +units toward the total, PV raised to the new
     * floor, and on the last unit the site becomes the building.
     *
     * @return array{done: int, total: int, completed: bool}|null null = not a site
     */
    public function advance(int $entityId, int $units): ?array
    {
        $db = new Db();
        $db->exe(
            'UPDATE construction_sites SET work_done = LEAST(work_done + ?, work_total)
             WHERE player_id = ? AND work_done < work_total',
            [max(1, $units), $entityId]
        );

        $progress = $this->progressOf($entityId);
        if ($progress === null) {
            return null;
        }

        $this->raisePvToFloor($entityId);

        if ($progress['done'] >= $progress['total']) {
            $db->exe('DELETE FROM construction_sites WHERE player_id = ?', $entityId);
            $db->exe("UPDATE buildings SET build_state = 'built' WHERE player_id = ?", $entityId);

            return ['done' => $progress['done'], 'total' => $progress['total'], 'completed' => true];
        }

        return ['done' => $progress['done'], 'total' => $progress['total'], 'completed' => false];
    }

    /**
     * PV never sit below floor(max × done/total), min 1 — max on the last
     * stone. Raise-only: battle damage deeper than the floor is mended by
     * the next gesture, never worsened by one.
     */
    private function raisePvToFloor(int $entityId): void
    {
        $legacy = PlayerFactory::legacy($entityId);
        $legacy->get_data();
        $legacy->get_caracs();
        $max = (int) ($legacy->caracs->pv ?? 0);
        if ($max <= 0) {
            return;
        }

        $progress = $this->progressOf($entityId);
        $floor = $progress === null || $progress['done'] >= $progress['total']
            ? $max
            : max(1, intdiv($max * $progress['done'], $progress['total']));

        (new Db())->exe(
            "INSERT INTO players_bonus (player_id, name, n) VALUES (?, 'pv', ?)
             ON DUPLICATE KEY UPDATE n = GREATEST(n, VALUES(n))",
            [$entityId, $floor - $max]
        );
    }
}
