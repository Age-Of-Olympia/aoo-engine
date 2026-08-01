<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The container types leave the races catalogue.
 *
 * A chest was catalogued twice, and that duplication is what let a name mean
 * two different things — the trap that put a wall on a leather ingot one
 * migration ago. Now that every standing chest is an exemplar typed by `items`,
 * the `races` rows answer for nobody and go.
 *
 * Deleting them is safe in a way worth stating, because `players.race` still
 * reads 'coffre_bois' on the converted entities: the catalogue is chosen by the
 * DISCRIMINATOR, not by the name. That is precisely what the obstruction and
 * door lookups were taught, and this deletion is the first thing that depends
 * on it — nothing resolves a chest through `races` any more.
 *
 * No satellite refers to them: race_spells, race_starter_actions, race_recipes
 * and race_actions hold no row for any chest, so no cascade is being relied on.
 *
 * A chest is no longer buildable from a race, and nothing regresses: no recipe
 * produces a chest and no bag holds one, so the build path was never walked for
 * a container. When one becomes obtainable, building it must INSTALL its
 * exemplar rather than mint a building — the whole point of the conversion.
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
