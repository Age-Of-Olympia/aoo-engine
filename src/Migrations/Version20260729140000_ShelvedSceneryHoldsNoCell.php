<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A shelved decor lets go of its cells.
 *
 * `vanish()` moves an entity to the tombstone plan and used to re-lay its
 * cells there. That was invisible while only the pieces were drawn — the
 * pieces are deleted on the way out. Now that a figure is drawn FROM its
 * cells, every decor ever shelved would be piled on the tombstone's origin.
 *
 * The service no longer creates them; this clears the ones already there.
 * Idempotent, and it touches nothing standing on a real plan.
 */
final class Version20260729140000_ShelvedSceneryHoldsNoCell extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Shelved scenery drops the cells it kept on the tombstone plan';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "DELETE ec FROM entity_cells ec
               JOIN coords c ON c.id = ec.coords_id
              WHERE c.plan = 'limbes_batiments'"
        );
    }

    /**
     * Nothing to put back: these cells said an object off the board still
     * occupied one, which was never true.
     */
    public function down(Schema $schema): void
    {
    }
}
