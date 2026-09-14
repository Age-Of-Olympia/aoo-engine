<?php

namespace App\Service;

use Classes\Db;

/**
 * Marks on the cells (map_marks): footsteps, the flag. Drawn above the
 * elements and under the characters, with no effect on who walks there —
 * so several share a cell, and one sits on water. The editor owns the
 * permanent ones (endTime 0); the dated ones are the game's, purged by
 * the hourly cron.
 *
 * Laying one never purges the cached boards: the step that leaves a
 * footstep already purges its origin and its destination.
 */
class MapMarkService
{
    public function put(string $name, int $coordsId, int $turns): void
    {
        (new Db())->exe(
            'INSERT INTO map_marks (`name`, `coords_id`, `endTime`) VALUE (?, ?, ?)
             ON DUPLICATE KEY UPDATE endTime = VALUES(endTime)',
            [$name, $coordsId, time() + ($turns * TurnScheduleService::referenceTurnSeconds())]
        );
    }
}
