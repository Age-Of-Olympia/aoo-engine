<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A faction is a faction: one column, on the entity.
 *
 * `players.faction` and `buildings.faction` were the same idea in two places,
 * and they had drifted — the satellite named a faction on every building that
 * had one, the entity named none. Only the satellite was ever written, so it is
 * the one that told the truth, and its values move up.
 *
 * Carrying a faction is not being a MEMBER of one, and the code already knew:
 * `FactionService` counts members with `player_type IN ('real','npc')`, so a
 * building cannot join a faction, inflate its rolls, or keep it from being
 * deleted. Consolidating changes none of that.
 *
 * This is what `owner_id` and `is_open` did one step earlier, for the same
 * reason: belonging to someone is not a privilege of buildings, and a chest
 * that can be owned by a faction needs the answer in one place.
 */
final class Version20260803160000_OneFactionColumnForEveryone extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'buildings.faction folds into players.faction: one column for every entity';
    }

    public function up(Schema $schema): void
    {
        /* The satellite wins where they disagree: it is the one the admin
         * writes, and the entity's has never been filled for a building. */
        $this->addSql(
            "UPDATE players p
               JOIN buildings b ON b.player_id = p.id
                SET p.faction = b.faction
              WHERE b.faction <> ''"
        );

        $this->addSql('ALTER TABLE buildings DROP COLUMN IF EXISTS faction');
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "ALTER TABLE buildings ADD COLUMN IF NOT EXISTS faction VARCHAR(100) NOT NULL DEFAULT ''"
        );
        $this->addSql(
            'UPDATE buildings b JOIN players p ON p.id = b.player_id SET b.faction = p.faction'
        );
    }
}
