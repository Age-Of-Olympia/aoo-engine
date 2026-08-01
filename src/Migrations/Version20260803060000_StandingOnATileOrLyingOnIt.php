<?php

declare(strict_types=1);

namespace App\Migrations;

use App\Service\Map\EntityLocationService;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Being on a tile splits in two: standing on it, or lying on it.
 *
 * A chest is part of its tile — drawn as a figure, occupying `entity_cells`,
 * there to be hit. A dropped sword only lies there: a marker, picked up freely,
 * occupying nothing. Containment alone cannot tell them apart, since both are
 * an entity with a `coords_id`.
 *
 * The `slot` column already says HOW an entity is where it is — bag, main1,
 * bank — so it says this too. Every entity standing on a cell today is
 * installed by definition: nothing was ever merely dropped before items became
 * entities.
 *
 * Stored rather than inferred from "holds no cell", because that absence
 * already means something else: `EntityCellService::drift()` reads it as
 * corruption and `reconcile()` repairs it by laying cells, which would promote
 * every piece of loot into a figure on the board. The marker is what lets
 * drift() ask the right question instead.
 *
 * After this, an empty `slot` never coexists with a `coords_id`, so the column
 * reads the same way wherever it is read.
 */
final class Version20260803060000_StandingOnATileOrLyingOnIt extends AbstractMigration
{
    public function getDescription(): string
    {
        return "players.slot: entities on a cell are 'installed', telling them from what is merely dropped";
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'UPDATE players SET slot = ? WHERE coords_id IS NOT NULL AND slot = ?',
            [EntityLocationService::SLOT_INSTALLED, EntityLocationService::SLOT_CARRIED]
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'UPDATE players SET slot = ? WHERE coords_id IS NOT NULL AND slot = ?',
            [EntityLocationService::SLOT_CARRIED, EntityLocationService::SLOT_INSTALLED]
        );
    }
}
