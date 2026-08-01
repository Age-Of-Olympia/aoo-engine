<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Drop the `coffre_*` structure races, now that every chest is an exemplar.
 *
 * `players.race` still reads 'coffre_bois' on the converted entities: the
 * catalogue is chosen by the discriminator, not by the name.
 */
final class Version20260803240000_ContainerTypesLeaveTheRaces extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'races: the coffre_* structure types retire, chests are items now';
    }

    public function up(Schema $schema): void
    {
        /* Refuses to fire while a chest still answers as a race — a half-run
         * conversion must not take the type out from under a standing entity. */
        $this->addSql(
            "DELETE FROM races
              WHERE name LIKE 'coffre%'
                AND kind = 'structure'
                AND NOT EXISTS (
                        SELECT 1 FROM players p
                         WHERE p.player_type = 'building'
                           AND CONVERT(p.race USING utf8mb4) = CONVERT(races.name USING utf8mb4)
                    )"
        );
    }

    public function down(Schema $schema): void
    {
        /* Not restored. The rows carried sixteen caracs seeded from a JSON that
         * is no longer read, so re-inventing them would put made-up numbers
         * back on the board; the conversion's own down() re-creates buildings
         * typed by whatever the catalogue then holds. */
    }
}
