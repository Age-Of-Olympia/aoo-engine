<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Clear the obstruction flags on items whose homonymous race is a resource
 * node: one places an `obstacle` or a `decor`, one mines a `ressource`.
 *
 * Keyed on the nature so a resource added later cannot re-inherit.
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
