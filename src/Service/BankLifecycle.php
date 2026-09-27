<?php

namespace App\Service;

use App\Factory\EntityManagerFactory;
use App\Interface\BuildingLifecycleInterface;

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
    public function fell(int $buildingId, string $plan, string $faction): void
    {
        // Another finished bank still stands on the plan: nothing changes.
        if ((new BuildingService())->builtBuildingInPlan($plan, ['banque']) !== null) {
            return;
        }

        $released = $this->connection()->executeStatement(
            'UPDATE players p ' . ContainerService::STANDING_CHEST_JOIN . "
                SET p.owner_id = NULL, p.faction = '', p.is_open = 1
              WHERE c.plan = ?",
            [$plan]
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
