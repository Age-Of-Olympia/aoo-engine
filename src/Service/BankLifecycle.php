<?php

namespace App\Service;

use App\Factory\EntityManagerFactory;
use App\Interface\BuildingLifecycleInterface;
use App\Service\Map\EntityLocationService;

/**
 * The bank and the plan's chests (chests & bank work, part 2).
 *
 * - Last bank destroyed: every placed chest of the plan becomes public
 *   — no owner, no faction, lid open. Forcing the lid open is not a
 *   detail: nobody can turn a public chest's lock any more
 *   (LockService::mayLock), so a closed one would stay sealed forever.
 * - Bank finished: it takes the ownerless chests (owner NULL, empty
 *   faction) for its faction — and only those. Personal chests and
 *   other factions' chests do not move, which keeps the rule
 *   replayable and predictable when several banks coexist.
 */
class BankLifecycle implements BuildingLifecycleInterface
{
    public function rose(int $buildingId, string $plan, string $faction): void
    {
        // A factionless bank takes nothing (admin placement): the
        // ownerless chests stay public.
        if ($faction === '') {
            return;
        }

        $claimed = $this->connection()->executeStatement(
            "UPDATE players p
               JOIN item_instances ii ON ii.entity_id = p.id
               JOIN items i ON i.id = ii.item_id
               JOIN coords c ON c.id = p.coords_id
                SET p.faction = ?
              WHERE i.lockable = 1
                AND p.slot = ?
                AND p.owner_id IS NULL AND p.faction = ''
                AND c.plan = ?",
            [$faction, EntityLocationService::SLOT_INSTALLED, $plan]
        );

        if ($claimed > 0) {
            (new FactionLogService())->add(
                $faction,
                null,
                'La banque récupère ' . $claimed . ' coffre(s) du plan pour la faction.'
            );
        }
    }

    public function fell(int $buildingId, string $plan, string $faction): void
    {
        // Another finished bank still stands on the plan: nothing changes.
        if ((new BuildingService())->builtBuildingInPlan($plan, ['banque']) !== null) {
            return;
        }

        $released = $this->connection()->executeStatement(
            "UPDATE players p
               JOIN item_instances ii ON ii.entity_id = p.id
               JOIN items i ON i.id = ii.item_id
               JOIN coords c ON c.id = p.coords_id
                SET p.owner_id = NULL, p.faction = '', p.is_open = 1
              WHERE i.lockable = 1
                AND p.slot = ?
                AND c.plan = ?",
            [EntityLocationService::SLOT_INSTALLED, $plan]
        );

        if ($released > 0 && $faction !== '') {
            (new FactionLogService())->add(
                $faction,
                null,
                'La banque a été détruite : les coffres du plan sont désormais ouverts à tous.'
            );
        }
    }

    private function connection(): \Doctrine\DBAL\Connection
    {
        return EntityManagerFactory::getEntityManager()->getConnection();
    }
}
