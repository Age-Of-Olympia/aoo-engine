<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * `entity_cells.role` says what a cell IS, and nothing else.
 *
 * `anchor` marked the cell an entity stands on — a position, in a column
 * whose other values describe nature. It duplicated `players.coords_id`,
 * which is why `drift()` and `reconcile()` had to exist at all, and it took
 * the slot that "no opinion" needed, which is why `part` had to be invented.
 *
 * The origin stays `players.coords_id`. Every former anchor becomes `part`:
 * the cell belongs to its entity and leaves passability to the type, which is
 * exactly what `anchor` already meant to the occupancy service.
 */
final class Version20260728180000_TheAnchorRoleGoesAway extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'entity_cells.role drops `anchor`: a cell says what it is, not where it is';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE entity_cells SET role = 'part' WHERE role = 'anchor'");

        $this->addSql(
            "ALTER TABLE entity_cells
             MODIFY role VARCHAR(16) NOT NULL DEFAULT 'part'
             COMMENT 'part|block|cover|door|open — part = belongs to the entity, the type decides'"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "ALTER TABLE entity_cells
             MODIFY role VARCHAR(16) NOT NULL DEFAULT 'anchor'
             COMMENT 'anchor|part|block|cover|door|open — part = appartient à l''emprise, le type tranche'"
        );

        /* Only the cell an entity stands on was ever an anchor. */
        $this->addSql(
            "UPDATE entity_cells ec
               JOIN players p ON p.id = ec.player_id AND p.coords_id = ec.coords_id
                SET ec.role = 'anchor'
              WHERE ec.role = 'part'"
        );
    }
}
