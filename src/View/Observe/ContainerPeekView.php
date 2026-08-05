<?php

namespace App\View\Observe;

use App\Service\ContainerService;
use App\Service\LockService;
use Classes\Player;

/**
 * What the tile's containers hold, in the TILE section of the
 * observation panel — beside the ground loot, not among the actions.
 *
 * One only sees inside what serves them: the household rule
 * (mayActOn) and an open lid. A shut or foreign container keeps its
 * contents to itself, and shows nothing here.
 */
final class ContainerPeekView
{
    public static function render(Player $player, int $x, int $y, object $coords): void
    {
        $conn = \App\Factory\EntityManagerFactory::getEntityManager()->getConnection();

        /* The lockable entities standing on this cell — anchor or any
         * cell of their footprint. */
        $entities = $conn->fetchAllAssociative(
            'SELECT DISTINCT p.id, p.name
               FROM players p
               JOIN coords c ON (c.x = ? AND c.y = ? AND c.z = ? AND CONVERT(c.plan USING utf8mb4) = CONVERT(? USING utf8mb4))
              WHERE p.coords_id = c.id
                 OR EXISTS (SELECT 1 FROM entity_cells ec WHERE ec.player_id = p.id AND ec.coords_id = c.id)',
            [$x, $y, (int) $coords->z, (string) $coords->plan]
        );

        $lock = new LockService();
        $service = new ContainerService();

        foreach ($entities as $entity) {
            $entityId = (int) $entity['id'];

            /* Verrouillable ET contenant : une banque a une porte mais
             * pas de coffre à montrer (ContainerService::isContainer). */
            if (!$lock->isLockable($entityId) || !$service->isContainer($entityId)) {
                continue;
            }
            if ($service->closureReasonOf($entityId) !== null || !$service->mayUse($entityId, (int) $player->id)) {
                continue;
            }

            $contents = $service->contentsOf($entityId);
            if ($contents['stacks'] === [] && $contents['exemplars'] === []) {
                continue;
            }

            echo '<div class="case-infos">';
            echo '<div class="text"><b>Dans '
                . htmlspecialchars((string) $entity['name'], ENT_QUOTES, 'UTF-8') . ' :</b><br />';

            foreach ($contents['stacks'] as $row) {
                echo '<img src="' . htmlspecialchars(\Classes\View::exemplarSprite((string) $row['name'], (string) $row['name']), ENT_QUOTES, 'UTF-8') . '" style="max-height:22px;vertical-align:middle;" alt="" /> '
                    . htmlspecialchars(ContainerService::stackLabel($row), ENT_QUOTES, 'UTF-8') . '<br />';
            }

            foreach ($contents['exemplars'] as $row) {
                echo '<img src="' . htmlspecialchars(\Classes\View::exemplarSprite((string) $row['name'], (string) $row['name']), ENT_QUOTES, 'UTF-8') . '" style="max-height:22px;vertical-align:middle;" alt="" /> '
                    . htmlspecialchars(ContainerService::exemplarEntryLabel($row), ENT_QUOTES, 'UTF-8') . '<br />';
            }

            echo '</div></div>';
        }
    }
}
