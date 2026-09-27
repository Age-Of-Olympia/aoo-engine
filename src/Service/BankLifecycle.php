<?php

namespace App\Service;

use App\Factory\EntityManagerFactory;
use App\Interface\BuildingLifecycleInterface;
use App\Service\Map\EntityLocationService;

/**
 * The bank and the plan's chests (chests & bank work, part 2).
 *
 * - Last bank destroyed: every placed chest of the plan becomes public
 *   — no owner, no faction, lid open: the bank no longer vouches for
 *   anyone's chest, and anyone may now turn its lock.
 * - Bank finished: nothing moves. Its faction chooses, chest by chest,
 *   which public ones to take back (FactionChestService).
 */
class BankLifecycle implements BuildingLifecycleInterface
{
    public function rose(int $buildingId, string $plan, string $faction): void
    {
        // Nothing moves on its own: the faction takes back the public
        // chests it wants from its panel (FactionChestService::claim).
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
