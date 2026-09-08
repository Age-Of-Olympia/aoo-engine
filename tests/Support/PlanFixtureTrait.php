<?php

namespace Tests\Support;

use App\Service\BuildingService;
use App\Service\PlanService;
use Classes\View;
use Doctrine\DBAL\Connection;

/**
 * A throwaway plan to stand things on: a tile of it on demand, and the whole
 * of it swept away afterwards.
 *
 * The sweep is the same fourteen statements every plan-bound case used to
 * carry — entities and their satellites first, then the map layers, the cells
 * and the coords they all hold by foreign key, the plans row last.
 */
trait PlanFixtureTrait
{
    /** The coords id of (x, y, z) on a plan, created when absent. */
    protected function coordsIdOn(string $plan, int $x, int $y, int $z = 0): int
    {
        return (int) View::get_coords_id((object) ['x' => $x, 'y' => $y, 'z' => $z, 'plan' => $plan]);
    }

    /** Nullable so a tearDown after a skipped setUp needs no guard of its own. */
    protected function purgePlan(?Connection $conn, string $plan): void
    {
        if ($conn === null) {
            return;
        }

        foreach ($conn->fetchFirstColumn(
            'SELECT p.id FROM players p JOIN coords c ON c.id = p.coords_id WHERE c.plan = ?',
            [$plan]
        ) as $id) {
            // Satellites first: their keys are RESTRICT and the service undoes them by hand elsewhere.
            foreach (['entity_cells', 'buildings', 'resources', 'unique_objects'] as $satellite) {
                $conn->executeStatement("DELETE FROM {$satellite} WHERE player_id = ?", [(int) $id]);
            }
            BuildingService::deleteEntityRows($conn, (int) $id);
            BuildingService::purgeEntityCaches((int) $id);
        }

        foreach (['tiles', 'routes', 'plants', 'resources', 'elements', 'foregrounds', 'triggers', 'dialogs', 'items'] as $layer) {
            $conn->executeStatement(
                "DELETE m FROM map_{$layer} m JOIN coords c ON c.id = m.coords_id WHERE c.plan = ?",
                [$plan]
            );
        }

        // Cells written by hand hold their coords by a RESTRICT key too.
        $conn->executeStatement(
            'DELETE ec FROM entity_cells ec JOIN coords c ON c.id = ec.coords_id WHERE c.plan = ?',
            [$plan]
        );
        $conn->executeStatement('DELETE FROM coords WHERE plan = ?', [$plan]);
        $conn->executeStatement('DELETE FROM plans WHERE slug = ?', [$plan]);
        PlanService::forget($plan);
    }
}
