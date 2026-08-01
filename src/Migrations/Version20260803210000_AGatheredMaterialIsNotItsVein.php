<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A gathered material does not obstruct like the vein it came from.
 *
 * `Version20260803200000` seeded `items.blocks_passage` / `blocks_projectiles`
 * by joining on the homonymous `races` row, restricted to `kind = 'structure'`.
 * Resource nodes are structures too — a bronze vein is one — so six materials
 * inherited the blocking of the node they are mined from: bronze, cendre,
 * cuir, nickel, salpetre, tourbe. An ingot in a bag was carrying a wall.
 *
 * Nothing installs a material today, so nothing showed. It would have shown
 * the day something did, as an arrow stopped by a pile of leather.
 *
 * The rule the seed should have carried: only `obstacle` and `decor` describe
 * something one PLACES. A `ressource` nature is a node one mines, and the item
 * of the same name is what falls out of it — the two share a name and nothing
 * else. Reset from the nature rather than from a list of six, so a resource
 * added later cannot repeat it.
 */
final class Version20260803210000_AGatheredMaterialIsNotItsVein extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'items: a material mined from a resource node stops obstructing like the node';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "UPDATE items i
                JOIN races r ON CONVERT(r.name USING utf8mb4) = CONVERT(i.name USING utf8mb4)
                SET i.blocks_passage = 0,
                    i.blocks_projectiles = 0
              WHERE r.kind = 'structure'
                AND r.structure_nature = 'ressource'"
        );
    }

    public function down(Schema $schema): void
    {
        /* Restoring the mistake would mean re-copying from the vein. The
         * columns default to 0 and the seed is replayable from the earlier
         * migration, so this stays a no-op rather than a lie. */
    }
}
